-- Migration: Create StudioBookings Table
-- Description: Tạo bảng StudioBookings để quản lý đặt phòng studio

CREATE TABLE IF NOT EXISTS StudioBookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    studio_id INT NOT NULL,
    service_request_id INT NOT NULL,
    customer_id INT NOT NULL,
    booking_date DATE NOT NULL,
    booking_time VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_date DATETIME NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (studio_id) REFERENCES Studios(id) ON DELETE CASCADE,
    FOREIGN KEY (service_request_id) REFERENCES ServiceRequests(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES Customers(id) ON DELETE CASCADE,
    
    INDEX idx_studio_id (studio_id),
    INDEX idx_service_request_id (service_request_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;








