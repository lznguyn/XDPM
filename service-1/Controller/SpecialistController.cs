using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;
using Microsoft.AspNetCore.Authorization;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class SpecialistController : ControllerBase
    {
        private readonly MuTraProDbContext _context;

        public SpecialistController(MuTraProDbContext context)
        {
            _context = context;
        }

        // GET: api/Specialist/requests
        [HttpGet("requests")]
        public async Task<IActionResult> GetMyRequests([FromQuery] int specialistId)
        {
            var requests = await _context.ServiceRequests
                .Include(r => r.Customer)
                .Include(r => r.AssignedSpecialist)
                .Where(r => r.AssignedSpecialistId == specialistId && 
                           (r.Status == RequestStatus.Assigned || 
                            r.Status == RequestStatus.InProgress ||
                            r.Status == RequestStatus.PendingMeetingConfirmation))
                .OrderByDescending(r => r.CreatedDate)
                .Select(r => new
                {
                    r.Id,
                    r.CustomerId,
                    CustomerName = r.Customer != null ? r.Customer.Name : null,
                    CustomerEmail = r.Customer != null ? r.Customer.Email : null,
                    r.ServiceType,
                    r.Title,
                    r.Description,
                    r.FileName,
                    r.Status,
                    r.CreatedDate,
                    r.DueDate,
                    r.ScheduledDate,
                    r.ScheduledTimeSlot,
                    r.MeetingNotes,
                    r.Priority
                })
                .ToListAsync();

            return Ok(requests);
        }

        // GET: api/Specialist/pending-meetings
        [HttpGet("pending-meetings")]
        public async Task<IActionResult> GetPendingMeetings([FromQuery] int specialistId)
        {
            var requests = await _context.ServiceRequests
                .Include(r => r.Customer)
                .Where(r => r.AssignedSpecialistId == specialistId && 
                           r.Status == RequestStatus.PendingMeetingConfirmation)
                .OrderBy(r => r.ScheduledDate)
                .Select(r => new
                {
                    r.Id,
                    r.CustomerId,
                    CustomerName = r.Customer != null ? r.Customer.Name : null,
                    CustomerEmail = r.Customer != null ? r.Customer.Email : null,
                    r.ServiceType,
                    r.Title,
                    r.Description,
                    r.ScheduledDate,
                    r.ScheduledTimeSlot,
                    r.MeetingNotes,
                    r.CreatedDate
                })
                .ToListAsync();

            return Ok(requests);
        }

        // POST: api/Specialist/requests/{id}/accept-meeting
        [HttpPost("requests/{id}/accept-meeting")]
        public async Task<IActionResult> AcceptMeeting(int id)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            if (request.Status != RequestStatus.PendingMeetingConfirmation)
                return BadRequest(new { message = "Chỉ có thể chấp nhận meeting khi ở trạng thái PendingMeetingConfirmation." });

            // Chuyên gia chấp nhận → chuyển sang Completed
            request.Status = RequestStatus.Completed;
            await _context.SaveChangesAsync();

            return Ok(new { 
                message = "Yêu cầu của bạn đã được hoàn thành. Vui lòng thanh toán.", 
                status = request.Status.ToString() 
            });
        }

        // POST: api/Specialist/requests/{id}/reject-meeting
        [HttpPost("requests/{id}/reject-meeting")]
        public async Task<IActionResult> RejectMeeting(int id, [FromBody] RejectMeetingDto? dto = null)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            if (request.Status != RequestStatus.PendingMeetingConfirmation)
                return BadRequest(new { message = "Chỉ có thể từ chối meeting khi ở trạng thái PendingMeetingConfirmation." });

            // Chuyên gia từ chối → chuyển sang RejectedByExpert hoặc Cancelled
            request.Status = RequestStatus.RejectedByExpert;
            if (!string.IsNullOrEmpty(dto?.Reason))
            {
                request.MeetingNotes = (request.MeetingNotes ?? "") + $"\nLý do từ chối: {dto.Reason}";
            }

            // Giải phóng time slot trong schedule
            if (request.ScheduledDate.HasValue && request.AssignedSpecialistId.HasValue)
            {
                var schedule = await _context.SpecialistSchedules
                    .FirstOrDefaultAsync(s => s.SpecialistId == request.AssignedSpecialistId.Value && 
                                             s.Date.Date == request.ScheduledDate.Value.Date);
                
                if (schedule != null && !string.IsNullOrEmpty(request.ScheduledTimeSlot))
                {
                    switch (request.ScheduledTimeSlot)
                    {
                        case "0-4":
                            schedule.TimeSlot1 = false;
                            break;
                        case "6-10":
                            schedule.TimeSlot2 = false;
                            break;
                        case "12-16":
                            schedule.TimeSlot3 = false;
                            break;
                        case "18-22":
                            schedule.TimeSlot4 = false;
                            break;
                    }
                    schedule.UpdatedAt = DateTime.Now;
                }
            }

            await _context.SaveChangesAsync();

            return Ok(new { 
                message = $"Chuyên gia đã từ chối gặp{(dto?.Reason != null ? $", lý do: {dto.Reason}" : "")}.", 
                status = request.Status.ToString() 
            });
        }

        // PUT: api/Specialist/requests/{id}/respond
        [HttpPut("requests/{id}/respond")]
        public async Task<IActionResult> RespondToRequest(int id, [FromBody] RespondToRequestDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            if (request.Status != RequestStatus.Assigned && request.Status != RequestStatus.InProgress)
                return BadRequest(new { message = "Can only respond to Assigned or InProgress requests." });

            if (!string.IsNullOrEmpty(dto.Notes))
            {
                request.MeetingNotes = (request.MeetingNotes ?? "") + "\n" + dto.Notes;
            }

            if (Enum.TryParse(dto.NewStatus, true, out RequestStatus newStatus))
            {
                request.Status = newStatus;
            }

            await _context.SaveChangesAsync();

            return Ok(new { message = "Response recorded successfully.", status = request.Status.ToString() });
        }

        // GET: api/Specialist/schedule
        [HttpGet("schedule")]
        public async Task<IActionResult> GetMySchedule([FromQuery] int specialistId, [FromQuery] DateTime? startDate, [FromQuery] DateTime? endDate)
        {
            var query = _context.SpecialistSchedules.Where(s => s.SpecialistId == specialistId);

            if (startDate.HasValue)
                query = query.Where(s => s.Date >= startDate.Value.Date);
            if (endDate.HasValue)
                query = query.Where(s => s.Date <= endDate.Value.Date);

            var schedules = await query.OrderBy(s => s.Date).ToListAsync();

            return Ok(schedules.Select(s => new
                {
                    s.Id,
                    s.SpecialistId,
                    date = s.Date.ToString("yyyy-MM-dd"), // Format date as string
                    timeSlots = new
                    {
                        slot1 = s.TimeSlot1, // 0-4h
                        slot2 = s.TimeSlot2, // 6-10h
                        slot3 = s.TimeSlot3, // 12-16h
                        slot4 = s.TimeSlot4  // 18-22h
                    },
                    createdAt = s.CreatedAt,
                    updatedAt = s.UpdatedAt
                }));
        }

        // POST: api/Specialist/schedule
        [HttpPost("schedule")]
        public async Task<IActionResult> UpdateSchedule([FromBody] UpdateScheduleDto dto)
        {
            if (dto.SpecialistId <= 0)
                return BadRequest(new { message = "Invalid specialist ID." });

            if (dto.Date == default(DateTime))
                return BadRequest(new { message = "Invalid date." });

            try
            {
                var schedule = await _context.SpecialistSchedules
                    .FirstOrDefaultAsync(s => s.SpecialistId == dto.SpecialistId && 
                                             s.Date.Date == dto.Date.Date);

                if (schedule == null)
                {
                    schedule = new SpecialistSchedule
                    {
                        SpecialistId = dto.SpecialistId,
                        Date = dto.Date.Date,
                        TimeSlot1 = dto.TimeSlot1,
                        TimeSlot2 = dto.TimeSlot2,
                        TimeSlot3 = dto.TimeSlot3,
                        TimeSlot4 = dto.TimeSlot4,
                        CreatedAt = DateTime.Now,
                        UpdatedAt = DateTime.Now
                    };
                    _context.SpecialistSchedules.Add(schedule);
                }
                else
                {
                    schedule.TimeSlot1 = dto.TimeSlot1;
                    schedule.TimeSlot2 = dto.TimeSlot2;
                    schedule.TimeSlot3 = dto.TimeSlot3;
                    schedule.TimeSlot4 = dto.TimeSlot4;
                    schedule.UpdatedAt = DateTime.Now;
                }

                await _context.SaveChangesAsync();

                return Ok(new { 
                    message = "Schedule updated successfully.",
                    date = schedule.Date.ToString("yyyy-MM-dd"),
                    timeSlots = new {
                        slot1 = schedule.TimeSlot1,
                        slot2 = schedule.TimeSlot2,
                        slot3 = schedule.TimeSlot3,
                        slot4 = schedule.TimeSlot4
                    }
                });
            }
            catch (Exception ex)
            {
                return StatusCode(500, new { message = "Error updating schedule.", error = ex.Message });
            }
        }

        public class RespondToRequestDto
        {
            public string? Notes { get; set; }
            public string? NewStatus { get; set; }
        }

        public class UpdateScheduleDto
        {
            public int SpecialistId { get; set; }
            public DateTime Date { get; set; }
            public bool TimeSlot1 { get; set; }
            public bool TimeSlot2 { get; set; }
            public bool TimeSlot3 { get; set; }
            public bool TimeSlot4 { get; set; }
        }

        public class RejectMeetingDto
        {
            public string? Reason { get; set; }
        }
    }
}

