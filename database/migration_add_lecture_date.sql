-- Migration: Add lecture_date to timetable
-- Run this on existing databases to support date-specific scheduling.

ALTER TABLE timetable
    ADD COLUMN lecture_date DATE NOT NULL DEFAULT CURRENT_DATE AFTER day_of_week,
    ADD INDEX idx_lecture_date (lecture_date);

-- Backfill existing rows to ensure consistency
UPDATE timetable SET lecture_date = CURRENT_DATE WHERE lecture_date IS NULL;

-- Optional: drop default to match schema design (date must be provided going forward)
ALTER TABLE timetable MODIFY lecture_date DATE NOT NULL;
