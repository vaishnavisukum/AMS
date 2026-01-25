<?php
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

// Count total completed lectures
$result = $db->query("SELECT COUNT(*) as total FROM attendance_sessions WHERE status = 'completed'");
$row = $result->fetch_assoc();
echo "Total completed lectures: " . $row['total'] . "\n\n";

// Also show breakdown by subject
echo "Breakdown by subject:\n";
echo str_repeat("-", 60) . "\n";

$subjectResult = $db->query("
    SELECT 
        s.subject_code,
        s.subject_name,
        COUNT(sess.id) as lecture_count
    FROM attendance_sessions sess
    INNER JOIN subjects s ON sess.subject_id = s.id
    WHERE sess.status = 'completed'
    GROUP BY s.id, s.subject_code, s.subject_name
    ORDER BY s.subject_code
");

printf("%-15s %-30s %s\n", "Subject Code", "Subject Name", "Lectures");
echo str_repeat("-", 60) . "\n";

while ($row = $subjectResult->fetch_assoc()) {
  printf(
    "%-15s %-30s %s\n",
    $row['subject_code'],
    $row['subject_name'],
    $row['lecture_count']
  );
}
