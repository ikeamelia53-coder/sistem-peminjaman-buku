<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_start();
require_once 'lib/auth.php';
require_once 'lib/functions.php';
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// CSRF Check
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
die('Invalid request. CSRF token mismatch.');
}
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
// jika tidak ada pilihan role maka role akan diisi dengan role: admin
$role = $_POST['role'] ?? 'mahasiswa';
if (empty($username) || empty($password)) {
$error = "All fields are required.";
} else {
// Validate password strength
$passwordErrors = validatePassword($password, false); // ganti menjadi: false agar bebas membuat password
if (!empty($passwordErrors)) {
$error = implode('', $passwordErrors);
} else {
if (registerUser($username, $password, $role)) {
$success = "Registration successful! You can now log in.";
} else {
$error = "Username already exists or registration failed.";
}
}
}
}
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center min-vh-100">
<div class="card p-4 shadow" style="width: 100%; max-width: 400px;">
<h3 class="text-center mb-4">Register</h3>
<?php if ($error): ?>
<?php showAlert($error); ?>
<?php endif; ?>
<?php if ($success): ?>
<?php showAlert($success, 'success'); ?>
<?php endif; ?>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
<div class="mb-3">
<label class="form-label">Username</label>
<input type="text" name="username" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" required>
<div class="form-text">
Must be 8+ chars, with uppercase, lowercase, and number.
</div>
</div>
<div class="mb-3">
<label class="form-label">Role</label>
<select name="role" class="form-control">
<option value="admin">Admin</option>
<option value="dosen">Dosen</option>
<option value="mahasiswa">Mahasiswa</option>
<option value="kaprodi">Kaprodi</option>
<option value="dekan">Dekan</option>
</select>
</div>
<button type="submit" class="btn btn-success w-100">Register</button>
<div class="text-center mt-3">
<a href="login.php">Already have an account?</a>
</div>
</form>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
