<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';

requireAuth();
requireModuleAccess('users');

require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

$stmt = mysqli_prepare($connection, "SELECT id, username, password, nama_lengkap, email, role, status, created_at FROM `users` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$users = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$users) {
    redirect('index.php');
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_post = trim($_POST['username'] ?? '');
    $password_post = trim($_POST['password'] ?? '');
    $nama_lengkap_post = trim($_POST['nama_lengkap'] ?? '');
    $email_post = trim($_POST['email'] ?? '');
    $role_post = trim($_POST['role'] ?? '');
    $status_post = trim($_POST['status'] ?? '');
    $created_at_post = trim($_POST['created_at'] ?? '');
    if (empty($username_post) || empty($password_post) || empty($nama_lengkap_post) || empty($email_post)) {
        $error = "Username dan Password dan Nama Lengkap dan Email wajib diisi.";
    }
    if (!$error) {
        $stmt = mysqli_prepare($connection, "UPDATE `users` SET `username` = ?, `password` = ?, `nama_lengkap` = ?, `email` = ?, `role` = ?, `status` = ?, `created_at` = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssi", $username_post, $password_post, $nama_lengkap_post, $email_post, $role_post, $status_post, $created_at_post, $id);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Users berhasil diperbarui.";
            mysqli_stmt_close($stmt);
            $stmt = mysqli_prepare($connection, "SELECT id, username, password, nama_lengkap, email, role, status, created_at FROM `users` WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $users = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            $error = "Gagal memperbarui: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

            <h2>Edit Users</h2>
            <?php if ($error): ?>
                <?= showAlert($error, 'danger') ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <?= showAlert($success, 'success') ?>
            <?php endif; ?>
            <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username*</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($users['username']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password*</label>
                        <input type="text" name="password" class="form-control" value="<?= htmlspecialchars($users['password']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap*</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($users['nama_lengkap']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email*</label>
                        <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($users['email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="admin" <?= $users['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                            <option value="petugas" <?= $users['role'] === 'petugas' ? 'selected' : '' ?>>petugas</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif" <?= $users['status'] === 'aktif' ? 'selected' : '' ?>>aktif</option>
                            <option value="nonaktif" <?= $users['status'] === 'nonaktif' ? 'selected' : '' ?>>nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Created At</label>
                        <input type="datetime-local" name="created_at" class="form-control" value="<?= $users['created_at'] ? date('Y-m-d\TH:i', strtotime($users['created_at'])) : '' ?>">
                    </div>
                <button type="submit" class="btn btn-primary">Perbarui</button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </form>


<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
