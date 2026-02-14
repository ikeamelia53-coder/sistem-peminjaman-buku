<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('buku');
require_once '../config/database.php';
$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');
// Ambil data buku dari database
$stmt = mysqli_prepare($connection, "SELECT * FROM `buku` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$buku = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if (!$buku) {
$_SESSION['error'] = "Buku tidak ditemukan.";
redirect('index.php');
}
// Ambil statistik peminjaman
$peminjaman_query = "
SELECT
COUNT(*) as total_peminjaman,
SUM(CASE WHEN status_buku = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam,
SUM(CASE WHEN status_buku = 'selesai' THEN 1 ELSE 0 END) as sudah_kembali,
SUM(CASE WHEN status_buku = 'terlambat' THEN 1 ELSE 0 END) as terlambat
FROM peminjaman_detail
WHERE buku_id = ?
";
$stmt = mysqli_prepare($connection, $peminjaman_query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$statistik = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Buku - Perpustakaan</title>
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            box-shadow: 0 4px 10px rgba(139, 69, 19, 0.3);
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
                        <li class="breadcrumb-item"><a href="index.php">Daftar Buku</a></li>
                        <li class="breadcrumb-item active">Detail Buku</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-book-open me-2 text-primary"></i>
                    Detail Buku
                </h2>
                <p class="text-muted mb-0">Kode: <?= htmlspecialchars($buku['kode_buku']) ?></p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <a href="edit.php?id=<?= $buku['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Kolom Kiri: Cover dan Info Dasar -->
            <div class="col-md-4">
                <!-- Cover -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-image me-2"></i> Cover Buku
                    </div>
                    <div class="card-body text-center">
                        <?php if ($buku['cover'] && file_exists('../uploads/cover/' . $buku['cover'])): ?>
                        <img src="../uploads/cover/<?= htmlspecialchars($buku['cover']) ?>"
                             alt="Cover" class="img-fluid rounded"
                             style="max-height: 400px; object-fit: contain;">
                        <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                             style="height: 400px;">
                            <i class="fas fa-book fa-6x text-muted"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Info Singkat -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i> Info Singkat
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Kode Buku</small>
                            <div class="fw-bold"><?= htmlspecialchars($buku['kode_buku']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">ISBN</small>
                            <div><?= htmlspecialchars($buku['isbn'] ?: '-') ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Status</small>
                            <div>
                                <span class="badge bg-<?= getStatusBadgeColor($buku['status']) ?>">
                                    <?= ucfirst($buku['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Lokasi Rak</small>
                            <div>
                                <?php if ($buku['lokasi_rak']): ?>
                                <span class="badge bg-dark"><?= htmlspecialchars($buku['lokasi_rak']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistik Peminjaman -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Statistik Peminjaman
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="fw-bold text-primary fs-4"><?= $statistik['total_peminjaman'] ?></div>
                                <small class="text-muted">Total</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="fw-bold text-warning fs-4"><?= $statistik['sedang_dipinjam'] ?></div>
                                <small class="text-muted">Dipinjam</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold text-success fs-4"><?= $statistik['sudah_kembali'] ?></div>
                                <small class="text-muted">Kembali</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold text-danger fs-4"><?= $statistik['terlambat'] ?></div>
                                <small class="text-muted">Terlambat</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Kolom Kanan: Detail Lengkap -->
            <div class="col-md-8">
                <!-- Informasi Buku -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-book me-2"></i> Informasi Buku
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Judul Buku</label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($buku['judul_buku']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kategori</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-info"><?= htmlspecialchars($buku['kategori']) ?></span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Pengarang</label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($buku['pengarang']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Penerbit</label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($buku['penerbit']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tahun Terbit</label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($buku['tahun_terbit'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Bahasa</label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($buku['bahasa']) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Jumlah Halaman</label>
                                <p class="form-control-plaintext"><?= $buku['halaman'] ?: '-' ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Ditambahkan</label>
                                <p class="form-control-plaintext">
                                    <?= date('d/m/Y H:i', strtotime($buku['created_at'] ?? 'now')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stok dan Ketersediaan -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-boxes me-2"></i> Stok dan Ketersediaan
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <label class="form-label fw-bold">Stok Total</label>
                                    <div class="display-4 text-primary"><?= $buku['stok_total'] ?></div>
                                    <small class="text-muted">Eksemplar tersedia</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="text-center">
                                    <label class="form-label fw-bold">Stok Tersedia</label>
                                    <div class="display-4 text-<?= $buku['stok_tersedia'] > 0 ? 'success' : 'danger' ?>">
                                        <?= $buku['stok_tersedia'] ?>
                                    </div>
                                    <small class="text-muted">Eksemplar dapat dipinjam</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold mb-3">Persentase Ketersediaan</label>
                                <div class="progress" style="height: 25px;">
                                    <?php
                                    $percentage = $buku['stok_total'] > 0
                                        ? ($buku['stok_tersedia'] / $buku['stok_total']) * 100
                                        : 0;
                                    $color = $percentage >= 50 ? 'bg-success' :
                                             ($percentage >= 20 ? 'bg-warning' : 'bg-danger');
                                    ?>
                                    <div class="progress-bar <?= $color ?> progress-bar-striped"
                                         style="width: <?= $percentage ?>%">
                                        <span class="fw-bold"><?= round($percentage, 1) ?>%</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">0%</small>
                                    <small class="text-muted">50%</small>
                                    <small class="text-muted">100%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sinopsis -->
                <?php if (!empty($buku['sinopsis'])): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-align-left me-2"></i> Sinopsis
                    </div>
                    <div class="card-body">
                        <div class="sinopsis-content" style="line-height: 1.8; text-align: justify;">
                            <?= nl2br(htmlspecialchars($buku['sinopsis'])) ?>
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
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                    <div class="btn-group">
                        <?php if ($buku['stok_tersedia'] > 0): ?>
                        <a href="../peminjaman/add.php?buku_id=<?= $buku['id'] ?>" class="btn btn-success">
                            <i class="fas fa-book-reader me-1"></i> Pinjam Buku
                        </a>
                        <?php endif; ?>
                        <a href="edit.php?id=<?= $buku['id'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i> Edit Buku
                        </a>
                        <?php if ($buku['stok_tersedia'] == $buku['stok_total']): ?>
                        <a href="delete.php?id=<?= $buku['id'] ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Hapus buku <?= htmlspecialchars(addslashes($buku['judul_buku'])) ?>?')">
                            <i class="fas fa-trash me-1"></i> Hapus Buku
                        </a>
                        <?php else: ?>
                        <button type="button" class="btn btn-danger" disabled
                                title="Buku tidak dapat dihapus karena sedang dipinjam">
                            <i class="fas fa-ban me-1"></i> Tidak Dapat Dihapus
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>