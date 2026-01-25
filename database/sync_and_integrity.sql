-- Database Synchronization and Integrity Fix
-- This script ensures all tables are properly connected and synchronized

-- Step 1: Ensure all timetable entries have corresponding attendance_sessions
-- For completed timetable entries without sessions, create sessions
INSERT INTO attendance_sessions (subject_id, faculty_id, timetable_id, session_identifier, secret_key, started_at, ended_at, status)
SELECT 
    t.subject_id,
    t.faculty_id,
    t.id,
    MD5(CONCAT(t.id, '-', UNIX_TIMESTAMP())),
    SHA2(CONCAT(t.id, '-', RAND(), '-', UNIX_TIMESTAMP()), 256),
    TIMESTAMP(CONCAT(t.lecture_date, ' ', t.start_time)),
    TIMESTAMP(CONCAT(t.lecture_date, ' ', t.end_time)),
    CASE WHEN t.attendance_status = 'completed' THEN 'completed' 
         WHEN t.attendance_status = 'active' THEN 'active'
         ELSE 'active' END
FROM timetable t
WHERE t.id NOT IN (SELECT DISTINCT timetable_id FROM attendance_sessions WHERE timetable_id IS NOT NULL)
AND t.attendance_status IN ('completed', 'active');

-- Step 2: Update attendance_sessions to match timetable status where timetable_id is linked
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
AND (sess.status != CASE 
    WHEN tt.attendance_status = 'completed' THEN 'completed'
    WHEN tt.attendance_status = 'active' THEN 'active'
    ELSE 'active'
END OR sess.started_at != TIMESTAMP(CONCAT(tt.lecture_date, ' ', tt.start_time)));

-- Step 3: Ensure all subject_attendance records have corresponding attendance_sessions
DELETE FROM subject_attendance 
WHERE session_id NOT IN (SELECT id FROM attendance_sessions);

-- Step 4: Add trigger to sync timetable status to attendance_sessions (auto update)
DELIMITER $$

DROP TRIGGER IF EXISTS sync_timetable_to_session$$
CREATE TRIGGER sync_timetable_to_session
AFTER UPDATE ON timetable
FOR EACH ROW
BEGIN
    -- Update the corresponding attendance_session when timetable status changes
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
END$$

-- Trigger to sync attendance_sessions to timetable (auto update)
DROP TRIGGER IF EXISTS sync_session_to_timetable$$
CREATE TRIGGER sync_session_to_timetable
AFTER UPDATE ON attendance_sessions
FOR EACH ROW
BEGIN
    -- Update the corresponding timetable when session status changes
    IF NEW.status != OLD.status AND NEW.timetable_id IS NOT NULL THEN
        UPDATE timetable
        SET attendance_status = CASE 
                WHEN NEW.status = 'completed' THEN 'completed'
                WHEN NEW.status = 'active' THEN 'active'
                ELSE 'not_started'
            END
        WHERE id = NEW.timetable_id;
    END IF;
END$$

-- Trigger to prevent orphaned subject_attendance records
DROP TRIGGER IF EXISTS validate_session_exists$$
CREATE TRIGGER validate_session_exists
BEFORE INSERT ON subject_attendance
FOR EACH ROW
BEGIN
    IF NEW.session_id NOT IN (SELECT id FROM attendance_sessions) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid session_id: corresponding attendance_session does not exist';
    END IF;
END$$

DELIMITER ;

-- Step 5: Create indexes for better performance on common queries
ALTER TABLE timetable ADD INDEX idx_attendance_status (attendance_status);
ALTER TABLE timetable ADD INDEX idx_subject_faculty (subject_id, faculty_id);
ALTER TABLE attendance_sessions ADD INDEX idx_timetable_status (timetable_id, status);
ALTER TABLE subject_attendance ADD INDEX idx_subject_student (subject_id, student_id);
ALTER TABLE subject_attendance ADD INDEX idx_attendance_date_status (attendance_date, status);

-- Step 6: Verify data integrity
SELECT 'Checking for orphaned subject_attendance records' as check_type;
SELECT COUNT(*) as orphaned_count FROM subject_attendance WHERE session_id NOT IN (SELECT id FROM attendance_sessions);

SELECT 'Checking for timetable without attendance_sessions' as check_type;
SELECT COUNT(*) as timetable_without_session FROM timetable t 
WHERE t.attendance_status IN ('completed', 'active')
AND t.id NOT IN (SELECT DISTINCT timetable_id FROM attendance_sessions WHERE timetable_id IS NOT NULL);

SELECT 'Checking status mismatch between timetable and attendance_sessions' as check_type;
SELECT COUNT(*) as status_mismatch FROM (
    SELECT t.id, t.attendance_status, sess.status
    FROM timetable t
    INNER JOIN attendance_sessions sess ON t.id = sess.timetable_id
    WHERE (t.attendance_status = 'completed' AND sess.status != 'completed')
    OR (t.attendance_status = 'active' AND sess.status != 'active')
) mismatches;

SELECT 'Completed lectures count (from timetable)' as description;
SELECT COUNT(*) as total FROM timetable WHERE attendance_status = 'completed';

SELECT 'Completed attendance sessions count' as description;
SELECT COUNT(*) as total FROM attendance_sessions WHERE status = 'completed';

SELECT 'Completed sessions with timetable link' as description;
SELECT COUNT(*) as total FROM attendance_sessions WHERE status = 'completed' AND timetable_id IS NOT NULL;
