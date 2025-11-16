# Hướng Dẫn Truy Cập Trang Login.php

## 📋 Tổng Quan

Trang `login.php` là giao diện đăng nhập của hệ thống MuTraPro, được viết bằng PHP và gọi API từ `auth-service` (.NET).

## 🚀 Cách 1: Chạy PHP Server (Khuyến Nghị)

### Bước 1: Kiểm tra PHP đã cài đặt

Mở PowerShell/CMD và chạy:
```bash
php --version
```

Nếu chưa có PHP, bạn có thể:
- **Cài PHP trực tiếp**: https://www.php.net/downloads.php
- **Hoặc dùng XAMPP/WAMP** (đã bao gồm PHP + Apache)

### Bước 2: Chạy PHP Server

**Cách A: Dùng script tự động (Dễ nhất)**
```bash
# Chạy file batch
start-php-server.bat
```

**Cách B: Chạy thủ công**
```bash
cd service-1\Web
php -S localhost:8082
```

### Bước 3: Truy cập trang Login

Mở trình duyệt và vào:
```
http://localhost:8082/login.php
```

## 🔧 Cách 2: Dùng XAMPP/WAMP

### Bước 1: Copy thư mục Web vào htdocs

1. Copy thư mục `service-1\Web` vào:
   - **XAMPP**: `C:\xampp\htdocs\`
   - **WAMP**: `C:\wamp64\www\`

2. Đổi tên thành `MuTraPro` (tùy chọn)

### Bước 2: Khởi động Apache

- Mở XAMPP Control Panel → Start Apache
- Hoặc WAMP → Start All Services

### Bước 3: Truy cập

```
http://localhost/MuTraPro/login.php
```
hoặc
```
http://localhost/MuTraPro/login.php
```

## ⚙️ Cấu Hình API Endpoint

File `login.php` đã được cấu hình để gọi API qua **Kong Gateway** (port 8000).

### Các tùy chọn API URL:

1. **Qua Kong Gateway** (Mặc định - Khuyến nghị):
   ```
   http://localhost:8000/api/Auth/login
   ```
   - ✅ Tất cả services đều qua Gateway
   - ✅ Dễ quản lý và bảo mật

2. **Trực tiếp auth-service** (Docker):
   ```
   http://localhost:8081/api/Auth/login
   ```
   - Dùng khi chạy `auth-service` qua Docker

3. **Local .NET service**:
   ```
   http://localhost:5200/api/Auth/login
   ```
   - Dùng khi chạy `service-1` trực tiếp bằng `dotnet run`

### Thay đổi API URL:

Mở file `service-1/Web/login.php` và tìm dòng:
```php
$api_url = "http://localhost:8000/api/Auth/login";
```

Thay đổi theo nhu cầu của bạn.

## ✅ Kiểm Tra Trước Khi Đăng Nhập

### 1. Đảm bảo auth-service đang chạy

**Nếu dùng Docker:**
```bash
docker-compose ps auth-service
```

**Nếu chạy local:**
```bash
cd service-1
dotnet run
```

### 2. Kiểm tra Kong Gateway (nếu dùng)

```bash
# Kiểm tra Kong đang chạy
docker-compose ps kong

# Test API endpoint
curl http://localhost:8000/api/Auth/login
```

### 3. Kiểm tra MySQL Database

```bash
# Kiểm tra MySQL container
docker-compose ps mysql-db

# Hoặc test connection
docker exec -it mysql-db mysql -uroot -proot123 -e "USE MuTraProDB; SHOW TABLES;"
```

## 🐛 Xử Lý Lỗi

### Lỗi: "PHP is not recognized"
- **Giải pháp**: Cài đặt PHP và thêm vào PATH, hoặc dùng XAMPP/WAMP

### Lỗi: "Connection refused" khi đăng nhập
- **Nguyên nhân**: API service chưa chạy hoặc URL sai
- **Giải pháp**: 
  1. Kiểm tra `auth-service` đang chạy
  2. Kiểm tra URL trong `login.php` đúng với port service

### Lỗi: "Failed to fetch" hoặc "CORS error"
- **Nguyên nhân**: CORS chưa được cấu hình đúng
- **Giải pháp**: Kiểm tra CORS trong `service-1/Program.cs`

### Lỗi: "404 Not Found" khi truy cập login.php
- **Nguyên nhân**: PHP server chưa chạy hoặc đường dẫn sai
- **Giải pháp**: Đảm bảo đã chạy `php -S localhost:8082` trong thư mục `service-1/Web`

## 📝 Ghi Chú

- Port mặc định cho PHP server: **8082** (tránh conflict với các service khác)
- Port auth-service (Docker): **8081**
- Port Kong Gateway: **8000**
- Port auth-service (local): **5200**

## 🔗 Các Trang Liên Quan

- **Login**: `http://localhost:8082/login.php`
- **Register**: `http://localhost:8082/register.php`
- **Admin Dashboard**: `http://localhost:8082/admin/admin_page.php` (sau khi đăng nhập với role Admin)
- **Customer Dashboard**: `http://localhost:8082/dashboard.php` (sau khi đăng nhập với role User)

## 🎯 Quick Start

```bash
# 1. Start auth-service (Docker)
docker-compose up -d auth-service

# 2. Start PHP server
start-php-server.bat

# 3. Mở trình duyệt
# http://localhost:8082/login.php
```

---

**Lưu ý**: Đảm bảo `auth-service` và `mysql-db` đang chạy trước khi đăng nhập!

