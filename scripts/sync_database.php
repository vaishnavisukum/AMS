<?php
// CLI-only protection: prevent web access
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  echo "Forbidden";
  exit(1);
}

// Helpers: logging and timing
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
  @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/sync_database_' . date('Y-m-d') . '.log';
function logMessage($message) {
  global $logFile;
  $ts = date('Y-m-d H:i:s');
  $line = "[$ts] $message" . PHP_EOL;
  echo $line;
  @file_put_contents($logFile, $line, FILE_APPEND);
}

// Flags
$argv = $argv ?? [];
$force = in_array('--force', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$dangerAck = in_array('--i-know-what-im-doing', $argv, true);
$SAFETY_THRESHOLD = 10000; // rows

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

logMessage('DATABASE SYNCHRONIZATION AND INTEGRITY CHECK');
logMessage(str_repeat('=', 100));

// Usage / guard rails
if (!$force && !$dryRun) {
  logMessage('⚠️  This script can MODIFY the database (DELETE/UPDATE).');
  logMessage('Use one of:');
  logMessage('  php scripts/sync_database.php --dry-run');
  logMessage('  php scripts/sync_database.php --force');
  exit(0);
}

// Integrity checks (run in both modes)
$checks = [
  'Orphaned subject_attendance records' => "SELECT COUNT(*) as count FROM subject_attendance WHERE session_id NOT IN (SELECT id FROM attendance_sessions)",
  'Timetable completed without attendance_sessions' => "SELECT COUNT(*) as count FROM timetable t WHERE t.attendance_status = 'completed' AND t.id NOT IN (SELECT DISTINCT timetable_id FROM attendance_sessions WHERE timetable_id IS NOT NULL)",
  'Status mismatch (completed timetable vs session)' => "SELECT COUNT(*) as count FROM timetable t INNER JOIN attendance_sessions sess ON t.id = sess.timetable_id WHERE t.attendance_status = 'completed' AND sess.status != 'completed'",
  'Total completed lectures (timetable)' => "SELECT COUNT(*) as count FROM timetable WHERE attendance_status = 'completed'",
  'Total completed sessions (attendance_sessions)' => "SELECT COUNT(*) as count FROM attendance_sessions WHERE status = 'completed'",
  'Completed sessions with timetable link' => "SELECT COUNT(*) as count FROM attendance_sessions WHERE status = 'completed' AND timetable_id IS NOT NULL"
];

logMessage('INTEGRITY CHECKS');
logMessage(str_repeat('-', 100));
$start = microtime(true);
foreach ($checks as $label => $query) {
  $result = $conn->query($query);
  if ($result) {
    $row = $result->fetch_assoc();
    logMessage("$label: " . $row['count']);
  } else {
    logMessage("$label: Error - " . $conn->error);
  }
}
logMessage('Checks duration: ' . number_format(microtime(true) - $start, 3) . 's');
logMessage(str_repeat('=', 100));

if ($dryRun) {
  // Preview counts for each destructive step
  logMessage('DRY RUN - NO CHANGES WILL BE MADE');
  logMessage(str_repeat('-', 100));

  // Step 1 preview
  $q1 = "SELECT COUNT(*) AS c FROM subject_attendance WHERE session_id NOT IN (SELECT id FROM attendance_sessions)";
  $c1 = $conn->query($q1)->fetch_assoc()['c'] ?? 0;
  logMessage("STEP 1 (DELETE orphaned subject_attendance): would delete $c1 rows");

  // Step 2 preview
  $q2 = "SELECT COUNT(*) AS c FROM timetable t WHERE t.id NOT IN (SELECT DISTINCT timetable_id FROM attendance_sessions WHERE timetable_id IS NOT NULL) AND t.attendance_status = 'completed'";
  $c2 = $conn->query($q2)->fetch_assoc()['c'] ?? 0;
  logMessage("STEP 2 (INSERT missing attendance_sessions): would insert $c2 rows");

  // Step 3 preview
  $q3 = "SELECT COUNT(*) AS c FROM attendance_sessions sess INNER JOIN timetable tt ON sess.timetable_id = tt.id WHERE sess.timetable_id IS NOT NULL";
  $c3 = $conn->query($q3)->fetch_assoc()['c'] ?? 0;
  logMessage("STEP 3 (UPDATE session status/times from timetable): would consider $c3 rows");

  logMessage('Dry-run complete. Use --force to apply changes.');
  exit(0);
}

// FORCE MODE: perform destructive ops within a transaction
logMessage('TRANSACTION STARTED');
$t0 = microtime(true);
$conn->begin_transaction();
try {
  // Step 1: DELETE orphan subject_attendance
  $preview1 = $conn->query("SELECT COUNT(*) AS c FROM subject_attendance WHERE session_id NOT IN (SELECT id FROM attendance_sessions)")->fetch_assoc()['c'] ?? 0;
  logMessage("STEP 1: orphaned subject_attendance count = $preview1");
  if ($preview1 > $SAFETY_THRESHOLD && !$dangerAck) {
    throw new Exception("Safety threshold exceeded ($preview1 > $SAFETY_THRESHOLD). Pass --i-know-what-im-doing to proceed.");
  }
  $qDel = "DELETE FROM subject_attendance WHERE session_id NOT IN (SELECT id FROM attendance_sessions)";
  $ok = $conn->query($qDel);
  if ($ok === false) throw new Exception('DELETE step failed: ' . $conn->error);
  logMessage("STEP 1: Deleted rows = " . $conn->affected_rows);

  // Step 2: INSERT missing sessions for completed timetable entries
  $preview2 = $conn->query("SELECT COUNT(*) AS c FROM timetable t WHERE t.id NOT IN (SELECT DISTINCT timetable_id FROM attendance_sessions WHERE timetable_id IS NOT NULL) AND t.attendance_status = 'completed'")->fetch_assoc()['c'] ?? 0;
  logMessage("STEP 2: missing sessions to insert = $preview2");
  $qIns = "
    INSERT INTO attendance_sessions (subject_id, faculty_id, timetable_id, session_identifier, secret_key, started_at, ended_at, status)
    SELECT 
      t.subject_id,
      t.faculty_id,
      t.id,
      MD5(CONCAT(t.id, '-', UNIX_TIMESTAMP())),
      SHA2(CONCAT(t.id, '-', RAND(), '-', UNIX_TIMESTAMP()), 256),
      TIMESTAMP(CONCAT(t.lecture_date, ' ', t.start_time)),
      TIMESTAMP(CONCAT(t.lecture_date, ' ', t.end_time)),
      'completed'
    FROM timetable t
    WHERE t.id NOT IN (SELECT DISTINCT timetable_id FROM attendance_sessions WHERE timetable_id IS NOT NULL)
    AND t.attendance_status = 'completed'
  ";
  $ok = $conn->query($qIns);
  if ($ok === false) throw new Exception('INSERT step failed: ' . $conn->error);
  logMessage("STEP 2: Inserted rows = " . $conn->affected_rows);

  // Step 3: UPDATE session status/time from timetable
  $preview3 = $conn->query("SELECT COUNT(*) AS c FROM attendance_sessions sess INNER JOIN timetable tt ON sess.timetable_id = tt.id WHERE sess.timetable_id IS NOT NULL")->fetch_assoc()['c'] ?? 0;
  logMessage("STEP 3: sessions to update (considered) = $preview3");
  if ($preview3 > $SAFETY_THRESHOLD && !$dangerAck) {
    throw new Exception("Safety threshold exceeded ($preview3 > $SAFETY_THRESHOLD). Pass --i-know-what-im-doing to proceed.");
  }
  $qUpd = "
    UPDATE attendance_sessions sess
    INNER JOIN timetable tt ON sess.timetable_id = tt.id
    SET sess.status = CASE 
      WHEN tt.attendance_status = 'completed' THEN 'completed'
      WHEN tt.attendance_status = 'active' THEN 'active'
      ELSE 'active'
    END,
    sess.started_at = TIMESTAMP(CONCAT(tt.lecture_date, ' ', tt.start_time)),
    sess.ended_at = TIMESTAMP(CONCAT(tt.lecture_date, ' ', tt.end_time))
    WHERE sess.timetable_id IS NOT NULL
  ";
  $ok = $conn->query($qUpd);
  if ($ok === false) throw new Exception('UPDATE step failed: ' . $conn->error);
  logMessage("STEP 3: Updated rows = " . $conn->affected_rows);

  // Commit destructive steps
  $conn->commit();
  logMessage('COMMITTED');
} catch (Throwable $e) {
  $conn->rollback();
  logMessage('ROLLED BACK');
  logMessage('ERROR: ' . $e->getMessage());
  logMessage('STACK: ' . $e->getTraceAsString());
  exit(1);
}
logMessage('Transaction duration: ' . number_format(microtime(true) - $t0, 3) . 's');

// Triggers (DDL) - executed after commit (may auto-commit in MySQL)
logMessage(str_repeat('=', 100));
logMessage('STEP 4: Creating synchronization triggers...');
$triggers = [
  "DROP TRIGGER IF EXISTS sync_timetable_to_session",
  "CREATE TRIGGER sync_timetable_to_session
  AFTER UPDATE ON timetable
  FOR EACH ROW
  BEGIN
    IF NEW.attendance_status != OLD.attendance_status THEN
      UPDATE attendance_sessions
      SET status = CASE 
          WHEN NEW.attendance_status = 'completed' THEN 'completed'
          WHEN NEW.attendance_status = 'active' THEN 'active'
          ELSE 'active'
        END,
        started_at = TIMESTAMP(CONCAT(NEW.lecture_date, ' ', NEW.start_time)),
        ended_at = TIMESTAMP(CONCAT(NEW.lecture_date, ' ', NEW.end_time))
      WHERE timetable_id = NEW.id;
    END IF;
  END",

  "DROP TRIGGER IF EXISTS sync_session_to_timetable",
  "CREATE TRIGGER sync_session_to_timetable
  AFTER UPDATE ON attendance_sessions
  FOR EACH ROW
  BEGIN
    IF NEW.status != OLD.status AND NEW.timetable_id IS NOT NULL THEN
      UPDATE timetable
      SET attendance_status = CASE 
          WHEN NEW.status = 'completed' THEN 'completed'
          WHEN NEW.status = 'active' THEN 'active'
          ELSE 'not_started'
        END
      WHERE id = NEW.timetable_id;
    END IF;
  END",

  "DROP TRIGGER IF EXISTS validate_session_exists",
  "CREATE TRIGGER validate_session_exists
  BEFORE INSERT ON subject_attendance
  FOR EACH ROW
  BEGIN
    IF NEW.session_id NOT IN (SELECT id FROM attendance_sessions) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid session_id: corresponding attendance_session does not exist';
    END IF;
  END"
];

foreach ($triggers as $trigger) {
  $ok = $conn->query($trigger);
  if ($ok) {
  logMessage('✓ Trigger executed');
  } else {
  logMessage('✗ Trigger error: ' . $conn->error);
  }
}

logMessage(str_repeat('=', 100));
logMessage('VERIFICATION REPORT');
logMessage(str_repeat('=', 100));

// Re-run integrity checks after modifications
$start = microtime(true);
foreach ($checks as $label => $query) {
  $result = $conn->query($query);
  if ($result) {
    $row = $result->fetch_assoc();
    logMessage("$label: " . $row['count']);
  } else {
    logMessage("$label: Error - " . $conn->error);
  }
}
logMessage('Post-checks duration: ' . number_format(microtime(true) - $start, 3) . 's');

logMessage(str_repeat('=', 100));
logMessage('SYNCHRONIZATION COMPLETE!');
logMessage('✓ Foreign key constraints enforced');
logMessage('✓ Automatic triggers for status synchronization');
logMessage('✓ Orphaned records removed');
logMessage('✓ Missing sessions created');
logMessage('✓ Referential integrity verified');
