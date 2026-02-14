<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('pengembalian');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

// Ambil data master pengembalian
$query_master = "SELECT p.*, pm.kode_peminjaman, pm.nama_anggota, pm.tanggal_pinjam, pm.tanggal_harus_kembali,
a.kode_anggota, a.email, a.alamat, u.username as petugas_nama
FROM pengembalian p
LEFT JOIN peminjaman pm ON p.peminjaman_id = pm.id
LEFT JOIN anggota a ON pm.nama_anggota = a.nama_anggota
LEFT JOIN users u ON p.petugas_id = u.id
WHERE p.id = ?";
$stmt_master = mysqli_prepare($connection, $query_master);
mysqli_stmt_bind_param($stmt_master, "i", $id);
mysqli_stmt_execute($stmt_master);
$result_master = mysqli_stmt_get_result($stmt_master);
$master_data = mysqli_fetch_assoc($result_master);
mysqli_stmt_close($stmt_master);

// Redirect jika data tidak ditemukan
if (!$master_data) {
    $_SESSION['error'] = "Data pengembalian tidak ditemukan.";
    redirect('index.php');
}

// Ambil data detail pengembalian
$query_detail = "SELECT pd.*, pmd.judul_buku, pmd.kode_buku, pmd.pengarang, pmd.cover as cover_buku
FROM pengembalian_detail pd
LEFT JOIN peminjaman_detail pmd ON pd.peminjaman_detail_id = pmd.id
WHERE pd.pengembalian_id = ?
ORDER BY pd.id";
$stmt_detail = mysqli_prepare($connection, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);
$detail_data = [];
while ($row = mysqli_fetch_assoc($result_detail)) {
    $detail_data[] = $row;
}
mysqli_stmt_close($stmt_detail);

// Format tanggal
$tanggal_kembali = $master_data['tanggal_kembali'] ? date('d/m/Y', strtotime($master_data['tanggal_kembali'])) : '-';
$tanggal_dibuat = $master_data['created_at'] ? date('d/m/Y H:i', strtotime($master_data['created_at'])) : '-';
$tanggal_pinjam = $master_data['tanggal_pinjam'] ? date('d/m/Y', strtotime($master_data['tanggal_pinjam'])) : '-';
$tanggal_harus_kembali = $master_data['tanggal_harus_kembali'] ? date('d/m/Y', strtotime($master_data['tanggal_harus_kembali'])) : '-';

// Hitung total buku dan total denda
$total_buku = count($detail_data);
$total_denda = 0;
foreach ($detail_data as $detail) {
    $total_denda += (float)$detail['denda'];
}

// Hitung hari keterlambatan
$terlambat_hari = $master_data['jumlah_hari_terlambat'] > 0 ? $master_data['jumlah_hari_terlambat'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengembalian - Perpustakaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B4513;
            --primary-dark: #654321;
            --primary-light: #A0522D;
            --success: #2E7D32;
            --warning: #FF8C00;
            --danger: #C62828;
            --border-radius: 12px;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        body {
            background: linear-gradient(135deg, #f5f5dc 0%, #e8e4d4 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.3rem;
        }
        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
            border: none;
        }
        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #FF9800);
            border: none;
            box-shadow: 0 4px 10px rgba(255, 140, 0, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, var(--success), #4CAF50);
            border: none;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }
        .badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 8px;
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--primary);
        }
        .breadcrumb-item a {
            color: var(--primary-dark);
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 600;
        }
        .display-4 {
            font-size: 3rem;
            font-weight: 300;
        }
        .cover-thumbnail {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .anggota-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }
        .book-cover {
            width: 100%;
            height: 150px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .book-cover:hover {
            transform: scale(1.05);
        }
        .modal-cover-img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../admin/index.php">
                <i class="fas fa-book-reader me-2"></i>
                Perpustakaan Admin
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user me-1"></i>
                    <?= $_SESSION['username'] ?? 'Admin' ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Daftar Pengembalian</a></li>
                        <li class="breadcrumb-item active">Detail Pengembalian</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    Detail Pengembalian
                </h2>
                <p class="text-muted mb-0">Kode: <?= htmlspecialchars($master_data['kode_pengembalian']) ?></p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <a href="edit.php?id=<?= $master_data['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Kolom Kiri: Info Pengembalian -->
            <div class="col-md-6">
                <!-- Informasi Pengembalian -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-undo me-2"></i> Informasi Pengembalian
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kode Pengembalian</label>
                                <p class="form-control-plaintext">
                                    <strong><?= htmlspecialchars($master_data['kode_pengembalian']) ?></strong>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kode Peminjaman</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-info">
                                        <?= htmlspecialchars($master_data['kode_peminjaman']) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Pinjam</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-calendar me-2 text-primary"></i>
                                    <?= $tanggal_pinjam ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Harus Kembali</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-calendar-check me-2 text-primary"></i>
                                    <?= $tanggal_harus_kembali ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Kembali</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-calendar me-2 text-primary"></i>
                                    <?= $tanggal_kembali ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Dibuat</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-clock me-2 text-primary"></i>
                                    <?= $tanggal_dibuat ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Jumlah Hari Terlambat</label>
                                <p class="form-control-plaintext">
                                    <?php if ($terlambat_hari > 0): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-clock me-1"></i><?= $terlambat_hari ?> hari
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Tepat waktu</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Total Denda</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-<?= $total_denda > 0 ? 'danger' : 'success' ?>">
                                        Rp <?= number_format($total_denda, 0, ',', '.') ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Petugas ID</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-dark">
                                        ID: <?= $master_data['petugas_id'] ?> (<?= htmlspecialchars($master_data['petugas_nama']) ?>)
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kondisi Umum</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-<?= getStatusBadgeColor($master_data['kondisi_buku']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $master_data['kondisi_buku'])) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informasi Anggota -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i> Informasi Anggota
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Nama Anggota</label>
                                <p class="form-control-plaintext">
                                    <div class="d-flex align-items-center">
                                        <div class="anggota-avatar me-3">
                                            <?= strtoupper(substr($master_data['nama_anggota'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($master_data['nama_anggota']) ?></strong>
                                        </div>
                                    </div>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kode Anggota</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-hashtag me-2 text-primary"></i>
                                    <?= htmlspecialchars($master_data['kode_anggota']) ?>
                                </p>
                            </div>
                            <?php if ($master_data['email']): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-envelope me-2 text-primary"></i>
                                    <?= htmlspecialchars($master_data['email']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            <?php if ($master_data['alamat']): ?>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Alamat</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                    <?= nl2br(htmlspecialchars($master_data['alamat'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Daftar Buku -->
            <div class="col-md-6">
                <!-- Daftar Buku yang Dikembalikan -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-book me-2"></i> Daftar Buku yang Dikembalikan</span>
                        <span class="badge bg-primary">
                            <i class="fas fa-book me-1"></i><?= $total_buku ?> buku
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if ($total_buku > 0): ?>
                            <div class="row g-3">
                                <?php foreach ($detail_data as $index => $buku): ?>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <?php if (!empty($buku['cover_buku'])): ?>
                                            <div class="position-relative">
                                                <img src="../uploads/cover/<?= htmlspecialchars($buku['cover_buku']) ?>"
                                                     class="card-img-top book-cover"
                                                     alt="<?= htmlspecialchars($buku['judul_buku']) ?>"
                                                     onclick="showCoverPreview('<?= htmlspecialchars($buku['cover_buku']) ?>', '<?= htmlspecialchars($buku['judul_buku']) ?>')"
                                                     onerror="this.src='../assets/img/default-book.png'">
                                                <span class="position-absolute top-0 end-0 m-2 badge bg-primary">
                                                    #<?= $index + 1 ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center"
                                                 style="height: 150px;"
                                                 onclick="showCoverPreview(null, '<?= htmlspecialchars($buku['judul_buku']) ?>')">
                                                <i class="fas fa-book fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title mb-1"><?= htmlspecialchars($buku['judul_buku']) ?></h6>
                                            <small class="text-muted d-block mb-2">
                                                <i class="fas fa-barcode me-1"></i>
                                                <?= htmlspecialchars($buku['kode_buku']) ?>
                                            </small>
                                            <p class="card-text small mb-2">
                                                <i class="fas fa-user-edit me-1"></i>
                                                <?= htmlspecialchars($buku['pengarang'] ?? '-') ?>
                                            </p>
                                            <div class="card-footer bg-transparent border-0 p-0 mt-2">
                                                <span class="badge bg-<?= getStatusBadgeColor($buku['kondisi_buku']) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $buku['kondisi_buku'])) ?>
                                                </span>
                                                <span class="badge bg-danger ms-2">
                                                    Rp <?= number_format($buku['denda'], 0, ',', '.') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-4x text-light mb-3"></i>
                                <p class="text-muted mb-0">Tidak ada data buku</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Keterangan Pengembalian -->
                <?php if (!empty($master_data['keterangan'])): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sticky-note me-2"></i> Keterangan Pengembalian
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= nl2br(htmlspecialchars($master_data['keterangan'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aksi Tambahan -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar
                    </a>
                    <div class="btn-group">
                        <a href="edit.php?id=<?= $master_data['id'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i> Edit Pengembalian
                        </a>
                        <a href="delete.php?id=<?= $master_data['id'] ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Hapus pengembalian <?= htmlspecialchars(addslashes($master_data['kode_pengembalian'])) ?>?')">
                            <i class="fas fa-trash me-2"></i> Hapus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Preview Cover -->
    <div class="modal fade" id="coverPreviewModal" tabindex="-1" aria-labelledby="coverPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="coverPreviewModalLabel">Preview Cover Buku</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalCoverImage" src="" class="modal-cover-img" alt="Cover Buku">
                    <div id="noCoverMessage" class="d-none">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Cover buku tidak tersedia
                        </div>
                    </div>
                    <h5 class="mt-3" id="modalBookTitle"></h5>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk menampilkan preview cover
        function showCoverPreview(coverFilename, bookTitle) {
            const modal = new bootstrap.Modal(document.getElementById('coverPreviewModal'));
            const modalCoverImage = document.getElementById('modalCoverImage');
            const modalBookTitle = document.getElementById('modalBookTitle');
            const noCoverMessage = document.getElementById('noCoverMessage');
            
            modalBookTitle.textContent = bookTitle;
            
            if (coverFilename) {
                modalCoverImage.src = '../uploads/cover/' + coverFilename;
                modalCoverImage.classList.remove('d-none');
                noCoverMessage.classList.add('d-none');
            } else {
                modalCoverImage.classList.add('d-none');
                noCoverMessage.classList.remove('d-none');
            }
            
            modal.show();
        }

        // Fungsi untuk warna badge status
        function getStatusBadgeColor(status) {
            switch(status) {
                case 'baik': return 'success';
                case 'rusak_ringan': return 'warning';
                case 'rusak_berat': return 'danger';
                case 'hilang': return 'dark';
                default: return 'secondary';
            }
        }

        // Inisialisasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltip untuk semua elemen dengan title
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>