-- =====================================================
-- Service Prices Table
-- Bảng lưu giá dịch vụ (Transcription, Arrangement)
-- Recording không có ở đây vì giá được quản lý qua Studio
-- =====================================================

USE MuTraProDB;

-- Service Prices Table
CREATE TABLE IF NOT EXISTS ServicePrices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type VARCHAR(50) NOT NULL UNIQUE,
    price DECIMAL(18, 2) NOT NULL DEFAULT 50000.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES Users(Id) ON DELETE SET NULL,
    INDEX idx_service_type (service_type)
);

-- Insert default prices nếu chưa có
INSERT IGNORE INTO ServicePrices (service_type, price) VALUES
('Transcription', 50000.00),
('Arrangement', 50000.00);

