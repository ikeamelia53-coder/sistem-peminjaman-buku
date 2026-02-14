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
$query = "SELECT p.*, a.nama_anggota, a.kode_anggota
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
    redirect('index.php');
}

// Ambil detail buku yang sudah dipinjam
$query_detail = "SELECT pd.*, b.kode_buku, b.judul_buku, b.pengarang, b.penerbit, b.cover, b.stok_tersedia
                 FROM peminjaman_detail pd
                 LEFT JOIN buku b ON pd.buku_id = b.id
                 WHERE pd.peminjaman_id = ?
                 ORDER BY pd.id";
$stmt_detail = mysqli_prepare($connection, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);
$buku_details_existing = [];
while ($row = mysqli_fetch_assoc($result_detail)) {
    $buku_details_existing[] = $row;
}
mysqli_stmt_close($stmt_detail);

// --- FUNGSI ---
function getAnggotaList($connection) {
    $anggota_list = [];
    $query = "SELECT id, nama_anggota, kode_anggota, status
              FROM anggota
              WHERE status = 'aktif'
              ORDER BY nama_anggota";
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $anggota_list[] = $row;
        }
        mysqli_free_result($result);
    }
    return $anggota_list;
}

function getBukuList($connection) {
    $buku_list = [];
    $query = "SELECT id, kode_buku, judul_buku, cover, stok_tersedia, pengarang, penerbit
              FROM buku
              WHERE status = 'tersedia'
              ORDER BY judul_buku";
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $buku_list[] = $row;
        }
        mysqli_free_result($result);
    }
    return $buku_list;
}
// --- AKHIR FUNGSI ---

$error = $success = '';

// Ambil daftar anggota dan buku dari database
$anggota_list = getAnggotaList($connection);
$buku_list = getBukuList($connection);

// --- PROSES FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_peminjaman = $_POST['kode_peminjaman'] ?? '';
    $nama_anggota = $_POST['nama_anggota'] ?? '';
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_harus_kembali = $_POST['tanggal_harus_kembali'] ?? '';
    $status = $_POST['status'] ?? 'dipinjam';
    $buku_ids = $_POST['buku_id'] ?? [];
    
    // Validasi
    if (empty($nama_anggota) || empty($buku_ids)) {
        $error = "Nama anggota dan buku harus dipilih!";
    } elseif (count($buku_ids) < 1 || count($buku_ids) > 5) {
        $error = "Jumlah buku harus antara 1-5!";
    } else {
        // Cek duplikat buku
        $unique_ids = array_unique($buku_ids);
        if (count($unique_ids) !== count($buku_ids)) {
            $error = "Tidak boleh meminjam buku yang sama lebih dari satu kali!";
        } else {
            // Mulai transaction
            mysqli_begin_transaction($connection);
            
            try {
                // Update tabel peminjaman
                $query_peminjaman = "UPDATE peminjaman 
                                     SET kode_peminjaman = ?, nama_anggota = ?, 
                                         tanggal_pinjam = ?, tanggal_harus_kembali = ?, status = ?
                                     WHERE id = ?";
                $stmt_peminjaman = mysqli_prepare($connection, $query_peminjaman);
                mysqli_stmt_bind_param($stmt_peminjaman, "sssssi", 
                    $kode_peminjaman, $nama_anggota, $tanggal_pinjam, 
                    $tanggal_harus_kembali, $status, $id);
                mysqli_stmt_execute($stmt_peminjaman);
                mysqli_stmt_close($stmt_peminjaman);
                
                // Hapus detail lama
                $query_delete_detail = "DELETE FROM peminjaman_detail WHERE peminjaman_id = ?";
                $stmt_delete = mysqli_prepare($connection, $query_delete_detail);
                mysqli_stmt_bind_param($stmt_delete, "i", $id);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);
                
                // Insert detail baru & update stok
                foreach ($buku_ids as $buku_id) {
                    // Insert detail
                    $query_detail = "INSERT INTO peminjaman_detail (peminjaman_id, buku_id) VALUES (?, ?)";
                    $stmt_detail = mysqli_prepare($connection, $query_detail);
                    mysqli_stmt_bind_param($stmt_detail, "ii", $id, $buku_id);
                    mysqli_stmt_execute($stmt_detail);
                    mysqli_stmt_close($stmt_detail);
                }
                
                mysqli_commit($connection);
                $_SESSION['success'] = "Peminjaman berhasil diperbarui!";
                header("Location: index.php");
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($connection);
                $error = "Gagal memperbarui: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Peminjaman - Perpustakaan</title>
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            border: none;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
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
        }
        
        .book-preview-card {
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .book-preview-card .card-body {
            padding: 8px 10px !important;
        }
        
        .book-preview-card .card-title {
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .book-preview-card .card-text {
            font-size: 0.75rem;
            margin-bottom: 0.4rem;
        }
        
        .book-info-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        /* Cover placeholder */
        .book-cover-placeholder {
            width: 100px;
            height: 140px;
            background: linear-gradient(135deg, #e0e0e0, #f0f0f0);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8B4513;
            font-size: 2rem;
        }
        
        .book-cover {
            width: 100px;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        .book-row {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
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
                        <li class="breadcrumb-item active">Edit Peminjaman</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-edit me-2 text-warning"></i>
                    Edit Peminjaman
                </h2>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Kode: <?= htmlspecialchars($peminjaman['kode_peminjaman']) ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="index.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i> Kembali
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="row g-4">
                <!-- Kolom Kiri - Form Utama -->
                <div class="col-xl-8 col-lg-7">
                    <!-- Informasi Peminjaman -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-white p-2 me-3">
                                    <i class="fas fa-clipboard-list text-primary fs-5"></i>
                                </div>
                                <h5 class="mb-0">Informasi Peminjaman</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="kode_peminjaman" class="form-label">
                                            <i class="fas fa-hashtag me-2 text-primary"></i>Kode Peminjaman
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="kode_peminjaman" id="kode_peminjaman"
                                               class="form-control" required
                                               value="<?= htmlspecialchars($peminjaman['kode_peminjaman']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="nama_anggota" class="form-label">
                                            <i class="fas fa-user me-2 text-primary"></i>Nama Anggota
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="nama_anggota" id="nama_anggota" class="form-select" required>
                                            <option value="">-- Pilih Anggota --</option>
                                            <?php foreach ($anggota_list as $anggota): ?>
                                                <option value="<?= htmlspecialchars($anggota['nama_anggota']) ?>"
                                                        <?= $anggota['nama_anggota'] == $peminjaman['nama_anggota'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($anggota['nama_anggota']) ?>
                                                    (<?= $anggota['kode_anggota'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="tanggal_pinjam" class="form-label">
                                            <i class="fas fa-calendar-plus me-2 text-primary"></i>Tanggal Pinjam
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" name="tanggal_pinjam" id="tanggal_pinjam"
                                               class="form-control" required
                                               value="<?= htmlspecialchars($peminjaman['tanggal_pinjam']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="tanggal_harus_kembali" class="form-label">
                                            <i class="fas fa-calendar-check me-2 text-primary"></i>Tanggal Harus Kembali
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" name="tanggal_harus_kembali" id="tanggal_harus_kembali"
                                               class="form-control" required
                                               value="<?= htmlspecialchars($peminjaman['tanggal_harus_kembali']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label for="status" class="form-label">
                                            <i class="fas fa-info-circle me-2 text-primary"></i>Status
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="status" id="status" class="form-select" required>
                                            <option value="dipinjam" <?= $peminjaman['status'] == 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                                            <option value="dikembalikan" <?= $peminjaman['status'] == 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                                            <option value="terlambat" <?= $peminjaman['status'] == 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="fas fa-info-circle fa-2x me-3 text-primary"></i>
                                        <div>
                                            <h6 class="alert-heading mb-1">Perhatian!</h6>
                                            <p class="mb-0">Peminjaman minimal 1 buku, maksimal 5 buku (boleh pilih buku yang sama).</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Buku yang Dipinjam -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-white p-2 me-3">
                                    <i class="fas fa-book text-primary fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Daftar Buku yang Dipinjam</h5>
                                    <small class="text-muted">Pilih 1 sampai 5 buku</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-white text-primary me-3" id="book-counter">
                                    <span id="selected-count"><?= count($buku_details_existing) ?></span>/5 buku
                                </span>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addBookRow()" id="add-book-btn">
                                    <i class="fas fa-plus me-1"></i> Tambah Buku
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="book-list">
                                <!-- Baris buku dari data existing -->
                                <?php foreach ($buku_details_existing as $index => $buku): ?>
                                    <div class="book-row" id="book-row-<?= $index + 1 ?>">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">
                                                    <i class="fas fa-book me-1"></i>Pilih Buku
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <select name="buku_id[]" class="form-select buku-select" required onchange="updateBookInfo(<?= $index + 1 ?>)">
                                                    <option value="">-- Pilih Buku --</option>
                                                    <?php foreach ($buku_list as $b): ?>
                                                        <option value="<?= $b['id'] ?>"
                                                                data-cover="<?= $b['cover'] ?? '' ?>"
                                                                data-judul="<?= htmlspecialchars($b['judul_buku']) ?>"
                                                                data-kode="<?= $b['kode_buku'] ?>"
                                                                data-pengarang="<?= $b['pengarang'] ?? '' ?>"
                                                                data-penerbit="<?= $b['penerbit'] ?? '' ?>"
                                                                data-stok="<?= $b['stok_tersedia'] ?>"
                                                                <?= ($b['id'] == $buku['buku_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($b['judul_buku']) ?>
                                                            <span class="text-muted">(<?= $b['kode_buku'] ?>)</span> - Stok: <?= $b['stok_tersedia'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="mt-2 buku-info" id="book-info-<?= $index + 1 ?>">
                                                    <?php if ($buku['buku_id']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-check-circle text-success me-2"></i>
                                                            <div>
                                                                <strong><?= htmlspecialchars($buku['judul_buku']) ?></strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?= htmlspecialchars($buku['pengarang'] ?? '') ?>
                                                                    <?= $buku['penerbit'] ? ' • ' . htmlspecialchars($buku['penerbit']) : '' ?>
                                                                </small>
                                                                <br>
                                                                <small class="text-success">
                                                                    <i class="fas fa-box me-1"></i>Stok: <?= $buku['stok_tersedia'] ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3">
                                                        <?php if ($buku['cover']): ?>
                                                            <img id="cover-image-<?= $index + 1 ?>"
                                                                 class="book-cover"
                                                                 src="../uploads/cover/<?= htmlspecialchars($buku['cover']) ?>"
                                                                 alt="Cover Buku">
                                                            <div id="cover-placeholder-<?= $index + 1 ?>" class="book-cover-placeholder d-none">
                                                                <i class="fas fa-book"></i>
                                                            </div>
                                                        <?php else: ?>
                                                            <div id="cover-placeholder-<?= $index + 1 ?>" class="book-cover-placeholder">
                                                                <i class="fas fa-book"></i>
                                                            </div>
                                                            <img id="cover-image-<?= $index + 1 ?>" class="book-cover d-none" src="" alt="Cover Buku">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="mb-2">
                                                            <small class="text-muted d-block">Pengarang</small>
                                                            <strong id="author-<?= $index + 1 ?>"><?= htmlspecialchars($buku['pengarang'] ?? '-') ?></strong>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted d-block">Kode Buku</small>
                                                            <strong id="kode-<?= $index + 1 ?>"><?= htmlspecialchars($buku['kode_buku']) ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <button type="button" class="btn btn-danger btn-sm" onclick="removeBookRow(<?= $index + 1 ?>)"
                                                        <?= count($buku_details_existing) <= 1 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Pesan jika belum ada buku -->
                            <div id="empty-book-message" class="text-center py-5" style="<?= count($buku_details_existing) > 0 ? 'display: none;' : '' ?>">
                                <div class="mb-4">
                                    <i class="fas fa-book fa-4x text-light mb-3"></i>
                                </div>
                                <h5 class="text-muted mb-2">Belum ada buku dipilih</h5>
                                <p class="text-muted mb-4">Klik tombol "Tambah Buku" untuk mulai menambahkan buku</p>
                                <button type="button" class="btn btn-primary" onclick="addBookRow()">
                                    <i class="fas fa-plus me-2"></i> Tambah Buku Pertama
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan - Preview dan Informasi -->
                <div class="col-xl-4 col-lg-5">
                    <!-- Ringkasan Peminjaman -->
                    <div class="card summary-card">
                        <div class="card-header border-0">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-white p-2 me-3">
                                    <i class="fas fa-chart-pie text-danger fs-5"></i>
                                </div>
                                <h5 class="mb-0">Ringkasan Peminjaman</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Kode Peminjaman:</span>
                                    <span class="fw-bold" id="summary-kode"><?= htmlspecialchars($peminjaman['kode_peminjaman']) ?></span>
                                </div>
                            </div>
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Buku:</span>
                                    <span class="fw-bold" id="summary-total-buku"><?= count($buku_details_existing) ?> buku</span>
                                </div>
                            </div>
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Lama Pinjam:</span>
                                    <span class="fw-bold" id="summary-lama">
                                        <?php
                                        $tgl_pinjam = new DateTime($peminjaman['tanggal_pinjam']);
                                        $tgl_akhir = new DateTime($peminjaman['tanggal_harus_kembali']);
                                        $interval = $tgl_pinjam->diff($tgl_akhir);
                                        echo $interval->days . ' hari';
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Status:</span>
                                    <span class="badge bg-success" id="summary-status">Siap Disimpan</span>
                                </div>
                            </div>
                            <hr class="my-4 border-white">
                            <div class="text-center">
                                <button type="submit" class="btn btn-light btn-lg w-100" id="submit-btn">
                                    <i class="fas fa-save me-2"></i> Perbarui Peminjaman
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Buku -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-white p-2 me-3">
                                    <i class="fas fa-eye text-primary fs-5"></i>
                                </div>
                                <h5 class="mb-0">Preview Buku Terpilih</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="book-preview" class="row g-3">
                                <?php if (count($buku_details_existing) > 0): ?>
                                    <?php foreach ($buku_details_existing as $index => $buku): ?>
                                        <div class="col-12">
                                            <div class="card book-preview-card">
                                                <div class="row g-0">
                                                    <div class="col-4">
                                                        <div class="position-relative h-100">
                                                            <?php if ($buku['cover']): ?>
                                                                <img src="../uploads/cover/<?= htmlspecialchars($buku['cover']) ?>"
                                                                     class="book-preview-cover" 
                                                                     alt="<?= htmlspecialchars($buku['judul_buku']) ?>"
                                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\"book-preview-cover bg-gradient-primary d-flex align-items-center justify-content-center\"><i class=\"fas fa-book fa-3x text-white\"></i></div>'">
                                                            <?php else: ?>
                                                                <div class="book-preview-cover bg-gradient-primary d-flex align-items-center justify-content-center">
                                                                    <i class="fas fa-book fa-3x text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <span class="book-info-badge">#<?= $index + 1 ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8">
                                                        <div class="card-body p-3">
                                                            <h6 class="card-title mb-1 text-truncate"><?= htmlspecialchars($buku['judul_buku']) ?></h6>
                                                            <p class="card-text small text-muted mb-2">
                                                                <i class="fas fa-user-edit me-1"></i><?= htmlspecialchars($buku['pengarang'] ?? '-') ?>
                                                            </p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small class="text-primary">
                                                                    <i class="fas fa-barcode me-1"></i><?= htmlspecialchars($buku['kode_buku']) ?>
                                                                </small>
                                                                <span class="badge bg-primary">
                                                                    <i class="fas fa-book me-1"></i>Buku <?= $index + 1 ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div id="empty-preview" class="text-center py-4" style="display: none;">
                                        <div class="mb-3">
                                            <i class="fas fa-book-open fa-3x text-light"></i>
                                        </div>
                                        <p class="text-muted mb-0">Pilih buku untuk melihat preview</p>
                                    </div>
                                <?php else: ?>
                                    <div id="empty-preview" class="text-center py-4">
                                        <div class="mb-3">
                                            <i class="fas fa-book-open fa-3x text-light"></i>
                                        </div>
                                        <p class="text-muted mb-0">Pilih buku untuk melihat preview</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data buku untuk referensi
        const bukuData = <?= json_encode($buku_list) ?>;
        let bookRows = <?= count($buku_details_existing) ?>;
        let bookCounter = <?= count($buku_details_existing) ?>;
        const maxBooks = 5;
        const minBooks = 1;
        let bukuDipilih = [];

        // Tambah baris buku baru
        function addBookRow() {
            if (bookCounter >= maxBooks) {
                alert(`Maksimal hanya boleh meminjam ${maxBooks} buku.`);
                return;
            }
            
            bookCounter++;
            const bookList = document.getElementById('book-list');
            const emptyMessage = document.getElementById('empty-book-message');
            
            // Sembunyikan pesan kosong
            if (emptyMessage) {
                emptyMessage.style.display = 'none';
            }
            
            // Buat baris buku baru
            const bookRow = document.createElement('div');
            bookRow.className = 'book-row';
            bookRow.id = `book-row-${bookCounter}`;
            bookRow.innerHTML = `
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-book me-1"></i>Pilih Buku
                            <span class="text-danger">*</span>
                        </label>
                        <select name="buku_id[]" class="form-select buku-select" required onchange="updateBookInfo(${bookCounter})">
                            <option value="">-- Pilih Buku --</option>
                            ${bukuData.map(buku => `
                                <option value="${buku.id}"
                                        data-cover="${buku.cover || ''}"
                                        data-judul="${buku.judul_buku}"
                                        data-kode="${buku.kode_buku}"
                                        data-pengarang="${buku.pengarang || ''}"
                                        data-penerbit="${buku.penerbit || ''}"
                                        data-stok="${buku.stok_tersedia}">
                                    ${buku.judul_buku}
                                    <span class="text-muted">(${buku.kode_buku})</span> - Stok: ${buku.stok_tersedia}
                                </option>
                            `).join('')}
                        </select>
                        <div class="mt-2 buku-info" id="book-info-${bookCounter}"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div id="cover-placeholder-${bookCounter}" class="book-cover-placeholder">
                                    <i class="fas fa-book"></i>
                                </div>
                                <img id="cover-image-${bookCounter}" class="book-cover d-none" src="" alt="Cover Buku">
                            </div>
                            <div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Pengarang</small>
                                    <strong id="author-${bookCounter}">-</strong>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Kode Buku</small>
                                    <strong id="kode-${bookCounter}">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeBookRow(${bookCounter})" ${bookCounter === 1 ? 'disabled' : ''}>
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            bookList.appendChild(bookRow);
            bookRows++;
            updateBookCounter();
            updateSummary();
            updateSubmitButton();
            updatePreview();
        }

        // Hapus baris buku
        function removeBookRow(rowId) {
            if (bookRows <= minBooks) {
                alert(`Minimal harus meminjam ${minBooks} buku.`);
                return;
            }
            
            const row = document.getElementById(`book-row-${rowId}`);
            if (row) {
                row.remove();
                bookRows--;
                bookCounter--;
                
                // Update ulang ID untuk baris yang tersisa
                const bookRowsList = document.querySelectorAll('.book-row');
                bookRowsList.forEach((row, index) => {
                    const newId = index + 1;
                    row.id = `book-row-${newId}`;
                });
                
                // Tampilkan pesan jika semua buku dihapus
                if (bookRows === 0) {
                    document.getElementById('empty-book-message').style.display = 'block';
                }
                
                updateBookCounter();
                updatePreview();
                updateSummary();
                updateSubmitButton();
            }
        }

        // Update informasi buku yang dipilih
        function updateBookInfo(rowId) {
            const select = document.querySelector(`#book-row-${rowId} .buku-select`);
            const selectedOption = select.options[select.selectedIndex];
            const bukuId = select.value;
            const coverPlaceholder = document.getElementById(`cover-placeholder-${rowId}`);
            const coverImage = document.getElementById(`cover-image-${rowId}`);
            const bookInfo = document.getElementById(`book-info-${rowId}`);
            const authorElement = document.getElementById(`author-${rowId}`);
            const kodeElement = document.getElementById(`kode-${rowId}`);
            
            if (bukuId) {
                const cover = selectedOption.getAttribute('data-cover');
                const judul = selectedOption.getAttribute('data-judul');
                const kode = selectedOption.getAttribute('data-kode');
                const pengarang = selectedOption.getAttribute('data-pengarang');
                const stok = parseInt(selectedOption.getAttribute('data-stok'));
                
                // Update cover
                if (cover) {
                    coverPlaceholder.classList.add('d-none');
                    coverImage.classList.remove('d-none');
                    coverImage.src = `../uploads/cover/${cover}`;
                    coverImage.alt = `Cover ${judul}`;
                } else {
                    coverPlaceholder.classList.remove('d-none');
                    coverImage.classList.add('d-none');
                }
                
                // Update info
                authorElement.textContent = pengarang || '-';
                kodeElement.textContent = kode;
                bookInfo.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <div>
                            <strong>${judul}</strong>
                            <br>
                            <small class="text-success">
                                <i class="fas fa-box me-1"></i>Stok tersedia: ${stok}
                            </small>
                        </div>
                    </div>
                `;
                
                // Tambah ke array bukuDipilih jika belum ada
                if (!bukuDipilih.includes(bukuId)) {
                    bukuDipilih.push(bukuId);
                }
            } else {
                // Reset jika buku tidak dipilih
                coverPlaceholder.classList.remove('d-none');
                coverImage.classList.add('d-none');
                authorElement.textContent = '-';
                kodeElement.textContent = '-';
                bookInfo.innerHTML = '';
                
                // Hapus dari array bukuDipilih
                const index = bukuDipilih.indexOf(bukuId);
                if (index > -1) {
                    bukuDipilih.splice(index, 1);
                }
            }
            
            updatePreview();
            updateSummary();
            updateSubmitButton();
        }

        // Update preview buku
        function updatePreview() {
            const previewContainer = document.getElementById('book-preview');
            const emptyPreview = document.getElementById('empty-preview');
            let previewHTML = '';
            let selectedBooks = 0;
            
            // Kumpulkan buku yang dipilih
            const selectedBooksData = [];
            for (let i = 1; i <= bookCounter; i++) {
                const row = document.getElementById(`book-row-${i}`);
                if (row) {
                    const select = row.querySelector('.buku-select');
                    if (select && select.value) {
                        const selectedOption = select.options[select.selectedIndex];
                        selectedBooks++;
                        selectedBooksData.push({
                            cover: selectedOption.getAttribute('data-cover'),
                            judul: selectedOption.getAttribute('data-judul'),
                            kode: selectedOption.getAttribute('data-kode'),
                            pengarang: selectedOption.getAttribute('data-pengarang'),
                            penerbit: selectedOption.getAttribute('data-penerbit')
                        });
                    }
                }
            }
            
            // Buat preview card
            selectedBooksData.forEach((book, index) => {
                previewHTML += `
                    <div class="col-12">
                        <div class="card book-preview-card">
                            <div class="row g-0">
                                <div class="col-4">
                                    <div class="position-relative h-100">
                                        ${book.cover ?
                                            `<img src="../uploads/cover/${book.cover}" class="book-preview-cover" alt="${book.judul}"
                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\"book-preview-cover bg-gradient-primary d-flex align-items-center justify-content-center\"><i class=\"fas fa-book fa-3x text-white\"></i></div>'">` :
                                            `<div class="book-preview-cover bg-gradient-primary d-flex align-items-center justify-content-center">
                                                <i class="fas fa-book fa-3x text-white"></i>
                                            </div>`}
                                        <span class="book-info-badge">#${index + 1}</span>
                                    </div>
                                </div>
                                <div class="col-8">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1 text-truncate">${book.judul}</h6>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="fas fa-user-edit me-1"></i>${book.pengarang || '-'}
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-primary">
                                                <i class="fas fa-barcode me-1"></i>${book.kode}
                                            </small>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-book me-1"></i>Buku ${index + 1}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Update tampilan
            if (selectedBooks > 0) {
                if (emptyPreview) emptyPreview.style.display = 'none';
                previewContainer.innerHTML = previewHTML;
            } else {
                if (emptyPreview) emptyPreview.style.display = 'block';
                previewContainer.innerHTML = '';
            }
        }

        // Update counter buku
        function updateBookCounter() {
            const selectedCount = document.querySelectorAll('.buku-select[value!=""]').length;
            const counterElement = document.getElementById('selected-count');
            const addButton = document.getElementById('add-book-btn');
            
            if (counterElement) {
                counterElement.textContent = selectedCount;
                
                // Update warna badge berdasarkan jumlah buku
                const badge = counterElement.closest('.badge');
                if (selectedCount < minBooks) {
                    badge.className = 'badge bg-warning text-dark';
                } else if (selectedCount >= minBooks && selectedCount <= maxBooks) {
                    badge.className = 'badge bg-success text-white';
                } else {
                    badge.className = 'badge bg-danger text-white';
                }
            }
            
            // Disable tombol tambah jika sudah mencapai maksimum
            if (addButton) {
                addButton.disabled = selectedCount >= maxBooks;
            }
        }

        // Update ringkasan
        function updateSummary() {
            const selectedCount = document.querySelectorAll('.buku-select[value!=""]').length;
            const tanggalPinjam = document.getElementById('tanggal_pinjam').value;
            const tanggalKembali = document.getElementById('tanggal_harus_kembali').value;
            const kodePeminjaman = document.getElementById('kode_peminjaman').value;
            
            // Update kode peminjaman
            document.getElementById('summary-kode').textContent = kodePeminjaman;
            
            // Update total buku
            document.getElementById('summary-total-buku').textContent = `${selectedCount} buku`;
            
            // Update status
            const statusElement = document.getElementById('summary-status');
            if (selectedCount >= minBooks && selectedCount <= maxBooks) {
                statusElement.textContent = 'Siap Disimpan';
                statusElement.className = 'badge bg-success text-white';
            } else if (selectedCount < minBooks) {
                statusElement.textContent = 'Minimal 1 buku';
                statusElement.className = 'badge bg-warning text-dark';
            } else {
                statusElement.textContent = 'Maksimal 5 buku';
                statusElement.className = 'badge bg-danger text-white';
            }
            
            // Update lama pinjam
            if (tanggalPinjam && tanggalKembali) {
                const date1 = new Date(tanggalPinjam);
                const date2 = new Date(tanggalKembali);
                const diffTime = Math.abs(date2 - date1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                document.getElementById('summary-lama').textContent = `${diffDays} hari`;
            }
        }

        // Update submit button
        function updateSubmitButton() {
            const submitBtn = document.getElementById('submit-btn');
            const selectedCount = document.querySelectorAll('.buku-select[value!=""]').length;
            const namaAnggota = document.getElementById('nama_anggota').value;
            const kodePeminjaman = document.getElementById('kode_peminjaman').value;
            const tanggalPinjam = document.getElementById('tanggal_pinjam').value;
            const tanggalKembali = document.getElementById('tanggal_harus_kembali').value;
            
            // Cek validasi
            const isNamaValid = namaAnggota.trim().length > 0;
            const isKodeValid = kodePeminjaman.trim().length > 0;
            const isTanggalValid = tanggalPinjam && tanggalKembali;
            const isBookCountValid = selectedCount >= minBooks && selectedCount <= maxBooks;
            
            // Enable/disable button
            if (isNamaValid && isKodeValid && isTanggalValid && isBookCountValid) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Perbarui Peminjaman';
                submitBtn.className = 'btn btn-light btn-lg w-100';
            } else {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i> Data Belum Lengkap';
                submitBtn.className = 'btn btn-secondary btn-lg w-100';
            }
        }

        // Update lama pinjam
        function updateLamaPinjam() {
            const tanggalPinjam = document.getElementById('tanggal_pinjam');
            const tanggalKembali = document.getElementById('tanggal_harus_kembali');
            
            if (tanggalPinjam.value && tanggalKembali.value) {
                const date1 = new Date(tanggalPinjam.value);
                const date2 = new Date(tanggalKembali.value);
                
                if (date1 > date2) {
                    alert('Tanggal pinjam tidak boleh lebih besar dari tanggal harus kembali');
                    tanggalPinjam.value = '';
                    return;
                }
                
                const diffTime = Math.abs(date2 - date1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                document.getElementById('summary-lama').textContent = `${diffDays} hari`;
            }
            
            updateSubmitButton();
        }

        // Inisialisasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Event listener untuk tanggal
            document.getElementById('tanggal_pinjam').addEventListener('change', updateLamaPinjam);
            document.getElementById('tanggal_harus_kembali').addEventListener('change', updateLamaPinjam);
            
            // Event listener untuk nama anggota
            document.getElementById('nama_anggota').addEventListener('change', function() {
                updateSummary();
                updateSubmitButton();
            });
            
            // Event listener untuk kode peminjaman
            document.getElementById('kode_peminjaman').addEventListener('input', updateSubmitButton);
            
            // Event listener untuk buku select yang sudah ada
            document.querySelectorAll('.buku-select').forEach((select, index) => {
                select.addEventListener('change', function() {
                    updateBookInfo(index + 1);
                });
            });
            
            // Validasi form sebelum submit
            document.querySelector('form').addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.buku-select[value!=""]').length;
                
                if (selectedCount < minBooks) {
                    e.preventDefault();
                    alert(`Minimal harus meminjam ${minBooks} buku.`);
                    return false;
                }
                
                if (selectedCount > maxBooks) {
                    e.preventDefault();
                    alert(`Maksimal hanya boleh meminjam ${maxBooks} buku.`);
                    return false;
                }
                
                // Tampilkan konfirmasi
                if (!confirm('Apakah Anda yakin ingin memperbarui data peminjaman ini?')) {
                    e.preventDefault();
                    return false;
                }
                
                // Disable submit button untuk mencegah double submit
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Menyimpan...';
            });
            
            // === PENTING: Panggil updatePreview saat halaman pertama kali dimuat ===
            updatePreview();
            updateBookCounter();
            updateSummary();
            updateSubmitButton();
        });

        // Bootstrap validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })();
    </script>
</body>
</html>