<?php
// Cấu hình timezone UTC+7 (Vietnam Time)
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();

// Kiểm tra đăng nhập Admin
$admin_id = $_SESSION['user']['id'] ?? null;
if (!$admin_id) {
    header('location:../login.php');
    exit();
}

// API base URL - Gọi qua Kong Gateway
require_once __DIR__ . '/../config.php';
$apiBase = getApiBaseUrl('Admin');
$token = $_SESSION['token'] ?? '';

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
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);

    $result = [
        'code' => $httpCode,
        'body' => null,
        'error' => $curlError
    ];

    if ($curlError) {
        error_log("CURL Error in admin_booking.php: $curlError for URL: $url");
        error_log("CURL Info: " . json_encode($curlInfo));
    }
    
    // Kiểm tra nếu response là false hoặc empty
    if ($response === false || empty($response)) {
        if ($httpCode === 0) {
            // Connection failed
            error_log("Connection failed to URL: $url");
            $result['error'] = $curlError ?: "Connection failed to upstream server";
        }
    }

    if ($response !== false && !empty($response)) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['body'] = $decoded;
        } else {
            // Nếu không parse được JSON, log lỗi và trả về response raw
            error_log("JSON decode error in admin_booking.php: " . json_last_error_msg() . " for URL: $url");
            error_log("Response preview: " . substr($response, 0, 500));
            // Trả về empty array nếu không parse được để tránh lỗi
            $result['body'] = [];
        }
    } else if ($response === false && empty($curlError)) {
        // Response false nhưng không có curl error - có thể là timeout hoặc connection issue
        error_log("Empty response from URL: $url, HTTP Code: $httpCode");
        $result['body'] = [];
    }

    if ($httpCode >= 400 || $curlError) {
        $errorMsg = "API Error in admin_booking.php: HTTP $httpCode for URL: $url";
        if ($curlError) {
            $errorMsg .= ", CURL Error: $curlError";
        }
        if ($response) {
            $errorMsg .= ", Response: " . substr($response, 0, 500);
        }
        error_log($errorMsg);
    }

    return $result;
}

// Hàm normalize status để so sánh - xử lý cả PascalCase và camelCase
// Phải định nghĩa trước khi sử dụng
function normalizeStatus($status) {
    if (empty($status)) return '';
    
    $status = trim($status);
    
    // Nếu đã là format đúng (PascalCase), giữ nguyên
    // Kiểm tra các format chuẩn trước
    $validStatuses = [
        'Requested', 'Pending', 'Submitted', 'Assigned', 'InProgress',
        'PendingReview', 'PendingMeetingConfirmation', 'Completed',
        'RevisionRequested', 'RejectedByExpert', 'Cancelled'
    ];
    
    // Nếu status đã đúng format, trả về luôn
    if (in_array($status, $validStatuses)) {
        return $status;
    }
    
    // Map các biến thể về format chuẩn (case-insensitive)
    $statusMap = [
        // Requested
        'requested' => 'Requested',
        
        // Pending
        'pending' => 'Pending',
        
        // Submitted
        'submitted' => 'Submitted',
        
        // Assigned
        'assigned' => 'Assigned',
        
        // InProgress
        'inprogress' => 'InProgress',
        'in_progress' => 'InProgress',
        'inProgress' => 'InProgress',
        
        // PendingReview
        'pendingreview' => 'PendingReview',
        'pending_review' => 'PendingReview',
        'pendingReview' => 'PendingReview',
        
        // PendingMeetingConfirmation
        'pendingmeetingconfirmation' => 'PendingMeetingConfirmation',
        'pending_meeting_confirmation' => 'PendingMeetingConfirmation',
        'pendingMeetingConfirmation' => 'PendingMeetingConfirmation',
        
        // Completed
        'completed' => 'Completed',
        
        // RevisionRequested
        'revisionrequested' => 'RevisionRequested',
        'revision_requested' => 'RevisionRequested',
        'revisionRequested' => 'RevisionRequested',
        
        // RejectedByExpert
        'rejectedbyexpert' => 'RejectedByExpert',
        'rejected_by_expert' => 'RejectedByExpert',
        'rejectedByExpert' => 'RejectedByExpert',
        
        // Cancelled
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled'
    ];
    
    $lowerStatus = strtolower($status);
    
    // Tìm trong map
    if (isset($statusMap[$lowerStatus])) {
        return $statusMap[$lowerStatus];
    }
    
    // Nếu không tìm thấy, thử convert từ camelCase sang PascalCase
    // Ví dụ: "pendingReview" -> "PendingReview"
    if (preg_match('/^[a-z]+[A-Z]/', $status)) {
        return ucfirst($status);
    }
    
    // Mặc định: capitalize first letter
    return ucfirst($status);
}

// ✅ Xử lý chấp nhận request (Pending/Requested → PendingReview)
if (isset($_GET['accept_request'])) {
    $requestId = intval($_GET['accept_request']);
    $res = callApi("$apiBase/service-requests/$requestId/accept", "POST", null, $token);

    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã chấp nhận yêu cầu thành công!";
        // Redirect về tab PendingReview vì status đã chuyển từ Pending/Requested → PendingReview
        $newStatus = $res['body']['status'] ?? 'PendingReview';
        // Normalize status
        $newStatus = normalizeStatus($newStatus);
        header('location:admin_booking.php?tab=' . urlencode($newStatus));
    } else {
        $errorMsg = $res['body']['message'] ?? ($res['body']['error'] ?? 'Unknown error');
        $_SESSION['toast_message'] = "❌ Lỗi chấp nhận: " . $errorMsg;
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
    
    // Normalize status trước khi gửi lên API
    $normalizedStatus = normalizeStatus($status);
    
    $res = callApi("$apiBase/service-requests/$requestId/status", "PATCH", ["status" => $normalizedStatus], $token);

    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã cập nhật trạng thái thành công!";
        
        // Lấy status từ API response, nếu không có thì dùng status đã normalize
        $newStatus = $res['body']['status'] ?? $normalizedStatus;
        
        // Normalize status từ response để đảm bảo format đúng
        $newStatus = normalizeStatus($newStatus);
        
        // Đảm bảo tab tồn tại trong $requestsByStatus, nếu không thì dùng 'all'
        $validTabs = ['all', 'Requested', 'Pending', 'Submitted', 'Assigned', 'InProgress', 
                      'PendingReview', 'PendingMeetingConfirmation', 'Completed', 
                      'RevisionRequested', 'RejectedByExpert', 'Cancelled'];
        
        if (!in_array($newStatus, $validTabs)) {
            $newStatus = 'all';
        }
        
        header('location:admin_booking.php?tab=' . urlencode($newStatus));
    } else {
        $errorMsg = $res['body']['message'] ?? ($res['body']['error'] ?? 'Unknown error');
        $_SESSION['toast_message'] = "❌ Lỗi cập nhật trạng thái: " . $errorMsg;
        
        // Giữ tab hiện tại nếu có lỗi
        $currentTab = $_GET['tab'] ?? 'all';
        if ($currentTab !== 'all') {
            $currentTab = normalizeStatus($currentTab);
        }
        header('location:admin_booking.php?tab=' . urlencode($currentTab));
    }
    exit();
}

// ✅ Lấy danh sách service requests từ API
$res = callApi("$apiBase/service-requests", "GET", null, $token);
$allRequests = [];

// Kiểm tra response và parse đúng cách
if ($res['code'] == 200 && isset($res['body'])) {
    $body = $res['body'];
    
    // Nếu body là array, sử dụng trực tiếp
    if (is_array($body)) {
        $allRequests = $body;
    } 
    // Nếu body là JSON string, decode lại
    else if (is_string($body)) {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $allRequests = $decoded;
        } else {
            error_log("Admin Booking: Failed to decode JSON string. JSON Error: " . json_last_error_msg());
        }
    }
    // Nếu body là object (stdClass), convert sang array
    else if (is_object($body)) {
        $allRequests = json_decode(json_encode($body), true);
    }
    
    // Debug: Log số lượng requests và sample data (chỉ khi có vấn đề)
    if (count($allRequests) > 0) {
        $firstReq = $allRequests[0];
        // Log nếu thiếu dữ liệu quan trọng
        if (empty($firstReq['id']) && empty($firstReq['title'])) {
            error_log("Admin Booking Debug: First request structure: " . json_encode($firstReq ?? []));
        }
        // Log status để debug
        if (isset($firstReq['status'])) {
            error_log("Admin Booking Debug: Sample status from API: '" . $firstReq['status'] . "' (type: " . gettype($firstReq['status']) . ")");
            error_log("Admin Booking Debug: Normalized status: '" . normalizeStatus($firstReq['status']) . "'");
        }
    }
    
    // Đảm bảo mỗi request có đầy đủ các field cần thiết với giá trị mặc định
    $allRequests = array_map(function($req) {
        // Normalize field names (handle both camelCase and PascalCase)
        $normalized = [];
        foreach ($req as $key => $value) {
            // Convert PascalCase to camelCase
            $camelKey = lcfirst($key);
            $normalized[$camelKey] = $value;
        }
        
        // Đảm bảo các field quan trọng có giá trị
        return [
            'id' => $normalized['id'] ?? $req['Id'] ?? $req['ID'] ?? null,
            'customerId' => $normalized['customerId'] ?? $req['CustomerId'] ?? null,
            'customerName' => $normalized['customerName'] ?? $req['CustomerName'] ?? 'N/A',
            'customerEmail' => $normalized['customerEmail'] ?? $req['CustomerEmail'] ?? 'N/A',
            'serviceType' => $normalized['serviceType'] ?? $req['ServiceType'] ?? 'Transcription',
            'title' => $normalized['title'] ?? $req['Title'] ?? 'N/A',
            'description' => $normalized['description'] ?? $req['Description'] ?? null,
            'status' => $normalized['status'] ?? $req['Status'] ?? 'Submitted',
            'createdDate' => $normalized['createdDate'] ?? $req['CreatedDate'] ?? null,
            'dueDate' => $normalized['dueDate'] ?? $req['DueDate'] ?? null,
            'assignedSpecialistId' => $normalized['assignedSpecialistId'] ?? $req['AssignedSpecialistId'] ?? null,
            'assignedSpecialistName' => $normalized['assignedSpecialistName'] ?? $req['AssignedSpecialistName'] ?? null,
            'priority' => $normalized['priority'] ?? $req['Priority'] ?? 'normal',
            'paid' => $normalized['paid'] ?? $req['Paid'] ?? false
        ];
    }, $allRequests);
    
} else {
    // Log lỗi nếu có
    $errorMsg = "Lỗi khi lấy danh sách yêu cầu dịch vụ. ";
    $httpCode = $res['code'] ?? 0;
    
    // Xử lý các loại lỗi khác nhau
    if ($httpCode === 0 || !empty($res['error'])) {
        // Connection error hoặc CURL error
        $errorMsg .= "Không thể kết nối đến server. ";
        if (!empty($res['error'])) {
            $errorMsg .= "Chi tiết: " . $res['error'];
        } else {
            $errorMsg .= "Vui lòng kiểm tra kết nối mạng hoặc thử lại sau.";
        }
    } else if ($httpCode >= 500) {
        // Server error
        $errorMsg .= "Lỗi server (HTTP $httpCode). ";
        if (isset($res['body']['message'])) {
            $errorMsg .= $res['body']['message'];
        } else if (isset($res['body']['error'])) {
            $errorMsg .= $res['body']['error'];
        } else {
            $errorMsg .= "Vui lòng thử lại sau hoặc liên hệ quản trị viên.";
        }
    } else if ($httpCode >= 400) {
        // Client error
        $errorMsg .= "Lỗi yêu cầu (HTTP $httpCode). ";
        if (isset($res['body']['message'])) {
            $errorMsg .= $res['body']['message'];
        }
    } else {
        // Unknown error
        $errorMsg .= "HTTP Code: " . $httpCode;
        if (isset($res['body']['message'])) {
            $errorMsg .= " - " . $res['body']['message'];
        }
    }
    
    error_log("Admin Booking Error: " . $errorMsg);
    error_log("Full response: " . json_encode($res));
    
    // Hiển thị thông báo lỗi cho user
    if (!isset($_SESSION['toast_message'])) {
        $_SESSION['toast_message'] = "⚠️ " . $errorMsg;
    }
    
    // Set empty array để tránh lỗi khi render
    $allRequests = [];
}

// Phân loại requests theo trạng thái (case-insensitive)
$requestsByStatus = [
    'all' => $allRequests,
    'Requested' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Requested'),
    'Pending' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Pending'),
    'Submitted' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Submitted'),
    'Assigned' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Assigned'),
    'InProgress' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'InProgress'),
    'PendingReview' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'PendingReview'),
    'PendingMeetingConfirmation' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'PendingMeetingConfirmation'),
    'Completed' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Completed'),
    'RevisionRequested' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'RevisionRequested'),
    'RejectedByExpert' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'RejectedByExpert'),
    'Cancelled' => array_filter($allRequests, fn($r) => normalizeStatus($r['status'] ?? '') === 'Cancelled')
];

// Lấy tab hiện tại từ URL - mặc định là all
$currentTab = $_GET['tab'] ?? 'all';
// Normalize tab name để đảm bảo match với key trong $requestsByStatus (trừ 'all')
if ($currentTab !== 'all') {
    $currentTab = normalizeStatus($currentTab);
}
$requests = $requestsByStatus[$currentTab] ?? $allRequests;
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
            <a href="?tab=Requested" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Requested' ? 'bg-blue-500 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                <i class="fas fa-paper-plane mr-2"></i>Yêu cầu mới (<?= count($requestsByStatus['Requested']) ?>)
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
            <a href="?tab=PendingMeetingConfirmation" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'PendingMeetingConfirmation' ? 'bg-indigo-500 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' ?>">
                <i class="fas fa-calendar-check mr-2"></i>Chờ xác nhận (<?= count($requestsByStatus['PendingMeetingConfirmation']) ?>)
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
                        Không có yêu cầu nào ở trạng thái "<?= htmlspecialchars($currentTab, ENT_QUOTES, 'UTF-8') ?>".
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
                        'Requested' => 'bg-blue-100 text-blue-700',
                        'Pending' => 'bg-orange-100 text-orange-700',
                        'Submitted' => 'bg-blue-100 text-blue-700',
                        'Assigned' => 'bg-yellow-100 text-yellow-700',
                        'InProgress' => 'bg-purple-100 text-purple-700',
                        'PendingReview' => 'bg-orange-100 text-orange-700',
                        'PendingMeetingConfirmation' => 'bg-indigo-100 text-indigo-700',
                        'Completed' => 'bg-green-100 text-green-700',
                        'RevisionRequested' => 'bg-red-100 text-red-700',
                        'RejectedByExpert' => 'bg-red-100 text-red-700',
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
                            <h2 class="font-bold text-lg text-gray-900"><?= htmlspecialchars($req['title'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-sm text-gray-500 mt-1">ID: #<?= htmlspecialchars((string)($req['id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="flex flex-col gap-2 items-end">
                            <span class="text-xs px-3 py-1 rounded-full <?= $statusColor ?>">
                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="text-xs px-3 py-1 rounded-full <?= $priorityColor ?>">
                                <?= htmlspecialchars(ucfirst($priority), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2 mb-4">
                        <p class="text-gray-700">
                            <i class="fas fa-user text-primary mr-2"></i>
                            <strong>Khách hàng:</strong> <?= htmlspecialchars($req['customerName'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="text-gray-700">
                            <i class="fas fa-envelope text-primary mr-2"></i>
                            <strong>Email:</strong> <?= htmlspecialchars($req['customerEmail'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="text-gray-700">
                            <i class="fas fa-tag text-primary mr-2"></i>
                            <strong>Loại dịch vụ:</strong> <?= htmlspecialchars($serviceType, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php if (!empty($req['assignedSpecialistName'])): ?>
                        <p class="text-gray-700">
                            <i class="fas fa-user-tie text-primary mr-2"></i>
                            <strong>Chuyên gia:</strong> <?= htmlspecialchars($req['assignedSpecialistName'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($req['description'])): ?>
                        <p class="text-gray-700">
                            <i class="fas fa-file-alt text-primary mr-2"></i>
                            <strong>Mô tả:</strong> <?= htmlspecialchars(substr($req['description'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8') ?><?= strlen($req['description'] ?? '') > 100 ? '...' : '' ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-gray-700">
                            <i class="fas fa-calendar text-primary mr-2"></i>
                            <strong>Ngày tạo:</strong> 
                            <?php 
                                if (!empty($req['createdDate'])) {
                                    $createdDate = $req['createdDate'];
                                    // Xử lý nhiều format date
                                    if (is_string($createdDate)) {
                                        // Thử parse với DateTime
                                        $date = DateTime::createFromFormat('Y-m-d\TH:i:s', $createdDate);
                                        if (!$date) {
                                            $date = DateTime::createFromFormat('Y-m-d H:i:s', $createdDate);
                                        }
                                        if (!$date) {
                                            $date = new DateTime($createdDate);
                                        }
                                        if ($date) {
                                            echo $date->format('d/m/Y H:i');
                                        } else {
                                            echo htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8');
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </p>
                        <?php if (!empty($req['dueDate'])): ?>
                        <p class="text-gray-700">
                            <i class="fas fa-clock text-primary mr-2"></i>
                            <strong>Hạn chót:</strong> 
                            <?php 
                                $dueDate = $req['dueDate'];
                                if (is_string($dueDate)) {
                                    $date = DateTime::createFromFormat('Y-m-d\TH:i:s', $dueDate);
                                    if (!$date) {
                                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dueDate);
                                    }
                                    if (!$date) {
                                        $date = new DateTime($dueDate);
                                    }
                                    if ($date) {
                                        echo $date->format('d/m/Y H:i');
                                    } else {
                                        echo htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8');
                                    }
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-gray-700">
                            <i class="fas fa-money-bill text-primary mr-2"></i>
                            <strong>Đã thanh toán:</strong> 
                            <?php $paid = $req['paid'] ?? false; ?>
                            <span class="<?= $paid ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $paid ? 'Có' : 'Chưa' ?>
                            </span>
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="mt-5 flex flex-wrap gap-2">
                        <?php if ($status !== 'Completed' && $status !== 'Cancelled'): ?>
                        <select id="status_<?= htmlspecialchars((string)($req['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="flex-1 min-w-[150px] px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="Requested" <?= $status === 'Requested' ? 'selected' : '' ?>>Requested</option>
                            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Submitted" <?= $status === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="Assigned" <?= $status === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                            <option value="InProgress" <?= $status === 'InProgress' ? 'selected' : '' ?>>InProgress</option>
                            <option value="PendingReview" <?= $status === 'PendingReview' ? 'selected' : '' ?>>PendingReview</option>
                            <option value="PendingMeetingConfirmation" <?= $status === 'PendingMeetingConfirmation' ? 'selected' : '' ?>>PendingMeetingConfirmation</option>
                            <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="RevisionRequested" <?= $status === 'RevisionRequested' ? 'selected' : '' ?>>RevisionRequested</option>
                            <option value="RejectedByExpert" <?= $status === 'RejectedByExpert' ? 'selected' : '' ?>>RejectedByExpert</option>
                            <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button onclick="updateStatus(<?= (int)($req['id'] ?? 0) ?>)" 
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

