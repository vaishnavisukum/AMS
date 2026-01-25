<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

try {
    // Only admin can view all attendance
    Auth::requireRole('admin');

    $db = Database::getInstance();

    // Get filters from query params
    $subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
    $studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : null;
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $attendanceType = isset($_GET['type']) ? $_GET['type'] : 'subject'; // 'subject' or 'campus'

    if ($attendanceType === 'subject') {
        // Subject attendance - show all completed sessions with attendance status
        $sql = "
            SELECT 
                sess.id as session_id,
                COALESCE(sa.id, 0) as attendance_id,
                COALESCE(sa.attendance_date, DATE(sess.started_at)) as attendance_date,
                COALESCE(sa.status, 'absent') as status,
                sa.marked_at,
                COALESCE(sa.marked_method, 'auto_absent') as marked_method,
                stu.full_name as student_name,
                stu.student_id as student_number,
                sub.subject_name,
                sub.subject_code,
                fac.full_name as faculty_name,
                sess.started_at,
                sess.ended_at
            FROM attendance_sessions sess
            JOIN subjects sub ON sess.subject_id = sub.id
            JOIN users fac ON sess.faculty_id = fac.id
            CROSS JOIN users stu
            LEFT JOIN subject_attendance sa ON sa.session_id = sess.id AND sa.student_id = stu.id
            WHERE sess.status = 'completed'
            AND stu.role = 'student'
        ";

        $params = [];
        $types = "";

        if ($subjectId) {
            $sql .= " AND sess.subject_id = ?";
            $params[] = $subjectId;
            $types .= "i";
        }

        if ($studentId) {
            $sql .= " AND stu.student_id = ?";
            $params[] = $studentId;
            $types .= "s";
        }

        if ($dateFrom) {
            $sql .= " AND DATE(sess.started_at) >= ?";
            $params[] = $dateFrom;
            $types .= "s";
        }

        if ($dateTo) {
            $sql .= " AND DATE(sess.started_at) <= ?";
            $params[] = $dateTo;
            $types .= "s";
        }

        $sql .= " ORDER BY attendance_date DESC, sess.started_at DESC LIMIT 500";

        $stmt = $db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        // Campus attendance - show all dates with lectures, derive absent if no attendance
        $sql = "
            SELECT DISTINCT
                COALESCE(ca.id, 0) as id,
                dates.attendance_date,
                COALESCE(ca.status, 
                    CASE 
                        WHEN has_present.student_id IS NOT NULL THEN 'present'
                        ELSE 'absent'
                    END
                ) as status,
                ca.marked_at,
                COALESCE(ca.is_derived, 
                    CASE 
                        WHEN ca.id IS NULL THEN 1
                        ELSE 0
                    END
                ) as is_derived,
                u.full_name as student_name,
                u.student_id as student_number
            FROM (
                SELECT DISTINCT DATE(sess.started_at) as attendance_date
                FROM attendance_sessions sess
                WHERE sess.status = 'completed'
            ) dates
            CROSS JOIN users u
            LEFT JOIN campus_attendance ca ON ca.student_id = u.id AND ca.attendance_date = dates.attendance_date
            LEFT JOIN (
                SELECT DISTINCT sa.student_id, DATE(sess.started_at) as session_date
                FROM subject_attendance sa
                JOIN attendance_sessions sess ON sa.session_id = sess.id
                WHERE sa.status = 'present' AND sess.status = 'completed'
            ) has_present ON has_present.student_id = u.id AND has_present.session_date = dates.attendance_date
            WHERE u.role = 'student'
        ";

        $params = [];
        $types = "";

        if ($studentId) {
            $sql .= " AND u.student_id = ?";
            $params[] = $studentId;
            $types .= "s";
        }

        if ($dateFrom) {
            $sql .= " AND dates.attendance_date >= ?";
            $params[] = $dateFrom;
            $types .= "s";
        }

        if ($dateTo) {
            $sql .= " AND dates.attendance_date <= ?";
            $params[] = $dateTo;
            $types .= "s";
        }

        $sql .= " ORDER BY dates.attendance_date DESC LIMIT 500";

        $stmt = $db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'type' => $attendanceType,
        'records' => $records
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching attendance records: ' . $e->getMessage()
    ]);
}
