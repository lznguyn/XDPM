-- =====================================================
-- MuTraPro Integrated Database Schema
-- PostgreSQL for Service-3 (Coordinator + Payment)
-- =====================================================

-- Note: This script runs in the database specified by POSTGRES_DB (mutrapro_db)
-- If you need to create schemas in a different database, connect to that database first

-- Create schemas
CREATE SCHEMA IF NOT EXISTS coordinator;
CREATE SCHEMA IF NOT EXISTS payment;

-- =====================================================
-- COORDINATOR SERVICE TABLES
-- =====================================================

-- Work Orders (từ customers)
CREATE TABLE coordinator.work_orders (
    id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    status VARCHAR(50) DEFAULT 'submitted',
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date TIMESTAMP,
    assigned_specialist_id INT,
    assigned_specialist_name VARCHAR(255),
    priority VARCHAR(20) DEFAULT 'normal',
    updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tasks (công việc chi tiết)
CREATE TABLE coordinator.tasks (
    id SERIAL PRIMARY KEY,
    work_order_id INT NOT NULL REFERENCES coordinator.work_orders(id) ON DELETE CASCADE,
    task_name VARCHAR(255) NOT NULL,
    task_description TEXT,
    assigned_to_id INT,
    assigned_to_name VARCHAR(255),
    task_status VARCHAR(50) DEFAULT 'pending',
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Studio Information
CREATE TABLE coordinator.studios (
    id SERIAL PRIMARY KEY,
    studio_name VARCHAR(255) NOT NULL,
    studio_owner_id INT NOT NULL,
    contact_phone VARCHAR(20),
    contact_email VARCHAR(255),
    address TEXT,
    hourly_rate DECIMAL(10, 2),
    is_active BOOLEAN DEFAULT TRUE,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Revisions/Feedback from customers
CREATE TABLE coordinator.revisions (
    id SERIAL PRIMARY KEY,
    work_order_id INT NOT NULL REFERENCES coordinator.work_orders(id) ON DELETE CASCADE,
    feedback_text TEXT NOT NULL,
    revision_needed BOOLEAN DEFAULT FALSE,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_date TIMESTAMP
);

-- =====================================================
-- PAYMENT SERVICE TABLES
-- =====================================================

-- Payment Records
CREATE TABLE payment.payments (
    id SERIAL PRIMARY KEY,
    work_order_id INT NOT NULL,
    customer_id INT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status VARCHAR(50) DEFAULT 'pending',
    transaction_id VARCHAR(255),
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_date TIMESTAMP,
    notes TEXT
);

-- Invoice Records
CREATE TABLE payment.invoices (
    id SERIAL PRIMARY KEY,
    payment_id INT NOT NULL REFERENCES payment.payments(id) ON DELETE CASCADE,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date TIMESTAMP,
    status VARCHAR(50) DEFAULT 'issued',
    pdf_path VARCHAR(500)
);

-- Customer Wallet/Balance
CREATE TABLE payment.customer_balance (
    id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL UNIQUE,
    customer_email VARCHAR(255) NOT NULL,
    total_balance DECIMAL(12, 2) DEFAULT 0,
    total_spent DECIMAL(12, 2) DEFAULT 0,
    total_earned DECIMAL(12, 2) DEFAULT 0,
    last_transaction TIMESTAMP,
    updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payment History
CREATE TABLE payment.payment_history (
    id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL,
    payment_id INT REFERENCES payment.payments(id),
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    description TEXT,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    balance_after DECIMAL(12, 2)
);

-- =====================================================
-- INDEXES for Performance
-- =====================================================

-- Coordinator indexes
CREATE INDEX idx_work_orders_customer_id ON coordinator.work_orders(customer_id);
CREATE INDEX idx_work_orders_status ON coordinator.work_orders(status);
CREATE INDEX idx_tasks_work_order_id ON coordinator.tasks(work_order_id);
CREATE INDEX idx_tasks_status ON coordinator.tasks(task_status);
CREATE INDEX idx_revisions_work_order_id ON coordinator.revisions(work_order_id);

-- Payment indexes
CREATE INDEX idx_payments_customer_id ON payment.payments(customer_id);
CREATE INDEX idx_payments_status ON payment.payments(payment_status);
CREATE INDEX idx_payment_history_customer_id ON payment.payment_history(customer_id);
CREATE INDEX idx_invoices_payment_id ON payment.invoices(payment_id);

-- =====================================================
-- INITIAL DATA (Optional)
-- =====================================================

-- Insert sample studios
INSERT INTO coordinator.studios (studio_name, studio_owner_id, contact_email, hourly_rate) VALUES
('Studio A', 1, 'studio-a@example.com', 50.00),
('Studio B', 2, 'studio-b@example.com', 60.00);

-- Insert sample customer balance
INSERT INTO payment.customer_balance (customer_id, customer_email, total_balance) VALUES
(1, 'customer1@example.com', 1000.00),
(2, 'customer2@example.com', 500.00);
