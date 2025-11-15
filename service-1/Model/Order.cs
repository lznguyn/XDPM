using System.ComponentModel.DataAnnotations;
using System.Net;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class Order
    {   
        [Key]
        public int Id { get; set; }

        [Column("user_id")]
        public int UserId { get; set; }

        [Column("name")]
        public string Name { get; set; } = string.Empty;

        [Column("number")]
        public string Number { get; set; } = string.Empty;

        [Column("email")]
        public string Email { get; set; } = string.Empty;

        [Column("method")]
        public string Method { get; set; } = string.Empty;
        
        [Column("total_products")]
        public string TotalProducts { get; set; } = string.Empty;

        [Column("total_price")]
        public int TotalPrice { get; set; }  // <--- EF Core sẽ map đúng tên cột total_price

        [Column("placed_on")]
        public DateTime PlacedOn { get; set; } = DateTime.Now;

        [Column("payment_status")]
        public Status PaymentStatus { get; set; } = Status.Pending;
    }
    public enum Status
    {
        Pending,
        Completed
    }
}