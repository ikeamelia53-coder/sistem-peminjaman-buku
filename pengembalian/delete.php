<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('pengembalian');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
$success = false;
$error_message = '';

if ($id) {
    try {
        // Mulai transaksi
        mysqli_begin_transaction($connection);
        
        // 1. Hapus detail pengembalian terlebih dahulu
        $stmt_detail = mysqli_prepare($connection, "DELETE FROM `pengembalian_detail` WHERE pengembalian_id = ?");
        mysqli_stmt_bind_param($stmt_detail, "i", $id);
        mysqli_stmt_execute($stmt_detail);
        mysqli_stmt_close($stmt_detail);
        
        // 2. Hapus pengembalian
        $stmt = mysqli_prepare($connection, "DELETE FROM `pengembalian` WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Commit transaksi
        mysqli_commit($connection);
        $success = true;
        $_SESSION['success'] = "Pengembalian berhasil dihapus.";
    } catch (Exception $e) {
        // Rollback jika terjadi error
        mysqli_rollback($connection);
        $_SESSION['error'] = "Gagal menghapus pengembalian: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ID pengembalian tidak valid.";
}

// Redirect ke halaman index pengembalian
header("Location: index.php");
exit();
?>