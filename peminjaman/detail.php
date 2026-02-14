<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('peminjaman');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

// Ambil data peminjaman dari database
$query = "SELECT p.*, a.nama_anggota, a.kode_anggota, a.email, a.alamat
          FROM peminjaman p
          LEFT JOIN anggota a ON p.nama_anggota = a.nama_anggota
          WHERE p.id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$peminjaman = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$peminjaman) {
    $_SESSION['error'] = "Data peminjaman tidak ditemukan.";
    redirect('index.php');
}

// Ambil detail buku yang dipinjam dengan cover
$query_detail = "SELECT pd.*, b.kode_buku, b.judul_buku, b.pengarang, b.penerbit, b.tahun_terbit, b.cover, b.stok_tersedia
                 FROM peminjaman_detail pd
                 LEFT JOIN buku b ON pd.buku_id = b.id
                 WHERE pd.peminjaman_id = ?
                 ORDER BY pd.id";
$stmt_detail = mysqli_prepare($connection, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);
$buku_details = [];
while ($row = mysqli_fetch_assoc($result_detail)) {
    $buku_details[] = $row;
}
mysqli_stmt_close($stmt_detail);

// Format tanggal
$tanggal_pinjam = $peminjaman['tanggal_pinjam'] ? date('d/m/Y', strtotime($peminjaman['tanggal_pinjam'])) : '-';
$tanggal_harus_kembali = $peminjaman['tanggal_harus_kembali'] ? date('d/m/Y', strtotime($peminjaman['tanggal_harus_kembali'])) : '-';
$tanggal_kembali = $peminjaman['tanggal_harus_kembali'] ? date('d/m/Y', strtotime($peminjaman['tanggal_harus_kembali'])) : '-';

// Hitung total buku
$total_buku = count($buku_details);

// Hitung hari keterlambatan
$terlambat_hari = 0;
$denda = 0;
if ($peminjaman['status'] == 'dipinjam' || $peminjaman['status'] == 'terlambat') {
    $tenggat = new DateTime($peminjaman['tanggal_harus_kembali']);
    $hari_ini = new DateTime();
    if ($hari_ini > $tenggat) {
        $interval = $tenggat->diff($hari_ini);
        $terlambat_hari = $interval->days;
        $denda = $terlambat_hari * 1000; // Denda Rp 1.000 per hari
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Peminjaman - Perpustakaan</title>
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
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
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
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #D32F2F);
            border: none;
            box-shadow: 0 4px 10px rgba(198, 40, 40, 0.3);
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
        
        /* Preview Buku */
        .book-preview-cover {
            height: 300px;
            width: 190px;
            object-fit: cover;
            border-radius: 8px;
            border: 3px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .book-preview-cover:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .book-preview-card {
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .book-preview-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        .book-preview-card .card-body {
            padding: 12px !important;
        }
        
        .book-preview-card .card-title {
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
            font-weight: 600;
        }
        
        .book-preview-card .card-text {
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
        }
        
        .book-info-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 10px;
            z-index: 10;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }
        
        /* Avatar Anggota */
        .anggota-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-size: 2rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Info Card */
        .info-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .info-card-label {
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.9rem;
        }
        
        .info-card-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
        }
        
        /* Denda */
        .denda-box {
            background: linear-gradient(135deg, var(--danger), #D32F2F);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(198, 40, 40, 0.3);
        }
        
        .denda-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
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
                        <li class="breadcrumb-item"><a href="index.php">Daftar Peminjaman</a></li>
                        <li class="breadcrumb-item active">Detail Peminjaman</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    Detail Peminjaman
                </h2>
                <p class="text-muted mb-0">
                    <span class="badge bg-primary">
                        <i class="fas fa-hashtag me-1"></i>
                        <?= htmlspecialchars($peminjaman['kode_peminjaman']) ?>
                    </span>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <a href="edit.php?id=<?= $peminjaman['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Kolom Kiri: Info Peminjaman -->
            <div class="col-lg-8">
                <!-- Informasi Peminjaman -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-clipboard-list me-2"></i> Informasi Peminjaman
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Status</div>
                                    <div class="info-card-value">
                                        <span class="badge bg-<?= getStatusBadgeColor($peminjaman['status']) ?> fs-6">
                                            <i class="fas fa-<?= $peminjaman['status'] == 'dipinjam' ? 'clock' : ($peminjaman['status'] == 'dikembalikan' ? 'check-circle' : 'exclamation-triangle') ?> me-1"></i>
                                            <?= ucfirst($peminjaman['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Tanggal Pinjam</div>
                                    <div class="info-card-value">
                                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                        <?= $tanggal_pinjam ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Tanggal Harus Kembali</div>
                                    <div class="info-card-value">
                                        <i class="fas fa-calendar-check me-2 text-primary"></i>
                                        <?= $tanggal_harus_kembali ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($peminjaman['status'] == 'dikembalikan'): ?>
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Tanggal Kembali</div>
                                    <div class="info-card-value">
                                        <i class="fas fa-calendar me-2 text-success"></i>
                                        <?= $tanggal_kembali ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($terlambat_hari > 0 && $peminjaman['status'] != 'dikembalikan'): ?>
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Keterlambatan</div>
                                    <div class="info-card-value text-danger">
                                        <i class="fas fa-clock me-2"></i>
                                        <?= $terlambat_hari ?> hari
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Denda</div>
                                    <div class="info-card-value text-danger">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        Rp <?= number_format($denda, 0, ',', '.') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-4">
                                <div class="info-card">
                                    <div class="info-card-label">Jumlah Buku</div>
                                    <div class="info-card-value">
                                        <span class="badge bg-info fs-6">
                                            <i class="fas fa-book me-1"></i><?= $total_buku ?> buku
                                        </span>
                                    </div>
                                </div>
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
                                <label class="form-label fw-bold">Profil Anggota</label>
                                <div class="d-flex align-items-center">
                                    <div class="anggota-avatar me-4">
                                        <?= strtoupper(substr($peminjaman['nama_anggota'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($peminjaman['nama_anggota']) ?></h5>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-hashtag me-2"></i>
                                            <?= htmlspecialchars($peminjaman['kode_anggota']) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php if ($peminjaman['email']): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-envelope me-2 text-primary"></i>
                                    <?= htmlspecialchars($peminjaman['email']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            <?php if ($peminjaman['alamat']): ?>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Alamat</label>
                                <p class="form-control-plaintext">
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                    <?= nl2br(htmlspecialchars($peminjaman['alamat'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Preview Buku -->
            <div class="col-lg-4">
                <!-- Preview Buku Terpilih -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-eye me-2"></i> Preview Buku
                    </div>
                    <div class="card-body">
                        <?php if ($total_buku > 0): ?>
                            <?php foreach ($buku_details as $index => $buku): ?>
                                <div class="mb-4">
                                    <div class="position-relative mb-3">
                                        <?php if ($buku['cover']): ?>
                                            <img src="../uploads/cover/<?= htmlspecialchars($buku['cover']) ?>"
                                                 class="book-preview-cover w-100"
                                                 alt="<?= htmlspecialchars($buku['judul_buku']) ?>"
                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\"book-preview-cover w-100 bg-light d-flex align-items-center justify-content-center\"><i class=\"fas fa-book fa-4x text-muted\"></i></div>'">
                                            <span class="book-info-badge">#<?= $index + 1 ?></span>
                                        <?php else: ?>
                                            <div class="book-preview-cover w-100 bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-book fa-4x text-muted"></i>
                                            </div>
                                            <span class="book-info-badge">#<?= $index + 1 ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold mb-2"><?= htmlspecialchars($buku['judul_buku']) ?></h6>
                                    <small class="text-muted d-block mb-1">
                                        <i class="fas fa-barcode me-1"></i>
                                        <?= htmlspecialchars($buku['kode_buku']) ?>
                                    </small>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-user-edit me-1"></i>
                                        <?= htmlspecialchars($buku['pengarang'] ?? '-') ?>
                                    </p>
                                    <?php if ($buku['penerbit']): ?>
                                        <p class="text-muted small mb-1">
                                            <i class="fas fa-building me-1"></i>
                                            <?= htmlspecialchars($buku['penerbit']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($buku['tahun_terbit']): ?>
                                        <p class="text-muted small mb-0">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= htmlspecialchars($buku['tahun_terbit']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($index < $total_buku - 1): ?>
                                    <hr class="my-4">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-4x text-light mb-3"></i>
                                <p class="text-muted mb-0">Tidak ada data buku</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Aksi Cepat -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tasks me-2"></i> Aksi Cepat
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($peminjaman['status'] == 'dipinjam' || $peminjaman['status'] == 'terlambat'): ?>
                                <a href="kembalikan.php?id=<?= $peminjaman['id'] ?>" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle me-2"></i> Tandai Kembali
                                </a>
                            <?php endif; ?>
                            <a href="edit.php?id=<?= $peminjaman['id'] ?>" class="btn btn-warning btn-lg">
                                <i class="fas fa-edit me-2"></i> Edit Peminjaman
                            </a>
                            <a href="delete.php?id=<?= $peminjaman['id'] ?>"
                               class="btn btn-danger btn-lg"
                               onclick="return confirm('Hapus peminjaman <?= htmlspecialchars(addslashes($peminjaman['kode_peminjaman'])) ?>?')">
                                <i class="fas fa-trash me-2"></i> Hapus
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>