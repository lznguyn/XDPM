using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class ServicePrice
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("service_type")]
        public ServiceType ServiceType { get; set; }

        [Required]
        [Column("price")]
        public decimal Price { get; set; }

        [Column("updated_at")]
        public DateTime UpdatedAt { get; set; } = DateTime.Now;

        [Column("updated_by")]
        public int? UpdatedBy { get; set; }
    }
}

