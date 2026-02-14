<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// PERBAIKAN: Tangani error koneksi
if (!$connection) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

$kode_anggota = isset($_GET['kode_anggota']) ? trim($_GET['kode_anggota']) : '';

if (empty($kode_anggota)) {
    echo json_encode(['error' => 'Kode anggota tidak valid']);
    exit;
}

// Query untuk cek peminjaman aktif
$query = "SELECT p.id, p.kode_peminjaman, 
                 DATE_FORMAT(p.tanggal_pinjam, '%d/%m/%Y') as tanggal_pinjam, 
                 DATE_FORMAT(p.tanggal_harus_kembali, '%d/%m/%Y') as tanggal_harus_kembali,
                 COUNT(pd.id) as jumlah_buku
          FROM peminjaman p
          LEFT JOIN peminjaman_detail pd ON p.id = pd.peminjaman_id
          WHERE p.kode_anggota = ? 
            AND (p.status = 'dipinjam' OR p.status = 'terlambat')
          GROUP BY p.id
          ORDER BY p.tanggal_pinjam DESC
          LIMIT 5";

$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "s", $kode_anggota);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$peminjaman_aktif = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Cek apakah terlambat
    $tenggat = new DateTime($row['tanggal_harus_kembali_raw'] ?? '');
    $hari_ini = new DateTime();
    $row['terlambat'] = $hari_ini > $tenggat;
    $peminjaman_aktif[] = $row;
}

mysqli_stmt_close($stmt);

$total_peminjaman_aktif = count($peminjaman_aktif);

echo json_encode([
    'total_peminjaman_aktif' => $total_peminjaman_aktif,
    'peminjaman_aktif' => $peminjaman_aktif
]);
?>