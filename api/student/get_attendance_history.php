<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only students can view their own attendance
Auth::requireRole('student');

$studentId = Auth::userId();
$db = Database::getInstance();

// Get subject attendance history
$stmt = $db->prepare("
    SELECT 
        sa.id, sa.attendance_date, sa.status, sa.marked_at, sa.marked_method,
        sub.subject_name, sub.subject_code,
        u.full_name as faculty_name
    FROM subject_attendance sa
    JOIN subjects sub ON sa.subject_id = sub.id
    JOIN attendance_sessions sess ON sa.session_id = sess.id
    JOIN users u ON sess.faculty_id = u.id
    WHERE sa.student_id = ?
    ORDER BY sa.attendance_date DESC, sa.marked_at DESC
    LIMIT 100
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$subjectAttendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get campus attendance history - include all dates with lectures
$stmt = $db->prepare("
    SELECT DISTINCT
        COALESCE(ca.id, 0) as id,
        dates.attendance_date,
        COALESCE(ca.status, 
            CASE 
                WHEN has_present.session_date IS NOT NULL THEN 'present'
                ELSE 'absent'
            END
        ) as status,
        ca.marked_at,
        COALESCE(ca.is_derived, 
            CASE 
                WHEN ca.id IS NULL THEN 1
                ELSE 0
            END
        ) as is_derived
    FROM (
        SELECT DISTINCT DATE(sess.started_at) as attendance_date
        FROM attendance_sessions sess
        WHERE sess.status = 'completed'
    ) dates
    LEFT JOIN campus_attendance ca ON ca.student_id = ? AND ca.attendance_date = dates.attendance_date
    LEFT JOIN (
        SELECT DISTINCT DATE(sess.started_at) as session_date
        FROM subject_attendance sa
        JOIN attendance_sessions sess ON sa.session_id = sess.id
        WHERE sa.student_id = ? AND sa.status = 'present' AND sess.status = 'completed'
    ) has_present ON has_present.session_date = dates.attendance_date
    ORDER BY dates.attendance_date DESC
    LIMIT 100
");
$stmt->bind_param("ii", $studentId, $studentId);
$stmt->execute();
$campusAttendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get subject attendance statistics (present/absent count per subject)
// Always include subjects that have completed sessions so students see the graph even without prior attendance
$stmt = $db->prepare("
    SELECT 
        sub.id, sub.subject_code, sub.subject_name,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) AS recorded_absent_count,
        COUNT(DISTINCT sess.id) AS total_sessions
    FROM subjects sub
    INNER JOIN attendance_sessions sess ON sub.id = sess.subject_id AND sess.status = 'completed'
    LEFT JOIN subject_attendance sa ON sa.session_id = sess.id AND sa.student_id = ?
    GROUP BY sub.id, sub.subject_code, sub.subject_name
    ORDER BY sub.subject_name
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$subjectStatsRaw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Derive absent count to include sessions with no record for the student
$subjectStats = array_map(function ($row) {
    $present = (int)$row['present_count'];
    $recordedAbsent = (int)$row['recorded_absent_count'];
    $totalSessions = (int)$row['total_sessions'];

    $unmarkedAsAbsent = max($totalSessions - ($present + $recordedAbsent), 0);
    $absentTotal = $recordedAbsent + $unmarkedAsAbsent;

    return [
        'id' => (int)$row['id'],
        'subject_code' => $row['subject_code'],
        'subject_name' => $row['subject_name'],
        'present_count' => $present,
        'absent_count' => $absentTotal,
        'total_count' => $totalSessions
    ];
}, $subjectStatsRaw);

// Get detailed lecture-wise attendance for each subject
$stmt = $db->prepare("
    SELECT 
        sess.id as session_id,
        sess.started_at as lecture_date,
        sess.ended_at,
        sub.id as subject_id,
        sub.subject_code,
        sub.subject_name,
        u.full_name as faculty_name,
        sa.status,
        sa.marked_method,
        sa.marked_at
    FROM attendance_sessions sess
    JOIN subjects sub ON sess.subject_id = sub.id
    JOIN users u ON sess.faculty_id = u.id
    LEFT JOIN subject_attendance sa ON sess.id = sa.session_id AND sa.student_id = ?
    WHERE sess.status = 'completed'
    ORDER BY sub.subject_name, sess.started_at DESC
    LIMIT 200
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$lectureWiseAttendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group lecture-wise attendance by subject
$lecturesBySubject = [];
foreach ($lectureWiseAttendance as $record) {
    $subjectKey = $record['subject_id'];
    if (!isset($lecturesBySubject[$subjectKey])) {
        $lecturesBySubject[$subjectKey] = [
            'subject_id' => $record['subject_id'],
            'subject_code' => $record['subject_code'],
            'subject_name' => $record['subject_name'],
            'lectures' => []
        ];
    }
    $lecturesBySubject[$subjectKey]['lectures'][] = [
        'session_id' => $record['session_id'],
        'lecture_date' => $record['lecture_date'],
        'ended_at' => $record['ended_at'],
        'faculty_name' => $record['faculty_name'],
        'status' => $record['status'] ?? 'absent',
        'marked_method' => $record['marked_method'],
        'marked_at' => $record['marked_at']
    ];
}

// Get overall campus attendance statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
        COUNT(*) as total_count
    FROM campus_attendance
    WHERE student_id = ?
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$campusStats = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'subject_attendance' => $subjectAttendance,
    'campus_attendance' => $campusAttendance,
    'subject_stats' => $subjectStats,
    'campus_stats' => $campusStats,
    'lectures_by_subject' => array_values($lecturesBySubject)
]);
