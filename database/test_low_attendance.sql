-- Test data for low attendance feature
-- This script creates sample attendance records to test the low attendance reporting

-- First, let's add attendance sessions for testing
INSERT INTO attendance_sessions (subject_id, faculty_id, session_identifier, secret_key, started_at, ended_at, status)
VALUES 
    (1, 2, 'session_cs101_001', 'key_001', '2026-01-19 09:00:00', '2026-01-19 10:30:00', 'completed'),
    (1, 2, 'session_cs101_002', 'key_002', '2026-01-20 09:00:00', '2026-01-20 10:30:00', 'completed'),
    (1, 2, 'session_cs101_003', 'key_003', '2026-01-21 09:00:00', '2026-01-21 10:30:00', 'completed'),
    (1, 2, 'session_cs101_004', 'key_004', '2026-01-22 09:00:00', '2026-01-22 10:30:00', 'completed'),
    (1, 2, 'session_cs101_005', 'key_005', '2026-01-23 09:00:00', '2026-01-23 10:30:00', 'completed'),
    
    (2, 2, 'session_cs201_001', 'key_006', '2026-01-19 11:00:00', '2026-01-19 12:30:00', 'completed'),
    (2, 2, 'session_cs201_002', 'key_007', '2026-01-20 11:00:00', '2026-01-20 12:30:00', 'completed'),
    (2, 2, 'session_cs201_003', 'key_008', '2026-01-21 11:00:00', '2026-01-21 12:30:00', 'completed'),
    (2, 2, 'session_cs201_004', 'key_009', '2026-01-22 11:00:00', '2026-01-22 12:30:00', 'completed'),
    (2, 2, 'session_cs201_005', 'key_010', '2026-01-23 11:00:00', '2026-01-23 12:30:00', 'completed'),
    
    (3, 3, 'session_math101_001', 'key_011', '2026-01-19 14:00:00', '2026-01-19 15:30:00', 'completed'),
    (3, 3, 'session_math101_002', 'key_012', '2026-01-20 14:00:00', '2026-01-20 15:30:00', 'completed'),
    (3, 3, 'session_math101_003', 'key_013', '2026-01-21 14:00:00', '2026-01-21 15:30:00', 'completed'),
    (3, 3, 'session_math101_004', 'key_014', '2026-01-22 14:00:00', '2026-01-22 15:30:00', 'completed'),
    (3, 3, 'session_math101_005', 'key_015', '2026-01-23 14:00:00', '2026-01-23 15:30:00', 'completed'),
    
    (4, 3, 'session_phy101_001', 'key_016', '2026-01-19 09:00:00', '2026-01-19 10:30:00', 'completed'),
    (4, 3, 'session_phy101_002', 'key_017', '2026-01-20 09:00:00', '2026-01-20 10:30:00', 'completed'),
    (4, 3, 'session_phy101_003', 'key_018', '2026-01-21 09:00:00', '2026-01-21 10:30:00', 'completed'),
    (4, 3, 'session_phy101_004', 'key_019', '2026-01-22 09:00:00', '2026-01-22 10:30:00', 'completed'),
    (4, 3, 'session_phy101_005', 'key_020', '2026-01-23 09:00:00', '2026-01-23 10:30:00', 'completed');

-- Now add subject attendance records
-- Student 1 (Alice Brown - STU001): Low attendance in CS101 and MATH101
-- CS101: 1/5 present (20%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (1, 4, 1, '2026-01-19', 'present', '2026-01-19 09:05:00', 'qr_scan'),
    (2, 4, 1, '2026-01-20', 'absent', NULL, 'auto_absent'),
    (3, 4, 1, '2026-01-21', 'absent', NULL, 'auto_absent'),
    (4, 4, 1, '2026-01-22', 'absent', NULL, 'auto_absent'),
    (5, 4, 1, '2026-01-23', 'absent', NULL, 'auto_absent');

-- CS201: 2/5 present (40%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (6, 4, 2, '2026-01-19', 'present', '2026-01-19 11:05:00', 'qr_scan'),
    (7, 4, 2, '2026-01-20', 'absent', NULL, 'auto_absent'),
    (8, 4, 2, '2026-01-21', 'present', '2026-01-21 11:05:00', 'qr_scan'),
    (9, 4, 2, '2026-01-22', 'absent', NULL, 'auto_absent'),
    (10, 4, 2, '2026-01-23', 'absent', NULL, 'auto_absent');

-- MATH101: 1/5 present (20%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (11, 4, 3, '2026-01-19', 'absent', NULL, 'auto_absent'),
    (12, 4, 3, '2026-01-20', 'absent', NULL, 'auto_absent'),
    (13, 4, 3, '2026-01-21', 'present', '2026-01-21 14:05:00', 'qr_scan'),
    (14, 4, 3, '2026-01-22', 'absent', NULL, 'auto_absent'),
    (15, 4, 3, '2026-01-23', 'absent', NULL, 'auto_absent');

-- PHY101: 3/5 present (60%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (16, 4, 4, '2026-01-19', 'present', '2026-01-19 09:05:00', 'qr_scan'),
    (17, 4, 4, '2026-01-20', 'present', '2026-01-20 09:05:00', 'qr_scan'),
    (18, 4, 4, '2026-01-21', 'absent', NULL, 'auto_absent'),
    (19, 4, 4, '2026-01-22', 'present', '2026-01-22 09:05:00', 'qr_scan'),
    (20, 4, 4, '2026-01-23', 'absent', NULL, 'auto_absent');

-- Student 2 (Bob Wilson - STU002): Good overall but low in one subject
-- CS101: 4/5 present (80%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (1, 5, 1, '2026-01-19', 'present', '2026-01-19 09:05:00', 'qr_scan'),
    (2, 5, 1, '2026-01-20', 'present', '2026-01-20 09:05:00', 'qr_scan'),
    (3, 5, 1, '2026-01-21', 'absent', NULL, 'auto_absent'),
    (4, 5, 1, '2026-01-22', 'present', '2026-01-22 09:05:00', 'qr_scan'),
    (5, 5, 1, '2026-01-23', 'present', '2026-01-23 09:05:00', 'qr_scan');

-- CS201: 2/5 present (40%) - Below 75%
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (6, 5, 2, '2026-01-19', 'absent', NULL, 'auto_absent'),
    (7, 5, 2, '2026-01-20', 'present', '2026-01-20 11:05:00', 'qr_scan'),
    (8, 5, 2, '2026-01-21', 'absent', NULL, 'auto_absent'),
    (9, 5, 2, '2026-01-22', 'present', '2026-01-22 11:05:00', 'qr_scan'),
    (10, 5, 2, '2026-01-23', 'absent', NULL, 'auto_absent');

-- MATH101: 5/5 present (100%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (11, 5, 3, '2026-01-19', 'present', '2026-01-19 14:05:00', 'qr_scan'),
    (12, 5, 3, '2026-01-20', 'present', '2026-01-20 14:05:00', 'qr_scan'),
    (13, 5, 3, '2026-01-21', 'present', '2026-01-21 14:05:00', 'qr_scan'),
    (14, 5, 3, '2026-01-22', 'present', '2026-01-22 14:05:00', 'qr_scan'),
    (15, 5, 3, '2026-01-23', 'present', '2026-01-23 14:05:00', 'qr_scan');

-- PHY101: 5/5 present (100%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (16, 5, 4, '2026-01-19', 'present', '2026-01-19 09:05:00', 'qr_scan'),
    (17, 5, 4, '2026-01-20', 'present', '2026-01-20 09:05:00', 'qr_scan'),
    (18, 5, 4, '2026-01-21', 'present', '2026-01-21 09:05:00', 'qr_scan'),
    (19, 5, 4, '2026-01-22', 'present', '2026-01-22 09:05:00', 'qr_scan'),
    (20, 5, 4, '2026-01-23', 'present', '2026-01-23 09:05:00', 'qr_scan');

-- Student 3 (Carol Davis - STU003): Good attendance overall
-- All subjects: 4-5/5 present (80-100%)
INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
VALUES 
    (1, 6, 1, '2026-01-19', 'present', '2026-01-19 09:05:00', 'qr_scan'),
    (2, 6, 1, '2026-01-20', 'present', '2026-01-20 09:05:00', 'qr_scan'),
    (3, 6, 1, '2026-01-21', 'present', '2026-01-21 09:05:00', 'qr_scan'),
    (4, 6, 1, '2026-01-22', 'present', '2026-01-22 09:05:00', 'qr_scan'),
    (5, 6, 1, '2026-01-23', 'absent', NULL, 'auto_absent'),
    (6, 6, 2, '2026-01-19', 'present', '2026-01-19 11:05:00', 'qr_scan'),
    (7, 6, 2, '2026-01-20', 'present', '2026-01-20 11:05:00', 'qr_scan'),
    (8, 6, 2, '2026-01-21', 'present', '2026-01-21 11:05:00', 'qr_scan'),
    (9, 6, 2, '2026-01-22', 'present', '2026-01-22 11:05:00', 'qr_scan'),
    (10, 6, 2, '2026-01-23', 'present', '2026-01-23 11:05:00', 'qr_scan'),
    (11, 6, 3, '2026-01-19', 'present', '2026-01-19 14:05:00', 'qr_scan'),
    (12, 6, 3, '2026-01-20', 'present', '2026-01-20 14:05:00', 'qr_scan'),
    (13, 6, 3, '2026-01-21', 'present', '2026-01-21 14:05:00', 'qr_scan'),
    (14, 6, 3, '2026-01-22', 'present', '2026-01-22 14:05:00', 'qr_scan'),
    (15, 6, 3, '2026-01-23', 'present', '2026-01-23 14:05:00', 'qr_scan'),
    (16, 6, 4, '2026-01-19', 'present', '2026-01-19 09:05:00', 'qr_scan'),
    (17, 6, 4, '2026-01-20', 'present', '2026-01-20 09:05:00', 'qr_scan'),
    (18, 6, 4, '2026-01-21', 'present', '2026-01-21 09:05:00', 'qr_scan'),
    (19, 6, 4, '2026-01-22', 'present', '2026-01-22 09:05:00', 'qr_scan'),
    (20, 6, 4, '2026-01-23', 'present', '2026-01-23 09:05:00', 'qr_scan');
