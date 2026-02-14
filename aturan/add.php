<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';

requireAuth();
requireModuleAccess('aturan');

require_once '../config/database.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max_pinjaman_post = trim($_POST['max_pinjaman'] ?? '');
    $max_hari_post = trim($_POST['max_hari'] ?? '');
    $denda_per_hari_post = trim($_POST['denda_per_hari'] ?? '');
    $max_keterlambatan_post = trim($_POST['max_keterlambatan'] ?? '');
    $created_at_post = trim($_POST['created_at'] ?? '');
    // No required validation
    if (!$error) {
        $stmt = mysqli_prepare($connection, "INSERT INTO `aturan` (max_pinjaman, max_hari, denda_per_hari, max_keterlambatan, created_at) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iidis", $max_pinjaman_post, $max_hari_post, $denda_per_hari_post, $max_keterlambatan_post, $created_at_post);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Aturan berhasil ditambahkan.";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            $error = "Gagal menyimpan: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>


            <h2>Tambah Aturan</h2>
            <?php if ($error): ?>
                <?= showAlert($error, 'danger') ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <?= showAlert($success, 'success') ?>
                <a href="index.php" class="btn btn-secondary">Kembali ke Daftar</a>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Max Pinjaman</label>
                        <input type="number" name="max_pinjaman" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Hari</label>
                        <input type="number" name="max_hari" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Denda Per Hari</label>
                        <input type="number" step="any" name="denda_per_hari" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Keterlambatan</label>
                        <input type="number" name="max_keterlambatan" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Created At</label>
                        <input type="datetime-local" name="created_at" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                </form>
            <?php endif; ?>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
