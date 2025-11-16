using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class CustomerPayment
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("customer_id")]
        public int CustomerId { get; set; }

        [ForeignKey("CustomerId")]
        public Customer? Customer { get; set; }

        [Required]
        [Column("service_request_id")]
        public int ServiceRequestId { get; set; }

        [ForeignKey("ServiceRequestId")]
        public ServiceRequest? ServiceRequest { get; set; }

        [Required]
        [Column("amount")]
        public decimal Amount { get; set; }

        [Required]
        [Column("payment_method")]
        public string PaymentMethod { get; set; } = string.Empty;

        [Required]
        [Column("payment_status")]
        public CustomerPaymentStatus PaymentStatus { get; set; } = CustomerPaymentStatus.Pending;

        [Column("payment_date")]
        public DateTime PaymentDate { get; set; } = DateTime.Now;

        [Column("transaction_id")]
        public string? TransactionId { get; set; }
    }

    public enum CustomerPaymentStatus
    {
        Pending,
        Completed,
        Failed,
        Refunded
    }
}

