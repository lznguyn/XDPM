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
        public DbSet<StudioBooking> StudioBookings { get; set; }
        
        // Customer Service Tables
        public DbSet<Customer> Customers { get; set; }
        public DbSet<ServiceRequest> ServiceRequests { get; set; }
        public DbSet<CustomerFeedback> CustomerFeedbacks { get; set; }
        public DbSet<CustomerPayment> CustomerPayments { get; set; }
        public DbSet<CustomerTransaction> CustomerTransactions { get; set; }
        public DbSet<SpecialistSchedule> SpecialistSchedules { get; set; }
        public DbSet<ServicePrice> ServicePrices { get; set; }
        public DbSet<Notification> Notifications { get; set; }
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
            
            // Customer Service Enum Conversions
            modelBuilder.Entity<ServiceRequest>()
                .Property(s => s.ServiceType)
                .HasConversion<string>();
            modelBuilder.Entity<ServiceRequest>()
                .Property(s => s.Status)
                .HasConversion<string>();
            modelBuilder.Entity<CustomerPayment>()
                .Property(p => p.PaymentStatus)
                .HasConversion<string>();
            modelBuilder.Entity<CustomerTransaction>()
                .Property(t => t.TransactionType)
                .HasConversion<string>();
            
            // ServicePrice Enum Conversion
            modelBuilder.Entity<ServicePrice>()
                .Property(s => s.ServiceType)
                .HasConversion<string>();
            
            // Notification Enum Conversion
            modelBuilder.Entity<Notification>()
                .Property(n => n.Type)
                .HasConversion<string>();
            
            // StudioBooking Enum Conversion
            modelBuilder.Entity<StudioBooking>()
                .Property(b => b.Status)
                .HasConversion<string>();
        }
    }
}
