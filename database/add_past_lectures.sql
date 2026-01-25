-- Add past lectures for testing the Past Lectures calendar feature
-- Run this to populate the database with lectures from previous days

-- Add lectures from 5 days ago
INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number, attendance_status)
VALUES 
    (1, 2, 'Wednesday', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:00:00', '09:30:00', 'Room 101', 'completed'),
    (2, 2, 'Wednesday', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '10:00:00', '11:30:00', 'Room 102', 'completed');

-- Add lectures from 4 days ago
INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number, attendance_status)
VALUES 
    (3, 3, 'Thursday', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:00:00', '10:30:00', 'Lab 1', 'completed'),
    (1, 2, 'Thursday', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '14:00:00', '15:30:00', 'Room 201', 'completed');

-- Add lectures from 3 days ago
INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number, attendance_status)
VALUES 
    (2, 2, 'Friday', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:00:00', '12:30:00', 'Room 102', 'completed'),
    (4, 3, 'Friday', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:00:00', '14:30:00', 'Lab 2', 'completed');

-- Add lectures from 2 days ago
INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number, attendance_status)
VALUES 
    (1, 2, 'Saturday', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00', '10:30:00', 'Room 101', 'completed'),
    (3, 3, 'Saturday', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '11:00:00', '12:30:00', 'Room 201', 'completed');

-- Add lectures from 1 day ago (yesterday)
INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number, attendance_status)
VALUES 
    (2, 2, 'Sunday', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '09:30:00', 'Room 102', 'completed'),
    (4, 3, 'Sunday', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:00:00', '15:30:00', 'Lab 1', 'completed');
