<?php
// Cấu hình timezone UTC+7 (Vietnam Time)
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();

// Nếu chưa đăng nhập Admin
$admin_id = $_SESSION['user']['id'] ?? null;
if (!$admin_id) {
    header('location:login.php');
    exit();
}

// API base URL - Gọi qua Kong Gateway
$apiBase = "http://localhost:8000/api/payments";

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
    $paymentId = $_GET['confirm'];
    $res = callApi("$apiBase/$paymentId/confirm", "POST", ["result" => "SUCCESS"]);

    if ($res['code'] == 200 || $res['code'] == 201) {
        $_SESSION['toast_message'] = "✅ Thanh toán #$paymentId đã được xác nhận!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi xác nhận thanh toán!";
    }

    header('location:admin_order.php');
    exit();
}

// ✅ Lấy danh sách thanh toán từ API
$res = callApi("$apiBase");
$payments = $res['body'] ?? [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thanh toán - MuTraPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .primary {
            color: #667eea;
        }
        .bg-primary {
            background-color: #667eea;
        }
    </style>
</head>
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<div class="min-h-screen pt-20">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-6 py-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="bg-primary bg-opacity-10 p-3 rounded-xl">
                    <i class="fas fa-credit-card text-primary text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Quản lý thanh toán</h1>
                    <p class="text-gray-600 mt-1">Xem và xác nhận các giao dịch thanh toán</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <?php
        $totalPayments = count($payments);
        $paidCount = count(array_filter($payments, function($p) { return isset($p['status']) && strtoupper($p['status']) === 'PAID'; }));
        $pendingCount = count(array_filter($payments, function($p) { return isset($p['status']) && strtoupper($p['status']) === 'PENDING'; }));
        $failedCount = count(array_filter($payments, function($p) { return isset($p['status']) && strtoupper($p['status']) === 'FAILED'; }));
        $totalAmount = array_sum(array_column($payments, 'amount'));
        ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                <div class="text-sm text-gray-600 mb-2">Tổng Thanh Toán</div>
                <div class="text-3xl font-bold text-gray-900"><?= $totalPayments ?></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                <div class="text-sm text-gray-600 mb-2">Đã Thanh Toán</div>
                <div class="text-3xl font-bold text-green-600"><?= $paidCount ?></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500">
                <div class="text-sm text-gray-600 mb-2">Chờ Xử Lý</div>
                <div class="text-3xl font-bold text-yellow-600"><?= $pendingCount ?></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                <div class="text-sm text-gray-600 mb-2">Tổng Tiền</div>
                <div class="text-2xl font-bold text-purple-600"><?= number_format($totalAmount, 0, ',', '.') ?> ₫</div>
            </div>
        </div>
    </div>

    <!-- Danh sách thanh toán -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã Đơn</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khách Hàng</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Số Tiền</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phương Thức</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng Thái</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày Tạo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                                    <div>Chưa có thanh toán nào.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): 
                                $status = isset($payment['status']) ? strtoupper($payment['status']) : 'UNKNOWN';
                                $statusClass = '';
                                $statusText = '';
                                $statusIcon = '';
                                
                                switch ($status) {
                                    case 'PAID':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusText = 'Đã Thanh Toán';
                                        $statusIcon = 'fa-check-circle';
                                        break;
                                    case 'PENDING':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        $statusText = 'Chờ Xử Lý';
                                        $statusIcon = 'fa-clock';
                                        break;
                                    case 'FAILED':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        $statusText = 'Thất Bại';
                                        $statusIcon = 'fa-times-circle';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        $statusText = 'Không Xác Định';
                                        $statusIcon = 'fa-question-circle';
                                }
                                
                                $method = isset($payment['method']) ? $payment['method'] : 'UNKNOWN';
                                $methodText = '';
                                switch (strtoupper($method)) {
                                    case 'BANK_TRANSFER':
                                        $methodText = '🏦 Chuyển Khoản';
                                        break;
                                    case 'CREDIT_CARD':
                                        $methodText = '💳 Thẻ Tín Dụng';
                                        break;
                                    case 'MOMO':
                                        $methodText = '💰 MoMo';
                                        break;
                                    case 'CASH':
                                        $methodText = '💵 Tiền Mặt';
                                        break;
                                    default:
                                        $methodText = $method;
                                }
                                
                                $createdAt = isset($payment['createdAt']) ? date('d/m/Y H:i', strtotime($payment['createdAt'])) : 'N/A';
                                $amount = isset($payment['amount']) ? floatval($payment['amount']) : 0;
                                $paymentId = isset($payment['id']) ? $payment['id'] : '';
                                $orderId = isset($payment['orderId']) ? $payment['orderId'] : '';
                                $customerId = isset($payment['customerId']) ? $payment['customerId'] : '';
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= substr($paymentId, 0, 8) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($orderId) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    KH-<?= htmlspecialchars($customerId) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    <?= number_format($amount, 0, ',', '.') ?> ₫
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($methodText) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                        <i class="fas <?= $statusIcon ?> mr-1"></i>
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $createdAt ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($status === 'PENDING'): ?>
                                        <a href="?confirm=<?= urlencode($paymentId) ?>"
                                           onclick="return confirm('Xác nhận thanh toán này đã hoàn tất?');"
                                           class="text-green-600 hover:text-green-900 mr-4">
                                            <i class="fas fa-check mr-1"></i>Xác nhận
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" onclick="viewPaymentDetails('<?= $paymentId ?>'); return false;"
                                       class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye mr-1"></i>Chi tiết
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div id="paymentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900">Chi tiết thanh toán</h2>
            <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div id="paymentModalBody" class="p-6">
            <!-- Payment details will be loaded here -->
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

async function viewPaymentDetails(paymentId) {
    try {
        const response = await fetch(`<?= $apiBase ?>/${paymentId}`);
        if (!response.ok) throw new Error('Không thể tải thông tin thanh toán');
        
        const payment = await response.json();
        
        const modalBody = document.getElementById('paymentModalBody');
        modalBody.innerHTML = `
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">ID Thanh Toán</label>
                    <div class="mt-1 text-lg font-semibold text-gray-900">#${payment.id}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Mã Đơn Hàng</label>
                    <div class="mt-1 text-gray-900">${payment.orderId || 'N/A'}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Khách Hàng</label>
                    <div class="mt-1 text-gray-900">${payment.customerId || 'N/A'}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Số Tiền</label>
                    <div class="mt-1 text-2xl font-bold text-primary">${new Intl.NumberFormat('vi-VN').format(payment.amount || 0)} ₫</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Phương Thức Thanh Toán</label>
                    <div class="mt-1 text-gray-900">${payment.method || 'N/A'}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Trạng Thái</label>
                    <div class="mt-1">
                        <span class="px-3 py-1 rounded-full text-sm font-medium ${payment.status === 'PAID' ? 'bg-green-100 text-green-800' : payment.status === 'PENDING' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                            ${payment.status || 'N/A'}
                        </span>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Ngày Tạo</label>
                    <div class="mt-1 text-gray-900">${payment.createdAt ? new Date(payment.createdAt).toLocaleString('vi-VN') : 'N/A'}</div>
                </div>
                ${payment.paidAt ? `
                <div>
                    <label class="text-sm font-medium text-gray-500">Ngày Thanh Toán</label>
                    <div class="mt-1 text-gray-900">${new Date(payment.paidAt).toLocaleString('vi-VN')}</div>
                </div>
                ` : ''}
            </div>
        `;
        
        document.getElementById('paymentModal').classList.remove('hidden');
    } catch (error) {
        alert('Lỗi: ' + error.message);
    }
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
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

