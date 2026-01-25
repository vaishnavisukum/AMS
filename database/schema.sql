-- QR-based Attendance Management System Database Schema
-- Created: 2026-01-20

-- Drop existing tables if they exist
DROP TABLE IF EXISTS attendance_logs;
DROP TABLE IF EXISTS subject_attendance;
DROP TABLE IF EXISTS campus_attendance;
DROP TABLE IF EXISTS attendance_sessions;
DROP TABLE IF EXISTS timetable;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS users;

-- Users table (Students, Faculty, Admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'faculty', 'admin') NOT NULL,
    student_id VARCHAR(20) UNIQUE NULL,
    faculty_id VARCHAR(20) UNIQUE NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subjects table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    department VARCHAR(50),
    semester INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subject_code (subject_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timetable table
CREATE TABLE timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    faculty_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    lecture_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(20),
    attendance_status ENUM('not_started', 'active', 'completed') DEFAULT 'not_started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_faculty (faculty_id),
    INDEX idx_subject (subject_id),
    INDEX idx_day (day_of_week),
    INDEX idx_lecture_date (lecture_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Sessions table (for subject attendance)
CREATE TABLE attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    faculty_id INT NOT NULL,
    timetable_id INT NULL,
    session_identifier VARCHAR(64) UNIQUE NOT NULL,
    secret_key VARCHAR(128) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    qr_scan_count INT DEFAULT 0,
    physical_headcount INT NULL,
    headcount_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (timetable_id) REFERENCES timetable(id) ON DELETE SET NULL,
    INDEX idx_session_identifier (session_identifier),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campus Attendance table (daily presence)
CREATE TABLE campus_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent') DEFAULT 'absent',
    marked_at TIMESTAMP NULL,
    is_derived BOOLEAN DEFAULT FALSE COMMENT 'True if derived from subject attendance',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_date (student_id, attendance_date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_date (attendance_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subject Attendance table (class attendance)
CREATE TABLE subject_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent') DEFAULT 'absent',
    marked_at TIMESTAMP NULL,
    marked_method ENUM('qr_scan', 'manual', 'auto_absent') DEFAULT 'qr_scan',
    qr_data TEXT NULL COMMENT 'Stored QR data for audit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session_student (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_session (session_id),
    INDEX idx_date (attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Logs table (for manual changes and audit trail)
CREATE TABLE attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_type ENUM('campus', 'subject') NOT NULL,
    attendance_id INT NOT NULL COMMENT 'ID from campus_attendance or subject_attendance',
    student_id INT NOT NULL,
    modified_by INT NOT NULL COMMENT 'Faculty or Admin user ID',
    old_status ENUM('present', 'absent'),
    new_status ENUM('present', 'absent'),
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_modified_by (modified_by),
    INDEX idx_type (attendance_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user
-- NOTE: Current password hash is for "password" not "admin123"
-- To use "admin123", run test_login.php and use the generated SQL commands
-- Current working password: "password"
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@ams.com', 'admin');

-- Sample data for testing (optional)
-- Faculty users (password: "password")
INSERT INTO users (username, password, full_name, email, role, faculty_id) VALUES
('faculty1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. John Smith', 'john.smith@ams.com', 'faculty', 'FAC001'),
('faculty2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Sarah Johnson', 'sarah.johnson@ams.com', 'faculty', 'FAC002');

-- Student users (password: "password")
INSERT INTO users (username, password, full_name, email, role, student_id) VALUES
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Brown', 'alice.brown@student.ams.com', 'student', 'STU001'),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Wilson', 'bob.wilson@student.ams.com', 'student', 'STU002'),
('student3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carol Davis', 'carol.davis@student.ams.com', 'student', 'STU003');

-- Sample subjects
INSERT INTO subjects (subject_code, subject_name, department, semester) VALUES
('CS101', 'Introduction to Programming', 'Computer Science', 1),
('CS201', 'Data Structures', 'Computer Science', 3),
('MATH101', 'Calculus I', 'Mathematics', 1),
('PHY101', 'Physics I', 'Physics', 1);

-- Sample timetable (dates align with 2026 week starting Monday 2026-01-19)
INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number) VALUES
(1, 2, 'Monday', '2026-01-19', '09:00:00', '10:30:00', 'Room 101'),
(2, 2, 'Tuesday', '2026-01-20', '11:00:00', '12:30:00', 'Room 102'),
(3, 3, 'Wednesday', '2026-01-21', '14:00:00', '15:30:00', 'Room 201'),
(4, 3, 'Thursday', '2026-01-22', '09:00:00', '10:30:00', 'Lab 1');

