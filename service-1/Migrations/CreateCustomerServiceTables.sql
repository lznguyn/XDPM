-- =====================================================
-- Customer Service Tables for MySQL
-- Run this script after creating the database
-- =====================================================

USE MuTraProDB;

-- Customers Table
CREATE TABLE IF NOT EXISTS Customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    address TEXT,
    account_created DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES Users(Id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_user_id (user_id)
);

-- Service Requests Table
CREATE TABLE IF NOT EXISTS ServiceRequests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(500),
    status VARCHAR(50) NOT NULL DEFAULT 'Submitted',
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    due_date DATETIME,
    assigned_specialist_id INT,
    priority VARCHAR(20) DEFAULT 'normal',
    paid BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (customer_id) REFERENCES Customers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_specialist_id) REFERENCES Users(Id) ON DELETE SET NULL,
    INDEX idx_customer_id (customer_id),
    INDEX idx_status (status),
    INDEX idx_assigned_specialist (assigned_specialist_id)
);

-- Customer Feedback Table
CREATE TABLE IF NOT EXISTS CustomerFeedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    feedback_text TEXT NOT NULL,
    revision_needed BOOLEAN DEFAULT FALSE,
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES ServiceRequests(id) ON DELETE CASCADE,
    INDEX idx_request_id (request_id)
);

-- Customer Payments Table
CREATE TABLE IF NOT EXISTS CustomerPayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    service_request_id INT NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    transaction_id VARCHAR(255),
    FOREIGN KEY (customer_id) REFERENCES Customers(id) ON DELETE CASCADE,
    FOREIGN KEY (service_request_id) REFERENCES ServiceRequests(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_service_request_id (service_request_id)
);

-- Customer Transactions Table
CREATE TABLE IF NOT EXISTS CustomerTransactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_id INT,
    FOREIGN KEY (customer_id) REFERENCES Customers(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES CustomerPayments(id) ON DELETE SET NULL,
    INDEX idx_customer_id (customer_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_date (date)
);

