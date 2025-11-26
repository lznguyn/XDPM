-- =====================================================
-- Optimize ServiceRequests Table Performance
-- =====================================================
-- Thêm các index để tối ưu query performance cho admin page
-- =====================================================

USE MuTraProDB;

-- Step 1: Thêm index trên created_date để tối ưu OrderBy
SET @dbname = DATABASE();
SET @tablename = "ServiceRequests";
SET @indexname = "idx_created_date";

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = @dbname 
                     AND TABLE_NAME = @tablename 
                     AND INDEX_NAME = @indexname);

SET @preparedStatement = (SELECT IF(
  @index_exists > 0,
  "SELECT 'Index idx_created_date already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (created_date DESC)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 2: Thêm composite index trên (status, created_date) để tối ưu filter + sort
SET @indexname = "idx_status_created_date";
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = @dbname 
                     AND TABLE_NAME = @tablename 
                     AND INDEX_NAME = @indexname);

SET @preparedStatement = (SELECT IF(
  @index_exists > 0,
  "SELECT 'Index idx_status_created_date already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (status, created_date DESC)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 3: Thêm composite index trên (service_type, created_date) để tối ưu filter theo service type
SET @indexname = "idx_service_type_created_date";
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = @dbname 
                     AND TABLE_NAME = @tablename 
                     AND INDEX_NAME = @indexname);

SET @preparedStatement = (SELECT IF(
  @index_exists > 0,
  "SELECT 'Index idx_service_type_created_date already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (service_type, created_date DESC)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 4: Kiểm tra và hiển thị các index hiện có
SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    COLLATION
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = @dbname
  AND TABLE_NAME = @tablename
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT 'ServiceRequests performance indexes successfully added!' AS result;

