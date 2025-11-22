// Configuration - Use Kong Gateway
const API_BASE = 'http://localhost:8000';

// ============================================================================
// Timezone Helper Functions (UTC+7 - Vietnam Time)
// ============================================================================
function formatVietnamDate(dateInput, format = 'date') {
    if (!dateInput) return 'N/A';
    
    let date;
    if (dateInput instanceof Date) {
        date = dateInput;
    } else {
        date = new Date(dateInput);
    }
    
    const utcTime = date.getTime() + (date.getTimezoneOffset() * 60000);
    const vietnamTime = new Date(utcTime + (7 * 3600000));
    
    const options = {
        timeZone: 'Asia/Ho_Chi_Minh',
        ...(format === 'date' ? {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        } : format === 'datetime' ? {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        } : {
            hour: '2-digit',
            minute: '2-digit'
        })
    };
    
    try {
        return vietnamTime.toLocaleString('vi-VN', options);
    } catch (e) {
        const year = vietnamTime.getFullYear();
        const month = String(vietnamTime.getMonth() + 1).padStart(2, '0');
        const day = String(vietnamTime.getDate()).padStart(2, '0');
        
        if (format === 'date') {
            return `${day}/${month}/${year}`;
        } else if (format === 'datetime') {
            const hours = String(vietnamTime.getHours()).padStart(2, '0');
            const minutes = String(vietnamTime.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        }
        return `${day}/${month}/${year}`;
    }
}

function getVietnamNow() {
    const now = new Date();
    const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);
    return new Date(utcTime + (7 * 3600000));
}

// Cache để lưu giá dịch vụ
let servicePricesCache = {
    'Transcription': 50000,
    'Arrangement': 50000
};

// ==================== UTILITY: Load Service Prices ====================
async function loadServicePrices() {
    try {
        const response = await fetch(`${API_BASE}/api/Admin/service-prices`);
        if (response.ok) {
            const prices = await response.json();
            if (Array.isArray(prices)) {
                prices.forEach(price => {
                    const serviceType = price.serviceType || price.ServiceType;
                    if (serviceType && (serviceType === 'Transcription' || serviceType === 'Arrangement')) {
                        servicePricesCache[serviceType] = price.price || price.Price || 50000;
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error loading service prices:', error);
    }
}

loadServicePrices();
setInterval(loadServicePrices, 5 * 60 * 1000);

// ============================================================================
// NOTIFICATIONS SYSTEM
// ============================================================================
let notifications = [];
let notificationPollInterval = null;

async function loadNotifications() {
    if (!currentCustomerId) {
        console.warn('Cannot load notifications: currentCustomerId is not set');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/Notification/customer/${currentCustomerId}`);
        if (response.ok) {
            notifications = await response.json();
            renderNotifications();
            updateNotificationBadge();
        } else {
            console.error('Failed to load notifications:', response.status, response.statusText);
            const errorText = await response.text();
            console.error('Error response:', errorText);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

async function getUnreadCount() {
    if (!currentCustomerId) {
        return 0;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/Notification/customer/${currentCustomerId}/unread-count`);
        if (response.ok) {
            const data = await response.json();
            return data.count || 0;
        } else {
            console.error('Failed to get unread count:', response.status, response.statusText);
        }
    } catch (error) {
        console.error('Error getting unread count:', error);
    }
    return 0;
}

function renderNotifications() {
    const list = document.getElementById('notificationList');
    if (!list) return;

    if (!notifications || notifications.length === 0) {
        list.innerHTML = '<div class="notification-empty">Không có thông báo nào</div>';
        return;
    }

    list.innerHTML = notifications.map(notif => {
        // Hỗ trợ cả camelCase và PascalCase để tương thích
        const type = notif.type || notif.Type || 'Info';
        const isRead = notif.isRead !== undefined ? notif.isRead : (notif.IsRead !== undefined ? notif.IsRead : false);
        const id = notif.id || notif.Id;
        const title = notif.title || notif.Title || '';
        const message = notif.message || notif.Message || '';
        const createdAt = notif.createdAt || notif.CreatedAt;
        
        const typeIcon = {
            'Info': 'ℹ️',
            'info': 'ℹ️',
            'Success': '✅',
            'success': '✅',
            'Warning': '⚠️',
            'warning': '⚠️',
            'Error': '❌',
            'error': '❌',
            'StatusChange': '🔄',
            'statusChange': '🔄'
        }[type] || '📢';

        return `
            <div class="notification-item ${isRead ? '' : 'unread'}" onclick="markAsRead(${id})">
                <div class="notification-item-title">${typeIcon} ${title}</div>
                <div class="notification-item-message">${message}</div>
                <div class="notification-item-time">${formatVietnamDate(createdAt, 'datetime')}</div>
            </div>
        `;
    }).join('');
}

async function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;

    const count = await getUnreadCount();
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            loadNotifications();
        }
    }
}

async function markAsRead(notificationId) {
    if (!notificationId) return;
    
    try {
        const response = await fetch(`${API_BASE}/api/Notification/${notificationId}/read`, {
            method: 'PATCH'
        });
        if (response.ok) {
            const notif = notifications.find(n => (n.id || n.Id) === notificationId);
            if (notif) {
                notif.isRead = true;
                notif.IsRead = true;
                renderNotifications();
                updateNotificationBadge();
            }
        } else {
            console.error('Failed to mark notification as read:', response.status, response.statusText);
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllAsRead() {
    if (!currentCustomerId) {
        console.warn('Cannot mark all as read: currentCustomerId is not set');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/Notification/customer/${currentCustomerId}/read-all`, {
            method: 'PATCH'
        });
        if (response.ok) {
            notifications.forEach(n => {
                n.isRead = true;
                n.IsRead = true;
            });
            renderNotifications();
            updateNotificationBadge();
        } else {
            console.error('Failed to mark all as read:', response.status, response.statusText);
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

function startNotificationPolling() {
    if (!currentCustomerId) {
        console.warn('Cannot start notification polling: currentCustomerId is not set');
        return;
    }
    
    // Load ngay lập tức
    loadNotifications();
    updateNotificationBadge();
    
    // Polling mỗi 30 giây
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
    }
    notificationPollInterval = setInterval(() => {
        if (currentCustomerId) {
            loadNotifications();
            updateNotificationBadge();
        }
    }, 30000);
}

function stopNotificationPolling() {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
        notificationPollInterval = null;
    }
}

document.addEventListener('click', (e) => {
    const container = document.querySelector('.notification-container');
    const dropdown = document.getElementById('notificationDropdown');
    if (container && dropdown && !container.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Get URL parameters (from service-1 login redirect)
const urlParams = new URLSearchParams(window.location.search);
const urlCustomerId = urlParams.get('customerId');
const urlToken = urlParams.get('token');
const urlName = urlParams.get('name');

if (urlCustomerId) {
    localStorage.setItem('customerId', urlCustomerId);
    if (urlName) localStorage.setItem('customerName', urlName);
    if (urlToken) localStorage.setItem('token', urlToken);
    window.history.replaceState({}, document.title, window.location.pathname);
}

// Khởi tạo currentCustomerId sau khi xử lý URL parameters
let currentCustomerId = localStorage.getItem('customerId');
let currentCustomerName = localStorage.getItem('customerName');

// Initialize
document.addEventListener("DOMContentLoaded", function () {
    initializeApp();
});

function initializeApp() {
    // Đảm bảo currentCustomerId được cập nhật từ localStorage
    currentCustomerId = localStorage.getItem('customerId');
    currentCustomerName = localStorage.getItem('customerName');
    
    if (!currentCustomerId) {
        window.location.href = "auth.html";
    } else {
        updateCustomerDisplay();
        loadProfile();
        loadDashboard();
        startNotificationPolling();
    }
}

// ==================== LOGIN FLOW ====================
function logout() {
    localStorage.removeItem("customerId");
    localStorage.removeItem("customerName");
    localStorage.removeItem("token");
    window.location.href = "auth.html";
}

function updateCustomerDisplay() {
    document.getElementById("customerNameDisplay").textContent =
      currentCustomerName || "Khách hàng";
}

// ==================== TAB SWITCHING ====================
// Auto-refresh intervals for tracking and payments tabs
let requestsRefreshInterval = null;
let paymentsRefreshInterval = null;

function switchTab(tabName, event) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });

    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });

    const targetTab = document.getElementById(tabName);
    if (!targetTab) {
        console.error('Tab not found:', tabName);
        return;
    }
    targetTab.classList.add('active');

    if (event && event.target) {
        event.target.classList.add('active');
    } else {
        const buttons = document.querySelectorAll('.tab-button');
        buttons.forEach(btn => {
            const onclick = btn.getAttribute('onclick');
            if (onclick && onclick.includes(`switchTab('${tabName}')`) || onclick && onclick.includes(`switchTab("${tabName}")`)) {
                btn.classList.add('active');
            }
        });
    }

    // Clear existing intervals
    if (requestsRefreshInterval) {
        clearInterval(requestsRefreshInterval);
        requestsRefreshInterval = null;
    }
    if (paymentsRefreshInterval) {
        clearInterval(paymentsRefreshInterval);
        paymentsRefreshInterval = null;
    }

    if (tabName === 'dashboard') loadDashboard();
    if (tabName === 'tracking') {
        loadRequests();
        // Auto-refresh requests every 10 seconds when tracking tab is active
        requestsRefreshInterval = setInterval(() => {
            loadRequests();
        }, 10000);
    }
    if (tabName === 'history') loadHistory();
    if (tabName === 'payments') {
        loadPayments();
        // Auto-refresh payments every 10 seconds when payments tab is active
        paymentsRefreshInterval = setInterval(() => {
            loadPayments();
        }, 10000);
    }
    if (tabName === 'feedback') loadFeedback();
    if (tabName === 'studios') loadStudios();
}

// ==================== PROFILE MANAGEMENT ====================
async function loadProfile() {
    try {
        const response = await fetch(`${API_BASE}/customers/${currentCustomerId}`);
        if (!response.ok) return;

        const customer = await response.json();
        document.getElementById("profileName").value = customer.name || "";
        document.getElementById("profileEmail").value = customer.email || "";
        document.getElementById("profilePhone").value = customer.phone || "";
        document.getElementById("profileAddress").value = customer.address || "";
    } catch (error) {
        console.error("Error loading profile:", error);
    }
}

async function saveProfile() {
    const name = document.getElementById("profileName").value.trim();
    const email = document.getElementById("profileEmail").value.trim();
    const phone = document.getElementById("profilePhone").value.trim();
    const address = document.getElementById("profileAddress").value.trim();
    const messageDiv = document.getElementById("profileMessage");

    if (!name || !email) {
        showMessage(messageDiv, "Vui lòng nhập tên và email", "error");
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/customers/${currentCustomerId}`, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ name, email, phone, address }),
        });

        if (!response.ok) throw new Error("Lỗi cập nhật hồ sơ");

        showMessage(messageDiv, "✓ Hồ sơ đã được lưu thành công", "success");
        localStorage.setItem("customerName", name);
        updateCustomerDisplay();
    } catch (error) {
        showMessage(messageDiv, "Lỗi: " + error.message, "error");
    }
}

// ==================== FILE UPLOAD ====================
const fileUpload = document.getElementById("fileUpload");
const audioFileInput = document.getElementById("audioFile");

fileUpload.addEventListener("click", () => audioFileInput.click());

fileUpload.addEventListener("dragover", (e) => {
    e.preventDefault();
    fileUpload.classList.add("active");
});

fileUpload.addEventListener("dragleave", () => {
    fileUpload.classList.remove("active");
});

fileUpload.addEventListener("drop", (e) => {
    e.preventDefault();
    fileUpload.classList.remove("active");
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        audioFileInput.files = files;
    }
});

audioFileInput.addEventListener("change", () => {
    const fileName = audioFileInput.files[0]?.name || "Chọn tệp";
    fileUpload.querySelector(".upload-text").textContent = "✓ " + fileName;
});

async function submitRequest() {
    const serviceType = document.getElementById("serviceType").value;
    const description = document.getElementById("requestDescription").value.trim();
    const file = audioFileInput.files[0];
    const messageDiv = document.getElementById("uploadMessage");

    if (!serviceType || !file) {
        showMessage(messageDiv, "Vui lòng chọn loại dịch vụ và tệp", "error");
        return;
    }

    try {
        const formData = new FormData();
        formData.append("customer_id", currentCustomerId);
        formData.append("service_type", serviceType);
        formData.append("title", `${serviceType.toUpperCase()} - ${formatVietnamDate(getVietnamNow())}`);
        formData.append("status", "pending");
        formData.append("file", file);
        if (description) formData.append("description", description);

        messageDiv.innerHTML = '<div class="loading"><div class="spinner"></div> Đang tải lên...</div>';

        const response = await fetch(`${API_BASE}/requests`, {
            method: "POST",
            body: formData,
        });

        if (!response.ok) throw new Error("Lỗi gửi yêu cầu");

        const request = await response.json();
        showMessage(messageDiv, `✓ Yêu cầu đã được gửi thành công (ID: ${request.id.substring(0, 8)})`, "success");

        // Reset form
        document.getElementById("serviceType").value = "";
        document.getElementById("requestDescription").value = "";
        audioFileInput.value = "";
        fileUpload.querySelector(".upload-text").textContent = "Kéo thả tệp hoặc nhấp để chọn";

        setTimeout(() => {
            loadRequests();
            loadDashboard();
        }, 2000);
    } catch (error) {
        showMessage(messageDiv, "Lỗi: " + error.message, "error");
    }
}

// ==================== DASHBOARD ====================
async function loadDashboard() {
    try {
        const requestsResponse = await fetch(`${API_BASE}/requests/customer/${currentCustomerId}`);
        const requests = (await requestsResponse.ok) ? await requestsResponse.json() : [];

        const validRequests = requests.filter(r => !isRequestCancelled(r));
        const stats = {
            total: validRequests.length,
            completed: validRequests.filter(r => {
                const s = (r.status || '').toLowerCase();
                return s === 'completed' || s === 'finished';
            }).length,
            processing: validRequests.filter(r => {
                const s = (r.status || '').toLowerCase();
                return s === 'pendingreview' || s === 'pending_review' || 
                       s === 'pendingmeetingconfirmation' || s === 'pending_meeting_confirmation' ||
                       s === 'processing' || s === 'inprogress' || s === 'in_progress';
            }).length,
            pending: validRequests.filter(r => {
                const s = (r.status || '').toLowerCase();
                return s === 'requested' || s === 'pending' || s === 'submitted';
            }).length,
        };

        const statsGrid = document.getElementById("statsGrid");
        statsGrid.innerHTML = `
            <div class="stat-card">
                <div class="stat-number">${stats.total}</div>
                <div class="stat-label">Tổng Đơn Hàng</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${stats.pending}</div>
                <div class="stat-label">Chưa Xử Lý</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${stats.processing}</div>
                <div class="stat-label">Đang Xử Lý</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${stats.completed}</div>
                <div class="stat-label">Hoàn Thành</div>
            </div>
        `;

        const recentRequests = document.getElementById("recentRequests");
        const recentValidRequests = validRequests.filter(r => !isRequestCancelled(r));
        if (recentValidRequests.length === 0) {
            recentRequests.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Chưa có đơn hàng nào</p>';
        } else {
            recentRequests.innerHTML = recentValidRequests
                .slice(0, 5)
                .map(req => `
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">${req.service_type.toUpperCase()}</div>
                                <div class="card-meta">
                                    <span>ID: ${req.id.substring(0, 8)}</span>
                                    <span>${formatVietnamDate(req.created_at)}</span>
                                </div>
                            </div>
                            <span class="badge badge-${req.status}">${translateStatus(req.status)}</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${getProgressWidth(req.status)}%"></div>
                        </div>
                    </div>
                `)
                .join("");
        }
    } catch (error) {
        console.error("Error loading dashboard:", error);
    }
}

// ==================== REQUEST TRACKING ====================
async function loadRequests() {
    try {
        const response = await fetch(`${API_BASE}/requests/customer/${currentCustomerId}`);
        const requests = await response.ok ? await response.json() : [];

        const requestsList = document.getElementById("requestsList");

        if (requests.length === 0) {
            requestsList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Không có đơn hàng nào đang xử lý</p>';
            return;
        }

        requestsList.innerHTML = requests.map(req => {
            const serviceType = req.service_type || req.serviceType || '';
            const status = req.status || req.Status || 'pending';
            const paid = isRequestPaid(req);
            return `
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            ${getServiceIcon(serviceType)} ${serviceType.toUpperCase()}
                        </div>
                        <div class="card-meta">
                            <span>ID: ${req.id}</span>
                            <span>Tạo: ${formatVietnamDate(req.created_date || req.created_at || req.CreatedDate)}</span>
                            ${req.title ? `<span>📝 ${req.title}</span>` : ''}
                        </div>
                    </div>
                    <span class="badge badge-${status.toLowerCase()}">${translateStatus(status)}</span>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${getProgressWidth(status)}%"></div>
                </div>
                
                ${req.description ? `<p style="margin-top: 10px; color: #666; font-size: 14px;">📝 ${(req.description || '').substring(0, 100)}${(req.description || '').length > 100 ? '...' : ''}</p>` : ''}
                
                <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                    <button class="btn btn-secondary" onclick="viewRequestDetails('${req.id}')">Xem Chi Tiết</button>
                    ${status.toLowerCase() === 'pendingreview' || status.toLowerCase() === 'pending_review' ? `<button class="btn btn-primary" onclick="openSelectExpertModal('${req.id}')">👤 Chọn Chuyên Gia</button>` : ''}
                    ${status.toLowerCase() === 'pending' || status.toLowerCase() === 'submitted' || status.toLowerCase() === 'requested' ? `<button class="btn btn-danger" onclick="cancelRequest('${req.id}')">Hủy Yêu Cầu</button>` : ''}
                    ${!paid && !isRequestCancelled(req) && (status.toLowerCase() === 'completed' || status.toLowerCase() === 'finished') ? `<button class="btn btn-primary" onclick="openPaymentModal('${req.id}')">💳 Thanh Toán</button>` : ''}
                </div>
            </div>
        `;
        }).join('');
    } catch (error) {
        console.error('Error loading requests:', error);
        const requestsList = document.getElementById('requestsList');
        if (requestsList) {
            requestsList.innerHTML = '<p style="text-align: center; color: #dc2626; padding: 20px;">❌ Lỗi tải danh sách đơn hàng</p>';
        }
    }
}

// ==================== HISTORY (Completed & Paid Orders) ====================
async function loadHistory() {
    try {
        const response = await fetch(`${API_BASE}/requests/customer/${currentCustomerId}`);
        const allRequests = await response.ok ? await response.json() : [];

        const history = allRequests.filter(req => {
            const status = (req.status || req.Status || '').toLowerCase();
            const paid = isRequestPaid(req);
            const completed = status === 'completed' || status === 'finished';
            const cancelled = status === 'cancelled' || status === 'canceled';
            return (completed && paid) || cancelled;
        });

        history.sort((a, b) => {
            const dateA = new Date(a.created_date || a.created_at || a.CreatedDate || 0);
            const dateB = new Date(b.created_date || b.created_at || b.CreatedDate || 0);
            return dateB - dateA;
        });

        const messageDiv = document.getElementById('historyMessage');
        const historyList = document.getElementById('historyList');

        if (history.length === 0) {
            historyList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Chưa có đơn hàng nào trong lịch sử</p>';
            return;
        }

        historyList.innerHTML = history.map(req => {
            const serviceType = req.service_type || req.serviceType || '';
            const status = req.status || req.Status || 'completed';
            const statusLower = (status || '').toLowerCase();
            const paid = isRequestPaid(req);
            const cancelled = statusLower === 'cancelled' || statusLower === 'canceled';
            const completed = statusLower === 'completed' || statusLower === 'finished';
            const price = extractStudioPrice(req);
            
            const borderColor = cancelled ? '#dc2626' : '#10b981';
            const cardBg = cancelled ? '#fef2f2' : '#f9fafb';
            
            return `
            <div class="card" style="background: ${cardBg}; border-left: 4px solid ${borderColor};">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            ${getServiceIcon(serviceType)} ${serviceType.toUpperCase()}
                            ${req.title ? ` - ${req.title}` : ''}
                        </div>
                        <div class="card-meta">
                            <span>ID: ${req.id}</span>
                            <span>📅 ${formatVietnamDate(req.created_date || req.created_at || req.CreatedDate)}</span>
                            ${req.due_date || req.dueDate || req.DueDate ? `<span>⏰ Hạn: ${formatVietnamDate(req.due_date || req.dueDate || req.DueDate)}</span>` : ''}
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-end;">
                        ${cancelled ? 
                            `<span class="badge badge-cancelled">❌ Đã Hủy</span>` :
                            `<span class="badge badge-success">✅ Hoàn Thành</span>
                             <span class="badge badge-success">💳 Đã Thanh Toán</span>`
                        }
                    </div>
                </div>
                
                ${req.description ? `<p style="margin-top: 10px; color: #666; font-size: 14px;">📝 ${(req.description || '').substring(0, 100)}${(req.description || '').length > 100 ? '...' : ''}</p>` : ''}
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        ${!cancelled ? 
                            `<div style="font-size: 18px; font-weight: bold; color: #10b981;">
                                💰 ${new Intl.NumberFormat('vi-VN').format(price)} ₫
                            </div>` :
                            `<div style="font-size: 14px; color: #666; font-style: italic;">
                                Đơn hàng này đã bị hủy
                            </div>`
                        }
                        <div style="display: flex; gap: 10px;">
                            <button class="btn btn-secondary" onclick="viewRequestDetails('${req.id}')">Xem Chi Tiết</button>
                            ${!cancelled && (req.file_name || req.fileName || req.FileName) ? `<a href="${API_BASE}/requests/${req.id}/download" target="_blank" class="btn btn-primary">📥 Tải File</a>` : ''}
                        </div>
                    </div>
                </div>
            </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading history:', error);
        const historyList = document.getElementById('historyList');
        if (historyList) {
            historyList.innerHTML = '<p style="text-align: center; color: #dc2626; padding: 20px;">❌ Lỗi tải lịch sử đơn hàng</p>';
        }
    }
}

async function viewRequestDetails(requestId) {
    try {
        const response = await fetch(`${API_BASE}/requests/${requestId}`);
        const request = await response.json();
        alert(
            `Chi tiết đơn hàng:\n\nID: ${request.id}\nLoại: ${request.service_type}\nTrạng thái: ${translateStatus(request.status)}\nTạo: ${formatVietnamDate(request.created_at)}`
        );
    } catch (error) {
        alert("Lỗi: " + error.message);
    }
}

async function cancelRequest(requestId) {
    if (!confirm('Bạn có chắc muốn hủy yêu cầu này?')) return;
    try {
        const formData = new URLSearchParams();
        formData.append('status', 'cancelled');
        
        const response = await fetch(`${API_BASE}/requests/${requestId}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ detail: 'Lỗi hủy yêu cầu' }));
            throw new Error(errorData.detail || 'Lỗi hủy yêu cầu');
        }
        
        alert('✅ Đã hủy yêu cầu thành công!');
        loadRequests();
        loadDashboard();
    } catch (error) {
        console.error('Error canceling request:', error);
        alert('Lỗi: ' + error.message);
    }
}

// ==================== SELECT EXPERT & SCHEDULE ====================
let currentSelectExpertRequestId = null;

async function openSelectExpertModal(requestId) {
    currentSelectExpertRequestId = requestId;
    const modal = document.getElementById('selectExpertModal');
    const modalBody = document.getElementById('selectExpertModalBody');
    
    try {
        const requestResponse = await fetch(`${API_BASE}/requests/${requestId}`);
        if (!requestResponse.ok) throw new Error('Không thể tải thông tin yêu cầu');
        const request = await requestResponse.json();
        
        const expertsResponse = await fetch(`${API_BASE}/api/Admin/users`);
        if (!expertsResponse.ok) throw new Error('Không thể tải danh sách chuyên gia');
        const allUsers = await expertsResponse.json();
        
        const expertRoles = ['Arrangement', 'Transcription', 'Recorder', 'Coordinator'];
        const experts = allUsers.filter(u => expertRoles.includes(u.role || u.Role));
        
        const serviceType = request.service_type || request.serviceType || '';
        let suitableExperts = experts;
        if (serviceType) {
            const normalizedType = serviceType.charAt(0).toUpperCase() + serviceType.slice(1).toLowerCase();
            suitableExperts = experts.filter(e => {
                const role = e.role || e.Role || '';
                if (normalizedType === 'Transcription' && role === 'Transcription') return true;
                if (normalizedType === 'Arrangement' && role === 'Arrangement') return true;
                if (normalizedType === 'Recording' && role === 'Recorder') return true;
                if (role === 'Coordinator') return true;
                return false;
            });
            if (suitableExperts.length === 0) suitableExperts = experts;
        }
        
        modalBody.innerHTML = `
            <div style="padding: 20px;">
                <div class="form-group">
                    <label>Chọn Chuyên Gia *</label>
                    <select id="expertSelect" required>
                        <option value="">-- Chọn Chuyên Gia --</option>
                        ${suitableExperts.map(e => `
                            <option value="${e.id || e.Id}">${e.name || e.Name} (${e.role || e.Role})</option>
                        `).join('')}
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Ngày Gặp *</label>
                    <input type="date" id="scheduleDate" required min="${new Date().toISOString().split('T')[0]}">
                </div>
                
                <div class="form-group">
                    <label>Khung Giờ *</label>
                    <select id="scheduleTimeSlot" required>
                        <option value="">-- Chọn Khung Giờ --</option>
                        <option value="0-4">Ca 1: 0h - 4h</option>
                        <option value="6-10">Ca 2: 6h - 10h</option>
                        <option value="12-16">Ca 3: 12h - 16h</option>
                        <option value="18-22">Ca 4: 18h - 22h</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Ghi Chú (Tùy chọn)</label>
                    <textarea id="meetingNotes" placeholder="Ghi chú thêm về cuộc gặp..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button class="btn btn-primary" onclick="submitExpertSelection()" style="flex: 1;">✅ Xác Nhận</button>
                    <button class="btn btn-secondary" onclick="closeSelectExpertModal()" style="flex: 1;">❌ Hủy</button>
                </div>
            </div>
        `;
        
        modal.style.display = 'block';
    } catch (error) {
        console.error('Error opening select expert modal:', error);
        alert('Lỗi: ' + error.message);
    }
}

function closeSelectExpertModal() {
    const modal = document.getElementById('selectExpertModal');
    modal.style.display = 'none';
    currentSelectExpertRequestId = null;
}

async function submitExpertSelection() {
    if (!currentSelectExpertRequestId) return;
    
    const expertId = document.getElementById('expertSelect').value;
    const scheduleDate = document.getElementById('scheduleDate').value;
    const timeSlot = document.getElementById('scheduleTimeSlot').value;
    const meetingNotes = document.getElementById('meetingNotes').value;
    
    if (!expertId || !scheduleDate || !timeSlot) {
        alert('Vui lòng điền đầy đủ thông tin!');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/Customer/requests/${currentSelectExpertRequestId}/select-expert`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                specialistId: parseInt(expertId),
                scheduledDate: scheduleDate,
                timeSlot: timeSlot,
                meetingNotes: meetingNotes || null
            })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Lỗi chọn chuyên gia' }));
            throw new Error(errorData.message || errorData.detail || 'Lỗi chọn chuyên gia');
        }
        
        const result = await response.json();
        alert('✅ ' + (result.message || 'Đã chọn chuyên gia thành công!'));
        closeSelectExpertModal();
        loadRequests();
        loadDashboard();
    } catch (error) {
        console.error('Error submitting expert selection:', error);
        alert('Lỗi: ' + error.message);
    }
}

// ==================== UTILITY: Extract Service Price ====================
function extractStudioPrice(request) {
    if (!request) return 50000;
    
    if (request.description) {
        const match = request.description.match(/\[STUDIO_BOOKING\](.*?)\[\/STUDIO_BOOKING\]/);
        if (match) {
            try {
                const bookingInfo = JSON.parse(match[1]);
                if (bookingInfo.price !== undefined && bookingInfo.price !== null) {
                    const price = typeof bookingInfo.price === 'string' 
                        ? parseFloat(bookingInfo.price) 
                        : bookingInfo.price;
                    return isNaN(price) || price <= 0 ? 50000 : price;
                }
            } catch (e) {
                console.error('Error parsing studio booking info:', e);
            }
        }
    }
    
    const serviceType = request.service_type || request.serviceType || '';
    if (serviceType) {
        const normalizedType = serviceType.charAt(0).toUpperCase() + serviceType.slice(1).toLowerCase();
        if (normalizedType === 'Recording') return 50000;
        if (normalizedType === 'Transcription' || normalizedType === 'Arrangement') {
            return servicePricesCache[normalizedType] || 50000;
        }
    }
    
    return 50000;
}

// ==================== PAYMENTS ====================
async function loadPayments() {
    try {
        const requestsResponse = await fetch(`${API_BASE}/requests/customer/${currentCustomerId}?t=${Date.now()}`);
        const requests = await requestsResponse.ok ? await requestsResponse.json() : [];

        const validRequests = requests.filter(r => !isRequestCancelled(r));
        const unpaidCount = validRequests.filter(r => !isRequestPaid(r)).length;
        const paidCount = validRequests.filter(r => isRequestPaid(r)).length;
        const totalUnpaid = validRequests.filter(r => !isRequestPaid(r)).reduce((sum, r) => sum + extractStudioPrice(r), 0);
        const totalPaid = validRequests.filter(r => isRequestPaid(r)).reduce((sum, r) => sum + extractStudioPrice(r), 0);

        const paymentStats = document.getElementById("paymentStats");
        paymentStats.innerHTML = `
            <div class="stat-card">
                <div class="stat-number">${unpaidCount}</div>
                <div class="stat-label">Chưa Thanh Toán</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${(totalUnpaid / 1000).toFixed(0)}K</div>
                <div class="stat-label">Tổng Nợ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${paidCount}</div>
                <div class="stat-label">Đã Thanh Toán</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">${(totalPaid / 1000).toFixed(0)}K</div>
                <div class="stat-label">Tổng Đã Trả</div>
            </div>
        `;

        const unpaidRequests = document.getElementById('unpaidRequests');
        const unpaid = requests.filter(r => !isRequestPaid(r) && !isRequestCancelled(r));
        unpaidRequests.innerHTML = unpaid.length === 0 ? 
            '<p style="text-align: center; color: #999; padding: 20px;">✅ Không có đơn chưa thanh toán - Tất cả đã thanh toán!</p>' :
            unpaid.map(req => `
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${getServiceIcon(req.service_type)} ${req.service_type.toUpperCase()}</div>
                            <div class="card-meta">
                                <span>ID: ${req.id.substring(0, 8)}</span>
                                <span>Trạng thái: ${translateStatus(req.status)}</span>
                            </div>
                        </div>
                        <span class="badge badge-unpaid">Chưa Thanh Toán</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <div style="font-size: 18px; font-weight: bold; color: #667eea;">${new Intl.NumberFormat('vi-VN').format(extractStudioPrice(req))} ₫</div>
                        <button class="btn btn-primary" onclick="openPaymentModal('${req.id}')">💳 Thanh Toán Ngay</button>
                    </div>
                </div>
            `).join("");

        const paidRequests = requests.filter(r => isRequestPaid(r));
        if (paidRequests.length > 0) {
            const paidSection = document.getElementById('unpaidRequests').parentElement;
            let paidHTML = '<h3 style="margin-top: 30px; margin-bottom: 20px;">✅ Các Đơn Đã Thanh Toán</h3>';
            paidHTML += paidRequests.map(req => `
                <div class="card" style="opacity: 0.8;">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${getServiceIcon(req.service_type)} ${req.service_type.toUpperCase()}</div>
                            <div class="card-meta">
                                <span>ID: ${req.id.substring(0, 8)}</span>
                                <span>Trạng thái: ${translateStatus(req.status)}</span>
                            </div>
                        </div>
                        <span class="badge" style="background:#4caf50; color:white;">Đã Thanh Toán</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <div style="font-size: 18px; font-weight: bold; color: #4caf50;">${new Intl.NumberFormat('vi-VN').format(extractStudioPrice(req))} ₫</div>
                        <span style="color:#999; font-size:13px;">Thanh toán thành công</span>
                    </div>
                </div>
            `).join("");
            paidSection.innerHTML += paidHTML;
        }

        loadTransactions();
    } catch (error) {
        console.error("Error loading payments:", error);
    }
}

async function loadTransactions() {
    try {
        const response = await fetch(`${API_BASE}/transactions/${currentCustomerId}`);
        const transactions = (await response.ok) ? await response.json() : [];

        const tbody = document.getElementById("transactionsBody");
        tbody.innerHTML = transactions
            .map(trans => `
                <tr>
                    <td>${formatVietnamDate(trans.created_at)}</td>
                    <td>${trans.payment_id.substring(0, 8)}</td>
                    <td>${trans.request_id.substring(0, 8)}</td>
                    <td style="font-weight: bold; color: #667eea;">${(trans.amount / 1000).toFixed(0)}K ₫</td>
                    <td><span class="badge badge-paid">${translateStatus(trans.status)}</span></td>
                </tr>
            `)
            .join("");

        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #999; padding: 20px;">Chưa có giao dịch nào</td></tr>';
        }
    } catch (error) {
        console.error("Error loading transactions:", error);
    }
}

async function openPaymentModal(requestId) {
    try {
        const requestResponse = await fetch(`${API_BASE}/requests/${requestId}`);
        if (!requestResponse.ok) throw new Error('Không thể tải thông tin đơn hàng');
        
        const request = await requestResponse.json();
        
        if (isRequestCancelled(request)) {
            alert('⚠️ Đơn hàng này đã bị hủy nên không cần thanh toán.');
            return;
        }
        
        if (isRequestPaid(request)) {
            alert('✅ Đơn hàng này đã được thanh toán rồi.');
            return;
        }
        
        const paymentAmount = extractStudioPrice(request);
        
        const paymentServiceUrl = window.location.origin + '/payment.html';
        const params = new URLSearchParams({
            orderId: requestId,
            customerId: currentCustomerId,
            amount: paymentAmount.toString()
        });
        
        window.open(`${paymentServiceUrl}?${params.toString()}`, '_blank', 'width=800,height=900');
    } catch (error) {
        console.error('Error opening payment page:', error);
        alert('Lỗi: ' + error.message);
    }
}

function closePaymentModal() {
    document.getElementById("paymentModal").classList.remove("active");
}

// ==================== FEEDBACK ====================
async function loadFeedback() {
    try {
        const requestsResponse = await fetch(`${API_BASE}/requests/customer/${currentCustomerId}`);
        const requests = (await requestsResponse.ok) ? await requestsResponse.json() : [];

        const feedbackSelect = document.getElementById("feedbackRequestId");
        feedbackSelect.innerHTML = '<option value="">-- Chọn Đơn Hàng --</option>' +
            requests
                .map(req => `<option value="${req.id}">${req.service_type} - ${req.id.substring(0, 8)} (${translateStatus(req.status)})</option>`)
                .join("");

        const feedbackResponse = await fetch(`${API_BASE}/feedback/customer/${currentCustomerId}`);
        const feedbacks = (await feedbackResponse.ok) ? await feedbackResponse.json() : [];

        const feedbackHistory = document.getElementById("feedbackHistory");
        if (feedbacks.length === 0) {
            feedbackHistory.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Chưa có phản hồi nào</p>';
            return;
        }

        feedbackHistory.innerHTML = feedbacks
            .map(fb => `
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${
                              fb.feedback_type === "revision" ? "✏️ Yêu Cầu Chỉnh Sửa" :
                              fb.feedback_type === "bug" ? "🐛 Báo Lỗi" : "💡 Đề Xuất"
                            }</div>
                            <div class="card-meta">
                                <span>Đơn: ${fb.request_id.substring(0, 8)}</span>
                                <span>${formatVietnamDate(fb.created_date)}</span>
                            </div>
                        </div>
                    </div>
                    <p style="margin: 0; color: #666; line-height: 1.6;">${fb.content}</p>
                </div>
            `)
            .join("");
    } catch (error) {
        console.error("Error loading feedback:", error);
    }
}

async function submitFeedback() {
    const requestId = document.getElementById("feedbackRequestId").value;
    const content = document.getElementById("feedbackContent").value.trim();
    const type = document.getElementById("feedbackType").value;
    const messageDiv = document.getElementById("feedbackMessage");

    if (!requestId || !content || !type) {
        showMessage(messageDiv, "Vui lòng điền đầy đủ thông tin", "error");
        return;
    }

    try {
        const formData = new FormData();
        formData.append("request_id", requestId);
        formData.append("content", content);
        formData.append("feedback_type", type);

        const response = await fetch(`${API_BASE}/feedback`, {
            method: "POST",
            body: formData,
        });

        if (!response.ok) throw new Error("Lỗi gửi phản hồi");

        showMessage(messageDiv, "✓ Phản hồi đã được gửi thành công", "success");
        document.getElementById("feedbackRequestId").value = "";
        document.getElementById("feedbackContent").value = "";
        document.getElementById("feedbackType").value = "";

        setTimeout(() => loadFeedback(), 2000);
    } catch (error) {
        showMessage(messageDiv, "Lỗi: " + error.message, "error");
    }
}

// ==================== STUDIO BOOKING ====================
let selectedStudio = null;

async function loadStudios() {
    try {
        // Kiểm tra và cập nhật trạng thái studio trước khi load
        try {
            await fetch(`${API_BASE}/api/StudioBooking/check-dates`);
        } catch (e) {
            console.warn('Không thể gọi check-dates:', e);
        }
        
        const response = await fetch(`${API_BASE}/studios`);
        if (!response.ok) throw new Error('Không thể tải danh sách studio');
        
        const result = await response.json();
        const studios = result.data || [];
        const container = document.getElementById('studiosList');
        
        if (studios.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Chưa có studio nào</p>';
            return;
        }

        container.innerHTML = studios.map(studio => {
            let statusNum = studio.status;
            if (typeof studio.status === 'string') {
                const statusMap = {
                    'Available': 0,
                    'Occupied': 1,
                    'UnderMaintenance': 2
                };
                statusNum = statusMap[studio.status] ?? 0;
            }
            statusNum = Number(statusNum) || 0;
            
            const statusText = ['Còn chỗ', 'Đã có người đặt', 'Chưa đặt'][statusNum] || 'Không xác định';
            const statusClass = statusNum === 0 ? 'available' : statusNum === 1 ? 'occupied' : 'maintenance';
            const isAvailable = statusNum === 0;
            
            const studioPrice = Number(studio.price) || 0;
            
            return `
                <div class="card" style="border: 2px solid ${isAvailable ? '#2f9e44' : '#c92a2a'};">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${studio.name}</div>
                            <div class="card-meta">
                                <span>📍 ${studio.location}</span>
                            </div>
                        </div>
                        <span class="badge badge-${statusClass}">${statusText}</span>
                    </div>
                    ${studio.image ? `<img src="${studio.image}" alt="${studio.name}" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin: 10px 0;">` : ''}
                    <div style="padding: 10px 0;">
                        <p style="font-size: 18px; font-weight: 600; color: #667eea; margin: 10px 0;">
                            ${new Intl.NumberFormat('vi-VN').format(studioPrice)} VNĐ
                        </p>
                        <button 
                            class="btn btn-primary" 
                            onclick="openBookingModal(${studio.id}); return false;"
                            ${!isAvailable ? 'disabled' : ''}
                            style="width: 100%; margin-top: 10px;${!isAvailable ? ' opacity: 0.5; cursor: not-allowed;' : ''}"
                            type="button">
                            ${isAvailable ? '📅 Đặt Ngay' : '❌ Không Khả Dụng'}
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading studios:', error);
        const messageDiv = document.getElementById('studiosMessage');
        showMessage(messageDiv, 'Lỗi: ' + error.message, 'error');
    }
}

async function openBookingModal(studioId) {
    try {
        const response = await fetch(`${API_BASE}/studios/${studioId}`);
        if (!response.ok) throw new Error('Không thể tải thông tin studio');
        
        const result = await response.json();
        const studio = result.data;
        
        if (!studio) {
            alert('Không tìm thấy studio');
            return;
        }

        selectedStudio = studio;

        let statusNum = studio.status;
        if (typeof studio.status === 'string') {
            const statusMap = {
                'Available': 0,
                'Occupied': 1,
                'UnderMaintenance': 2
            };
            statusNum = statusMap[studio.status] ?? 0;
        }
        statusNum = Number(statusNum) || 0;
        
        if (statusNum !== 0) {
            alert('Studio này hiện không khả dụng (Status: ' + statusNum + ')');
            return;
        }

        const modalBody = document.getElementById('bookingModalBody');
        modalBody.innerHTML = `
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px;">${studio.name}</h3>
                <p style="color: #666; margin-bottom: 5px;">📍 ${studio.location}</p>
                <p style="color: #667eea; font-weight: 600; font-size: 18px;">
                    Giá: ${new Intl.NumberFormat('vi-VN').format(studio.price)} VNĐ
                </p>
            </div>
            
            <div class="form-group">
                <label>Ngày Đặt *</label>
                <input type="date" id="bookingDate" required min="${new Date().toISOString().split('T')[0]}">
            </div>
            
            <div class="form-group">
                <label>Giờ Đặt *</label>
                <select id="bookingTime" required>
                    <option value="">-- Chọn Giờ --</option>
                    <option value="08:00">08:00 - 10:00</option>
                    <option value="10:00">10:00 - 12:00</option>
                    <option value="13:00">13:00 - 15:00</option>
                    <option value="15:00">15:00 - 17:00</option>
                    <option value="18:00">18:00 - 20:00</option>
                    <option value="20:00">20:00 - 22:00</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Ghi Chú</label>
                <textarea id="bookingNotes" placeholder="Ghi chú thêm về yêu cầu của bạn..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="bookStudio()" style="flex: 1;">
                    ✅ Xác Nhận Đặt Studio
                </button>
                <button class="btn btn-secondary" onclick="closeBookingModal()" style="flex: 1;">
                    ❌ Hủy
                </button>
            </div>
        `;

        document.getElementById('bookingModal').classList.add('active');
    } catch (error) {
        console.error('Error opening booking modal:', error);
        alert('Lỗi: ' + error.message);
    }
}

function closeBookingModal() {
    document.getElementById('bookingModal').classList.remove('active');
    selectedStudio = null;
}

async function bookStudio() {
    if (!selectedStudio) {
        alert('Vui lòng chọn studio');
        return;
    }

    const date = document.getElementById('bookingDate').value;
    const time = document.getElementById('bookingTime').value;
    const notes = document.getElementById('bookingNotes').value.trim();
    const messageDiv = document.getElementById('studiosMessage');

    if (!date || !time) {
        showMessage(messageDiv, 'Vui lòng chọn ngày và giờ đặt', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('customer_id', currentCustomerId);
        formData.append('service_type', 'recording');
        formData.append('title', `Đặt Studio: ${selectedStudio.name}`);
        
        const bookingInfo = {
            studio_id: selectedStudio.id,
            studio_name: selectedStudio.name,
            studio_location: selectedStudio.location,
            booking_date: date,
            booking_time: time,
            notes: notes || '',
            price: selectedStudio.price,
            booking_timestamp: new Date().toISOString()
        };
        formData.append('description', `Đặt phòng thu âm\nStudio: ${selectedStudio.name}\nĐịa điểm: ${selectedStudio.location}\nNgày: ${date}\nGiờ: ${time}\nGhi chú: ${notes}\nGiá: ${selectedStudio.price} VNĐ\n\n[STUDIO_BOOKING]${JSON.stringify(bookingInfo)}[/STUDIO_BOOKING]`);
        
        const bookingDateTime = new Date(`${date}T${time}`);
        formData.append('due_date', bookingDateTime.toISOString());

        showMessage(messageDiv, '<div class="loading"><div class="spinner"></div> Đang xử lý đặt studio...</div>', 'info');
        
        // Không đổi studio status ngay - để studio phê duyệt trước
        const response = await fetch(`${API_BASE}/requests`, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.detail || errorData.message || 'Lỗi đặt studio');
        }

        const request = await response.json();
        showMessage(messageDiv, `✓ Đặt studio thành công! Mã đơn: ${request.id?.substring(0, 8) || request.id}`, 'success');
        
        closeBookingModal();
        
        setTimeout(() => {
            loadStudios();
            loadDashboard();
            loadRequests();
        }, 2000);
    } catch (error) {
        console.error('Error booking studio:', error);
        showMessage(messageDiv, 'Lỗi: ' + error.message, 'error');
    }
}

// ==================== UTILITY FUNCTIONS ====================
function isRequestPaid(request) {
    if (!request) return false;
    const paid = request.paid || request.Paid;
    return paid === true || paid === 'true' || paid === 1 || paid === '1';
}

function isRequestCancelled(request) {
    if (!request) return false;
    const status = (request.status || request.Status || '').toLowerCase();
    return status === 'cancelled' || status === 'canceled' || 
           status === 'rejectedbyexpert' || status === 'rejected_by_expert' ||
           status === 'rejectedbyadmin' || status === 'rejected_by_admin';
}

function translateStatus(status) {
    if (!status) return 'Chưa Xác Định';
    const normalizedStatus = (status || '').toLowerCase();
    const statusMap = {
        'requested': '📝 Đã Gửi Yêu Cầu',
        'pending': '⏳ Chưa Xử Lý',
        'submitted': '📤 Đã Gửi',
        'assigned': '👤 Đã Phân Công',
        'inprogress': '⚙️ Đang Xử Lý',
        'in_progress': '⚙️ Đang Xử Lý',
        'processing': '⚙️ Đang Xử Lý',
        'pendingreview': '👀 Chờ Chọn Chuyên Gia',
        'pending_review': '👀 Chờ Chọn Chuyên Gia',
        'pendingmeetingconfirmation': '⏰ Chờ Chuyên Gia Xác Nhận',
        'pending_meeting_confirmation': '⏰ Chờ Chuyên Gia Xác Nhận',
        'completed': '✅ Hoàn Thành',
        'finished': '✅ Hoàn Thành',
        'rejectedbyexpert': '❌ Chuyên Gia Từ Chối',
        'rejected_by_expert': '❌ Chuyên Gia Từ Chối',
        'revisionrequested': '✏️ Yêu Cầu Chỉnh Sửa',
        'revision_requested': '✏️ Yêu Cầu Chỉnh Sửa',
        'cancelled': '❌ Đã Hủy',
        'canceled': '❌ Đã Hủy',
        'paid': '✅ Đã Thanh Toán',
        'unpaid': '❌ Chưa Thanh Toán'
    };
    return statusMap[normalizedStatus] || status;
}

function getServiceIcon(serviceType) {
    const icons = {
        'transcription': '🎤',
        'arrangement': '🎼',
        'recording': '🎙️'
    };
    return icons[serviceType.toLowerCase()] || '📁';
}

function getProgressWidth(status) {
    if (!status) return 0;
    const normalizedStatus = (status || '').toLowerCase();
    const progress = {
        'requested': 10,
        'pending': 20,
        'submitted': 20,
        'pendingreview': 30,
        'pending_review': 30,
        'pendingmeetingconfirmation': 50,
        'pending_meeting_confirmation': 50,
        'processing': 75,
        'inprogress': 75,
        'in_progress': 75,
        'completed': 100,
        'finished': 100,
        'cancelled': 0,
        'canceled': 0,
        'rejectedbyexpert': 0,
        'rejected_by_expert': 0
    };
    return progress[normalizedStatus] || 0;
}

function showMessage(element, message, type) {
    element.innerHTML = `<div class="message ${type}">${message}</div>`;
    setTimeout(() => {
        element.innerHTML = '';
    }, 5000);
}

document.addEventListener('click', (e) => {
    const paymentModal = document.getElementById('paymentModal');
    if (e.target === paymentModal) {            
        closePaymentModal();
    }
    
    const bookingModal = document.getElementById('bookingModal');
    if (e.target === bookingModal) {
        closeBookingModal();
    }
    
    const selectExpertModal = document.getElementById('selectExpertModal');
    if (e.target === selectExpertModal) {
        closeSelectExpertModal();
    }
});