using MuTraProAPI.Models;
using MuTraProAPI.Data;

namespace MuTraProAPI.Helpers
{
    /// <summary>
    /// Helper class để tạo thông báo cho khách hàng
    /// </summary>
    public static class NotificationHelper
    {
        /// <summary>
        /// Tạo thông báo cho khách hàng về thay đổi trạng thái yêu cầu dịch vụ
        /// </summary>
        public static async Task CreateNotificationAsync(
            MuTraProDbContext context,
            int customerId,
            int? serviceRequestId,
            string title,
            string message,
            NotificationType type = NotificationType.StatusChange)
        {
            var notification = new Notification
            {
                CustomerId = customerId,
                ServiceRequestId = serviceRequestId,
                Title = title,
                Message = message,
                Type = type,
                CreatedAt = DateTimeHelper.Now
            };

            context.Notifications.Add(notification);
            await context.SaveChangesAsync();
        }

        /// <summary>
        /// Tạo thông báo khi trạng thái yêu cầu thay đổi
        /// </summary>
        public static async Task NotifyStatusChangeAsync(
            MuTraProDbContext context,
            ServiceRequest request,
            RequestStatus oldStatus,
            RequestStatus newStatus,
            string? additionalInfo = null)
        {
            if (request.CustomerId <= 0) return;

            string title = "Thay đổi trạng thái yêu cầu";
            string message = "";
            NotificationType type = NotificationType.StatusChange;

            // Tạo message dựa trên trạng thái mới
            switch (newStatus)
            {
                case RequestStatus.PendingReview:
                    title = "✅ Yêu cầu đã được chấp nhận";
                    message = $"Yêu cầu dịch vụ \"{request.Title}\" đã được admin chấp nhận. Vui lòng chọn ngày gặp chuyên gia.";
                    type = NotificationType.Success;
                    break;

                case RequestStatus.Cancelled:
                    title = "❌ Yêu cầu bị từ chối";
                    message = $"Yêu cầu dịch vụ \"{request.Title}\" đã bị từ chối.";
                    if (!string.IsNullOrEmpty(additionalInfo))
                    {
                        message += $" Lý do: {additionalInfo}";
                    }
                    type = NotificationType.Error;
                    break;

                case RequestStatus.PendingMeetingConfirmation:
                    title = "📅 Đang chờ xác nhận lịch hẹn";
                    message = $"Yêu cầu dịch vụ \"{request.Title}\" đã được lên lịch. Đang chờ chuyên gia xác nhận.";
                    type = NotificationType.Info;
                    break;

                case RequestStatus.Completed:
                    title = "🎉 Yêu cầu đã hoàn thành";
                    message = $"Yêu cầu dịch vụ \"{request.Title}\" đã được chuyên gia hoàn thành. Vui lòng thanh toán.";
                    type = NotificationType.Success;
                    break;

                case RequestStatus.RejectedByExpert:
                    title = "⚠️ Chuyên gia từ chối gặp";
                    message = $"Chuyên gia đã từ chối gặp cho yêu cầu \"{request.Title}\".";
                    if (!string.IsNullOrEmpty(additionalInfo))
                    {
                        message += $" Lý do: {additionalInfo}";
                    }
                    type = NotificationType.Warning;
                    break;

                case RequestStatus.Assigned:
                    title = "👤 Đã phân công chuyên gia";
                    message = $"Yêu cầu dịch vụ \"{request.Title}\" đã được phân công cho chuyên gia.";
                    type = NotificationType.Info;
                    break;

                default:
                    message = $"Trạng thái yêu cầu \"{request.Title}\" đã thay đổi từ {oldStatus} sang {newStatus}.";
                    break;
            }

            await CreateNotificationAsync(context, request.CustomerId, request.Id, title, message, type);

            // Publish MQTT notification
            try
            {
                var mqttMessage = new
                {
                    customerId = request.CustomerId,
                    serviceRequestId = request.Id,
                    title = title,
                    message = message,
                    type = type.ToString(),
                    oldStatus = oldStatus.ToString(),
                    newStatus = newStatus.ToString(),
                    timestamp = DateTimeHelper.Now
                };

                await MqttHelper.PublishAsync($"notifications/customer/{request.CustomerId}", mqttMessage, qos: 1);
                await MqttHelper.PublishAsync("notifications/all", mqttMessage, qos: 1);
            }
            catch (Exception ex)
            {
                // Log error but don't fail the notification creation
                Console.WriteLine($"Error publishing MQTT notification: {ex.Message}");
            }
        }

        /// <summary>
        /// Publish payment notification via MQTT
        /// </summary>
        public static async Task NotifyPaymentAsync(
            int customerId,
            int serviceRequestId,
            decimal amount,
            string paymentMethod,
            string transactionId)
        {
            try
            {
                var mqttMessage = new
                {
                    customerId = customerId,
                    serviceRequestId = serviceRequestId,
                    amount = amount,
                    paymentMethod = paymentMethod,
                    transactionId = transactionId,
                    timestamp = DateTimeHelper.Now,
                    type = "Payment"
                };

                await MqttHelper.PublishAsync($"notifications/customer/{customerId}/payment", mqttMessage, qos: 1);
                await MqttHelper.PublishAsync("notifications/payments", mqttMessage, qos: 1);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error publishing MQTT payment notification: {ex.Message}");
            }
        }

        /// <summary>
        /// Publish schedule update notification via MQTT
        /// </summary>
        public static async Task NotifyScheduleUpdateAsync(
            int specialistId,
            DateTime date,
            string? timeSlot = null)
        {
            try
            {
                var mqttMessage = new
                {
                    specialistId = specialistId,
                    date = date.ToString("yyyy-MM-dd"),
                    timeSlot = timeSlot,
                    timestamp = DateTimeHelper.Now,
                    type = "ScheduleUpdate"
                };

                await MqttHelper.PublishAsync($"notifications/specialist/{specialistId}/schedule", mqttMessage, qos: 1);
                await MqttHelper.PublishAsync("notifications/schedules", mqttMessage, qos: 1);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error publishing MQTT schedule notification: {ex.Message}");
            }
        }
    }
}

