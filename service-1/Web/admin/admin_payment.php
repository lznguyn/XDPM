<?php
session_start();

// Nếu chưa đăng nhập Admin
if (!isset($_SESSION['admin_id'])) {
    header('location:login.php');
    exit();
}

// API base URL
$apiBase = "http://localhost:5200/api/Admin";

// ✅ Hàm gọi API
function callApi($url, $method = 'GET', $data = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// ✅ Xử lý xác nhận thanh toán
if (isset($_GET['confirm'])) {
    $orderId = intval($_GET['confirm']);
    $res = callApi("$apiBase/orders/$orderId/status", "PATCH", ["paymentStatus" => "Completed"]);

    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đơn hàng #$orderId đã được xác nhận thanh toán!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi xác nhận thanh toán!";
    }

    header('location:admin_orders.php');
    exit();
}

// ✅ Xử lý xóa đơn hàng
if (isset($_GET['delete'])) {
    $orderId = intval($_GET['delete']);
    $res = callApi("$apiBase/orders/$orderId", "DELETE");

    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "🗑️ Đã xóa đơn hàng thành công!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi xóa đơn hàng!";
    }

    header('location:admin_orders.php');
    exit();
}

// ✅ Lấy danh sách đơn hàng từ API
$res = callApi("$apiBase/orders");
$orders = $res['body'] ?? [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - MuTraPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<div class="min-h-screen pt-20">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-6 py-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="bg-primary bg-opacity-10 p-3 rounded-xl">
                    <i class="fas fa-shopping-cart text-primary text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Quản lý đơn hàng</h1>
                    <p class="text-gray-600 mt-1">Xem và xác nhận thanh toán cho các đơn hàng</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách đơn hàng -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($orders)): ?>
                <div class="col-span-full text-center text-gray-500">Chưa có đơn hàng nào.</div>
            <?php else: ?>
                <?php foreach ($orders as $order): 
                    $isPaid = $order['paymentStatus'] === 'Completed';
                ?>
                <div class="bg-white border-2 rounded-xl shadow-sm p-6 hover:shadow-lg transition">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="font-bold text-lg text-gray-900">Đơn hàng #<?= htmlspecialchars($order['id']) ?></h2>
                        <span class="text-sm px-3 py-1 rounded-full 
                            <?= $isPaid ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                            <?= $isPaid ? 'Đã thanh toán' : 'Chưa thanh toán' ?>
                        </span>
                    </div>

                    <p class="text-gray-700"><strong>Khách hàng:</strong> <?= htmlspecialchars($order['name']) ?></p>
                    <p class="text-gray-700"><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
                    <p class="text-gray-700"><strong>Tổng tiền:</strong> <?= number_format($order['totalPrice'], 0, ',', '.') ?>₫</p>
                    <p class="text-gray-700"><strong>Ngày đặt:</strong> <?= htmlspecialchars($order['placedOn']) ?></p>

                    <div class="mt-5 flex flex-col gap-2">
                        <?php if (!$isPaid): ?>
                        <a href="?confirm=<?= $order['id'] ?>"
                           onclick="return confirm('Xác nhận đơn hàng này đã thanh toán?');"
                           class="bg-green-50 hover:bg-green-100 text-green-700 py-2 rounded-lg font-medium text-center transition">
                            <i class="fas fa-check mr-2"></i>Xác nhận thanh toán
                        </a>
                        <?php endif; ?>

                        <a href="?delete=<?= $order['id'] ?>"
                           onclick="return confirm('Bạn có chắc muốn xóa đơn hàng này không?');"
                           class="bg-red-50 hover:bg-red-100 text-red-600 py-2 rounded-lg font-medium text-center transition">
                            <i class="fas fa-trash mr-2"></i>Xóa đơn hàng
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Toast thông báo -->
<script>
function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.textContent = message;
    toast.className = `fixed bottom-6 right-6 px-4 py-3 rounded-lg text-white shadow-lg z-50 ${type === "success" ? "bg-green-600" : "bg-red-600"}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add("opacity-0", "transition");
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}
</script>

<?php if (isset($_SESSION['toast_message'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    showToast("<?= addslashes($_SESSION['toast_message']) ?>");
});
</script>
<?php unset($_SESSION['toast_message']); endif; ?>

</body>
</html>
