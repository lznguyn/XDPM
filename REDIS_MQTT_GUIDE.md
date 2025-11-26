# Hướng Dẫn Sử Dụng Redis Cache và MQTT trong MuTraPro

## 📋 Mục Lục
1. [Redis Cache](#redis-cache)
2. [MQTT](#mqtt)
3. [Lợi Ích Tổng Thể](#lợi-ích-tổng-thể)

---

## 🔴 Redis Cache

### Công Dụng Chính

Redis Cache là một **in-memory data store** được sử dụng để:

#### 1. **Tăng Tốc Độ Phản Hồi API**
- **Cache dữ liệu thường xuyên truy cập**: Lưu trữ các thông tin như customer profiles, payment records, work orders trong bộ nhớ RAM
- **Giảm thời gian truy vấn**: Thay vì query từ database (MySQL/PostgreSQL) mỗi lần, data được lấy trực tiếp từ Redis (nhanh hơn 100-1000 lần)
- **Ví dụ**: 
  - Không có cache: Query database → 50-200ms
  - Có Redis cache: Lấy từ memory → 1-5ms

#### 2. **Giảm Tải Cho Database**
- **Giảm số lượng queries**: Các request phổ biến không cần truy cập database
- **Bảo vệ database**: Tránh quá tải khi có nhiều request đồng thời
- **Kéo dài tuổi thọ database**: Giảm wear và tear trên database server

#### 3. **Cải Thiện Trải Nghiệm Người Dùng**
- **Response time nhanh hơn**: API trả về kết quả gần như tức thì
- **Tăng throughput**: Hệ thống xử lý được nhiều request hơn trong cùng thời gian
- **Cải thiện UX**: Người dùng không phải chờ đợi lâu

#### 4. **Caching Strategy trong MuTraPro**

**Customer Service:**
```
- GET /customers/{id} → Cache customer profile (TTL: 1 giờ)
- Khi update customer → Invalidate cache để đảm bảo data mới nhất
```

**Payment Service:**
```
- GET /payments/{id} → Cache payment details (TTL: 1 giờ)
- GET /payments/by-order/{orderId} → Cache list payments theo order
- Khi create/update payment → Invalidate cache tự động
```

**Coordinator Service:**
```
- GET /work-orders/{id} → Cache work order với tasks
- GET /work-orders → Cache list work orders
- Khi tạo/update work order → Invalidate cache
```

#### 5. **Cache Invalidation**
- Tự động xóa cache khi data được cập nhật
- Đảm bảo người dùng luôn nhận được dữ liệu mới nhất
- Pattern: Write-through (ghi vào DB và cache cùng lúc)

---

## 📡 MQTT (Message Queuing Telemetry Transport)

### Công Dụng Chính

MQTT là một **lightweight messaging protocol** được sử dụng để:

#### 1. **Real-Time Communication giữa Services**
- **Pub/Sub Pattern**: Services có thể publish messages và subscribe để nhận notifications
- **Decoupled Architecture**: Services không cần biết trực tiếp về nhau
- **Event-Driven**: Phản ứng với events trong hệ thống

#### 2. **Event Notifications**

**Payment Events:**
```
Topic: payment/created
- Khi tạo payment mới
- Notify: Customer Service, Coordinator Service
- Payload: { paymentId, orderId, customerId, amount, status }

Topic: payment/confirmed
- Khi payment được confirm thành công
- Notify: Customer để update order status
- Notify: Coordinator để start work order

Topic: payment/failed
- Khi payment thất bại
- Notify: Customer để hiển thị error
```

**Work Order Events:**
```
Topic: coordinator/work-order/created
- Khi tạo work order mới
- Notify: Payment Service để tạo payment request
- Payload: { workOrderId, customerId, serviceType }

Topic: coordinator/work-order/completed
- Khi work order hoàn thành
- Notify: Customer Service để update status
- Notify: Payment Service để finalize payment

Topic: coordinator/task/assigned
- Khi assign task cho specialist
- Notify: Specialist để bắt đầu work
- Payload: { taskId, assignedTo, taskType }

Topic: coordinator/task/status-updated
- Khi cập nhật task status
- Notify: Customer để cập nhật progress
```

**Customer Events:**
```
Topic: customer/request/created
- Khi customer tạo service request
- Notify: Coordinator để tạo work order
- Notify: Payment để tạo payment record

Topic: customer/payment/created
- Khi customer tạo payment
- Notify: Payment Service để process
```

#### 3. **Microservices Integration**
- **Loose Coupling**: Services giao tiếp qua messages, không phụ thuộc trực tiếp
- **Scalability**: Dễ dàng thêm services mới mà không ảnh hưởng services hiện có
- **Resilience**: Nếu một service down, messages được queue và xử lý sau

#### 4. **Real-Time Updates**
- **Live Status Updates**: Customer có thể nhận real-time updates về order status
- **Notification System**: Có thể extend để gửi push notifications, emails, SMS
- **Dashboard Updates**: Admin dashboard có thể hiển thị real-time metrics

#### 5. **Workflow Orchestration**
```
Ví dụ luồng xử lý order:
1. Customer tạo request → Publish "customer/request/created"
2. Coordinator nhận → Tạo work order → Publish "coordinator/work-order/created"
3. Payment nhận → Tạo payment → Publish "payment/created"
4. Customer nhận → Hiển thị payment QR code
5. Customer thanh toán → Payment confirm → Publish "payment/confirmed"
6. Coordinator nhận → Start work → Publish "coordinator/task/assigned"
7. Specialist hoàn thành → Publish "coordinator/task/status-updated"
8. All tasks done → Publish "coordinator/work-order/completed"
9. Customer nhận → Hiển thị completed status
```

---

## 🎯 Lợi Ích Tổng Thể

### 1. **Performance Improvements**
- ✅ API response time giảm từ 50-200ms xuống 1-5ms (với cached data)
- ✅ Database load giảm 60-80%
- ✅ Hệ thống xử lý được nhiều request hơn (tăng throughput)

### 2. **Real-Time Capabilities**
- ✅ Live updates cho customers về order status
- ✅ Instant notifications khi có events
- ✅ Better user experience với real-time feedback

### 3. **Scalability**
- ✅ Dễ dàng thêm services mới
- ✅ Services có thể scale độc lập
- ✅ Load balancing dễ dàng hơn với cached data

### 4. **Reliability**
- ✅ Messages được queue nếu service tạm thời unavailable
- ✅ Cache giúp hệ thống vẫn hoạt động nếu database chậm
- ✅ Decoupled architecture giảm single point of failure

### 4. **Cost Efficiency**
- ✅ Giảm database server costs (ít queries hơn)
- ✅ Có thể sử dụng smaller database instances
- ✅ Better resource utilization

---

## 📊 So Sánh Trước và Sau

### Trước khi có Redis & MQTT:
```
Request → API → Database Query (50-200ms) → Response
Database bị quá tải khi có nhiều requests
Services giao tiếp trực tiếp (tight coupling)
Không có real-time updates
```

### Sau khi có Redis & MQTT:
```
Request → API → Redis Cache (1-5ms) → Response
Database chỉ query khi cần (cache miss)
Services giao tiếp qua MQTT (loose coupling)
Real-time updates qua MQTT pub/sub
```

---

## 🚀 Use Cases Cụ Thể trong MuTraPro

### 1. **Customer View Order Status**
```
- Không có cache: Query database mỗi lần refresh → 100ms
- Có Redis: Lấy từ cache → 2ms (50x nhanh hơn)
- Real-time: Nhận MQTT update khi status thay đổi → instant
```

### 2. **Payment Processing Flow**
```
- Customer thanh toán → Payment Service xử lý
- Payment Service publish "payment/confirmed" via MQTT
- Coordinator Service nhận message → Tự động start work order
- Customer Service nhận message → Update UI real-time
- Không cần polling hoặc direct API calls
```

### 3. **Admin Dashboard**
```
- Cache frequently accessed data (total orders, revenue, etc.)
- Real-time updates via MQTT khi có events mới
- Dashboard không cần refresh liên tục
```

---

## 💡 Best Practices

### Redis Cache:
- ✅ Cache data thường xuyên đọc nhưng ít thay đổi
- ✅ Set TTL (Time To Live) hợp lý (1 giờ cho most cases)
- ✅ Invalidate cache khi data được update
- ✅ Monitor cache hit rate để tối ưu

### MQTT:
- ✅ Use descriptive topic names (payment/created, not p/c)
- ✅ Publish structured JSON messages
- ✅ Subscribe to relevant topics only
- ✅ Handle connection failures gracefully
- ✅ Use QoS levels appropriately (0: fire and forget, 1: at least once)

---

## 🔧 Monitoring

### Redis Metrics:
- Cache hit rate (target: >80%)
- Memory usage
- Connection count
- Response time

### MQTT Metrics:
- Messages published per second
- Messages subscribed per second
- Connection status
- Topic subscription count

---

## 📚 Tài Liệu Tham Khảo

- Redis: https://redis.io/docs/
- MQTT: https://mqtt.org/
- NestJS Cache Manager: https://docs.nestjs.com/techniques/caching
- FastAPI Redis: https://redis.io/docs/clients/python/

