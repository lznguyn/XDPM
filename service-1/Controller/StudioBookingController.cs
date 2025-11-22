using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Data;
using MuTraProAPI.Models;
using MuTraProAPI.Helpers;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class StudioBookingController : ControllerBase
    {
        private readonly MuTraProDbContext _context;

        public StudioBookingController(MuTraProDbContext context)
        {
            _context = context;
        }

        // GET: api/StudioBooking/requests
        [HttpGet("requests")]
        public async Task<IActionResult> GetBookingRequests()
        {
            var bookings = await _context.StudioBookings
                .Include(b => b.Studio)
                .Include(b => b.Customer)
                .Include(b => b.ServiceRequest)
                .OrderByDescending(b => b.CreatedDate)
                .Select(b => new
                {
                    id = b.Id,
                    studio_id = b.StudioId,
                    studio_name = b.Studio != null ? b.Studio.Name : null,
                    service_request_id = b.ServiceRequestId,
                    customer_id = b.CustomerId,
                    customer_name = b.Customer != null ? b.Customer.Name : null,
                    customer_email = b.Customer != null ? b.Customer.Email : null,
                    booking_date = b.BookingDate,
                    booking_time = b.BookingTime,
                    status = b.Status.ToString(),
                    created_date = b.CreatedDate,
                    approved_date = b.ApprovedDate,
                    notes = b.Notes,
                    request_title = b.ServiceRequest != null ? b.ServiceRequest.Title : null,
                    request_description = b.ServiceRequest != null ? b.ServiceRequest.Description : null
                })
                .ToListAsync();

            return Ok(new { status = "success", message = "Lấy danh sách yêu cầu đặt phòng thành công", data = bookings });
        }

        // POST: api/StudioBooking/{id}/approve
        [HttpPost("{id}/approve")]
        public async Task<IActionResult> ApproveBooking(int id)
        {
            var booking = await _context.StudioBookings
                .Include(b => b.Studio)
                .Include(b => b.ServiceRequest)
                .FirstOrDefaultAsync(b => b.Id == id);

            if (booking == null)
                return NotFound(new { status = "error", message = "Không tìm thấy yêu cầu đặt phòng!" });

            if (booking.Status != BookingStatus.Pending)
                return BadRequest(new { status = "error", message = "Chỉ có thể phê duyệt yêu cầu đang ở trạng thái Pending." });

            // Cập nhật booking status
            booking.Status = BookingStatus.Approved;
            booking.ApprovedDate = DateTimeHelper.Now;

            // Cập nhật ServiceRequest status thành Completed để khách hàng có thể thanh toán
            // Khi studio approve booking, service request được coi là hoàn thành và sẵn sàng thanh toán
            if (booking.ServiceRequest != null)
            {
                booking.ServiceRequest.Status = RequestStatus.Completed;
            }

            await _context.SaveChangesAsync();

            return Ok(new
            {
                status = "success",
                message = "Đã phê duyệt yêu cầu đặt phòng thành công!",
                data = new
                {
                    id = booking.Id,
                    status = booking.Status.ToString(),
                    approved_date = booking.ApprovedDate
                }
            });
        }

        // POST: api/StudioBooking/{id}/reject
        [HttpPost("{id}/reject")]
        public async Task<IActionResult> RejectBooking(int id, [FromBody] RejectBookingDto? dto = null)
        {
            var booking = await _context.StudioBookings
                .Include(b => b.ServiceRequest)
                .FirstOrDefaultAsync(b => b.Id == id);

            if (booking == null)
                return NotFound(new { status = "error", message = "Không tìm thấy yêu cầu đặt phòng!" });

            if (booking.Status != BookingStatus.Pending)
                return BadRequest(new { status = "error", message = "Chỉ có thể từ chối yêu cầu đang ở trạng thái Pending." });

            // Cập nhật booking status
            booking.Status = BookingStatus.Rejected;
            if (!string.IsNullOrEmpty(dto?.Reason))
            {
                booking.Notes = dto.Reason;
            }

            // Cập nhật ServiceRequest status
            if (booking.ServiceRequest != null)
            {
                booking.ServiceRequest.Status = RequestStatus.Cancelled;
            }

            await _context.SaveChangesAsync();

            return Ok(new
            {
                status = "success",
                message = "Đã từ chối yêu cầu đặt phòng.",
                data = new
                {
                    id = booking.Id,
                    status = booking.Status.ToString()
                }
            });
        }

        // GET: api/StudioBooking/check-dates
        [HttpGet("check-dates")]
        public async Task<IActionResult> CheckAndUpdateStudioStatus()
        {
            var today = DateTimeHelper.Now.Date;
            var updatedStudios = new List<object>();

            // Lấy tất cả bookings đã được approved và có booking_date là hôm nay hoặc đã qua
            var activeBookings = await _context.StudioBookings
                .Include(b => b.Studio)
                .Where(b => b.Status == BookingStatus.Approved && 
                           b.BookingDate.Date <= today)
                .ToListAsync();

            foreach (var booking in activeBookings)
            {
                // Nếu booking_date là hôm nay, đổi studio status thành Occupied
                if (booking.BookingDate.Date == today && booking.Studio != null)
                {
                    if (booking.Studio.Status != StudioStatus.Occupied)
                    {
                        booking.Studio.Status = StudioStatus.Occupied;
                        updatedStudios.Add(new
                        {
                            studio_id = booking.Studio.Id,
                            studio_name = booking.Studio.Name,
                            status = "Occupied",
                            booking_date = booking.BookingDate
                        });
                    }
                }

                // Nếu booking_date đã qua (quá khứ), đánh dấu booking là Completed
                if (booking.BookingDate.Date < today && booking.Status == BookingStatus.Approved)
                {
                    booking.Status = BookingStatus.Completed;
                }
            }

            // Kiểm tra các studio không có booking active hôm nay và đang ở trạng thái Occupied
            // Nếu không có booking nào cho hôm nay, đổi về Available
            var occupiedStudios = await _context.Studios
                .Where(s => s.Status == StudioStatus.Occupied)
                .ToListAsync();

            foreach (var studio in occupiedStudios)
            {
                var hasActiveBookingToday = await _context.StudioBookings
                    .AnyAsync(b => b.StudioId == studio.Id &&
                                  b.Status == BookingStatus.Approved &&
                                  b.BookingDate.Date == today);

                if (!hasActiveBookingToday)
                {
                    studio.Status = StudioStatus.Available;
                    updatedStudios.Add(new
                    {
                        studio_id = studio.Id,
                        studio_name = studio.Name,
                        status = "Available",
                        reason = "No active booking today"
                    });
                }
            }

            await _context.SaveChangesAsync();

            return Ok(new
            {
                status = "success",
                message = "Đã kiểm tra và cập nhật trạng thái studio.",
                updated_count = updatedStudios.Count,
                updated_studios = updatedStudios
            });
        }

        // GET: api/StudioBooking/studio/{studioId}
        [HttpGet("studio/{studioId}")]
        public async Task<IActionResult> GetBookingsByStudio(int studioId, [FromQuery] DateTime? date = null)
        {
            var query = _context.StudioBookings
                .Include(b => b.Customer)
                .Include(b => b.ServiceRequest)
                .Where(b => b.StudioId == studioId);

            if (date.HasValue)
            {
                query = query.Where(b => b.BookingDate.Date == date.Value.Date);
            }

            var bookings = await query
                .OrderByDescending(b => b.BookingDate)
                .ThenByDescending(b => b.CreatedDate)
                .Select(b => new
                {
                    id = b.Id,
                    customer_id = b.CustomerId,
                    customer_name = b.Customer != null ? b.Customer.Name : null,
                    booking_date = b.BookingDate,
                    booking_time = b.BookingTime,
                    status = b.Status.ToString(),
                    created_date = b.CreatedDate,
                    approved_date = b.ApprovedDate,
                    notes = b.Notes
                })
                .ToListAsync();

            return Ok(new { status = "success", message = "Lấy danh sách đặt phòng thành công", data = bookings });
        }

        public class RejectBookingDto
        {
            public string? Reason { get; set; }
        }
    }
}

