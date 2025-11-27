Link doc: https://phamnguyen0234.atlassian.net/wiki/spaces/XDHuongdoi/pages/edit-v2/721215?draftShareId=b519e9d8-d2d3-45be-ac37-1c750c436a7c

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



**MuTraPro** - Hệ thống quản lý dịch vụ âm nhạc chuyên nghiệp 🎵

