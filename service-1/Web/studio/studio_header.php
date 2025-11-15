<?php
// Bắt đầu session và lấy thông tin user từ session JWT
session_start();

// $message là mảng thông báo (nếu có)
$message = $_SESSION['message'] ?? [];
unset($_SESSION['message']);

// Lấy thông tin admin
$studioName = $_SESSION['user']['name'] ?? '';
$studioEmail = $_SESSION['user']['email'] ?? '';
$role = $_SESSION['user']['role'] ?? '';
?>

<!-- Thông báo -->
<?php if (!empty($message)) : ?>
    <div id="messageContainer" class="fixed top-5 right-5 z-50 space-y-2">
        <?php foreach ($message as $msg) : ?>
            <div class="bg-green-600 text-white px-4 py-3 rounded-lg shadow-lg flex items-center justify-between gap-4 animate-fade-in-down">
                <span><?php echo htmlspecialchars($msg); ?></span>
                <i class="fas fa-times cursor-pointer hover:text-gray-200" onclick="this.parentElement.remove();"></i>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<header class="fixed top-0 left-0 w-full bg-white shadow-md z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">

            <!-- Logo -->
            <a href="admin_page.php" class="flex items-center space-x-2 text-2xl font-bold text-primary">
                <i class="fas fa-laptop-code text-primary"></i>
                <span>Admin<span class="text-secondary">LAPTOP</span></span>
            </a>

            <!-- Navigation -->
            <nav class="hidden md:flex space-x-6 text-gray-700 font-medium">
                <a href="studio_page.php" class="hover:text-primary transition-colors">Trang chủ</a>
            </nav>

            <!-- Icons -->
            <div class="flex items-center gap-4">
                <button id="menu-btn" class="text-gray-600 text-xl md:hidden focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>
                <button id="user-btn" class="text-gray-600 text-xl focus:outline-none">
                    <i class="fas fa-user-circle"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Tài khoản -->
    <div id="account-box" class="hidden absolute right-5 top-20 bg-white border border-gray-200 rounded-xl shadow-xl p-4 w-72 transition-all duration-200">
        <p class="text-gray-700 font-medium mb-1">
            👤 Tên người dùng: <span class="font-semibold text-primary"><?php echo htmlspecialchars($studioName); ?></span>
        </p>
        <p class="text-gray-700 mb-3">
            📧 Email: <span class="font-medium"><?php echo htmlspecialchars($studioEmail); ?></span>
        </p>
        <p class="text-gray-700 mb-3">
            🛡 Role: <span class="font-medium"><?php echo htmlspecialchars($role); ?></span>
        </p>
        <button id="logoutBtn" class="block w-full text-center bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition mb-2">
            <i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất
        </button>
        <div class="flex justify-center text-sm text-gray-500 gap-2">
            <a href="login.php" class="hover:text-primary">Đăng nhập</a> |
            <a href="register.php" class="hover:text-primary">Đăng ký</a>
        </div>
    </div>
</header>

<!-- Responsive menu (mobile) -->
<div id="mobile-nav" class="hidden fixed inset-0 bg-black bg-opacity-50 z-30">
    <div class="absolute right-0 top-0 h-full w-64 bg-white shadow-xl flex flex-col p-6 space-y-4">
        <button id="close-menu" class="self-end text-gray-600 text-xl mb-4">
            <i class="fas fa-times"></i>
        </button>
        <a href="studio_page.php" class="hover:text-primary">Trang chủ</a>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const userBtn = document.getElementById("user-btn");
    const accountBox = document.getElementById("account-box");
    const menuBtn = document.getElementById("menu-btn");
    const mobileNav = document.getElementById("mobile-nav");
    const closeMenu = document.getElementById("close-menu");

    // Toggle account box
    userBtn?.addEventListener("click", () => {
        accountBox.classList.toggle("hidden");
    });

    // Toggle mobile menu
    menuBtn?.addEventListener("click", () => {
        mobileNav.classList.remove("hidden");
    });

    closeMenu?.addEventListener("click", () => {
        mobileNav.classList.add("hidden");
    });

    // Đóng account box khi click ra ngoài
    document.addEventListener("click", (e) => {
        if (!accountBox.contains(e.target) && !userBtn.contains(e.target)) {
            accountBox.classList.add("hidden");
        }
    });
});
// Gọi API logout ASP.NET Core
document.getElementById('logoutBtn')?.addEventListener('click', async () => {
    if (!confirm("Bạn có chắc chắn muốn đăng xuất không?")) return;

    const token = '<?php echo $_SESSION['token'] ?? ''; ?>';

    try {
        const res = await fetch('http://localhost:5200/api/Auth/logout', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });

        if (!res.ok) throw new Error('API logout thất bại');

        const data = await res.json();
        console.log('Server:', data);

        // Gọi PHP để xóa session
        window.location.href = '../admin/admin_logout.php';
    } catch (error) {
        console.error('Logout error:', error);
        alert('Có lỗi khi đăng xuất, vui lòng thử lại!');
    }
});

</script>

<style>
@keyframes fade-in-down {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down { animation: fade-in-down 0.3s ease-in-out; }
</style>
