using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Data;
using MuTraProAPI.Models;
using System.IO;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class ExpertsController : ControllerBase
    {
        private readonly MuTraProDbContext _context;
        private readonly IWebHostEnvironment _env;

        public ExpertsController(MuTraProDbContext context, IWebHostEnvironment env)
        {
            _context = context;
            _env = env;
        }

        // ===== LẤY DANH SÁCH CHUYÊN GIA =====
        [HttpGet]
        public async Task<IActionResult> GetStuido()
        {
            var experts = await _context.Studios.ToListAsync();
            return Ok(new { status = "success", message = "Lấy danh sách studio thành công", data = experts });
        }
         // ===== LẤY CHI TIẾT 1 PHÒNG THU =====
        [HttpGet("{id}")]
        public async Task<IActionResult> GetStudio(int id)
        {
            var studio = await _context.Studios.FindAsync(id);
            if (studio == null)
                return NotFound(new { status = "error", message = "Không tìm thấy phòng thu!" });

            return Ok(new
            {
                status = "success",
                message = "Lấy thông tin phòng thu thành công",
                data = studio
            });
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
