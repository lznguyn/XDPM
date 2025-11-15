using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using System.Security.Cryptography;
using Microsoft.AspNetCore.Cryptography.KeyDerivation;
using System.Text;
using Microsoft.IdentityModel.Tokens;
using System.IdentityModel.Tokens.Jwt;
using System.Security.Claims;
using MuTraProAPI.Data; 



namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class AuthController : ControllerBase
    {
        private readonly MuTraProDbContext _context;
        private readonly IConfiguration _configuration;

        public AuthController(MuTraProDbContext context, IConfiguration configuration)
        {
            _context = context;
            _configuration = configuration;
        }

        // Registration and login methods would go here
        [HttpPost("register")]
        public async Task<IActionResult> Register([FromBody] UserRegisterDto dto)
        {
            if(await _context.Users.AnyAsync(u => u.Email == dto.Email))
                return BadRequest(new { message = "Email đã tồn tại!" });

            if(dto.Password != dto.ConfirmPassword)
                return BadRequest(new { message = "Mật khẩu xác nhận không trùng khớp!" });

            if(dto.Password.Length < 8)
                return BadRequest(new { message = "Mật khẩu phải có ít nhất 8 ký tự!" });

            if(dto.Role == UserRole.Admin && dto.AdminCode != "admin123")
                return BadRequest(new { message = "Mã xác nhận Admin không đúng!" });

            var hashedPassword = BCrypt.Net.BCrypt.HashPassword(dto.Password);

            var user = new User {
                Name = dto.Name,
                Email = dto.Email,
                PasswordHash = hashedPassword,
                Role = dto.Role
            };

            _context.Users.Add(user);
            await _context.SaveChangesAsync();

            return Ok(new { message = "Đăng ký thành công!" });
        }
        [HttpPost("login")]
        public async Task<IActionResult> Login([FromBody] UserLoginDto dto)
        {
            var user = await _context.Users.FirstOrDefaultAsync(u => u.Email == dto.Email);
            if(user == null || !BCrypt.Net.BCrypt.Verify(dto.Password, user.PasswordHash))
                return Unauthorized(new { message = "Thông tin tài khoản hoặc mật khẩu không đúng!" });

            var token = GenerateJwtToken(user);

            return Ok(new {
                token,
                user = new {
                    user.Id,
                    user.Name,
                    user.Email,
                    Role = user.Role.ToString()
                }
            });
        }
        [HttpPost("logout")]
        public IActionResult Logout()
        {
            // In a stateless JWT authentication, logout can be handled on the client side
            // Xóa session
            HttpContext.Session.Clear();

            // Trả kết quả JSON (client sẽ redirect)
            return Ok(new { message = "Logged out successfully" });        
        }
        private string GenerateJwtToken(User user)
        {
            var claims = new[]
            {
                new Claim(JwtRegisteredClaimNames.Sub, user.Email),
                new Claim("id", user.Id.ToString()),
                new Claim("name", user.Name),
                new Claim("role", user.Role.ToString()),
            };

            var key = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(_configuration["Jwt:Key"]));
            var creds = new SigningCredentials(key, SecurityAlgorithms.HmacSha256);
            var token = new JwtSecurityToken(
                issuer: _configuration["Jwt:Issuer"],
                audience: _configuration["Jwt:Audience"],
                claims: claims,
                expires: DateTime.Now.AddHours(12),
                signingCredentials: creds
            );

            return new JwtSecurityTokenHandler().WriteToken(token);
        }
    }
    public class UserRegisterDto
    {
        public string Name { get; set; }  = string.Empty;
        public string Email { get; set; }  = string.Empty;
        public string Password { get; set; }  = string.Empty;
        public string ConfirmPassword { get; set; }  = string.Empty;
        public UserRole Role { get; set; }  = UserRole.User;
        public string AdminCode { get; set; }  = string.Empty;
    }
    public class UserLoginDto
    {
        public string Email { get; set; }  = string.Empty;
        public string Password { get; set; }  = string.Empty;
    }
}