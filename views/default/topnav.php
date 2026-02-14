<header class="top-navbar">
<div class="navbar-left">
<h1 class="page-title"><?php echo $_SESSION['username'] ?? 'User'; ?> Dashboard</h1>
</div>
<div class="navbar-right">
<button class="theme-toggle" id="themeToggle">
<i class="bi bi-moon-fill"></i>
</button>
<button class="notification-icon">
<i class="bi bi-bell"></i>
<span class="notification-badge">3</span>
</button>
<div class="user-dropdown">
<img src="https://picsum.photos/seed/user123/40/40.jpg" alt="User Avatar" class="user-avatar">
</div>
</header>
