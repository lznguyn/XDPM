-- =====================================================
-- Update Studios Table - Add price and image columns
-- =====================================================

USE MuTraProDB;

-- Add price column to Studios table (if not exists)
SET @dbname = DATABASE();
SET @tablename = "Studios";
SET @columnname = "price";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column price already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DECIMAL(18,2) NOT NULL DEFAULT 0")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add image column to Studios table (if not exists)
SET @columnname = "image";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column image already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " LONGTEXT NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update status column to use string instead of int if it's currently int
-- This is to support the enum conversion to string
SET @column_type = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = @dbname 
                    AND TABLE_NAME = @tablename 
                    AND COLUMN_NAME = 'status');
                    
SET @preparedStatement = (SELECT IF(
  @column_type = 'int',
  CONCAT("ALTER TABLE ", @tablename, " MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Available'"),
  "SELECT 'Column status is already VARCHAR or does not need modification.'"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SELECT 'Studios table update completed successfully!' AS result;

