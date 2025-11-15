# 🐳 Docker Setup Guide - MuTraPro

**Status:** ✅ Production Ready  
**Date:** November 12, 2025

---

## 🎯 Benefits

```
✅ Một container duy nhất (thay vì 3)
✅ Chia sẻ Python environment (tối ưu dung lượng)
✅ Multi-stage build (image nhỏ gọn)
✅ Volume sharing (hiệu suất cao)
✅ Easy deployment (docker-compose)
✅ Health check built-in
✅ Auto-restart on failure
```

---

## 📋 Yêu Cầu

### Cài Đặt Docker & Docker Compose

**Windows (Docker Desktop):**
```bash
# Download từ: https://www.docker.com/products/docker-desktop
# Hoặc dùng Chocolatey:
choco install docker-desktop
```

**Linux (Ubuntu/Debian):**
```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo apt-get install -y docker-compose-plugin
```

**Kiểm tra cài đặt:**
```bash
docker --version
docker-compose --version
```

---

## 🚀 Quick Start

### 1. Build Image (Lần Đầu)

```bash
cd C:\audio

# Build image (sẽ tối ưu dung lượng)
docker-compose build

# Kiểm tra:
docker images
# Sẽ thấy: mutrapro:latest (≈ 500-600 MB)
```

### 2. Start Services

```bash
# Start tất cả 3 services trong 1 container
docker-compose up

# Hoặc chạy background:
docker-compose up -d
```

### 3. Access Services

```
🎤 Backend:        http://localhost:8000
👤 Customer API:   http://localhost:8001
🌐 Frontend:       http://localhost:8080
```

### 4. Stop Services

```bash
# Stop container
docker-compose down

# Stop + remove volumes
docker-compose down -v

# View logs
docker-compose logs -f
```

---

## 📊 Architecture

```
┌────────────────────────────────────────────┐
│          Docker Container (1)              │
├────────────────────────────────────────────┤
│                                            │
│  📦 Shared Python Environment (venv)      │
│  ├─ FastAPI                               │
│  ├─ Pydantic                              │
│  ├─ librosa (audio processing)            │
│  ├─ numpy, scipy                          │
│  └─ [All dependencies]                    │
│                                            │
├─────────────────────┬──────────────────────┤
│  🎵 Backend         │  👤 Customer Service │
│  Port 8000          │  Port 8001           │
│  └─ main.py        │  └─ main.py           │
│                     │                      │
│  Uploads: ────┐     │  Data: ──────────┐   │
│               │     │         │        │   │
│  Outputs: ────┼─────┼──────┬──┴────┐  │    │
│               │     │      │       │  │    │
│              🌐 Frontend   🗄️ Database    │  │
│              Port 8080                     │
│              └─ HTTP Server                │
│                                            │
└────────────────────────────────────────────┘
         ↕
    🗄️ Docker Volumes
    (Data persistence)
```

---

## 💾 Persistent Data

```yaml
volumes:
  mutrapro-data:      # customer-service/data
  mutrapro-uploads:   # backend/uploads
  mutrapro-outputs:   # backend/outputs
```

**Sử dụng:**
```bash
# Kiểm tra data
docker volume ls
docker volume inspect mutrapro-data

# Backup data
docker run --rm -v mutrapro-data:/data -v $(pwd):/backup \
  alpine tar czf /backup/data.tar.gz /data

# Restore data
docker run --rm -v mutrapro-data:/data -v $(pwd):/backup \
  alpine tar xzf /backup/data.tar.gz -C /data
```

---

## 🔧 Common Commands

### Image Management
```bash
# Build image
docker-compose build

# Build without cache
docker-compose build --no-cache

# View images
docker images | grep mutrapro

# Remove image
docker rmi mutrapro:latest
```

### Container Management
```bash
# Start services
docker-compose up -d

# Stop services
docker-compose stop

# Restart services
docker-compose restart

# Remove everything
docker-compose down -v

# View running containers
docker ps

# View all containers
docker ps -a
```

### Logs & Debug
```bash
# View logs
docker-compose logs

# Follow logs
docker-compose logs -f

# Logs for specific service (từ compose chỉ có 1)
docker-compose logs -f mutrapro

# View last 100 lines
docker-compose logs --tail=100
```

### Exec Commands
```bash
# Run command in container
docker-compose exec mutrapro bash

# Python interactive
docker-compose exec mutrapro python

# Check data
docker-compose exec mutrapro cat /app/customer-service/data/customers.json

# Check ports
docker-compose exec mutrapro netstat -tuln
```

---

## 📈 Performance Optimization

### 1. Image Size Optimization

**Multi-stage build (already in Dockerfile):**
```
Builder stage:     ~800 MB (with build tools)
Final stage:       ~500 MB (only runtime)
                   Savings: 40%
```

### 2. Volume Performance

**Bind mounts (fast development):**
```yaml
volumes:
  - ./backend:/app/backend      # Live reload
  - ./customer-service:/app/customer-service
  - ./frontend:/app/frontend
```

**Named volumes (better performance):**
```yaml
volumes:
  - mutrapro-data:/app/customer-service/data
```

### 3. Layer Caching

```dockerfile
# Bad (slow rebuild):
COPY . /app
RUN pip install -r requirements.txt

# Good (fast rebuild):
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . /app
```

---

## 🐛 Troubleshooting

### Container không start
```bash
# Check logs
docker-compose logs

# Lỗi thường gặp:
# - Port đang dùng: docker-compose down hoặc đổi port
# - Không có image: docker-compose build
# - Permission denied: Use sudo hoặc add user to docker group
```

### Health check failed
```bash
# Test endpoint
curl http://localhost:8001/health

# Kiểm tra từ trong container
docker-compose exec mutrapro curl http://localhost:8001/health
```

### Data not persisting
```bash
# Verify volumes exist
docker volume ls | grep mutrapro

# Check volume mount
docker inspect mutrapro-full-stack | grep -A 10 Mounts
```

### Port conflict
```bash
# Kiểm tra port
netstat -tuln | grep 8000

# Hoặc đổi port trong docker-compose.yml:
ports:
  - "8002:8000"  # host:container
  - "8003:8001"
  - "8081:8080"
```

---

## 🚀 Deployment

### Development
```bash
docker-compose up
# Services start immediately with hot reload
```

### Production
```bash
# Build optimized image
docker-compose build --no-cache

# Start with auto-restart
docker-compose up -d

# View status
docker-compose ps

# Monitor health
watch -n 5 'docker-compose ps'
```

### Scaling
```bash
# Single container handles all traffic
# For multiple containers, use:
docker-compose up -d --scale mutrapro=3
# (Requires load balancer like nginx)
```

---

## 🔐 Security

### 1. Environment Variables
```bash
# .env file
ALLOWED_ORIGINS=https://yourdomain.com
API_KEY=your-secret-key
```

```yaml
# docker-compose.yml
environment:
  - ALLOWED_ORIGINS=${ALLOWED_ORIGINS}
```

### 2. Network Security
```yaml
# Restrict network
networks:
  mutrapro-net:
    driver: bridge
```

### 3. Volume Permissions
```bash
# Secure data folder
docker-compose exec mutrapro chmod 700 /app/customer-service/data
```

---

## 📊 Monitoring

### Container Status
```bash
docker-compose ps
```

### Resource Usage
```bash
docker stats mutrapro-full-stack
```

### Logs Analysis
```bash
# Error logs
docker-compose logs | grep ERROR

# Last hour
docker-compose logs --since 1h

# Specific time range
docker-compose logs --until 5m
```

---

## 🔄 CI/CD Integration

### GitHub Actions Example
```yaml
name: Build and Deploy

on: [push]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: docker/setup-buildx-action@v1
      - uses: docker/build-push-action@v2
        with:
          context: .
          push: true
          tags: myregistry/mutrapro:latest
```

---

## 📚 File Structure

```
C:\audio\
├─ Dockerfile              ← Multi-stage build
├─ docker-compose.yml      ← Orchestration
├─ .dockerignore           ← Optimization
│
├─ backend/
│  ├─ main.py
│  ├─ processing.py
│  ├─ requirements.txt
│  ├─ uploads/
│  └─ outputs/
│
├─ customer-service/
│  ├─ main.py
│  ├─ requirements.txt
│  └─ data/
│
└─ frontend/
   ├─ index.html
   ├─ auth.html
   ├─ customer-dashboard.html
   └─ guide.html
```

---

## ✅ Verification Checklist

```
After docker-compose up:

API Health:
[ ] curl http://localhost:8000/health → OK
[ ] curl http://localhost:8001/health → OK

Frontend:
[ ] http://localhost:8080 loads
[ ] index.html renders

Data:
[ ] customers.json writable
[ ] Can create account
[ ] Can upload file

Logs:
[ ] No error messages
[ ] All 3 services started
[ ] Health check passed
```

---

## 🎯 Next Steps

1. **Install Docker** (if not already installed)
2. **Build image:** `docker-compose build`
3. **Start services:** `docker-compose up -d`
4. **Verify:** `http://localhost:8080`
5. **Test:** Follow TEST_NEW_FEATURES.md
6. **Deploy:** Use in production

---

## 💡 Tips

- **Remove unused images:** `docker image prune`
- **Clean old volumes:** `docker volume prune`
- **Export image:** `docker save mutrapro:latest > mutrapro.tar`
- **Load image:** `docker load < mutrapro.tar`
- **Push to registry:** `docker tag mutrapro:latest myregistry/mutrapro:latest`

---

## 📞 Support

- **Docker docs:** https://docs.docker.com
- **Dockerfile reference:** https://docs.docker.com/engine/reference/builder/
- **Compose reference:** https://docs.docker.com/compose/compose-file/
- **Best practices:** https://docs.docker.com/develop/dev-best-practices/

---

**Version:** 1.0  
**Status:** ✅ Ready to Use  
**Last Updated:** November 12, 2025

🐳 **Containerization complete!**
