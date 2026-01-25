<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

try {
    // Admin and Faculty can view attendance logs
    Auth::require();
    if (!in_array(Auth::role(), ['admin', 'faculty'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $db = Database::getInstance();

    $stmt = $db->prepare("
        SELECT 
            al.id, al.attendance_type, al.attendance_id, al.old_status, al.new_status, 
            al.reason, al.created_at,
            stu.full_name as student_name, stu.student_id as student_number,
            modifier.full_name as modified_by_name, modifier.role as modified_by_role,
            sa.attendance_date as subject_attendance_date,
            sub.subject_name, sub.subject_code,
            ca.attendance_date as campus_attendance_date
        FROM attendance_logs al
        JOIN users stu ON al.student_id = stu.id
        JOIN users modifier ON al.modified_by = modifier.id
        LEFT JOIN subject_attendance sa ON al.attendance_type = 'subject' AND al.attendance_id = sa.id
        LEFT JOIN subjects sub ON sa.subject_id = sub.id
        LEFT JOIN campus_attendance ca ON al.attendance_type = 'campus' AND al.attendance_id = ca.id
        ORDER BY al.created_at DESC
        LIMIT 200
    ");
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching logs: ' . $e->getMessage()
    ]);
}
