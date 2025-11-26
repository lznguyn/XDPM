using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Data;
using MuTraProAPI.Models;
using MuTraProAPI.Helpers;
using System.IO;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class StudioController : ControllerBase
    {
        private readonly MuTraProDbContext _context;
        private readonly IWebHostEnvironment _env;

        public StudioController(MuTraProDbContext context, IWebHostEnvironment env)
        {
            _context = context;
            _env = env;
        }

        // ===== LẤY DANH SÁCH CHUYÊN GIA =====
        [HttpGet]
        public async Task<IActionResult> GetStuido()
        {
            try
            {
                // Logic check và update studio status đã được xử lý trong StudioBookingController
                // Frontend sẽ gọi /api/StudioBooking/check-dates trước khi load danh sách studio
                
                Console.WriteLine("[GetStuido] Starting to fetch studios from database...");
                
                // Query studios từ database - enum conversion đã được xử lý trong DbContext
                var studios = await _context.Studios.ToListAsync();
                
                Console.WriteLine($"[GetStuido] Found {studios.Count} studios from database");
                
                // Convert để đảm bảo enum được serialize đúng thành string
                var studiosData = studios.Select(s => new
                {
                    id = s.Id,
                    name = s.Name ?? string.Empty,
                    location = s.Location ?? string.Empty,
                    price = s.Price,
                    status = s.Status.ToString(), // Convert enum sang string (enum conversion đã handle từ DB)
                    image = s.Image ?? string.Empty
                }).ToList();
                
                Console.WriteLine($"[GetStuido] Successfully converted {studiosData.Count} studios");
                
                Console.WriteLine($"[GetStuido] Returning {studiosData.Count} studios");
                
                // Luôn trả về success, ngay cả khi danh sách rỗng
                return Ok(new { 
                    status = "success", 
                    message = studiosData.Count > 0 ? "Lấy danh sách studio thành công" : "Không có studio nào trong hệ thống",
                    data = studiosData 
                });
            }
            catch (Exception ex)
            {
                Console.WriteLine($"[GetStuido] Unexpected error: {ex.Message}");
                Console.WriteLine($"[GetStuido] StackTrace: {ex.StackTrace}");
                if (ex.InnerException != null)
                {
                    Console.WriteLine($"[GetStuido] InnerException: {ex.InnerException.Message}");
                    Console.WriteLine($"[GetStuido] InnerException StackTrace: {ex.InnerException.StackTrace}");
                }
                // Trả về lỗi nhưng vẫn giữ format hợp lệ
                return StatusCode(500, new { 
                    status = "error", 
                    message = "Lỗi khi lấy danh sách studio: " + (ex.InnerException?.Message ?? ex.Message),
                    data = new List<object>()
                });
            }
        }
         // ===== LẤY CHI TIẾT 1 PHÒNG THU =====
        [HttpGet("{id}")]
        public async Task<IActionResult> GetStudio(int id)
        {
            try
            {
                var studio = await _context.Studios.FindAsync(id);
                if (studio == null)
                    return NotFound(new { status = "error", message = "Không tìm thấy phòng thu!" });

                // Convert để đảm bảo enum được serialize đúng thành string
                var studioData = new
                {
                    id = studio.Id,
                    name = studio.Name,
                    location = studio.Location,
                    price = studio.Price,
                    status = studio.Status.ToString(), // Convert enum sang string
                    image = studio.Image
                };

                return Ok(new
                {
                    status = "success",
                    message = "Lấy thông tin phòng thu thành công",
                    data = studioData
                });
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error in GetStudio: {ex.Message}");
                return StatusCode(500, new { status = "error", message = "Lỗi khi lấy thông tin phòng thu: " + ex.Message });
            }
        }

        // ===== THÊM CHUYÊN GIA =====
        [HttpPost]
        public async Task<IActionResult> AddStudio([FromBody] StudioCreateRequest request)
        {
            if (string.IsNullOrEmpty(request.Name) || string.IsNullOrEmpty(request.Location))
                return BadRequest(new { status = "error", message = "Vui lòng nhập đầy đủ thông tin!" });

            bool exists = await _context.Studios.AnyAsync(s => s.Name == request.Name);
            if (exists)
                return BadRequest(new { status = "error", message = "Phòng thu đã tồn tại!" });

            var studio = new Studio
            {
                Name = request.Name,
                Location = request.Location,
                Status = request.Status,
                Price = request.Price,
                Image = request.Image
            };

            _context.Studios.Add(studio);
            await _context.SaveChangesAsync();

            return Ok(new { status = "success", message = "Thêm phòng thu thành công!", data = studio });
        }

        [HttpPut("{id}")]
        public async Task<IActionResult> UpdateStudio(int id, [FromBody] StudioUpdateRequest request)
        {
            var studio = await _context.Studios.FindAsync(id);
            if (studio == null)
                return NotFound(new { status = "error", message = "Không tìm thấy phòng thu!" });

            if (!string.IsNullOrEmpty(request.Name))
                studio.Name = request.Name;
            if (!string.IsNullOrEmpty(request.Location))
                studio.Location = request.Location;
            if (request.Status.HasValue)
                studio.Status = request.Status.Value;
            if (request.Price.HasValue)
                studio.Price = request.Price.Value;
            if (request.Image != null)
                studio.Image = request.Image;

            _context.Studios.Update(studio);
            await _context.SaveChangesAsync();

            return Ok(new { status = "success", message = "Cập nhật phòng thu thành công!", data = studio });
        }

        // ===== XÓA CHUYÊN GIA =====
        [HttpDelete("{id}")]
        public async Task<IActionResult> DeleteStudio(int id)
        {
            var studio = await _context.Studios.FindAsync(id);
            if (studio == null)
                return NotFound(new { status = "error", message = "Không tìm thấy phòng thu!" });

            _context.Studios.Remove(studio);
            await _context.SaveChangesAsync();

            return Ok(new { status = "success", message = "Xóa phòng thu thành công!" });
        }
    }

    // ===== Request Models =====
    public class StudioCreateRequest
    {
        [System.Text.Json.Serialization.JsonPropertyName("name")]
        public string Name { get; set; } = string.Empty;
        
        [System.Text.Json.Serialization.JsonPropertyName("location")]
        public string Location { get; set; } = string.Empty;
        
        [System.Text.Json.Serialization.JsonPropertyName("price")]
        public decimal Price { get; set; } = 0;
        
        [System.Text.Json.Serialization.JsonPropertyName("status")]
        public StudioStatus Status { get; set; } = StudioStatus.Available;
        
        [System.Text.Json.Serialization.JsonPropertyName("image")]
        public string? Image { get; set; }
    }

    public class StudioUpdateRequest
    {
        [System.Text.Json.Serialization.JsonPropertyName("name")]
        public string? Name { get; set; }
        
        [System.Text.Json.Serialization.JsonPropertyName("location")]
        public string? Location { get; set; }
        
        [System.Text.Json.Serialization.JsonPropertyName("price")]
        public decimal? Price { get; set; }
        
        [System.Text.Json.Serialization.JsonPropertyName("status")]
        public StudioStatus? Status { get; set; }
        
        [System.Text.Json.Serialization.JsonPropertyName("image")]
        public string? Image { get; set; }
    }
}
