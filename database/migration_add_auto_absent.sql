-- Migration: Add 'auto_absent' to marked_method enum
-- Run this if your database already has the subject_attendance table

ALTER TABLE subject_attendance 
MODIFY marked_method ENUM('qr_scan', 'manual', 'auto_absent') DEFAULT 'qr_scan';

-- Optional: Add a note to the schema
-- This allows the system to automatically mark students as absent when a lecture completes
-- and they have not scanned the QR code
