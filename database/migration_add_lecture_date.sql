-- Migration: Add lecture_date to timetable
-- Run this on existing databases to support date-specific scheduling.

-- Check if column exists, only add if missing
SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'timetable' 
    AND COLUMN_NAME = 'lecture_date'
);

-- Only execute if column doesn't exist
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE timetable ADD COLUMN lecture_date DATE NOT NULL DEFAULT CURRENT_DATE AFTER day_of_week, ADD INDEX idx_lecture_date (lecture_date)',
    'SELECT "Column lecture_date already exists, skipping ALTER TABLE"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
