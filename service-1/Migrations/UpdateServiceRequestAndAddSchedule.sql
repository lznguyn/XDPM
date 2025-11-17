-- =====================================================
-- Update ServiceRequests and Add SpecialistSchedules
-- =====================================================

USE MuTraProDB;

-- Update ServiceRequests table - add new columns (check if exists first)
SET @dbname = DATABASE();
SET @tablename = "ServiceRequests";
SET @columnname = "preferred_specialist_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " INT NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "scheduled_date";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DATETIME NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "scheduled_time_slot";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(20) NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "meeting_notes";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " TEXT NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key and index for preferred_specialist_id (if not exists)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = @dbname 
                  AND TABLE_NAME = @tablename 
                  AND COLUMN_NAME = 'preferred_specialist_id' 
                  AND CONSTRAINT_NAME LIKE '%preferred%');
SET @preparedStatement = (SELECT IF(
  @fk_exists > 0,
  "SELECT 'Foreign key already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD FOREIGN KEY (preferred_specialist_id) REFERENCES Users(Id) ON DELETE SET NULL, ADD INDEX idx_preferred_specialist (preferred_specialist_id)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update default status to 'Pending' for new records
ALTER TABLE ServiceRequests 
MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending';

-- Create SpecialistSchedules table
CREATE TABLE IF NOT EXISTS SpecialistSchedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    specialist_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot_1 BOOLEAN DEFAULT FALSE COMMENT '0h-4h',
    time_slot_2 BOOLEAN DEFAULT FALSE COMMENT '6h-10h',
    time_slot_3 BOOLEAN DEFAULT FALSE COMMENT '12h-16h',
    time_slot_4 BOOLEAN DEFAULT FALSE COMMENT '18h-22h',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (specialist_id) REFERENCES Users(Id) ON DELETE CASCADE,
    UNIQUE KEY unique_specialist_date (specialist_id, date),
    INDEX idx_specialist_id (specialist_id),
    INDEX idx_date (date)
);

-- Create Notifications table for system notifications
CREATE TABLE IF NOT EXISTS Notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(Id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);
