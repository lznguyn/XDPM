<?php
session_start();
$admin_id = $_SESSION['user']['id'] ?? null;
if (!$admin_id) {
    header('location:login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng điều khiển Admin - MuTraPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#f59e0b',
                        accent: '#10b981',
                        danger: '#dc2626',
                        success: '#059669',
                        warning: '#d97706',
                        info: '#0284c7'
                    }
                }
            }
        }
    </script>
</head>
<?php include 'admin_header.php'; ?>
<body class="bg-gray-50">

<div class="min-h-screen pt-20">
    <!-- Chào mừng -->
    <div class="bg-gradient-to-r from-primary to-blue-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2">Chào mừng trở lại, Admin!</h1>
                <p class="text-blue-100">Tổng quan hoạt động hệ thống MuTraPro hôm nay</p>
            </div>
            <div class="hidden md:block bg-white bg-opacity-20 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold" id="day"><?php echo date('d'); ?></div>
                <div class="text-sm" id="month"><?php echo date('M Y'); ?></div>
            </div>
        </div>
    </div>

    <!-- Thống kê -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="stats-grid">
            <!-- Cards sẽ được tạo bằng JS -->
        </div>
    </div>
</div>

<script>
// Hàm tạo card thống kê
function createStatCard(icon, title, value, colorClass, subtitle = '') {
    return `
    <div class="bg-white rounded-2xl shadow p-6 hover:shadow-lg transition">
        <div class="flex justify-between mb-4">
            <div class="${colorClass} p-3 rounded-xl">
                <i class="${icon} text-xl"></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-gray-900">${value}</h3>
        <p class="text-gray-600 text-sm mt-1">${subtitle || title}</p>
    </div>
    `;
}

// Lấy dữ liệu API ASP.NET Core
async function loadStats() {
    const token = '<?php echo $_SESSION['token'] ?? ''; ?>'; 
    console.log("Token:", token);

    
    // Kiểm tra token (Tùy chọn)
    if (!token) {
        console.error("Lỗi: Không tìm thấy token JWT trong Session.");
        // Hiển thị thông báo lỗi hoặc chuyển hướng...
        return; 
    }
    try {
        const res = await fetch('http://localhost:5200/api/Admin/stats', {
            headers: { 
                'Authorization': 'Bearer ' + token
            }
        });
        if (!res.ok) throw new Error('Không thể lấy dữ liệu API!');
        const data = await res.json();

        const container = document.getElementById('stats-grid');
        container.innerHTML = `
            ${createStatCard('fas fa-clock text-warning', 'Tổng tiền chờ xử lý', new Intl.NumberFormat('vi-VN').format(data.total_pendings) + ' VNĐ', 'bg-warning bg-opacity-10')}
            ${createStatCard('fas fa-check-circle text-success', 'Tổng tiền đã thanh toán', new Intl.NumberFormat('vi-VN').format(data.total_completed) + ' VNĐ', 'bg-success bg-opacity-10')}
            ${createStatCard('fas fa-shopping-cart text-info', 'Tổng đơn hàng', data.orders_count, 'bg-info bg-opacity-10')}
            ${createStatCard('fas fa-music text-purple-600', 'Dịch vụ âm nhạc', data.products_count, 'bg-purple-100')}
            ${createStatCard('fas fa-music text-purple-600', 'Yêu cầu nhạc chưa hoàn tất', data.musicsub_pending_count, 'bg-purple-100')}
            ${createStatCard('fas fa-music text-purple-600', 'Yêu cầu nhạc đã hoàn tất', data.musicsub_completed_count, 'bg-purple-100')}
            ${createStatCard('fas fa-user text-purple-600', 'Chuyên gia', data.experts_count, 'bg-purple-100')}
            ${createStatCard('fas fa-clock text-warning', 'Booking đang chờ xử lý', data.pending_orders_count, 'bg-warning bg-opacity-10')}
            ${createStatCard('fas fa-check-circle text-success', 'Booking đã hoàn thành', data.completed_orders_count, 'bg-success bg-opacity-10')}
            ${createStatCard('fas fa-users text-green-600', 'Người dùng', data.users_count, 'bg-green-100')}
            ${createStatCard('fas fa-user-shield text-red-600', 'Quản trị viên', data.admins_count, 'bg-red-100')}
            ${createStatCard('fas fa-user-tie text-green-600', 'Staff', data.staff_count, 'bg-green-100')}
            ${createStatCard('fas fa-comments text-yellow-600', 'Phòng thu âm', data.studios_count, 'bg-yellow-100')}
        `;
    } catch (err) {
        console.error(err);
    }
}

document.addEventListener('DOMContentLoaded', loadStats);
</script>

</body>
</html>
