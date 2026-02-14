<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';

requireAuth();
requireModuleAccess('aturan');

require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

$stmt = mysqli_prepare($connection, "SELECT id, max_pinjaman, max_hari, denda_per_hari, max_keterlambatan, created_at FROM `aturan` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$aturan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$aturan) {
    redirect('index.php');
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max_pinjaman_post = trim($_POST['max_pinjaman'] ?? '');
    $max_hari_post = trim($_POST['max_hari'] ?? '');
    $denda_per_hari_post = trim($_POST['denda_per_hari'] ?? '');
    $max_keterlambatan_post = trim($_POST['max_keterlambatan'] ?? '');
    $created_at_post = trim($_POST['created_at'] ?? '');
    // No required fields
    if (!$error) {
        $stmt = mysqli_prepare($connection, "UPDATE `aturan` SET `max_pinjaman` = ?, `max_hari` = ?, `denda_per_hari` = ?, `max_keterlambatan` = ?, `created_at` = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "iidisi", $max_pinjaman_post, $max_hari_post, $denda_per_hari_post, $max_keterlambatan_post, $created_at_post, $id);

        if (mysqli_stmt_execute($stmt)) {
            $success = "Aturan berhasil diperbarui.";
            mysqli_stmt_close($stmt);
            $stmt = mysqli_prepare($connection, "SELECT id, max_pinjaman, max_hari, denda_per_hari, max_keterlambatan, created_at FROM `aturan` WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $aturan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
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

            <h2>Edit Aturan</h2>
            <?php if ($error): ?>
                <?= showAlert($error, 'danger') ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <?= showAlert($success, 'success') ?>
            <?php endif; ?>
            <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Max Pinjaman</label>
                        <input type="number" name="max_pinjaman" class="form-control" value="<?= $aturan['max_pinjaman'] ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Hari</label>
                        <input type="number" name="max_hari" class="form-control" value="<?= $aturan['max_hari'] ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Denda Per Hari</label>
                        <input type="text" name="denda_per_hari" class="form-control" value="<?= htmlspecialchars($aturan['denda_per_hari']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Keterlambatan</label>
                        <input type="number" name="max_keterlambatan" class="form-control" value="<?= $aturan['max_keterlambatan'] ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Created At</label>
                        <input type="datetime-local" name="created_at" class="form-control" value="<?= $aturan['created_at'] ? date('Y-m-d\TH:i', strtotime($aturan['created_at'])) : '' ?>">
                    </div>
                <button type="submit" class="btn btn-primary">Perbarui</button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </form>


<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>
