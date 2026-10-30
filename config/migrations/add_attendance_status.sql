-- Add status column to attendance_summaries if it does not exist.
-- Defaults to 'Draft' so existing records stay hidden from the public dashboard.
ALTER TABLE attendance_summaries
    ADD COLUMN IF NOT EXISTS status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft'
    AFTER recorded_by;

-- Add unique key so ON DUPLICATE KEY UPDATE works (attendance_date, service_name).
ALTER TABLE attendance_summaries
    ADD UNIQUE KEY IF NOT EXISTS uniq_attendance_date_service (attendance_date, service_name);

-- Add status column to events if missing (used by main dashboard).
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS status ENUM('Draft','Published') NOT NULL DEFAULT 'Published'
    AFTER description;

-- Add status to meeting_minutes if missing (used by main dashboard).
ALTER TABLE meeting_minutes
    ADD COLUMN IF NOT EXISTS status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft'
    AFTER resolutions;

-- Add service_program table if it does not exist (referenced by dashboard.php).
CREATE TABLE IF NOT EXISTS service_program (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(150) NOT NULL,
    program_day VARCHAR(20) NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    program_order INT NOT NULL DEFAULT 1,
    activity_name VARCHAR(255) NOT NULL,
    person_responsible VARCHAR(150) NULL,
    notes TEXT NULL,
    status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);