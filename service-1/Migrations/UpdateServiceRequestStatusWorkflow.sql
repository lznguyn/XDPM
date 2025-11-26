-- =====================================================
-- Update ServiceRequests Table - Support New Status Workflow
-- =====================================================
-- 
-- Các Trạng Thái (States):
-- 1. Pending: khách hàng gửi yêu cầu dịch vụ (trạng thái ban đầu)
-- 2. Requested: (Legacy - có thể dùng cho tương thích ngược)
-- 3. PendingReview: admin đã duyệt yêu cầu, chờ người dùng chọn ngày/chuyên gia
-- 4. Cancelled: yêu cầu bị từ chối hoặc bị hủy
-- 5. PendingMeetingConfirmation: người dùng đã chọn ngày + khung giờ, đang chờ chuyên gia xác nhận
-- 6. Completed: chuyên gia chấp nhận gặp → yêu cầu hoàn thành, chờ thanh toán
-- 7. RejectedByExpert: chuyên gia từ chối gặp (với lý do)
--
-- Luồng Chuyển Trạng Thái:
-- Pending → (Admin review) → PendingReview hoặc Cancelled
-- PendingReview → (Customer selects date/expert) → PendingMeetingConfirmation
-- PendingMeetingConfirmation → (Expert confirms) → Completed hoặc RejectedByExpert/Cancelled
-- Completed → (Payment) → Completed (final)
-- =====================================================

USE MuTraProDB;

-- Step 1: Đảm bảo cột status là VARCHAR và có đủ độ dài
-- Default status là 'Pending' khi khách hàng tạo yêu cầu mới
ALTER TABLE ServiceRequests 
MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending';

-- Step 2: Cập nhật các giá trị status cũ sang giá trị mới (nếu cần)
-- Chuyển các status cũ sang status mới tương ứng
UPDATE ServiceRequests 
SET status = CASE 
    -- Giữ nguyên 'Pending' và 'Submitted' - đây là trạng thái ban đầu
    WHEN status = 'Pending' OR status = 'Submitted' THEN 'Pending'
    -- Chuyển các status cũ khác
    WHEN status = 'Assigned' THEN 'PendingReview'
    WHEN status = 'InProgress' THEN 'PendingReview'
    WHEN status = 'RevisionRequested' THEN 'PendingReview'
    -- Giữ nguyên các status mới
    WHEN status IN ('Requested', 'PendingReview', 'Cancelled', 
                    'PendingMeetingConfirmation', 'Completed', 'RejectedByExpert') 
    THEN status
    -- Mặc định cho các giá trị không xác định
    ELSE 'Pending'
END
WHERE status NOT IN ('Pending', 'Requested', 'PendingReview', 'Cancelled', 
                     'PendingMeetingConfirmation', 'Completed', 'RejectedByExpert');

-- Step 3: Thêm cột rejection_reason nếu chưa có (để lưu lý do từ chối của chuyên gia)
SET @dbname = DATABASE();
SET @tablename = "ServiceRequests";
SET @columnname = "rejection_reason";

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column rejection_reason already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " TEXT NULL COMMENT 'Lý do từ chối của chuyên gia'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 4: Thêm index cho status để tối ưu truy vấn
-- Kiểm tra xem index đã tồn tại chưa
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = @dbname 
                     AND TABLE_NAME = @tablename 
                     AND INDEX_NAME = 'idx_status');
SET @preparedStatement = (SELECT IF(
  @index_exists > 0,
  "SELECT 'Index idx_status already exists.'",
  CONCAT("ALTER TABLE ", @tablename, " ADD INDEX idx_status (status)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 5: Thêm constraint CHECK để đảm bảo chỉ chấp nhận các status hợp lệ
-- (MySQL 8.0.16+ hỗ trợ CHECK constraint)
SET @mysql_version = (SELECT VERSION());
SET @preparedStatement = (SELECT IF(
  @mysql_version >= '8.0.16',
  CONCAT("ALTER TABLE ", @tablename, " ADD CONSTRAINT chk_status CHECK (status IN ('Requested', 'PendingReview', 'Cancelled', 'PendingMeetingConfirmation', 'Completed', 'RejectedByExpert', 'Pending', 'Submitted', 'Assigned', 'InProgress', 'RevisionRequested'))"),
  "SELECT 'MySQL version does not support CHECK constraint. Please ensure status values are valid in application code.'"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 6: Cập nhật default value cho các record mới
-- Trạng thái ban đầu khi khách hàng tạo yêu cầu là 'Pending'
ALTER TABLE ServiceRequests 
MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending';

SELECT 'ServiceRequests status workflow successfully updated!' AS result;
SELECT 
    status,
    COUNT(*) as count
FROM ServiceRequests
GROUP BY status
ORDER BY count DESC;

