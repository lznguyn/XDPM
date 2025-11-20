# MuTraPro - Hệ Thống Quản Lý Dịch Vụ Âm Nhạc

MuTraPro là hệ thống quản lý dịch vụ âm nhạc được thiết kế theo kiến trúc **Microservices**, hỗ trợ các dịch vụ phiên âm (transcription), phối khí (arrangement), và thu âm (recording).

---

## 📋 Mục Lục

1. [Tổng Quan](#tổng-quan)
2. [Kiến Trúc Hệ Thống](#kiến-trúc-hệ-thống)
3. [Cài Đặt và Chạy](#cài-đặt-và-chạy)
4. [Hướng Dẫn Sử Dụng](#hướng-dẫn-sử-dụng)
5. [Trạng Thái Dự Án](#trạng-thái-dự-án)
6. [API Endpoints](#api-endpoints)

---

## 🎯 Tổng Quan

### Tính Năng Chính

**Cho Khách Hàng:**
- ✅ Upload audio files cho transcription
- ✅ Submit music scores cho arrangement
- ✅ Book recording sessions (đặt studio)
- ✅ Track service status (theo dõi trạng thái)
- ✅ Request revisions (yêu cầu chỉnh sửa)
- ✅ Make payments (thanh toán)
- ✅ View transaction history (xem lịch sử giao dịch)

**Cho Admin:**
- ✅ Quản lý users, customers, specialists
- ✅ Quản lý studio bookings
- ✅ Quản lý thanh toán và đơn hàng
- ✅ Xem báo cáo và thống kê

**Cho Specialists:**
- ✅ Nhận và xử lý tasks
- ✅ Upload kết quả
- ✅ Cập nhật tiến độ

---

## 🏗️ Kiến Trúc Hệ Thống

MuTraPro được thiết kế theo kiến trúc **Microservices** với các đặc điểm:

- **Service Independence**: Mỗi service có thể deploy và scale độc lập
- **Database per Service**: Mỗi service có database riêng
- **API Gateway**: Kong Gateway làm entry point cho tất cả requests
- **Inter-Service Communication**: Services giao tiếp qua HTTP REST APIs
- **Containerization**: Tất cả services được containerize với Docker

### Sơ Đồ Kiến Trúc

```
┌─────────────────────────────────────────────────────────────┐
│                      Client Applications                      │
│              (Web Frontend, Admin Panel, etc.)                │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                    Kong API Gateway                          │
│                    (Port: 8000)                              │
│  - Request Routing                                           │
│  - CORS Management                                           │
│  - Load Balancing                                            │
└─────┬──────┬──────┬──────┬──────┬──────┬─────────────────────┘
      │      │      │      │      │      │
      ▼      ▼      ▼      ▼      ▼      ▼
┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐
│  Auth   │ │Customer │ │ Backend │ │Coord.  │ │Payment │
│ Service │ │ Service │ │ Service │ │Service │ │Service │
│ :8081   │ │ :8001   │ │ :8000   │ │ :3000  │ │ :3001  │
└────┬────┘ └────┬────┘ └────┬────┘ └────┬───┘ └────┬───┘
     │          │            │            │          │
     ▼          ▼            │            ▼          ▼
┌─────────┐ ┌─────────┐      │      ┌─────────┐ ┌─────────┐
│  MySQL  │ │  MySQL  │      │      │PostgreSQL│ │PostgreSQL│
│ :3306   │ │ :3306   │      │      │ :5432   │ │ :5432   │
└─────────┘ └─────────┘      │      └─────────┘ └─────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  File Storage    │
                    │  (Volumes)       │
                    └─────────────────┘
```

### Chi Tiết Từng Service

#### 1. Auth Service (.NET Core) - Port 8081

**Technology Stack:**
- Framework: ASP.NET Core 9.0
- Database: MySQL 8.0
- ORM: Entity Framework Core
- Authentication: JWT

**Responsibilities:**
- User Authentication & Authorization
- User Management (CRUD)
- Customer Management
- Service Request Management
- Admin Operations
- Specialist Management
- Studio Management
- Arrangement Management

**API Endpoints:**
- `/api/Auth/*` - Authentication (register, login, logout)
- `/api/Admin/*` - Admin operations
- `/api/Customer/*` - Customer operations
- `/api/Specialist/*` - Specialist operations
- `/api/Studio/*` - Studio management
- `/api/Arrangement/*` - Arrangement operations

#### 2. Customer Service (FastAPI) - Port 8001

**Technology Stack:**
- Framework: FastAPI (Python)
- Database: MySQL (via Auth Service API)
- Communication: HTTP REST

**Responsibilities:**
- Customer Profile Management
- Service Request Submission
- Feedback & Revision Management
- Payment Processing (gọi Payment Service)
- Transaction History
- Studio Booking
- VietQR Code Generation

**API Endpoints:**
- `POST /customers` - Create customer
- `GET /customers/{id}` - Get customer
- `PUT /customers/{id}` - Update customer
- `POST /requests` - Create service request
- `GET /requests/customer/{id}` - Get customer requests
- `GET /requests/{id}` - Get request details
- `PUT /requests/{id}/status` - Update request status
- `POST /feedback` - Submit feedback
- `POST /payments` - Process payment
- `GET /payments/qr/{request_id}` - Generate VietQR code
- `GET /transactions/{id}` - Get transactions
- `GET /studios` - List studios
- `POST /studios` - Create studio
- `PUT /studios/{id}` - Update studio
- `GET /health` - Health check

#### 3. Backend Service (FastAPI) - Port 8000

**Technology Stack:**
- Framework: FastAPI (Python)
- Libraries: librosa, pretty_midi, etc.
- Storage: Docker volumes

**Responsibilities:**
- Audio File Processing
- Music Transcription (Audio → MIDI)
- File Upload/Download
- MIDI Generation

**API Endpoints:**
- `POST /api/v1/trans` - Upload audio for transcription
- `GET /api/v1/trans/midi/{filename}` - Download MIDI
- `GET /outputs/{filename}` - Static file serving

#### 4. Coordinator Service (NestJS) - Port 3000

**Technology Stack:**
- Framework: NestJS (TypeScript)
- Database: PostgreSQL
- ORM: TypeORM

**Responsibilities:**
- Work Order Management
- Task Assignment to Specialists
- Task Status Tracking
- Specialist Scheduling
- Workflow Coordination

**API Endpoints:**
- `POST /api/coordinator/work-orders` - Create work order
- `GET /api/coordinator/work-orders` - List work orders
- `GET /api/coordinator/work-orders/{id}` - Get work order
- `POST /api/coordinator/tasks/{id}/assign` - Assign task
- `PUT /api/coordinator/tasks/{id}/status` - Update task status

#### 5. Payment Service (NestJS) - Port 3001

**Technology Stack:**
- Framework: NestJS (TypeScript)
- Database: PostgreSQL
- ORM: TypeORM

**Responsibilities:**
- Payment Processing
- Invoice Generation
- Transaction Records
- Customer Balance Management
- Payment Status Tracking

**API Endpoints:**
- `GET /api/payments` - Get all payments (admin)
- `POST /api/payments` - Create payment
- `GET /api/payments/{id}` - Get payment
- `POST /api/payments/{id}/confirm` - Confirm payment
- `GET /api/payments/by-order/{orderId}` - Get payments by order
- `GET /api/payments/customer/{customerId}` - Get customer payments

**Frontend:**
- Payment UI: `service-2/frontend/payment.html` (truy cập qua frontend server port 8080)

---

## 🚀 Cài Đặt và Chạy

### Yêu Cầu Hệ Thống

**Bắt buộc:**
- Docker Desktop (Windows) hoặc Docker Engine (Linux/Mac)
- Docker Compose (thường đã bao gồm với Docker Desktop)

**Không cần cài đặt thêm:**
- MySQL (chạy trên Docker)
- PostgreSQL (chạy trên Docker)
- .NET SDK (không cần khi chạy Docker)
- Python (không cần khi chạy Docker)
- Node.js (không cần khi chạy Docker)
- PHP (không cần khi chạy Docker)

### Chạy Tất Cả Services Bằng Docker (Khuyến Nghị)

**Tất cả services (bao gồm cả frontend) sẽ chạy trên Docker:**

```bash
# 1. Di chuyển vào thư mục project
cd C:\Users\LENOVO\Desktop\test2\XDPM

# 2. Build và start tất cả services
docker-compose up -d --build

# 3. Kiểm tra tất cả services đã chạy
docker-compose ps

# 4. Xem logs nếu cần
docker-compose logs -f

# 5. Xem logs của một service cụ thể
docker-compose logs -f [service-name]

# 6. Dừng tất cả services
docker-compose down

# 7. Dừng và xóa volumes (dữ liệu sẽ bị xóa)
docker-compose down -v
```

**Services sẽ chạy tại:**

**Backend Services:**
- **Kong Gateway**: http://localhost:8000 (truy cập tất cả APIs qua đây)
- Auth Service: http://localhost:8081 (hoặc qua Kong: http://localhost:8000/api/Auth)
- Customer Service: http://localhost:8000/api/customers (qua Kong)
- Backend Service: http://localhost:8000/api/v1 (qua Kong)
- Coordinator Service: http://localhost:8000/api/coordinator (qua Kong)
- Payment Service: http://localhost:8000/api/payments (qua Kong)

**Frontend Services:**
- **PHP Frontend (Admin Panel)**: http://localhost:8082
  - Login: http://localhost:8082/login.php
  - Admin Panel: http://localhost:8082/admin/admin_page.php
  - Admin Orders: http://localhost:8082/admin/admin_order.php
  - Studio Page: http://localhost:8082/studio/studio_page.php

- **HTML Frontend (Customer Dashboard)**: http://localhost:8080
  - Customer Dashboard: http://localhost:8080/customer-dashboard.html
  - Payment Page: http://localhost:8080/payment.html
  - Guide: http://localhost:8080/guide.html
  - Auth: http://localhost:8080/auth.html

**Databases:**
- MySQL: localhost:3306
- PostgreSQL: localhost:5432

---

## 📖 Hướng Dẫn Sử Dụng

### Đăng Nhập Admin

1. Đảm bảo tất cả services đã chạy:
   ```bash
   docker-compose ps
   ```

2. Truy cập: http://localhost:8082/login.php

3. Đăng nhập với tài khoản admin

4. Sau khi đăng nhập, bạn sẽ được chuyển tới Admin Panel

5. Trong Admin Panel, bạn có thể:
   - Xem dashboard: http://localhost:8082/admin/admin_page.php
   - Quản lý users: http://localhost:8082/admin/admin_user.php
   - Quản lý đơn hàng: http://localhost:8082/admin/admin_order.php
   - Quản lý thanh toán: http://localhost:8082/admin/admin_order.php (xem tất cả payments từ service-3)
   - Quản lý studio: http://localhost:8082/studio/studio_page.php

### Đăng Nhập Customer

1. Đảm bảo tất cả services đã chạy:
   ```bash
   docker-compose ps
   ```

2. Truy cập: http://localhost:8080/customer-dashboard.html

3. Nếu chưa có tài khoản, đăng ký tại: http://localhost:8080/auth.html

4. Sau khi đăng nhập, bạn có thể:
   - Xem dashboard và thống kê
   - Tạo yêu cầu dịch vụ (transcription, arrangement, recording)
   - Đặt studio
   - Theo dõi đơn hàng
   - Thanh toán (sẽ chuyển tới trang payment.html của service-3)
   - Gửi feedback và yêu cầu chỉnh sửa

### Thanh Toán

1. Từ Customer Dashboard, vào tab "💰 Thanh Toán"

2. Chọn đơn hàng chưa thanh toán và bấm "💳 Thanh Toán Ngay"

3. Trang thanh toán sẽ mở trong cửa sổ mới (`payment.html`)

4. Chọn phương thức thanh toán:
   - **Bank Transfer (VietQR)**: Quét mã QR để chuyển khoản
   - **Credit Card**: Thẻ tín dụng
   - **MoMo**: Ví điện tử
   - **Cash**: Tiền mặt

5. Sau khi thanh toán, payment sẽ được lưu vào database của Payment Service (service-3)

6. Admin có thể xem tất cả thanh toán tại: http://localhost:8082/admin/admin_order.php

---

## 📊 Trạng Thái Dự Án

### ✅ Đã Hoàn Thành

#### 1. Kiến trúc Microservices
- ✅ Đã xác định và phân tích kiến trúc hiện tại
- ✅ Đã tích hợp tất cả services vào docker-compose.yml
- ✅ Đã cấu hình network cho tất cả services
- ✅ Đã tạo tài liệu kiến trúc

#### 2. Services Integration
- ✅ **Auth Service** (.NET Core) - Port 8081
- ✅ **Customer Service** (FastAPI) - Port 8001
- ✅ **Backend Service** (FastAPI) - Port 8000
- ✅ **Coordinator Service** (NestJS) - Port 3000
- ✅ **Payment Service** (NestJS) - Port 3001

#### 3. API Gateway
- ✅ Kong Gateway đã được cấu hình
- ✅ Đã route tất cả services
- ✅ Đã cấu hình CORS
- ✅ Đã có health check routes

#### 4. Databases
- ✅ MySQL cho Auth Service và Customer Service
- ✅ PostgreSQL cho Coordinator Service và Payment Service
- ✅ Đã có init scripts cho PostgreSQL

#### 5. Frontend
- ✅ Customer Dashboard (HTML/JS)
- ✅ Admin Panel (PHP)
- ✅ Payment UI (HTML/JS) trong service-2/frontend
- ✅ Studio Booking UI

#### 6. Payment Integration
- ✅ Payment Service với database riêng
- ✅ Payment UI để khách hàng thanh toán
- ✅ Admin page để xem tất cả payments
- ✅ Integration với Customer Service và VietQR

### ⚠️ Cần Hoàn Thiện

1. **Notification Service**: Email, SMS, Push notifications
2. **Service Separation**: Refactor Service-1 để tách thành các services nhỏ hơn
3. **Testing**: Unit tests, integration tests, e2e tests
4. **Monitoring**: Centralized logging, metrics collection
5. **Security Enhancements**: Rate limiting, API keys, OAuth 2.0
6. **File Storage**: Centralized file storage service (S3/MinIO)
7. **API Documentation**: Swagger/OpenAPI cho tất cả services

**Trạng thái tổng thể**: **85% Hoàn thành**

---

## 🔗 API Endpoints

### Qua Kong Gateway (Port 8000)

Tất cả requests nên đi qua Kong Gateway:

- **Auth**: `http://localhost:8000/api/Auth/*`
- **Admin**: `http://localhost:8000/api/Admin/*`
- **Customer**: `http://localhost:8000/api/Customer/*`
- **Customer Service**: `http://localhost:8000/customers/*`, `http://localhost:8000/requests/*`, etc.
- **Backend**: `http://localhost:8000/api/v1/*`
- **Coordinator**: `http://localhost:8000/api/coordinator/*`
- **Payment**: `http://localhost:8000/api/payments/*`

### Direct Access (Chỉ để development)

- Auth Service: http://localhost:8081/api
- Customer Service: http://localhost:8082
- Backend Service: http://localhost:8000/api/v1
- Coordinator Service: http://localhost:3000/api/coordinator
- Payment Service: http://localhost:3001/api/payments

---

## 🗄️ Databases

### MySQL (Auth & Customer Service)

- **Host**: localhost:3306 (hoặc mysql-db trong Docker)
- **Database**: MuTraProDB
- **User**: root
- **Password**: root123

**Tables:**
- Users
- Customers
- ServiceRequests
- CustomerPayments
- CustomerTransactions
- CustomerFeedbacks
- Studios
- SpecialistSchedules
- MusicSubmissions
- Orders
- Products

### PostgreSQL (Coordinator & Payment Service)

- **Host**: localhost:5432 (hoặc postgres-db trong Docker)
- **Database**: mutrapro_db hoặc mutrapro
- **User**: mutrapro
- **Password**: mutrapro_pw

**Schemas & Tables:**
- **coordinator schema**: work_orders, tasks, studios, revisions
- **payment schema**: payments, invoices, customer_balance, payment_history

---

## 📁 Cấu Trúc Project

```
XDPM/
├── docker-compose.yml          # Main docker-compose cho tất cả services
├── kong.yml                    # Kong Gateway configuration
├── README.md                   # File này
│
├── service-1/                  # Auth Service (.NET Core)
│   ├── Controller/             # API Controllers
│   ├── Model/                  # Database Models
│   ├── Data/                   # DbContext
│   ├── Migrations/             # Database Migrations
│   ├── Web/                    # PHP Frontend (Admin Panel)
│   │   ├── admin/              # Admin pages
│   │   ├── login.php           # Login page
│   │   └── register.php        # Register page
│   └── Dockerfile
│
├── service-2/                  # Customer & Backend Services
│   ├── customer-service/       # Customer Service (FastAPI)
│   │   ├── main.py             # Main application
│   │   ├── db_client.py        # Database client
│   │   └── Dockerfile
│   ├── backend/                # Backend Service (FastAPI)
│   │   ├── main.py             # Main application
│   │   ├── processing.py       # Audio processing
│   │   └── Dockerfile
│   └── frontend/               # Customer Frontend (HTML/JS)
│       ├── customer-dashboard.html
│       ├── payment.html        # Payment UI
│       ├── auth.html
│       └── server.py           # Python HTTP server
│
└── service-3/                  # Coordinator & Payment Services
    └── projectnew/mutrapro/
        ├── coordinator-service/ # Coordinator Service (NestJS)
        ├── payment-service/     # Payment Service (NestJS)
        │   └── public/          # Static files (nếu cần)
        ├── init-db.sql          # PostgreSQL init script
        └── docker-compose.yml   # (chỉ dùng khi chạy riêng service-3)
```

---

## 🛠️ Troubleshooting

### Lỗi: "Connection refused" khi gọi API
- **Giải pháp**: Kiểm tra service đang chạy: `docker-compose ps`
- Kiểm tra Kong Gateway: `docker logs kong`

### Lỗi: "Database does not exist"
- **Giải pháp**: 
  - MySQL: Kiểm tra database `MuTraProDB` đã được tạo
  - PostgreSQL: Đảm bảo database `mutrapro_db` hoặc `mutrapro` đã được tạo

### Lỗi: "503 Service Unavailable" từ Kong
- **Giải pháp**: Kiểm tra service backend đang chạy và có thể truy cập được từ Kong

### Lỗi: "Port already in use"
- **Giải pháp**: 
  - Tìm process đang dùng port: `netstat -ano | findstr :PORT`
  - Kill process: `taskkill /PID <PID> /F`
  - Hoặc thay đổi port trong cấu hình

### Frontend không kết nối được API
- **Giải pháp**: 
  - Kiểm tra API_BASE URL trong frontend code
  - Đảm bảo dùng `http://localhost:8000` (Kong Gateway)
  - Kiểm tra CORS đã được cấu hình

---

## 📝 Notes

- **Service-1 là Monolithic**: Mặc dù được gọi là "Auth Service", nó thực sự chứa nhiều responsibilities (Auth, Admin, Customer, Studio, etc.). Điều này OK trong giai đoạn hiện tại.

- **Payment Flow**: 
  1. Customer chọn thanh toán từ dashboard
  2. Redirect tới `payment.html` trong service-2/frontend
  3. Payment được tạo trong Payment Service (service-3)
  4. Payment được lưu vào PostgreSQL database
  5. Admin có thể xem tại `admin_order.php`

- **Database Strategy**: 
  - MySQL cho Auth/Customer (service-1, service-2)
  - PostgreSQL cho Coordinator/Payment (service-3)

---

## 🚀 Quick Start

```bash
# 1. Start tất cả services (bao gồm cả frontend)
docker-compose up -d --build

# 2. Kiểm tra tất cả services đã chạy
docker-compose ps

# 3. Truy cập:
# - Admin Panel: http://localhost:8082/login.php
# - Customer Dashboard: http://localhost:8080/customer-dashboard.html
# - API Gateway: http://localhost:8000

# 4. Xem logs nếu cần
docker-compose logs -f

# 5. Dừng tất cả services
docker-compose down
```

---

## 📞 Support

Nếu gặp vấn đề, kiểm tra:
1. Logs: `docker-compose logs [service-name]`
2. Health checks: `curl http://localhost:8000/api/payments` (ví dụ)
3. Database connections
4. Port conflicts

---

**MuTraPro** - Hệ thống quản lý dịch vụ âm nhạc chuyên nghiệp 🎵

