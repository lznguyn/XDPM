using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Data;
using MuTraProAPI.Models;
using System.IO;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class Arrangement : ControllerBase
    {
        private readonly MuTraProDbContext _context;
        private readonly IWebHostEnvironment _env;
        public Arrangement(MuTraProDbContext context, IWebHostEnvironment env)
        {
            _context = context;
            _env = env;
        }
       [HttpGet]
        public async Task<IActionResult> GetMusicSubmissions()
        {
            var result = await (from ms in _context.MusicSubmissions
                                join u in _context.Users on ms.UserId equals u.Id
                                where ms.TargetRole == "arrangement"
                                orderby ms.Create_at descending
                                select new
                                {
                                    ms.Id,
                                    ms.Title,
                                    CustomerName = u.Name, // lấy Username từ bảng Users
                                    ms.Status,
                                    ms.File_Name,
                                    ms.Create_at
                                }).ToListAsync();

            return Ok(new
            {
                status = "success",
                message = "Lấy danh sách bài hát yêu cầu thành công",
                data = result
            });
        }
        [HttpPost("upload")]
        public async Task<IActionResult> UploadMix([FromForm] int requestId, [FromForm] IFormFile mixFile)
        {
            if (mixFile == null || mixFile.Length == 0)
                return BadRequest(new { status = "error", message = "Vui lòng chọn file để upload." });

            var allowedExtensions = new[] { ".mp3", ".wav", ".flac" };  
            var ext = Path.GetExtension(mixFile.FileName).ToLower();

            if (!allowedExtensions.Contains(ext))
                return BadRequest(new { status = "error", message = "Định dạng file không hợp lệ. Chỉ mp3, wav, flac." });

            var submission = await _context.MusicSubmissions.FirstOrDefaultAsync(ms => ms.Id == requestId && ms.TargetRole == "arrangement");
            if (submission == null)
                return NotFound(new { status = "error", message = "Bài nhạc không tồn tại hoặc không thuộc chuyên gia này." });

            var uploadDir = Path.Combine(_env.WebRootPath, "uploaded_mixes");
            if (!Directory.Exists(uploadDir))
                Directory.CreateDirectory(uploadDir);

            var newFileName = $"mix_{Guid.NewGuid()}{ext}";
            var filePath = Path.Combine(uploadDir, newFileName);

            using (var stream = new FileStream(filePath, FileMode.Create))
            {
                await mixFile.CopyToAsync(stream);
            }

            submission.File_Name = newFileName;
            submission.Status = MusicStatus.Completed;
            await _context.SaveChangesAsync();

            return Ok(new { status = "success", message = "Upload bản phối thành công!", fileName = newFileName });
        }
    }
}