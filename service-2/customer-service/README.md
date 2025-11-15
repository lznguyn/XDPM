# MuTraPro - Customer Microservice

Customer management service for MuTraPro (Custom Music Transcription and Production System).

## Features

- **Customer Management**: Create and manage customer profiles
- **Service Requests**: Submit transcription, arrangement, or recording requests
- **Order Tracking**: Monitor status of submitted requests
- **Feedback & Revisions**: Request revisions on completed work
- **Payment Management**: Process and track payments
- **Transaction History**: View all transaction records

## Quick Start (Development)

### 1. Install Dependencies

```bash
cd C:\audio\customer-service
python -m venv venv
.\venv\Scripts\activate
pip install -r requirements.txt
```

### 2. Run Locally

```bash
python main.py
```

Or with auto-reload:

```bash
uvicorn main:app --reload --port 8001
```

Server runs on: http://localhost:8001

### 3. Test API

- Swagger UI: http://localhost:8001/docs
- ReDoc: http://localhost:8001/redoc
- Health: http://localhost:8001/health

## API Endpoints

### Customer Management

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/customers` | Create new customer |
| GET | `/customers` | List all customers |
| GET | `/customers/{customer_id}` | Get customer profile |
| PUT | `/customers/{customer_id}` | Update customer profile |

### Service Requests

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/requests` | Submit new service request |
| GET | `/requests/customer/{customer_id}` | Get customer's requests |
| GET | `/requests/{request_id}` | Get request details |
| PUT | `/requests/{request_id}/status` | Update request status |

### Feedback & Revisions

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/feedback` | Submit feedback or request revision |

### Payments & Transactions

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/payments` | Process payment |
| GET | `/transactions/{customer_id}` | View transaction history |

### Health Checks

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/health` | Health check |
| GET | `/ready` | Readiness check |

## Data Storage

Currently uses JSON files stored in `data/` directory:
- `customers.json` - Customer profiles
- `requests.json` - Service requests
- `payments.json` - Payment records
- `transactions.json` - Transaction history

*Note: For production, replace with proper database (PostgreSQL, MySQL, MongoDB)*

## Docker

### Build Image

```bash
cd C:\audio\customer-service
docker build -t customer-service:latest .
```

### Run Container

```bash
docker run -p 8001:8001 customer-service:latest
```

### Using Docker Compose

```bash
docker-compose up --build
```

## Example Usage

### Create Customer

```bash
curl -X POST http://localhost:8001/customers \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "name=John Doe&email=john@example.com&phone=555-1234&address=123 Main St"
```

### Submit Service Request

```bash
curl -X POST http://localhost:8001/requests \
  -F "customer_id=<customer_id>" \
  -F "service_type=transcription" \
  -F "title=Transcribe My Song" \
  -F "description=Need accurate transcription" \
  -F "file=@audio.wav"
```

### Get Customer Requests

```bash
curl http://localhost:8001/requests/customer/<customer_id>
```

### Process Payment

```bash
curl -X POST http://localhost:8001/payments \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "customer_id=<customer_id>&service_request_id=<request_id>&amount=50.00&payment_method=credit_card"
```

## Configuration

Edit `.env.example` or set environment variables:

```bash
export HOST=0.0.0.0
export PORT=8001
export RELOAD=false
```

## Integration with Other Services

This service can be integrated with:
- **Music Transcriber Service** (port 8000) - for transcription processing
- **Orchestration Service** - for coordinating between specialists
- **Notification Service** - for sending updates to customers

## Future Enhancements

- [ ] Database integration (PostgreSQL/MongoDB)
- [ ] Authentication & Authorization (JWT)
- [ ] File storage service (S3, cloud storage)
- [ ] Email notifications
- [ ] Advanced search and filtering
- [ ] Analytics and reporting

## Port Configuration

- Customer Service: **8001** (default)
- Music Transcriber: 8000
- Frontend: 8080

To change port:

```bash
export PORT=8002
python main.py
```

Or run with custom port:

```bash
uvicorn main:app --port 8002
```

## Support

For issues or questions, contact the development team.
