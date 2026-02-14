<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('peminjaman');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
$success = false;
$error_message = '';

if ($id) {
    // Mulai transaksi
    mysqli_begin_transaction($connection);
    try {
        // 1. Ambil data buku dari peminjaman_detail untuk update stok
        $query_detail = "SELECT buku_id FROM peminjaman_detail WHERE peminjaman_id = ?";
        $stmt_detail = mysqli_prepare($connection, $query_detail);
        mysqli_stmt_bind_param($stmt_detail, "i", $id);
        mysqli_stmt_execute($stmt_detail);
        $result_detail = mysqli_stmt_get_result($stmt_detail);
        $buku_ids = [];
        while ($row = mysqli_fetch_assoc($result_detail)) {
            $buku_ids[] = $row['buku_id'];
        }
        mysqli_stmt_close($stmt_detail);

        // 2. Update stok buku yang dipinjam
        foreach ($buku_ids as $buku_id) {
            $update_stok = "UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE id = ?";
            $stmt_stok = mysqli_prepare($connection, $update_stok);
            mysqli_stmt_bind_param($stmt_stok, "i", $buku_id);
            mysqli_stmt_execute($stmt_stok);
            mysqli_stmt_close($stmt_stok);

            // Update status buku jika stok > 0
            $update_status = "UPDATE buku SET status = 'tersedia' WHERE id = ? AND stok_tersedia > 0";
            $stmt_status = mysqli_prepare($connection, $update_status);
            mysqli_stmt_bind_param($stmt_status, "i", $buku_id);
            mysqli_stmt_execute($stmt_status);
            mysqli_stmt_close($stmt_status);
        }

        // 3. Hapus detail peminjaman terlebih dahulu
        $stmt1 = mysqli_prepare($connection, "DELETE FROM `peminjaman_detail` WHERE peminjaman_id = ?");
        mysqli_stmt_bind_param($stmt1, "i", $id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        // 4. Hapus peminjaman
        $stmt2 = mysqli_prepare($connection, "DELETE FROM `peminjaman` WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, "i", $id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        // Commit transaksi
        mysqli_commit($connection);
        $success = true;
        $_SESSION['success'] = "Peminjaman berhasil dihapus.";
    } catch (Exception $e) {
        // Rollback jika ada error
        mysqli_rollback($connection);
        $error_message = $e->getMessage();
        $_SESSION['error'] = "Gagal menghapus peminjaman: " . $error_message;
    }
} else {
    $_SESSION['error'] = "ID peminjaman tidak valid.";
}

// Redirect ke halaman index peminjaman
header("Location: index.php");
exit();
?>