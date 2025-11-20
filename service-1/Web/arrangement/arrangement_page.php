<?php
// Cấu hình timezone UTC+7 (Vietnam Time)
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();

// Kiểm tra đăng nhập - chỉ cho phép chuyên gia (experts)
$specialist_id = $_SESSION['user']['id'] ?? null;
$specialist_role = $_SESSION['user']['role'] ?? '';
$specialist_name = $_SESSION['user']['name'] ?? 'Chuyên gia';
$token = $_SESSION['token'] ?? '';

$expertRoles = ['Coordinator', 'Arrangement', 'Transcription', 'Recorder', 'Studio'];
if (!$specialist_id || !in_array($specialist_role, $expertRoles)) {
    header('location:../login.php?error=expert_only');
    exit();
}

// API base URL - Gọi qua Kong Gateway
$apiBase = "http://localhost:8000/api";
$specialistApiBase = "$apiBase/Specialist";

// Hàm gọi API
function callApi($url, $method = 'GET', $data = null, $token = '') {
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

// Xử lý cập nhật lịch
if (isset($_POST['update_schedule'])) {
    $date = $_POST['date'] ?? '';
    $timeSlot1 = isset($_POST['time_slot_1']) ? true : false;
    $timeSlot2 = isset($_POST['time_slot_2']) ? true : false;
    $timeSlot3 = isset($_POST['time_slot_3']) ? true : false;
    $timeSlot4 = isset($_POST['time_slot_4']) ? true : false;
    
    if (empty($date)) {
        $_SESSION['toast_message'] = "❌ Vui lòng chọn ngày!";
        header('location:arrangement_page.php');
        exit();
    }
    
    // Đảm bảo date được format đúng (yyyy-MM-dd) và parse đúng timezone
    // Nếu date đã là format yyyy-MM-dd thì giữ nguyên, nếu không thì parse lại
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        // Thử parse với format khác
        $dateObj = new DateTime($date);
    }
    $dateFormatted = $dateObj->format('Y-m-d');
    
    $requestData = [
        "specialistId" => intval($specialist_id),
        "date" => $dateFormatted, // Gửi date đã được format đúng
        "timeSlot1" => $timeSlot1,
        "timeSlot2" => $timeSlot2,
        "timeSlot3" => $timeSlot3,
        "timeSlot4" => $timeSlot4
    ];
    
    $url = "$specialistApiBase/schedule";
    $res = callApi($url, "POST", $requestData, $token);
    
    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã cập nhật lịch thành công cho ngày " . date('d/m/Y', strtotime($date)) . "!";
    } else {
        $errorMsg = $res['body']['message'] ?? $res['body']['title'] ?? 'Unknown error';
        $_SESSION['toast_message'] = "❌ Lỗi cập nhật lịch (HTTP {$res['code']}): " . $errorMsg;
        error_log("Schedule update error: " . json_encode($res));
    }
    
    header('location:arrangement_page.php');
    exit();
}

// Xử lý chấp nhận meeting
if (isset($_POST['accept_meeting'])) {
    $requestId = intval($_POST['request_id']);
    
    $res = callApi("$specialistApiBase/requests/$requestId/accept-meeting", "POST", null, $token);
    
    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã chấp nhận meeting thành công!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi: " . ($res['body']['message'] ?? 'Unknown error');
    }
    
    header('location:arrangement_page.php');
    exit();
}

// Xử lý từ chối meeting
if (isset($_POST['reject_meeting'])) {
    $requestId = intval($_POST['request_id']);
    $reason = $_POST['reject_reason'] ?? '';
    
    $res = callApi("$specialistApiBase/requests/$requestId/reject-meeting", "POST", [
        "reason" => $reason
    ], $token);
    
    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã từ chối meeting.";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi: " . ($res['body']['message'] ?? 'Unknown error');
    }
    
    header('location:arrangement_page.php');
    exit();
}

// Xử lý phản hồi yêu cầu (cho các request khác không phải PendingMeetingConfirmation)
if (isset($_POST['respond_request'])) {
    $requestId = intval($_POST['request_id']);
    $notes = $_POST['notes'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    
    $res = callApi("$specialistApiBase/requests/$requestId/respond", "PUT", [
        "notes" => $notes,
        "newStatus" => $newStatus
    ], $token);
    
    if ($res['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã phản hồi yêu cầu thành công!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi phản hồi: " . ($res['body']['message'] ?? 'Unknown error');
    }
    
    header('location:arrangement_page.php');
    exit();
}

// Lấy danh sách yêu cầu của chuyên gia
$requestsRes = callApi("$specialistApiBase/requests?specialistId=$specialist_id", "GET", null, $token);
$myRequests = $requestsRes['body'] ?? [];

// Lấy lịch của chuyên gia (tháng hiện tại)
$today = new DateTime();
$startDate = $today->format('Y-m-d');
$endDate = (clone $today)->modify('+1 month')->format('Y-m-d');

$scheduleRes = callApi("$specialistApiBase/schedule?specialistId=$specialist_id&startDate=$startDate&endDate=$endDate", "GET", null, $token);
$mySchedule = $scheduleRes['body'] ?? [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Chuyên Gia - MuTraPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-md fixed top-0 left-0 w-full z-40">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <i class="fas fa-user-tie text-purple-600 text-2xl"></i>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Trang Chuyên Gia</h1>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($specialist_name) ?> (<?= htmlspecialchars($specialist_role) ?>)</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="../login.php" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất
                </a>
            </div>
        </div>
    </header>

    <div class="min-h-screen pt-20">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <!-- Tabs -->
            <div class="bg-white rounded-xl shadow-sm p-2 mb-6 flex gap-2">
                <button onclick="showTab('requests')" id="tab-requests" 
                        class="flex-1 px-4 py-2 rounded-lg font-medium transition bg-purple-600 text-white">
                    <i class="fas fa-tasks mr-2"></i>Yêu cầu của tôi
                </button>
                <button onclick="showTab('schedule')" id="tab-schedule" 
                        class="flex-1 px-4 py-2 rounded-lg font-medium transition bg-gray-100 text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-calendar mr-2"></i>Lịch làm việc
                </button>
            </div>

            <!-- Tab: Yêu cầu -->
            <div id="content-requests" class="tab-content">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-tasks text-purple-600 mr-2"></i>Yêu cầu được gán cho tôi
                    </h2>
                    
                    <?php if (empty($myRequests)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                            <p class="text-gray-500">Chưa có yêu cầu nào được gán cho bạn.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        // Phân loại requests: PendingMeetingConfirmation và các request khác
                        $pendingMeetings = array_filter($myRequests, function($req) {
                            $status = strtolower($req['status'] ?? '');
                            return $status === 'pendingmeetingconfirmation' || $status === 'pending_meeting_confirmation';
                        });
                        $otherRequests = array_filter($myRequests, function($req) {
                            $status = strtolower($req['status'] ?? '');
                            return $status !== 'pendingmeetingconfirmation' && $status !== 'pending_meeting_confirmation';
                        });
                        ?>
                        
                        <!-- Pending Meeting Confirmation Requests -->
                        <?php if (!empty($pendingMeetings)): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                <i class="fas fa-clock text-orange-500 mr-2"></i>Chờ Xác Nhận Meeting
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($pendingMeetings as $req): ?>
                                <div class="border-2 border-orange-200 rounded-lg p-4 bg-orange-50">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="font-semibold text-lg text-gray-900"><?= htmlspecialchars($req['title'] ?? 'N/A') ?></h3>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-user mr-2"></i><?= htmlspecialchars($req['customerName'] ?? 'N/A') ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-tag mr-2"></i><?= htmlspecialchars($req['serviceType'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 bg-orange-200 text-orange-800 rounded-full text-xs font-medium">
                                            ⏰ Chờ Xác Nhận
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($req['description'])): ?>
                                    <p class="text-gray-700 mb-3"><?= htmlspecialchars($req['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($req['scheduledDate'])): ?>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                                        <p class="text-sm text-blue-700">
                                            <i class="fas fa-calendar-check mr-2"></i>
                                            <strong>Lịch hẹn:</strong> <?= date('d/m/Y', strtotime($req['scheduledDate'])) ?>
                                            <?php if (!empty($req['scheduledTimeSlot'])): ?>
                                            <span class="mx-2">•</span>
                                            <strong>Ca:</strong> <?= htmlspecialchars($req['scheduledTimeSlot']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Accept/Reject Buttons -->
                                    <div class="mt-4 space-y-3">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="submit" name="accept_meeting" 
                                                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition w-full">
                                                <i class="fas fa-check-circle mr-2"></i>✅ Chấp Nhận Meeting
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <div class="mb-2">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Lý do từ chối (nếu có):</label>
                                                <textarea name="reject_reason" rows="2" 
                                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                                          placeholder="Nhập lý do từ chối (tùy chọn)..."></textarea>
                                            </div>
                                            <button type="submit" name="reject_meeting" 
                                                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium transition w-full"
                                                    onclick="return confirm('Bạn có chắc muốn từ chối meeting này?');">
                                                <i class="fas fa-times-circle mr-2"></i>❌ Từ Chối Meeting
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Other Requests -->
                        <?php if (!empty($otherRequests)): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                <i class="fas fa-tasks text-purple-600 mr-2"></i>Các Yêu Cầu Khác
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($otherRequests as $req): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="font-semibold text-lg text-gray-900"><?= htmlspecialchars($req['title'] ?? 'N/A') ?></h3>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-user mr-2"></i><?= htmlspecialchars($req['customerName'] ?? 'N/A') ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-tag mr-2"></i><?= htmlspecialchars($req['serviceType'] ?? 'N/A') ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                            <?= htmlspecialchars($req['status'] ?? 'N/A') ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($req['description'])): ?>
                                    <p class="text-gray-700 mb-3"><?= htmlspecialchars($req['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($req['scheduledDate'])): ?>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                                        <p class="text-sm text-blue-700">
                                            <i class="fas fa-calendar-check mr-2"></i>
                                            <strong>Lịch hẹn:</strong> <?= date('d/m/Y', strtotime($req['scheduledDate'])) ?>
                                            <?php if (!empty($req['scheduledTimeSlot'])): ?>
                                            <span class="mx-2">•</span>
                                            <strong>Ca:</strong> <?= htmlspecialchars($req['scheduledTimeSlot']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <div class="mb-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú phản hồi:</label>
                                            <textarea name="notes" rows="3" 
                                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                                      placeholder="Nhập ghi chú phản hồi..."></textarea>
                                        </div>
                                        <div class="flex gap-2">
                                            <select name="new_status" class="px-4 py-2 border border-gray-300 rounded-lg">
                                                <option value="">Giữ nguyên trạng thái</option>
                                                <option value="InProgress">InProgress</option>
                                                <option value="Completed">Completed</option>
                                            </select>
                                            <button type="submit" name="respond_request" 
                                                    class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium transition">
                                                <i class="fas fa-paper-plane mr-2"></i>Phản hồi
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Lịch làm việc -->
            <div id="content-schedule" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-calendar text-purple-600 mr-2"></i>Quản lý lịch làm việc
                    </h2>
                    <p class="text-gray-600 mb-6">Chọn các ca bạn có thể làm việc. Mỗi ngày có 4 ca, mỗi ca kéo dài 4 tiếng.</p>
                    
                    <!-- Calendar Navigation -->
                    <div class="flex justify-between items-center mb-4">
                        <button onclick="changeMonth(-1)" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition">
                            <i class="fas fa-chevron-left mr-2"></i>Tháng trước
                        </button>
                        <h3 id="calendar-month-year" class="text-xl font-bold text-gray-900"></h3>
                        <button onclick="changeMonth(1)" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition">
                            Tháng sau<i class="fas fa-chevron-right ml-2"></i>
                        </button>
                    </div>
                    
                    <!-- Calendar -->
                    <div class="overflow-x-auto mb-6">
                        <div id="calendar-container" class="grid grid-cols-7 gap-2 min-w-full">
                            <!-- Calendar sẽ được tạo bằng JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Debug info (remove in production) -->
                    <?php if (!empty($scheduleRes['body'])): ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-4 text-xs text-gray-600">
                        <strong>Debug:</strong> Đã tải <?= count($mySchedule) ?> bản ghi lịch từ API
                    </div>
                    <?php elseif ($scheduleRes['code'] != 200): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-600">
                        <strong>Lỗi:</strong> Không thể tải lịch. HTTP <?= $scheduleRes['code'] ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Form cập nhật lịch cho ngày được chọn -->
                    <div id="schedule-form-container" class="hidden border-t pt-6 mt-6">
                        <h3 class="text-lg font-semibold mb-4" id="selected-date-title">Chọn ngày để cập nhật lịch</h3>
                        <form method="POST" id="schedule-form">
                            <input type="hidden" name="update_schedule" value="1">
                            <input type="hidden" name="date" id="schedule-date-input">
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <label class="flex items-center space-x-2 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="time_slot_1" value="1" class="schedule-checkbox">
                                    <div>
                                        <p class="font-medium">Ca 1</p>
                                        <p class="text-sm text-gray-600">0h - 4h</p>
                                    </div>
                                </label>
                                <label class="flex items-center space-x-2 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="time_slot_2" value="1" class="schedule-checkbox">
                                    <div>
                                        <p class="font-medium">Ca 2</p>
                                        <p class="text-sm text-gray-600">6h - 10h</p>
                                    </div>
                                </label>
                                <label class="flex items-center space-x-2 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="time_slot_3" value="1" class="schedule-checkbox">
                                    <div>
                                        <p class="font-medium">Ca 3</p>
                                        <p class="text-sm text-gray-600">12h - 16h</p>
                                    </div>
                                </label>
                                <label class="flex items-center space-x-2 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="time_slot_4" value="1" class="schedule-checkbox">
                                    <div>
                                        <p class="font-medium">Ca 4</p>
                                        <p class="text-sm text-gray-600">18h - 22h</p>
                                    </div>
                                </label>
                            </div>
                            
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium transition">
                                <i class="fas fa-save mr-2"></i>Cập nhật lịch
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const mySchedule = <?= json_encode($mySchedule) ?>;
        console.log('Loaded schedule:', mySchedule); // Debug
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        let selectedDate = null;

        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('button[id^="tab-"]').forEach(el => {
                el.classList.remove('bg-purple-600', 'text-white');
                el.classList.add('bg-gray-100', 'text-gray-700');
            });
            
            document.getElementById(`content-${tab}`).classList.remove('hidden');
            document.getElementById(`tab-${tab}`).classList.remove('bg-gray-100', 'text-gray-700');
            document.getElementById(`tab-${tab}`).classList.add('bg-purple-600', 'text-white');
            
            if (tab === 'schedule') {
                updateCalendarHeader();
                renderCalendar();
            }
        }
        
        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            updateCalendarHeader();
            renderCalendar();
        }
        
        function updateCalendarHeader() {
            const monthNames = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
                              'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
            const header = document.getElementById('calendar-month-year');
            if (header) {
                header.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            }
        }

        function renderCalendar() {
            const container = document.getElementById('calendar-container');
            if (!container) {
                console.error('Calendar container not found');
                return;
            }
            container.innerHTML = '';
            
            // Header
            const days = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
            days.forEach(day => {
                const header = document.createElement('div');
                header.className = 'text-center font-semibold text-gray-700 py-2 bg-gray-50 rounded-lg';
                header.textContent = day;
                container.appendChild(header);
            });
            
            updateCalendarHeader();
            
            // Days
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const today = new Date();
            
            // Empty cells for alignment
            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'border rounded-lg p-2 bg-gray-50';
                container.appendChild(emptyCell);
            }
            
            // Date cells
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(currentYear, currentMonth, day);
                // Format date theo local timezone (không dùng toISOString vì nó chuyển sang UTC)
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const dayStr = String(date.getDate()).padStart(2, '0');
                const dateStr = `${year}-${month}-${dayStr}`;
                
                // Format today theo local timezone
                const todayYear = today.getFullYear();
                const todayMonth = String(today.getMonth() + 1).padStart(2, '0');
                const todayDay = String(today.getDate()).padStart(2, '0');
                const todayStr = `${todayYear}-${todayMonth}-${todayDay}`;
                
                const isToday = dateStr === todayStr;
                const isPast = dateStr < todayStr;
                
                // Format date để match với API response (API trả về format yyyy-MM-dd)
                const schedule = mySchedule.find(s => {
                    const sDate = s.date;
                    if (typeof sDate === 'string') {
                        // API trả về format yyyy-MM-dd, so sánh trực tiếp
                        return sDate === dateStr || sDate.split('T')[0] === dateStr;
                    }
                    // Nếu là Date object từ API, format theo local timezone
                    if (sDate instanceof Date || (typeof sDate === 'object' && sDate.getFullYear)) {
                        const d = new Date(sDate);
                        const sYear = d.getFullYear();
                        const sMonth = String(d.getMonth() + 1).padStart(2, '0');
                        const sDay = String(d.getDate()).padStart(2, '0');
                        const sDateStr = `${sYear}-${sMonth}-${sDay}`;
                        return sDateStr === dateStr;
                    }
                    return false;
                });
                
                const bookedSlots = schedule && schedule.timeSlots ? [
                    schedule.timeSlots.slot1,
                    schedule.timeSlots.slot2,
                    schedule.timeSlots.slot3,
                    schedule.timeSlots.slot4
                ].filter(Boolean).length : 0;
                
                const cell = document.createElement('div');
                cell.className = `border rounded-lg p-2 min-h-[80px] transition ${isPast ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'cursor-pointer hover:bg-purple-50 bg-white'} ${isToday ? 'border-purple-500 bg-purple-50' : ''} ${selectedDate === dateStr ? 'bg-purple-200 border-purple-600 ring-2 ring-purple-300' : ''}`;
                cell.innerHTML = `
                    <div class="text-center font-medium ${isToday ? 'text-purple-600' : 'text-gray-900'} mb-1">${day}</div>
                    <div class="text-xs text-gray-600 text-center">${bookedSlots}/4 ca</div>
                `;
                
                if (!isPast) {
                    cell.style.cursor = 'pointer';
                    cell.addEventListener('click', function(e) {
                        e.preventDefault();
                        selectDate(dateStr, schedule);
                    });
                } else {
                    cell.style.cursor = 'not-allowed';
                }
                
                container.appendChild(cell);
            }
        }

        function selectDate(dateStr, schedule) {
            console.log('selectDate called:', dateStr, schedule); // Debug
            selectedDate = dateStr;
            const formContainer = document.getElementById('schedule-form-container');
            const dateInput = document.getElementById('schedule-date-input');
            const dateTitle = document.getElementById('selected-date-title');
            
            if (!formContainer || !dateInput || !dateTitle) {
                console.error('Form elements not found:', {formContainer, dateInput, dateTitle});
                return;
            }
            
            dateInput.value = dateStr;
            // Parse date string theo local timezone (không dùng T00:00:00 vì có thể bị lệch timezone)
            const [year, month, day] = dateStr.split('-').map(Number);
            const dateObj = new Date(year, month - 1, day);
            const dayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
            const dayName = dayNames[dateObj.getDay()];
            dateTitle.textContent = `Lịch ${dayName}, ${day}/${month}/${year}`;
            
            // Update checkboxes
            const checkboxes = document.querySelectorAll('.schedule-checkbox');
            console.log('Found checkboxes:', checkboxes.length); // Debug
            if (checkboxes.length === 4) {
                checkboxes.forEach((cb, index) => {
                    if (schedule && schedule.timeSlots) {
                        const slots = [schedule.timeSlots.slot1, schedule.timeSlots.slot2, schedule.timeSlots.slot3, schedule.timeSlots.slot4];
                        cb.checked = slots[index] || false;
                        console.log(`Slot ${index + 1}:`, slots[index]); // Debug
                    } else {
                        cb.checked = false;
                    }
                });
            }
            
            formContainer.classList.remove('hidden');
            formContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            renderCalendar();
        }

        // Toast notification
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

