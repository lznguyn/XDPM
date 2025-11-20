using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;
using BCrypt.Net;

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

            // Nếu không có UserId được cung cấp, tự động tạo User record
            int? userId = dto.UserId;
            if (!userId.HasValue)
            {
                // Kiểm tra xem User với email này đã tồn tại chưa
                var existingUser = await _context.Users
                    .FirstOrDefaultAsync(u => u.Email == dto.Email);
                
                if (existingUser == null)
                {
                    // Tạo User mới với role User và password mặc định (có thể cần đổi sau)
                    // Tạo password hash mặc định (khách hàng sẽ cần đổi password khi đăng nhập lần đầu)
                    var defaultPassword = BCrypt.Net.BCrypt.HashPassword("TempPassword123!");
                    
                    var newUser = new User
                    {
                        Name = dto.Name,
                        Email = dto.Email,
                        PasswordHash = defaultPassword,
                        Role = UserRole.User
                    };
                    
                    _context.Users.Add(newUser);
                    await _context.SaveChangesAsync();
                    userId = newUser.Id;
                }
                else
                {
                    // Nếu User đã tồn tại, sử dụng UserId đó
                    userId = existingUser.Id;
                }
            }

            var customer = new Customer
            {
                Name = dto.Name,
                Email = dto.Email,
                Phone = dto.Phone,
                Address = dto.Address,
                AccountCreated = DateTime.Now,
                IsActive = true,
                UserId = userId
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
                is_active = customer.IsActive,
                user_id = customer.UserId
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
                Status = RequestStatus.Requested, // Trạng thái ban đầu: Requested
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
                status = request.Status.ToString(), // Sẽ trả về "Pending"
                created_date = request.CreatedDate,
                due_date = request.DueDate,
                priority = request.Priority,
                paid = request.Paid,
                preferred_specialist_id = request.PreferredSpecialistId,
                scheduled_date = request.ScheduledDate,
                scheduled_time_slot = request.ScheduledTimeSlot,
                meeting_notes = request.MeetingNotes
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

        // POST: api/Customer/requests/{id}/select-expert
        [HttpPost("requests/{id}/select-expert")]
        public async Task<IActionResult> SelectExpertAndSchedule(int id, [FromBody] SelectExpertAndScheduleDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            // Chỉ cho phép khi request ở trạng thái PendingReview
            if (request.Status != RequestStatus.PendingReview)
                return BadRequest(new { message = "Chỉ có thể chọn chuyên gia khi yêu cầu ở trạng thái PendingReview." });

            // Kiểm tra chuyên gia có tồn tại không
            var specialist = await _context.Users.FindAsync(dto.SpecialistId);
            if (specialist == null)
                return NotFound(new { message = "Chuyên gia không tồn tại." });

            // Kiểm tra lịch làm việc của chuyên gia
            var schedule = await _context.SpecialistSchedules
                .FirstOrDefaultAsync(s => s.SpecialistId == dto.SpecialistId && 
                                         s.Date.Date == dto.ScheduledDate.Date);

            // Kiểm tra ngày có phải là ngày làm việc của chuyên gia không
            // (Có thể mở rộng logic này để kiểm tra working days)
            // Tạm thời chỉ kiểm tra time slot có available không

            bool isTimeSlotAvailable = false;
            if (schedule != null)
            {
                // Kiểm tra time slot có trống không
                isTimeSlotAvailable = dto.TimeSlot switch
                {
                    "0-4" => !schedule.TimeSlot1,
                    "6-10" => !schedule.TimeSlot2,
                    "12-16" => !schedule.TimeSlot3,
                    "18-22" => !schedule.TimeSlot4,
                    _ => false
                };
            }
            else
            {
                // Không có schedule → tất cả time slot đều available
                isTimeSlotAvailable = true;
            }

            if (!isTimeSlotAvailable)
                return BadRequest(new { message = "Không thể gặp chuyên gia vào ngày/giờ này, vui lòng chọn lại." });

            // Cập nhật request
            request.AssignedSpecialistId = dto.SpecialistId;
            request.ScheduledDate = dto.ScheduledDate;
            request.ScheduledTimeSlot = dto.TimeSlot;
            request.MeetingNotes = dto.MeetingNotes;
            request.Status = RequestStatus.PendingMeetingConfirmation; // Chờ chuyên gia xác nhận
            await _context.SaveChangesAsync();

            // Cập nhật hoặc tạo specialist schedule
            if (schedule == null)
            {
                schedule = new SpecialistSchedule
                {
                    SpecialistId = dto.SpecialistId,
                    Date = dto.ScheduledDate.Date
                };
                _context.SpecialistSchedules.Add(schedule);
            }

            // Đánh dấu time slot đã được đặt
            switch (dto.TimeSlot)
            {
                case "0-4":
                    schedule.TimeSlot1 = true;
                    break;
                case "6-10":
                    schedule.TimeSlot2 = true;
                    break;
                case "12-16":
                    schedule.TimeSlot3 = true;
                    break;
                case "18-22":
                    schedule.TimeSlot4 = true;
                    break;
            }
            schedule.UpdatedAt = DateTime.Now;
            await _context.SaveChangesAsync();

            return Ok(new
            {
                message = "Đã chọn chuyên gia và ngày gặp. Đang chờ chuyên gia xác nhận.",
                id = request.Id,
                status = request.Status.ToString()
            });
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

            // Mark request as paid FIRST (before adding payment)
            var request = await _context.ServiceRequests.FindAsync(dto.ServiceRequestId);
            if (request == null)
                return NotFound(new { message = "Service request not found" });

            request.Paid = true; // Đảm bảo cập nhật paid status

            _context.CustomerPayments.Add(payment);

            // Save payment and paid status together
            await _context.SaveChangesAsync();
            
            // Reload payment to ensure we have the database-generated ID
            await _context.Entry(payment).ReloadAsync();

            // Record transaction AFTER payment is saved and reloaded (so we have payment.Id)
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
            
            // Verify paid status was saved
            await _context.Entry(request).ReloadAsync();
            if (!request.Paid)
            {
                // If somehow paid wasn't saved, update it again
                request.Paid = true;
                await _context.SaveChangesAsync();
            }

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

        public class SelectExpertAndScheduleDto
        {
            public int SpecialistId { get; set; }
            public DateTime ScheduledDate { get; set; }
            public string TimeSlot { get; set; } = string.Empty; // "0-4", "6-10", "12-16", "18-22"
            public string? MeetingNotes { get; set; }
        }

        // PATCH: api/Customer/requests/{id}/paid
        [HttpPatch("requests/{id}/paid")]
        public async Task<IActionResult> UpdateRequestPaidStatus(int id, [FromBody] UpdatePaidStatusDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            request.Paid = dto.Paid;
            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = request.Id,
                paid = request.Paid,
                message = "Paid status updated successfully"
            });
        }

        public class UpdatePaidStatusDto
        {
            public bool Paid { get; set; }
        }
    }
}

