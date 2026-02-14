<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('anggota');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
$success = false;
$error_message = '';

if ($id) {
    // Cek apakah anggota masih memiliki peminjaman aktif
    $check_query = "SELECT COUNT(*) as count 
                    FROM peminjaman 
                    WHERE nama_anggota = (
                        SELECT nama_anggota FROM anggota WHERE id = ?
                    ) AND status IN ('dipinjam', 'terlambat')";
    $check_stmt = mysqli_prepare($connection, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_bind_result($check_stmt, $count);
    mysqli_stmt_fetch($check_stmt);
    mysqli_stmt_close($check_stmt);
    
    if ($count > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus anggota karena masih memiliki peminjaman aktif.";
    } else {
        // Mulai transaksi
        mysqli_begin_transaction($connection);
        try {
            // Hapus anggota
            $stmt = mysqli_prepare($connection, "DELETE FROM `anggota` WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_commit($connection);
                $_SESSION['success'] = "Anggota berhasil dihapus.";
            } else {
                throw new Exception("Gagal menghapus anggota: " . mysqli_error($connection));
            }
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $_SESSION['error'] = $e->getMessage();
        }
    }
} else {
    $_SESSION['error'] = "ID anggota tidak valid.";
}

// Redirect ke halaman index anggota
header("Location: index.php");
exit();
?>