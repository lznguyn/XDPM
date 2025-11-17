<?php
session_start();

// Kiểm tra đăng nhập Admin
$admin_id = $_SESSION['user']['id'] ?? null;
if (!$admin_id) {
    header('location:../login.php');
    exit();
}

// API base URL - Sử dụng Kong Gateway
$apiBase = "http://localhost:8000/api/Admin";
$token = $_SESSION['token'] ?? '';

// ✅ Hàm gọi API với JWT token
function callApi($url, $method = 'GET', $data = null, $token = '')
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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

// ✅ Xử lý chấp nhận request (Pending → InProgress)
if (isset($_GET['accept_request'])) {
    $requestId = intval($_GET['accept_request']);
    $res = callApi("$apiBase/service-requests/$requestId/accept", "POST", null, $token);

    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã chấp nhận yêu cầu thành công!";
        // Redirect về tab InProgress vì status đã chuyển từ Pending → InProgress
        $newStatus = $res['body']['status'] ?? 'InProgress';
        // Đảm bảo status đúng format
        $newStatus = ucfirst($newStatus);
        if ($newStatus === 'Inprogress') {
            $newStatus = 'InProgress';
        }
        header('location:admin_booking.php?tab=' . urlencode($newStatus));
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi chấp nhận: " . ($res['body']['message'] ?? 'Unknown error');
        // Giữ tab hiện tại nếu có lỗi
        $currentTab = $_GET['tab'] ?? 'Pending';
        header('location:admin_booking.php?tab=' . urlencode($currentTab));
    }
    exit();
}

// ✅ Xử lý cập nhật trạng thái
if (isset($_GET['update_status'])) {
    $requestId = intval($_GET['update_status']);
    $status = $_GET['status'] ?? 'Submitted';
    $res = callApi("$apiBase/service-requests/$requestId/status", "PATCH", ["status" => $status], $token);

    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã cập nhật trạng thái thành công!";
        // Redirect về tab tương ứng với status mới
        // Lấy status từ API response, nếu không có thì dùng status từ request
        $newStatus = $res['body']['status'] ?? $status;
        // Đảm bảo status đúng format (Capitalize first letter)
        $newStatus = ucfirst($newStatus);
        // Map các status có thể có (case-insensitive)
        $statusMap = [
            'Pending' => 'Pending',
            'pending' => 'Pending',
            'Submitted' => 'Submitted',
            'submitted' => 'Submitted',
            'Assigned' => 'Assigned',
            'assigned' => 'Assigned',
            'Inprogress' => 'InProgress',
            'InProgress' => 'InProgress',
            'inprogress' => 'InProgress',
            'Pendingreview' => 'PendingReview',
            'PendingReview' => 'PendingReview',
            'pendingreview' => 'PendingReview',
            'Completed' => 'Completed',
            'completed' => 'Completed',
            'Revisionrequested' => 'RevisionRequested',
            'RevisionRequested' => 'RevisionRequested',
            'revisionrequested' => 'RevisionRequested',
            'Cancelled' => 'Cancelled',
            'cancelled' => 'Cancelled'
        ];
        $newStatus = $statusMap[$newStatus] ?? $newStatus;
        header('location:admin_booking.php?tab=' . urlencode($newStatus));
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi cập nhật trạng thái: " . ($res['body']['message'] ?? 'Unknown error');
        // Giữ tab hiện tại nếu có lỗi
        $currentTab = $_GET['tab'] ?? 'all';
        header('location:admin_booking.php?tab=' . urlencode($currentTab));
    }
    exit();
}

// ✅ Lấy danh sách service requests từ API
$res = callApi("$apiBase/service-requests", "GET", null, $token);
$allRequests = $res['body'] ?? [];

// Debug: Log số lượng requests và status (có thể xóa sau)
if (empty($allRequests)) {
    error_log("Admin Booking: No requests found. API response code: " . ($res['code'] ?? 'N/A'));
} else {
    error_log("Admin Booking: Loaded " . count($allRequests) . " requests");
    // Log status distribution
    $statusCounts = [];
    foreach ($allRequests as $req) {
        $s = normalizeStatus($req['status'] ?? '');
        $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
    }
    error_log("Status distribution: " . json_encode($statusCounts));
}

// Hàm normalize status để so sánh
function normalizeStatus($status) {
    if (empty($status)) return '';
    // Chuyển về dạng chuẩn (Capitalize first letter, handle camelCase)
    $status = trim($status);
    $statusMap = [
        'pending' => 'Pending',
        'submitted' => 'Submitted',
        'assigned' => 'Assigned',
        'inprogress' => 'InProgress',
        'in_progress' => 'InProgress',
        'pendingreview' => 'PendingReview',
        'pending_review' => 'PendingReview',
        'completed' => 'Completed',
        'revisionrequested' => 'RevisionRequested',
        'revision_requested' => 'RevisionRequested',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled'
    ];
    $lowerStatus = strtolower($status);
    return $statusMap[$lowerStatus] ?? ucfirst($status);
}

// Phân loại requests theo trạng thái (case-insensitive)
$requestsByStatus = [
    'all' => $allRequests,
    'Pending' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Pending'),
    'Submitted' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Submitted'),
    'Assigned' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Assigned'),
    'InProgress' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'InProgress'),
    'PendingReview' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'PendingReview'),
    'Completed' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Completed'),
    'RevisionRequested' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'RevisionRequested'),
    'Cancelled' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Cancelled')
];

// Lấy tab hiện tại từ URL - mặc định là Pending
$currentTab = $_GET['tab'] ?? 'Pending';
$requests = $requestsByStatus[$currentTab] ?? $allRequests;

// Debug: Log current tab and request count
error_log("Admin Booking: Current tab = '$currentTab', Requests count = " . count($requests));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đặt lịch - MuTraPro Admin</title>
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
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<div class="min-h-screen pt-20">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-6 py-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="bg-primary bg-opacity-10 p-3 rounded-xl">
                    <i class="fas fa-calendar-check text-primary text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Quản lý đặt lịch</h1>
                    <p class="text-gray-600 mt-1">Xem và quản lý các yêu cầu dịch vụ của khách hàng</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="max-w-7xl mx-auto px-4 pt-6">
        <div class="bg-white rounded-xl shadow-sm p-2 flex flex-wrap gap-2 overflow-x-auto">
            <a href="?tab=all" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="fas fa-list mr-2"></i>Tất cả (<?= count($requestsByStatus['all']) ?>)
            </a>
            <a href="?tab=Pending" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Pending' ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-700 hover:bg-orange-100' ?>">
                <i class="fas fa-clock mr-2"></i>Chờ duyệt (<?= count($requestsByStatus['Pending']) ?>)
            </a>
            <a href="?tab=Submitted" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Submitted' ? 'bg-blue-500 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                <i class="fas fa-paper-plane mr-2"></i>Mới gửi (<?= count($requestsByStatus['Submitted']) ?>)
            </a>
            <a href="?tab=Assigned" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Assigned' ? 'bg-yellow-500 text-white' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' ?>">
                <i class="fas fa-user-check mr-2"></i>Đã gán (<?= count($requestsByStatus['Assigned']) ?>)
            </a>
            <a href="?tab=InProgress" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'InProgress' ? 'bg-purple-500 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100' ?>">
                <i class="fas fa-spinner mr-2"></i>Đang xử lý (<?= count($requestsByStatus['InProgress']) ?>)
            </a>
            <a href="?tab=PendingReview" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'PendingReview' ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-700 hover:bg-orange-100' ?>">
                <i class="fas fa-eye mr-2"></i>Chờ xem xét (<?= count($requestsByStatus['PendingReview']) ?>)
            </a>
            <a href="?tab=Completed" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Completed' ? 'bg-green-500 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                <i class="fas fa-check-circle mr-2"></i>Hoàn thành (<?= count($requestsByStatus['Completed']) ?>)
            </a>
            <a href="?tab=RevisionRequested" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'RevisionRequested' ? 'bg-red-500 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100' ?>">
                <i class="fas fa-edit mr-2"></i>Yêu cầu chỉnh sửa (<?= count($requestsByStatus['RevisionRequested']) ?>)
            </a>
            <a href="?tab=Cancelled" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Cancelled' ? 'bg-gray-500 text-white' : 'bg-gray-50 text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-times-circle mr-2"></i>Đã hủy (<?= count($requestsByStatus['Cancelled']) ?>)
            </a>
        </div>
    </div>

    <!-- Danh sách yêu cầu -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if (empty($requests)): ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">
                    <?php if ($currentTab === 'all'): ?>
                        Chưa có yêu cầu dịch vụ nào.
                    <?php else: ?>
                        Không có yêu cầu nào ở trạng thái "<?= htmlspecialchars($currentTab) ?>".
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($requests as $req): 
                    $status = normalizeStatus($req['status'] ?? 'Submitted');
                    $serviceType = $req['serviceType'] ?? 'Transcription';
                    $priority = $req['priority'] ?? 'normal';
                    
                    // Màu sắc theo trạng thái
                    $statusColors = [
                        'Submitted' => 'bg-blue-100 text-blue-700',
                        'Assigned' => 'bg-yellow-100 text-yellow-700',
                        'InProgress' => 'bg-purple-100 text-purple-700',
                        'PendingReview' => 'bg-orange-100 text-orange-700',
                        'Completed' => 'bg-green-100 text-green-700',
                        'RevisionRequested' => 'bg-red-100 text-red-700',
                        'Cancelled' => 'bg-gray-100 text-gray-700'
                    ];
                    $statusColor = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
                    
                    // Màu sắc theo độ ưu tiên
                    $priorityColors = [
                        'normal' => 'bg-gray-100 text-gray-700',
                        'high' => 'bg-yellow-100 text-yellow-700',
                        'urgent' => 'bg-red-100 text-red-700'
                    ];
                    $priorityColor = $priorityColors[$priority] ?? 'bg-gray-100 text-gray-700';
                ?>
                <div class="bg-white border-2 rounded-xl shadow-sm p-6 hover:shadow-lg transition">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($req['title'] ?? 'N/A') ?></h2>
                            <p class="text-sm text-gray-500 mt-1">ID: #<?= htmlspecialchars($req['id']) ?></p>
                        </div>
                        <div class="flex flex-col gap-2 items-end">
                            <span class="text-xs px-3 py-1 rounded-full <?= $statusColor ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                            <span class="text-xs px-3 py-1 rounded-full <?= $priorityColor ?>">
                                <?= htmlspecialchars(ucfirst($priority)) ?>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2 mb-4">
                        <p class="text-gray-700">
                            <i class="fas fa-user text-primary mr-2"></i>
                            <strong>Khách hàng:</strong> <?= htmlspecialchars($req['customerName'] ?? 'N/A') ?>
                        </p>
                        <p class="text-gray-700">
                            <i class="fas fa-envelope text-primary mr-2"></i>
                            <strong>Email:</strong> <?= htmlspecialchars($req['customerEmail'] ?? 'N/A') ?>
                        </p>
                        <p class="text-gray-700">
                            <i class="fas fa-tag text-primary mr-2"></i>
                            <strong>Loại dịch vụ:</strong> <?= htmlspecialchars($serviceType) ?>
                        </p>
                        <?php if (!empty($req['assignedSpecialistName'])): ?>
                        <p class="text-gray-700">
                            <i class="fas fa-user-tie text-primary mr-2"></i>
                            <strong>Chuyên gia:</strong> <?= htmlspecialchars($req['assignedSpecialistName']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($req['description'])): ?>
                        <p class="text-gray-700">
                            <i class="fas fa-file-alt text-primary mr-2"></i>
                            <strong>Mô tả:</strong> <?= htmlspecialchars(substr($req['description'], 0, 100)) ?><?= strlen($req['description']) > 100 ? '...' : '' ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-gray-700">
                            <i class="fas fa-calendar text-primary mr-2"></i>
                            <strong>Ngày tạo:</strong> <?= !empty($req['createdDate']) ? date('d/m/Y H:i', strtotime($req['createdDate'])) : 'N/A' ?>
                        </p>
                        <?php if (!empty($req['dueDate'])): ?>
                        <p class="text-gray-700">
                            <i class="fas fa-clock text-primary mr-2"></i>
                            <strong>Hạn chót:</strong> <?= date('d/m/Y H:i', strtotime($req['dueDate'])) ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-gray-700">
                            <i class="fas fa-money-bill text-primary mr-2"></i>
                            <strong>Đã thanh toán:</strong> 
                            <span class="<?= $req['paid'] ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $req['paid'] ? 'Có' : 'Chưa' ?>
                            </span>
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="mt-5 flex flex-wrap gap-2">
                        <?php if ($status !== 'Completed' && $status !== 'Cancelled'): ?>
                        <select id="status_<?= $req['id'] ?>" class="flex-1 min-w-[150px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="Submitted" <?= $status === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="Assigned" <?= $status === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                            <option value="InProgress" <?= $status === 'InProgress' ? 'selected' : '' ?>>InProgress</option>
                            <option value="PendingReview" <?= $status === 'PendingReview' ? 'selected' : '' ?>>PendingReview</option>
                            <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="RevisionRequested" <?= $status === 'RevisionRequested' ? 'selected' : '' ?>>RevisionRequested</option>
                            <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button onclick="updateStatus(<?= $req['id'] ?>)" 
                                class="bg-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition">
                            <i class="fas fa-save mr-2"></i>Cập nhật
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

function acceptRequest(requestId) {
    if (!confirm('Bạn có chắc muốn chấp nhận yêu cầu này? Yêu cầu sẽ chuyển sang trạng thái "Đang xử lý".')) {
        return;
    }
    
    window.location.href = `?accept_request=${requestId}`;
}

function updateStatus(requestId) {
    const statusSelect = document.getElementById('status_' + requestId);
    const status = statusSelect.value;
    
    if (!confirm(`Bạn có chắc muốn cập nhật trạng thái thành "${status}"?`)) {
        return;
    }
    
    // Lấy tab hiện tại từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'all';
    
    window.location.href = `?update_status=${requestId}&status=${status}&tab=${currentTab}`;
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

