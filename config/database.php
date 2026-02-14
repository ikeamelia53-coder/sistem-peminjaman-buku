<?php
// config/database.php
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'sistem_peminjaman_buku');
    define('BASE_URL', 'http://localhost/sistem_peminjaman_buku');
}

// Koneksi database
if (!isset($connection)) {
    try {
        $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$connection) {
            throw new Exception("Koneksi database gagal: " . mysqli_connect_error());
        }
        mysqli_set_charset($connection, "utf8mb4");
    } catch (Exception $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>