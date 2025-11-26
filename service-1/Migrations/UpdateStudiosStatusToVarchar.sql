-- =====================================================
-- Update Studios Table - Change status column from INT to VARCHAR
-- =====================================================

USE MuTraProDB;

-- Step 1: Add a temporary column để lưu status dạng string
ALTER TABLE Studios ADD COLUMN status_temp VARCHAR(50) NULL;

-- Step 2: Copy và convert dữ liệu từ status (int) sang status_temp (varchar)
-- 0 = Available
-- 1 = Occupied  
-- 2 = UnderMaintenance
UPDATE Studios 
SET status_temp = CASE 
    WHEN status = 0 THEN 'Available'
    WHEN status = 1 THEN 'Occupied'
    WHEN status = 2 THEN 'UnderMaintenance'
    ELSE 'Available'
END
WHERE status_temp IS NULL;

-- Step 3: Set default value cho status_temp nếu có NULL
UPDATE Studios 
SET status_temp = 'Available' 
WHERE status_temp IS NULL OR status_temp = '';

-- Step 4: Drop column status cũ (INT)
ALTER TABLE Studios DROP COLUMN status;

-- Step 5: Rename status_temp thành status và set NOT NULL
ALTER TABLE Studios CHANGE status_temp status VARCHAR(50) NOT NULL DEFAULT 'Available';

-- Step 6: Add index nếu cần
ALTER TABLE Studios ADD INDEX idx_status (status);

SELECT 'Studios status column successfully updated from INT to VARCHAR!' AS result;

