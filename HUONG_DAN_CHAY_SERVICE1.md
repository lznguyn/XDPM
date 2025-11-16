# 🚀 Hướng dẫn chạy Service-1 (Auth Service - .NET)

Service-1 là **Auth Service** được viết bằng **ASP.NET Core**, quản lý authentication, authorization và customer data.

## 📋 Yêu cầu

### Cách 1: Chạy bằng Docker (Khuyến nghị)
- Docker và Docker Compose đã cài đặt
- MySQL database đang chạy (qua docker-compose)

### Cách 2: Chạy trực tiếp bằng .NET
- .NET SDK 9.0 hoặc mới hơn
- MySQL Server đang chạy (localhost:3306)
- MySQL database `MuTraProDB` đã được tạo

## 🐳 Cách 1: Chạy bằng Docker (Dễ nhất)

### Bước 1: Đảm bảo MySQL đang chạy
```bash
docker ps | findstr mysql
```

Nếu chưa chạy:
```bash
docker-compose up -d mysql
```

### Bước 2: Chạy service-1
```bash
# Chạy tất cả services (bao gồm service-1)
docker-compose up -d

# Hoặc chỉ chạy service-1
docker-compose up -d auth-service
```

### Bước 3: Kiểm tra service đã chạy
```bash
docker ps | findstr auth-service
```

### Bước 4: Xem logs
```bash
docker logs auth-service
# Hoặc xem logs real-time
docker logs -f auth-service
```

### Bước 5: Kiểm tra service hoạt động
Mở trình duyệt hoặc dùng curl:
```bash
# Health check
curl http://localhost:8081/health

# Swagger UI (nếu Development mode)
http://localhost:8081/swagger
```

## 💻 Cách 2: Chạy trực tiếp bằng .NET CLI

### Bước 1: Kiểm tra .NET SDK
```bash
dotnet --version
```
Cần .NET 9.0 hoặc mới hơn.

### Bước 2: Di chuyển đến thư mục service-1
```bash
cd service-1
```

### Bước 3: Khôi phục dependencies
```bash
dotnet restore
```

### Bước 4: Kiểm tra connection string
Mở file `appsettings.json` và đảm bảo connection string đúng:
```json
{
  "ConnectionStrings": {
    "DefaultConnection": "Server=127.0.0.1;Port=3306;Database=MuTraProDB;User=root;Password=root123;TreatTinyAsBoolean=true;"
  }
}
```

**Lưu ý**: 
- Nếu MySQL chạy trong Docker, dùng `Server=localhost` hoặc `Server=127.0.0.1`
- Nếu MySQL chạy trên máy khác, thay đổi IP/hostname tương ứng

### Bước 5: Đảm bảo database đã được tạo
```bash
# Kiểm tra MySQL đang chạy
mysql -uroot -proot123 -e "SHOW DATABASES;" | findstr MuTraProDB

# Nếu chưa có, tạo database
mysql -uroot -proot123 -e "CREATE DATABASE IF NOT EXISTS MuTraProDB;"
```

### Bước 6: Chạy migrations (nếu cần)
```bash
# Kiểm tra migrations
dotnet ef migrations list

# Áp dụng migrations (nếu có migrations chưa apply)
dotnet ef database update
```

**Lưu ý**: Nếu chưa có `dotnet-ef` tool:
```bash
dotnet tool install --global dotnet-ef
```

### Bước 7: Chạy service
```bash
# Development mode
dotnet run

# Hoặc build và chạy
dotnet build
dotnet run
```

### Bước 8: Kiểm tra service
Service sẽ chạy tại:
- **HTTP**: http://localhost:5200 (theo launchSettings.json)
- **Swagger UI**: http://localhost:5200/swagger
- **Health Check**: http://localhost:5200/health

## 🔧 Cấu hình

### Port mặc định
- **Docker**: Port 8081
- **Local .NET**: Port 5200 (theo launchSettings.json)

### Thay đổi port khi chạy local
```bash
# Cách 1: Sửa launchSettings.json
# Tìm "applicationUrl" và thay đổi port

# Cách 2: Chạy với environment variable
$env:ASPNETCORE_URLS="http://localhost:8081"
dotnet run
```

### Connection String
File `appsettings.json`:
```json
{
  "ConnectionStrings": {
    "DefaultConnection": "Server=127.0.0.1;Port=3306;Database=MuTraProDB;User=root;Password=root123;TreatTinyAsBoolean=true;"
  }
}
```

## 📡 API Endpoints

Sau khi service chạy, các endpoints có sẵn:

### Authentication
- `POST /api/Auth/register` - Đăng ký user mới
- `POST /api/Auth/login` - Đăng nhập
- `POST /api/Auth/logout` - Đăng xuất

### Customer Management
- `GET /api/Customer` - Lấy tất cả customers
- `POST /api/Customer` - Tạo customer mới
- `GET /api/Customer/{id}` - Lấy customer theo ID
- `PUT /api/Customer/{id}` - Cập nhật customer

### Admin
- `GET /api/Admin/*` - Các endpoints quản trị

### Health Check
- `GET /health` - Kiểm tra service health

## 🐛 Troubleshooting

### Lỗi: "Connection string not found"
**Giải pháp**: Kiểm tra `appsettings.json` có connection string đúng không

### Lỗi: "Cannot connect to MySQL"
**Giải pháp**: 
- Kiểm tra MySQL đang chạy: `docker ps | findstr mysql`
- Kiểm tra connection string trong `appsettings.json`
- Kiểm tra firewall/network

### Lỗi: "Table 'Users' doesn't exist"
**Giải pháp**: 
- Chạy migration: `dotnet ef database update`
- Hoặc chạy SQL script: `service-1/Migrations/CreateUsersTable.sql`

### Lỗi: Port đã được sử dụng
**Giải pháp**:
- Thay đổi port trong `launchSettings.json`
- Hoặc kill process đang dùng port:
  ```bash
  netstat -ano | findstr :8081
  taskkill /PID <PID> /F
  ```

### Service không start trong Docker
**Giải pháp**:
```bash
# Xem logs chi tiết
docker logs auth-service

# Rebuild image
docker-compose build auth-service
docker-compose up -d auth-service
```

## 📝 Lưu ý quan trọng

1. **Database phải chạy trước**: Service-1 cần MySQL database để hoạt động
2. **Bảng Users phải tồn tại**: Nếu chưa có, chạy migration hoặc SQL script
3. **CORS**: Service đã cấu hình CORS cho `http://localhost`
4. **JWT**: Service sử dụng JWT token cho authentication

## 🔗 Liên kết

- **Swagger UI**: http://localhost:8081/swagger (Docker) hoặc http://localhost:5200/swagger (Local)
- **Health Check**: http://localhost:8081/health
- **API Base**: http://localhost:8081/api

## ✅ Kiểm tra service đã chạy thành công

1. Health check trả về 200:
   ```bash
   curl http://localhost:8081/health
   ```

2. Swagger UI mở được:
   - http://localhost:8081/swagger

3. Logs không có lỗi:
   ```bash
   docker logs auth-service
   ```

