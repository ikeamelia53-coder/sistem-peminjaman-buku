<?php
require_once '../config/database.php';

$peminjaman_id = (int)$_GET['peminjaman_id'] ?? 0;

if ($peminjaman_id == 0) {
    echo json_encode(['success' => false, 'message' => 'ID peminjaman tidak valid']);
    exit;
}

// Ambil data peminjaman dan anggota
$query = "SELECT p.*, a.nama, a.nomor_anggota, a.telepon 
          FROM peminjaman p
          JOIN anggota a ON p.anggota_id = a.id
          WHERE p.id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$peminjaman = mysqli_fetch_assoc($stmt);

// Ambil detail buku yang dipinjam (belum dikembalikan)
$query_detail = "SELECT pd.id, b.judul_buku, b.kode_buku, b.pengarang,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM pengembalian_detail pdd WHERE pdd.peminjaman_detail_id = pd.id) 
                    THEN 'dikembalikan' 
                    ELSE 'belum_dikembalikan' 
                END as status
                FROM peminjaman_detail pd
                JOIN buku b ON pd.buku_id = b.id
                WHERE pd.peminjaman_id = ?
                ORDER BY pd.id";
$stmt_detail = mysqli_prepare($connection, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $peminjaman_id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

$buku_list = [];
while ($row = mysqli_fetch_assoc($result_detail)) {
    $buku_list[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => [
        'anggota' => [
            'nama' => $peminjaman['nama'],
            'nomor_anggota' => $peminjaman['nomor_anggota'],
            'telepon' => $peminjaman['telepon']
        ],
        'tanggal_pinjam' => $peminjaman['tanggal_pinjam'],
        'buku' => $buku_list
    ]
]);
?>