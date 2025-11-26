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
require_once __DIR__ . '/../config.php';
$kongBase = getKongBaseUrl();
$apiBase = "$kongBase/api/Admin/orders"; // Gọi Admin API để lấy cả orders từ service-1 và payments từ service-3
$paymentApiBase = "$kongBase/api/payments"; // Payment service từ service-3 (cho confirm payment)

// Hàm gọi API
function callApi($url, $method = 'GET', $data = null, $token = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = [
        'code' => $httpCode,
        'body' => null,
        'error' => $curlError
    ];

    if ($curlError) {
        error_log("CURL Error in admin_order.php: $curlError for URL: $url");
    }

    if ($response !== false) {
        $decoded = json_decode($response, true);
        $result['body'] = ($decoded !== null) ? $decoded : $response;
    }

    if ($httpCode >= 400) {
        error_log("API Error in admin_order.php: HTTP $httpCode for URL: $url, Response: " . ($response ?: 'No response'));
    }

    return $result;
}

// ✅ Xử lý xác nhận thanh toán
if (isset($_GET['confirm'])) {
    $paymentId = $_GET['confirm'];
    // Gọi trực tiếp payment-service để confirm
    $res = callApi("$paymentApiBase/$paymentId/confirm", "POST", ["result" => "SUCCESS"], '');

    if ($res['code'] == 200 || $res['code'] == 201) {
        $_SESSION['toast_message'] = "✅ Thanh toán #$paymentId đã được xác nhận!";
    } else {
        $errorMsg = $res['body']['message'] ?? ($res['body']['error'] ?? 'Unknown error');
        $_SESSION['toast_message'] = "❌ Lỗi xác nhận thanh toán: " . htmlspecialchars($errorMsg);
    }

    // Redirect với refresh và debug
    header('location:admin_order.php?refresh=true&debug=1');
    exit();
}

// ✅ Lấy danh sách orders (merge từ service-1 và service-3) từ Admin API
$refresh = isset($_GET['refresh']) ? '?refresh=true' : '';
$res = callApi("$apiBase$refresh", "GET", null, '');
$payments = [];
$apiError = null;

// Debug mode - tự động bật khi có refresh=true
$debug = (isset($_GET['refresh']) || isset($_GET['debug'])) ? true : false;

if ($debug) {
    error_log("API Response Code: " . ($res['code'] ?? 'N/A'));
    error_log("API Response Body: " . json_encode($res['body'] ?? 'No body'));
    error_log("API Error: " . ($res['error'] ?? 'No error'));
}

if ($res['code'] == 200 && isset($res['body'])) {
    // Xử lý response - có thể là array hoặc string
    if (is_array($res['body'])) {
        $payments = $res['body'];
        if ($debug) {
            error_log("Payments count (array): " . count($payments));
        }
    } else if (is_string($res['body'])) {
        // Nếu là string, thử decode JSON
        $decoded = json_decode($res['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payments = $decoded;
            if ($debug) {
                error_log("Decoded payments count: " . count($payments));
            }
        } else {
            // Nếu không phải JSON, thử parse lại
            $decoded = json_decode($res['body'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payments = $decoded;
            } else {
                if ($debug) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    error_log("Response body type: " . gettype($res['body']));
                    error_log("Response body length: " . strlen($res['body']));
                    error_log("Response body preview: " . substr($res['body'], 0, 200));
                }
            }
        }
    } else {
        // Nếu không phải array hay string, log để debug
        if ($debug) {
            error_log("Unexpected response body type: " . gettype($res['body']));
        }
    }
    
    // Đảm bảo $payments là array
    if (!is_array($payments)) {
        $payments = [];
        if ($debug) {
            error_log("Warning: payments is not an array, resetting to empty array");
        }
    }
} else {
    $apiError = "API Error: HTTP " . ($res['code'] ?? 'N/A');
    if (isset($res['body']['message'])) {
        $apiError .= " - " . $res['body']['message'];
    } elseif (isset($res['error'])) {
        $apiError .= " - cURL Error: " . $res['error'];
    }
    if ($debug) {
        error_log("API Error: " . $apiError);
    }
}
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
            <div class="flex gap-2">
                <a href="?refresh=true&debug=1" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    <i class="fas fa-sync-alt mr-2"></i>Làm mới
                </a>
            </div>
        </div>
    </div>

    <!-- Error message nếu có -->
    <?php if ($apiError): ?>
    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <div>
                    <strong>Lỗi khi tải thanh toán:</strong> <?= htmlspecialchars($apiError) ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- Stats Cards -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <?php
        $totalPayments = count($payments);
        // Xử lý status từ cả service-1 (PaymentStatus) và service-3 (status)
        // Hỗ trợ cả PascalCase và camelCase
        $paidCount = count(array_filter($payments, function($p) { 
            $status = isset($p['status']) ? strtoupper($p['status']) : 
                     (isset($p['Status']) ? strtoupper($p['Status']) : 
                     (isset($p['PaymentStatus']) ? strtoupper($p['PaymentStatus']) : 
                     (isset($p['paymentStatus']) ? strtoupper($p['paymentStatus']) : '')));
            return $status === 'PAID' || $status === 'COMPLETED';
        }));
        $pendingCount = count(array_filter($payments, function($p) { 
            $status = isset($p['status']) ? strtoupper($p['status']) : 
                     (isset($p['Status']) ? strtoupper($p['Status']) : 
                     (isset($p['PaymentStatus']) ? strtoupper($p['PaymentStatus']) : 
                     (isset($p['paymentStatus']) ? strtoupper($p['paymentStatus']) : '')));
            return $status === 'PENDING';
        }));
        $failedCount = count(array_filter($payments, function($p) { 
            $status = isset($p['status']) ? strtoupper($p['status']) : 
                     (isset($p['Status']) ? strtoupper($p['Status']) : 
                     (isset($p['PaymentStatus']) ? strtoupper($p['PaymentStatus']) : 
                     (isset($p['paymentStatus']) ? strtoupper($p['paymentStatus']) : '')));
            return $status === 'FAILED';
        }));
        // Tính tổng tiền từ cả amount và TotalPrice
        // Hỗ trợ cả PascalCase và camelCase, và xử lý cả string và number
        $totalAmount = array_sum(array_map(function($p) {
            // Thử lấy từ amount (camelCase)
            if (isset($p['amount'])) {
                $amt = is_string($p['amount']) ? floatval($p['amount']) : floatval($p['amount']);
                if ($amt > 0) return $amt;
            }
            // Thử lấy từ Amount (PascalCase)
            if (isset($p['Amount'])) {
                $amt = is_string($p['Amount']) ? floatval($p['Amount']) : floatval($p['Amount']);
                if ($amt > 0) return $amt;
            }
            // Thử lấy từ TotalPrice (PascalCase)
            if (isset($p['TotalPrice'])) {
                $amt = is_string($p['TotalPrice']) ? floatval($p['TotalPrice']) : floatval($p['TotalPrice']);
                if ($amt > 0) return $amt;
            }
            // Thử lấy từ totalPrice (camelCase)
            if (isset($p['totalPrice'])) {
                $amt = is_string($p['totalPrice']) ? floatval($p['totalPrice']) : floatval($p['totalPrice']);
                if ($amt > 0) return $amt;
            }
            return 0;
        }, $payments));
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
                                // Xử lý cả orders từ service-1 và payments từ service-3
                                // Kiểm tra xem đây là order từ service-1 hay payment từ service-3
                                // Hỗ trợ cả PascalCase và camelCase
                                $isPayment = (isset($payment['Source']) && $payment['Source'] === 'service-3') || 
                                            (isset($payment['source']) && $payment['source'] === 'service-3');
                                
                                // Lấy paymentId - hỗ trợ cả Id và id
                                $paymentId = isset($payment['Id']) ? $payment['Id'] : (isset($payment['id']) ? $payment['id'] : '');
                                
                                // Lấy orderId - hỗ trợ cả Number, orderId, và number
                                $orderId = isset($payment['Number']) ? $payment['Number'] : 
                                          (isset($payment['orderId']) ? $payment['orderId'] : 
                                          (isset($payment['number']) ? $payment['number'] : ''));
                                
                                // Lấy customerId - hỗ trợ cả UserId, customerId, và userId
                                $customerId = isset($payment['UserId']) ? $payment['UserId'] : 
                                             (isset($payment['customerId']) ? $payment['customerId'] : 
                                             (isset($payment['userId']) ? $payment['userId'] : ''));
                                
                                // Lấy amount - hỗ trợ cả TotalPrice, amount, và totalPrice
                                // Xử lý cả string và number, đảm bảo không bị nhân 100
                                $amount = 0;
                                if (isset($payment['TotalPrice'])) {
                                    $amount = is_string($payment['TotalPrice']) ? floatval($payment['TotalPrice']) : floatval($payment['TotalPrice']);
                                } elseif (isset($payment['amount'])) {
                                    $amount = is_string($payment['amount']) ? floatval($payment['amount']) : floatval($payment['amount']);
                                } elseif (isset($payment['Amount'])) {
                                    $amount = is_string($payment['Amount']) ? floatval($payment['Amount']) : floatval($payment['Amount']);
                                } elseif (isset($payment['totalPrice'])) {
                                    $amount = is_string($payment['totalPrice']) ? floatval($payment['totalPrice']) : floatval($payment['totalPrice']);
                                }
                                
                                // Lấy method - hỗ trợ cả Method và method
                                $method = isset($payment['Method']) ? $payment['Method'] : 
                                         (isset($payment['method']) ? $payment['method'] : 'UNKNOWN');
                                
                                // Lấy status - hỗ trợ cả PaymentStatus, status, và paymentStatus
                                $status = isset($payment['PaymentStatus']) ? strtoupper($payment['PaymentStatus']) : 
                                         (isset($payment['status']) ? strtoupper($payment['status']) : 
                                         (isset($payment['paymentStatus']) ? strtoupper($payment['paymentStatus']) : 'UNKNOWN'));
                                
                                // Lấy createdAt - hỗ trợ cả PlacedOn, createdAt, và placedOn
                                $createdAt = isset($payment['PlacedOn']) ? $payment['PlacedOn'] : 
                                           (isset($payment['createdAt']) ? $payment['createdAt'] : 
                                           (isset($payment['placedOn']) ? $payment['placedOn'] : null)); 
                                // Status handling
                                $statusClass = '';
                                $statusText = '';
                                $statusIcon = '';
                                
                                switch ($status) {
                                    case 'PAID':
                                    case 'COMPLETED':
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
                                
                                // Method handling
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
                                
                                // Format created date
                                if ($createdAt) {
                                    if (is_string($createdAt)) {
                                        $createdAtFormatted = date('d/m/Y H:i', strtotime($createdAt));
                                    } else {
                                        $createdAtFormatted = 'N/A';
                                    }
                                } else {
                                    $createdAtFormatted = 'N/A';
                                }
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars(substr($paymentId, 0, 8)) ?>
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
                                    <?= $createdAtFormatted ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($status === 'PENDING' && $isPayment): ?>
                                        <a href="?confirm=<?= urlencode($paymentId) ?>"
                                           onclick="return confirm('Xác nhận thanh toán này đã hoàn tất?');"
                                           class="text-green-600 hover:text-green-900 mr-4">
                                            <i class="fas fa-check mr-1"></i>Xác nhận
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($isPayment): ?>
                                    <a href="#" onclick="viewPaymentDetails('<?= htmlspecialchars($paymentId, ENT_QUOTES) ?>'); return false;"
                                       class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye mr-1"></i>Chi tiết
                                    </a>
                                    <?php endif; ?>
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
        const response = await fetch(`<?= $paymentApiBase ?>/${paymentId}`);
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

