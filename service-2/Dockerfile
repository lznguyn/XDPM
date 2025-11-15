# Multi-stage Dockerfile - Tối ưu hóa cho MuTraPro

FROM python:3.10-slim as builder

# Install build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# Create virtual environment in builder
WORKDIR /app
RUN python -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Copy all requirements
COPY backend/requirements.txt backend_req.txt
COPY customer-service/requirements.txt customer_req.txt

# Install Python dependencies
RUN pip install --no-cache-dir -r backend_req.txt && \
    pip install --no-cache-dir -r customer_req.txt

# ============================================================
# Final stage - Lean runtime image
# ============================================================
FROM python:3.10-slim

# Install runtime dependencies only
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsndfile1 \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Copy virtual environment from builder
COPY --from=builder /opt/venv /opt/venv

# Set environment
ENV PATH="/opt/venv/bin:$PATH" \
    PYTHONUNBUFFERED=1 \
    PYTHONPATH=/app

# Create app directory
WORKDIR /app

# Copy application code
COPY backend/ /app/backend/
COPY customer-service/ /app/customer-service/
COPY frontend/ /app/frontend/

# Create data directories
RUN mkdir -p /app/backend/uploads /app/backend/outputs \
    && mkdir -p /app/customer-service/uploads /app/customer-service/data \
    && mkdir -p /app/frontend/outputs

# Create startup script
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
echo "🚀 Starting MuTraPro Services..."\n\
\n\
# Start services in background\n\
echo "📡 Starting Backend (Port 8000)..."\n\
cd /app/backend && python -m uvicorn main:app --host 0.0.0.0 --port 8000 &\n\
BACKEND_PID=$!\n\
\n\
echo "📡 Starting Customer Service (Port 8001)..."\n\
cd /app/customer-service && python -m uvicorn main:app --host 0.0.0.0 --port 8001 &\n\
CUSTOMER_PID=$!\n\
\n\
echo "📡 Starting Frontend (Port 8080)..."\n\
cd /app/frontend && python -m http.server 8080 &\n\
FRONTEND_PID=$!\n\
\n\
echo "✅ All services started!"\n\
echo "   🎤 Backend:    http://localhost:8000"\n\
echo "   👤 Customer:   http://localhost:8001"\n\
echo "   🌐 Frontend:   http://localhost:8080"\n\
\n\
# Keep container running\n\
wait\n\
' > /app/start.sh && chmod +x /app/start.sh

# Expose ports
EXPOSE 8000 8001 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('\''http://localhost:8001/health'\'').read()" || exit 1

# Start all services
CMD ["/app/start.sh"]
