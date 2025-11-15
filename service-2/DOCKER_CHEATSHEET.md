# 🐳 Docker Cheat Sheet - MuTraPro

**Quick reference for common Docker commands**

---

## 🚀 Essential Commands

### Start/Stop Services
```bash
# Start all services (foreground, see logs)
docker-compose up

# Start in background
docker-compose up -d

# Stop services
docker-compose stop

# Stop and remove containers
docker-compose down

# Stop, remove containers, and delete volumes
docker-compose down -v
```

---

## 🔨 Build & Image

### Build
```bash
# Build image (first time or after code changes)
docker-compose build

# Build without cache (fresh build)
docker-compose build --no-cache

# Build specific service
docker-compose build mutrapro
```

### Image Info
```bash
# List images
docker images | grep mutrapro

# Image details
docker inspect mutrapro:latest

# Remove image
docker rmi mutrapro:latest

# Remove all unused images
docker image prune
```

---

## 📊 Container Management

### View Status
```bash
# List running containers
docker ps

# List all containers (including stopped)
docker ps -a

# Container details
docker inspect mutrapro-full-stack

# Resource usage
docker stats mutrapro-full-stack
```

### Container Operations
```bash
# Restart container
docker-compose restart

# Pause container
docker-compose pause

# Unpause container
docker-compose unpause

# Remove container
docker-compose rm
```

---

## 📝 Logs

### View Logs
```bash
# View all logs
docker-compose logs

# Follow logs (live)
docker-compose logs -f

# Last 100 lines
docker-compose logs --tail=100

# Last 5 minutes
docker-compose logs --since 5m

# Timestamp
docker-compose logs --timestamps
```

### Search Logs
```bash
# Errors only
docker-compose logs | grep ERROR

# Specific service (in compose, only 1)
docker-compose logs mutrapro

# Follow and filter
docker-compose logs -f | grep "8001"
```

---

## 🔧 Execute Commands

### Shell Access
```bash
# Enter container shell
docker-compose exec mutrapro bash

# Python interactive
docker-compose exec mutrapro python

# Run command
docker-compose exec mutrapro ls /app
```

### Test APIs
```bash
# Test backend
docker-compose exec mutrapro curl http://localhost:8000/health

# Test customer service
docker-compose exec mutrapro curl http://localhost:8001/health

# Check data
docker-compose exec mutrapro cat /app/customer-service/data/customers.json
```

---

## 💾 Volume Management

### View Volumes
```bash
# List volumes
docker volume ls | grep mutrapro

# Volume details
docker volume inspect mutrapro-data

# Volume location
docker volume inspect mutrapro-data --format='{{.Mountpoint}}'
```

### Backup/Restore
```bash
# Backup data
docker run --rm -v mutrapro-data:/data -v $(pwd):/backup \
  alpine tar czf /backup/data.tar.gz /data

# Restore data
docker run --rm -v mutrapro-data:/data -v $(pwd):/backup \
  alpine tar xzf /backup/data.tar.gz -C /

# Clean volumes
docker volume prune
```

---

## 🔍 Debugging

### Check Services
```bash
# Are all ports open?
docker-compose exec mutrapro netstat -tuln

# What's running?
docker-compose exec mutrapro ps aux

# Environment variables
docker-compose exec mutrapro env
```

### Common Issues
```bash
# Port already in use?
netstat -tuln | grep 8001

# Check logs for errors
docker-compose logs | grep ERROR

# Health check status
docker-compose ps
# Check "STATUS" column

# Full container info
docker-compose exec mutrapro cat /etc/os-release
```

---

## 📦 Registry Operations

### Push Image
```bash
# Tag image
docker tag mutrapro:latest myregistry/mutrapro:latest

# Login to registry
docker login myregistry

# Push
docker push myregistry/mutrapro:latest
```

### Pull Image
```bash
# Pull image
docker pull myregistry/mutrapro:latest

# Use in compose
# Change Dockerfile to: FROM myregistry/mutrapro:latest
```

---

## 🧹 Cleanup

### Remove Everything
```bash
# Stop and remove containers
docker-compose down

# Remove volumes
docker-compose down -v

# Remove images
docker image prune -a

# Remove all unused resources
docker system prune -a
```

### Selective Cleanup
```bash
# Remove unused images
docker image prune

# Remove unused volumes
docker volume prune

# Remove unused networks
docker network prune

# Remove unused containers
docker container prune
```

---

## 📋 Compose Operations

### Service Status
```bash
# Status of all services
docker-compose ps

# Status in JSON
docker-compose ps --format json
```

### Config Management
```bash
# Show effective config
docker-compose config

# Validate compose file
docker-compose config --quiet

# Show specific service
docker-compose config --services
```

### Update Services
```bash
# Apply changes from docker-compose.yml
docker-compose up -d --no-deps --build

# Rebuild without restarting others
docker-compose build --no-deps mutrapro
```

---

## 🚨 Emergency Commands

### If Container Won't Start
```bash
# Check detailed error
docker-compose logs -f

# Remove and rebuild
docker-compose down -v
docker-compose build --no-cache
docker-compose up
```

### If Ports Are Stuck
```bash
# Find what's using port 8001
netstat -tuln | grep 8001
lsof -i :8001

# Force restart
docker-compose restart
docker-compose down -v && docker-compose up
```

### If Volume Is Corrupt
```bash
# Backup current data
docker run --rm -v mutrapro-data:/data -v $(pwd):/backup \
  alpine tar czf /backup/data.backup.tar.gz /data

# Remove volume
docker volume rm mutrapro-data

# Restart (volume recreated)
docker-compose up -d
```

---

## 💡 Quick Reference

| Command | Purpose |
|---------|---------|
| `docker-compose up` | Start services |
| `docker-compose up -d` | Start in background |
| `docker-compose down` | Stop services |
| `docker-compose logs -f` | View live logs |
| `docker-compose exec mutrapro bash` | Shell access |
| `docker-compose ps` | View status |
| `docker-compose build` | Build image |

---

## 🎯 Workflow Example

```bash
# 1. First time setup
docker-compose build

# 2. Start services
docker-compose up -d

# 3. Check status
docker-compose ps

# 4. View logs
docker-compose logs -f

# 5. Test API
docker-compose exec mutrapro curl http://localhost:8001/health

# 6. Access shell
docker-compose exec mutrapro bash

# 7. Exit shell
exit

# 8. Stop services
docker-compose down

# 9. Remove everything
docker-compose down -v
```

---

## 🔗 Useful Links

- **Docker Docs:** https://docs.docker.com
- **Compose Docs:** https://docs.docker.com/compose/
- **CLI Reference:** https://docs.docker.com/engine/reference/commandline/
- **Compose File:** https://docs.docker.com/compose/compose-file/

---

**Keep this handy for quick Docker operations!** ⚡

Last updated: November 12, 2025
