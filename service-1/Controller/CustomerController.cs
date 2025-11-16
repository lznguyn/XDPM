using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class CustomerController : ControllerBase
    {
        private readonly MuTraProDbContext _context;

        public CustomerController(MuTraProDbContext context)
        {
            _context = context;
        }

        // POST: api/Customer
        [HttpPost]
        public async Task<IActionResult> CreateCustomer([FromBody] CreateCustomerDto dto)
        {
            // Check if customer with email already exists
            var existingCustomer = await _context.Customers
                .FirstOrDefaultAsync(c => c.Email == dto.Email);
            
            if (existingCustomer != null)
                return BadRequest(new { message = "Email already exists" });

            var customer = new Customer
            {
                Name = dto.Name,
                Email = dto.Email,
                Phone = dto.Phone,
                Address = dto.Address,
                AccountCreated = DateTime.Now,
                IsActive = true,
                UserId = dto.UserId
            };

            _context.Customers.Add(customer);
            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = customer.Id,
                name = customer.Name,
                email = customer.Email,
                phone = customer.Phone,
                address = customer.Address,
                account_created = customer.AccountCreated,
                is_active = customer.IsActive
            });
        }

        // GET: api/Customer
        [HttpGet]
        public async Task<IActionResult> GetAllCustomers()
        {
            var customers = await _context.Customers
                .Select(c => new
                {
                    id = c.Id,
                    name = c.Name,
                    email = c.Email,
                    phone = c.Phone,
                    address = c.Address,
                    account_created = c.AccountCreated,
                    is_active = c.IsActive
                })
                .ToListAsync();

            return Ok(customers);
        }

        // GET: api/Customer/{id}
        [HttpGet("{id}")]
        public async Task<IActionResult> GetCustomerById(int id)
        {
            var customer = await _context.Customers
                .FirstOrDefaultAsync(c => c.Id == id);

            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            return Ok(new
            {
                id = customer.Id,
                name = customer.Name,
                email = customer.Email,
                phone = customer.Phone,
                address = customer.Address,
                account_created = customer.AccountCreated,
                is_active = customer.IsActive
            });
        }

        // PUT: api/Customer/{id}
        [HttpPut("{id}")]
        public async Task<IActionResult> UpdateCustomer(int id, [FromBody] UpdateCustomerDto dto)
        {
            var customer = await _context.Customers.FindAsync(id);
            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            if (!string.IsNullOrEmpty(dto.Name))
                customer.Name = dto.Name;
            if (dto.Phone != null)
                customer.Phone = dto.Phone;
            if (dto.Address != null)
                customer.Address = dto.Address;

            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = customer.Id,
                name = customer.Name,
                email = customer.Email,
                phone = customer.Phone,
                address = customer.Address,
                account_created = customer.AccountCreated,
                is_active = customer.IsActive
            });
        }

        // POST: api/Customer/requests
        [HttpPost("requests")]
        public async Task<IActionResult> CreateServiceRequest([FromBody] CreateServiceRequestDto dto)
        {
            // Verify customer exists
            var customer = await _context.Customers.FindAsync(dto.CustomerId);
            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            var request = new ServiceRequest
            {
                CustomerId = dto.CustomerId,
                ServiceType = Enum.Parse<ServiceType>(dto.ServiceType, true),
                Title = dto.Title,
                Description = dto.Description,
                FileName = dto.FileName,
                Status = RequestStatus.Submitted,
                CreatedDate = DateTime.Now,
                DueDate = dto.DueDate,
                Priority = dto.Priority ?? "normal",
                Paid = false
            };

            _context.ServiceRequests.Add(request);
            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = request.Id,
                customer_id = request.CustomerId,
                service_type = request.ServiceType.ToString(),
                title = request.Title,
                description = request.Description,
                file_name = request.FileName,
                status = request.Status.ToString(),
                created_date = request.CreatedDate,
                due_date = request.DueDate,
                priority = request.Priority,
                paid = request.Paid
            });
        }

        // GET: api/Customer/requests/customer/{customerId}
        [HttpGet("requests/customer/{customerId}")]
        public async Task<IActionResult> GetCustomerRequests(int customerId)
        {
            var requests = await _context.ServiceRequests
                .Where(r => r.CustomerId == customerId)
                .Select(r => new
                {
                    id = r.Id,
                    customer_id = r.CustomerId,
                    service_type = r.ServiceType.ToString(),
                    title = r.Title,
                    description = r.Description,
                    file_name = r.FileName,
                    status = r.Status.ToString(),
                    created_date = r.CreatedDate,
                    due_date = r.DueDate,
                    assigned_specialist_id = r.AssignedSpecialistId,
                    priority = r.Priority,
                    paid = r.Paid
                })
                .ToListAsync();

            if (!requests.Any())
                return NotFound(new { message = "No requests found" });

            return Ok(requests);
        }

        // GET: api/Customer/requests/{id}
        [HttpGet("requests/{id}")]
        public async Task<IActionResult> GetServiceRequestById(int id)
        {
            var request = await _context.ServiceRequests
                .FirstOrDefaultAsync(r => r.Id == id);

            if (request == null)
                return NotFound(new { message = "Request not found" });

            return Ok(new
            {
                id = request.Id,
                customer_id = request.CustomerId,
                service_type = request.ServiceType.ToString(),
                title = request.Title,
                description = request.Description,
                file_name = request.FileName,
                status = request.Status.ToString(),
                created_date = request.CreatedDate,
                due_date = request.DueDate,
                assigned_specialist_id = request.AssignedSpecialistId,
                priority = request.Priority,
                paid = request.Paid
            });
        }

        // PUT: api/Customer/requests/{id}/status
        [HttpPut("requests/{id}/status")]
        public async Task<IActionResult> UpdateRequestStatus(int id, [FromBody] UpdateRequestStatusDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            if (Enum.TryParse(dto.Status, true, out RequestStatus status))
            {
                request.Status = status;
                await _context.SaveChangesAsync();
                return Ok(new
                {
                    id = request.Id,
                    status = request.Status.ToString()
                });
            }

            return BadRequest(new { message = "Invalid status" });
        }

        // POST: api/Customer/feedback
        [HttpPost("feedback")]
        public async Task<IActionResult> SubmitFeedback([FromBody] CreateFeedbackDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(dto.RequestId);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            var feedback = new CustomerFeedback
            {
                RequestId = dto.RequestId,
                FeedbackText = dto.FeedbackText,
                RevisionNeeded = dto.RevisionNeeded ?? false,
                CreatedDate = DateTime.Now
            };

            _context.CustomerFeedbacks.Add(feedback);

            // If revision requested, update request status
            if (dto.RevisionNeeded == true)
            {
                request.Status = RequestStatus.RevisionRequested;
            }

            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = feedback.Id,
                request_id = feedback.RequestId,
                feedback_text = feedback.FeedbackText,
                revision_needed = feedback.RevisionNeeded,
                created_date = feedback.CreatedDate
            });
        }

        // POST: api/Customer/payments
        [HttpPost("payments")]
        public async Task<IActionResult> CreatePayment([FromBody] CreatePaymentDto dto)
        {
            var customer = await _context.Customers.FindAsync(dto.CustomerId);
            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            var payment = new CustomerPayment
            {
                CustomerId = dto.CustomerId,
                ServiceRequestId = dto.ServiceRequestId,
                Amount = dto.Amount,
                PaymentMethod = dto.PaymentMethod,
                PaymentStatus = CustomerPaymentStatus.Completed,
                PaymentDate = DateTime.Now,
                TransactionId = Guid.NewGuid().ToString()
            };

            _context.CustomerPayments.Add(payment);

            // Mark request as paid
            var request = await _context.ServiceRequests.FindAsync(dto.ServiceRequestId);
            if (request != null)
            {
                request.Paid = true;
            }

            // Record transaction
            var transaction = new CustomerTransaction
            {
                CustomerId = dto.CustomerId,
                Description = $"Payment for service request {dto.ServiceRequestId}",
                Amount = dto.Amount,
                TransactionType = TransactionType.Payment,
                Date = DateTime.Now,
                PaymentId = payment.Id
            };

            _context.CustomerTransactions.Add(transaction);
            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = payment.Id,
                customer_id = payment.CustomerId,
                service_request_id = payment.ServiceRequestId,
                amount = payment.Amount,
                payment_method = payment.PaymentMethod,
                payment_status = payment.PaymentStatus.ToString(),
                payment_date = payment.PaymentDate,
                transaction_id = payment.TransactionId
            });
        }

        // GET: api/Customer/transactions/{customerId}
        [HttpGet("transactions/{customerId}")]
        public async Task<IActionResult> GetCustomerTransactions(int customerId)
        {
            var transactions = await _context.CustomerTransactions
                .Where(t => t.CustomerId == customerId)
                .Select(t => new
                {
                    id = t.Id,
                    customer_id = t.CustomerId,
                    description = t.Description,
                    amount = t.Amount,
                    transaction_type = t.TransactionType.ToString(),
                    date = t.Date,
                    payment_id = t.PaymentId
                })
                .ToListAsync();

            if (!transactions.Any())
                return NotFound(new { message = "No transactions found" });

            return Ok(transactions);
        }

        // DTOs
        public class CreateCustomerDto
        {
            public string Name { get; set; } = string.Empty;
            public string Email { get; set; } = string.Empty;
            public string? Phone { get; set; }
            public string? Address { get; set; }
            public int? UserId { get; set; }
        }

        public class UpdateCustomerDto
        {
            public string? Name { get; set; }
            public string? Phone { get; set; }
            public string? Address { get; set; }
        }

        public class CreateServiceRequestDto
        {
            public int CustomerId { get; set; }
            public string ServiceType { get; set; } = string.Empty;
            public string Title { get; set; } = string.Empty;
            public string? Description { get; set; }
            public string? FileName { get; set; }
            public DateTime? DueDate { get; set; }
            public string? Priority { get; set; }
        }

        public class UpdateRequestStatusDto
        {
            public string Status { get; set; } = string.Empty;
        }

        public class CreateFeedbackDto
        {
            public int RequestId { get; set; }
            public string FeedbackText { get; set; } = string.Empty;
            public bool? RevisionNeeded { get; set; }
        }

        public class CreatePaymentDto
        {
            public int CustomerId { get; set; }
            public int ServiceRequestId { get; set; }
            public decimal Amount { get; set; }
            public string PaymentMethod { get; set; } = string.Empty;
        }
    }
}

