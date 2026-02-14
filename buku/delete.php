<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('buku');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id) {
    // Cek apakah buku sedang dipinjam
    $check_query = "SELECT COUNT(*) as count FROM peminjaman_detail 
                    WHERE buku_id = ? AND status_buku = 'dipinjam'";
    $check_stmt = mysqli_prepare($connection, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_bind_result($check_stmt, $count);
    mysqli_stmt_fetch($check_stmt);
    mysqli_stmt_close($check_stmt);
    
    if ($count > 0) {
        $_SESSION['error'] = "Buku tidak dapat dihapus karena sedang dipinjam oleh anggota.";
    } else {
        $stmt = mysqli_prepare($connection, "DELETE FROM `buku` WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Buku berhasil dihapus.";
        } else {
            $_SESSION['error'] = "Gagal menghapus buku.";
        }
        mysqli_stmt_close($stmt);
    }
}

redirect('index.php');
?>