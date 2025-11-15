using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;

namespace MuTraProAPI.Data
{
    public class MuTraProDbContext : DbContext
    {
        public MuTraProDbContext(DbContextOptions<MuTraProDbContext> options) : base(options) {}
        public DbSet<User> Users { get; set; }
        public DbSet<Order> Orders { get; set; }
        public DbSet<Product> Products { get; set; }
        public DbSet<MusicSubmission> MusicSubmissions { get; set; }
        public DbSet<Studio> Studios { get; set; }
        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            // Bắt buộc phải gọi base để đảm bảo các ánh xạ mặc định hoạt động
            base.OnModelCreating(modelBuilder); 

            // 🔑 CẤU HÌNH BẮT BUỘC ĐỂ KHẮC PHỤC LỖI MySQL ENUM CAST
            // Thiết lập thuộc tính Role (kiểu Enum) của User Model 
            // được lưu và truy xuất dưới dạng chuỗi (string) trong DB.
            modelBuilder.Entity<User>()
                .Property(u => u.Role)
                .HasConversion<string>();
            modelBuilder.Entity<Order>()
                .Property(o => o.PaymentStatus)
                .HasConversion<string>();
            modelBuilder.Entity<MusicSubmission>()
                .Property(m => m.Status)
                .HasConversion<string>();
            
            // THÊM CÁC CẤU HÌNH CHO CÁC ENUM KHÁC NẾU CÓ:
            // Ví dụ: Nếu Order có cột Status là Enum và được lưu là ENUM/VARCHAR trong MySQL:
            // modelBuilder.Entity<Order>()
            //     .Property(o => o.Status)
            //     .HasConversion<string>();
        }
    }
}
