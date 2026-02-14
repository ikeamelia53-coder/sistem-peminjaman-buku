<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('peminjaman');
require_once '../config/database.php';

// --- FUNGSI ---
function cekAnggotaAktif($connection, $nama_anggota) {
    $query = "SELECT COUNT(*) as total
              FROM peminjaman
              WHERE nama_anggota = ?
              AND status IN ('dipinjam', 'terlambat')";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $nama_anggota);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $total > 0;
}

function getAnggotaList($connection) {
    $anggota_list = [];
    $query = "SELECT id, nama_anggota, kode_anggota, status, email
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
              AND stok_tersedia > 0
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

// Generate kode peminjaman otomatis
$query = "SELECT MAX(kode_peminjaman) as max_kode FROM peminjaman WHERE kode_peminjaman LIKE 'PJM%'";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$next_number = 1;
if ($row['max_kode']) {
    $last_number = intval(substr($row['max_kode'], 3));
    $next_number = $last_number + 1;
}
$kode_peminjaman_default = 'PJM' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

$error = '';

// Ambil daftar anggota dan buku dari database
$anggota_list = getAnggotaList($connection);
$buku_list = getBukuList($connection);

// --- PROSES FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_peminjaman = $_POST['kode_peminjaman'] ?? '';
    $nama_anggota = $_POST['nama_anggota'] ?? '';
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_harus_kembali = $_POST['tanggal_harus_kembali'] ?? '';
    $buku_ids = $_POST['buku_id'] ?? [];
    
    // Validasi
    if (empty($nama_anggota) || empty($buku_ids)) {
        $error = "Nama anggota dan minimal 1 buku harus dipilih!";
    } elseif (count($buku_ids) < 1 || count($buku_ids) > 5) {
        $error = "Jumlah buku harus antara 1-5!";
    } else {
        // Cek apakah anggota sedang meminjam
        if (cekAnggotaAktif($connection, $nama_anggota)) {
            $error = "Anggota " . htmlspecialchars($nama_anggota) . " sedang meminjam buku. Tidak bisa meminjam lagi!";
        } else {
            // Cek stok buku
            $stok_cukup = true;
            
            foreach ($buku_ids as $buku_id) {
                $query_stok = "SELECT stok_tersedia, judul_buku FROM buku WHERE id = ?";
                $stmt_stok = mysqli_prepare($connection, $query_stok);
                mysqli_stmt_bind_param($stmt_stok, "i", $buku_id);
                mysqli_stmt_execute($stmt_stok);
                mysqli_stmt_bind_result($stmt_stok, $stok, $judul);
                mysqli_stmt_fetch($stmt_stok);
                mysqli_stmt_close($stmt_stok);
                
                // Hitung jumlah buku yang sama dipilih
                $jumlah_sama = array_count_values($buku_ids)[$buku_id];
                
                if ($stok < $jumlah_sama) {
                    $stok_cukup = false;
                    $error = "Stok buku \"" . htmlspecialchars($judul) . "\" tidak cukup. Hanya tersedia $stok eksemplar.";
                    break;
                }
            }
            
            if ($stok_cukup) {
                // Mulai transaction
                mysqli_begin_transaction($connection);
                
                try {
                    // Insert ke tabel peminjaman
                    $query_peminjaman = "INSERT INTO peminjaman 
                                        (kode_peminjaman, nama_anggota, tanggal_pinjam, tanggal_harus_kembali, status) 
                                        VALUES (?, ?, ?, ?, 'dipinjam')";
                    $stmt_peminjaman = mysqli_prepare($connection, $query_peminjaman);
                    mysqli_stmt_bind_param($stmt_peminjaman, "ssss", 
                        $kode_peminjaman, $nama_anggota, $tanggal_pinjam, $tanggal_harus_kembali);
                    mysqli_stmt_execute($stmt_peminjaman);
                    $peminjaman_id = mysqli_insert_id($connection);
                    mysqli_stmt_close($stmt_peminjaman);
                    
                    // Insert detail buku & kurangi stok
                    foreach ($buku_ids as $buku_id) {
                        // Insert detail peminjaman
                        $query_detail = "INSERT INTO peminjaman_detail (peminjaman_id, buku_id) VALUES (?, ?)";
                        $stmt_detail = mysqli_prepare($connection, $query_detail);
                        mysqli_stmt_bind_param($stmt_detail, "ii", $peminjaman_id, $buku_id);
                        mysqli_stmt_execute($stmt_detail);
                        mysqli_stmt_close($stmt_detail);
                        
                        // Kurangi stok buku
                        $query_stok = "UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id = ?";
                        $stmt_stok = mysqli_prepare($connection, $query_stok);
                        mysqli_stmt_bind_param($stmt_stok, "i", $buku_id);
                        mysqli_stmt_execute($stmt_stok);
                        mysqli_stmt_close($stmt_stok);
                    }
                    
                    mysqli_commit($connection);
                    
                    // === SET SESSION PESAN SUKSES DAN REDIRECT ===
                    $_SESSION['success'] = "Peminjaman <strong>" . htmlspecialchars($kode_peminjaman) . "</strong> berhasil ditambahkan!";
                    header("Location: index.php");
                    exit();
                    
                } catch (Exception $e) {
                    mysqli_rollback($connection);
                    $error = "Gagal menyimpan: " . $e->getMessage();
                }
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
    <title>Tambah Peminjaman - Perpustakaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Preview Buku - Ukuran Medium */
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
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(139, 69, 19, 0.4);
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
                        <li class="breadcrumb-item active">Tambah Peminjaman</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Tambah Peminjaman Baru
                </h2>
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
                                        <div class="input-group">
                                            <input type="text" name="kode_peminjaman" id="kode_peminjaman"
                                                   class="form-control" required readonly
                                                   value="<?= htmlspecialchars($kode_peminjaman_default) ?>">
                                            <button type="button" class="btn btn-outline-secondary" onclick="generateKode()">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Klik tombol refresh untuk generate kode baru</small>
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
                                                <?php
                                                // Cek apakah anggota sedang meminjam
                                                $is_meminjam = cekAnggotaAktif($connection, $anggota['nama_anggota']);
                                                ?>
                                                <option value="<?= htmlspecialchars($anggota['nama_anggota']) ?>"
                                                        <?= isset($_POST['nama_anggota']) && $_POST['nama_anggota'] == $anggota['nama_anggota'] ? 'selected' : '' ?>
                                                        data-status="<?= $is_meminjam ? 'meminjam' : 'bebas' ?>"
                                                        <?= $is_meminjam ? 'disabled style="color: #ccc;"' : '' ?>>
                                                    <?= htmlspecialchars($anggota['nama_anggota']) ?>
                                                    (<?= $anggota['kode_anggota'] ?>)
                                                    <?= $is_meminjam ? ' (Sedang Meminjam)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($anggota_list)): ?>
                                            <div class="alert alert-warning mt-2 p-2">
                                                <i class="fas fa-exclamation-circle me-2"></i>
                                                Tidak ada anggota aktif
                                            </div>
                                        <?php endif; ?>
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
                                               value="<?= isset($_POST['tanggal_pinjam']) ? htmlspecialchars($_POST['tanggal_pinjam']) : date('Y-m-d') ?>"
                                               min="<?= date('Y-m-d') ?>">
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
                                               value="<?= isset($_POST['tanggal_harus_kembali']) ? htmlspecialchars($_POST['tanggal_harus_kembali']) : date('Y-m-d', strtotime('+7 days')) ?>"
                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="fas fa-info-circle fa-2x me-3 text-primary"></i>
                                        <div>
                                            <h6 class="alert-heading mb-1">Perhatian!</h6>
                                            <p class="mb-0">Peminjaman minimal 1 buku, maksimal 5 buku (boleh pilih buku yang sama).</p>
                                            <p class="mb-0"><small>Anggota yang sedang meminjam buku tidak dapat meminjam lagi.</small></p>
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
                                    <small class="text-muted">Pilih 1 sampai 5 buku (boleh sama)</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-white text-primary me-3" id="book-counter">
                                    <span id="selected-count">0</span>/5 buku
                                </span>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addBookRow()" id="add-book-btn">
                                    <i class="fas fa-plus me-1"></i> Tambah Buku
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="book-list">
                                <!-- Baris buku akan ditambahkan secara dinamis -->
                            </div>
                            <!-- Pesan jika belum ada buku -->
                            <div id="empty-book-message" class="text-center py-5" style="display: block;">
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
                                    <span class="fw-bold" id="summary-kode"><?= htmlspecialchars($kode_peminjaman_default) ?></span>
                                </div>
                            </div>
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Buku:</span>
                                    <span class="fw-bold" id="summary-total-buku">0 buku</span>
                                </div>
                            </div>
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Lama Pinjam:</span>
                                    <span class="fw-bold" id="summary-lama">0 hari</span>
                                </div>
                            </div>
                            <div class="summary-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Status Anggota:</span>
                                    <span class="badge bg-success" id="summary-status-anggota">Belum Dipilih</span>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Status Form:</span>
                                    <span class="badge bg-warning text-dark" id="summary-status">
                                        Belum Siap
                                    </span>
                                </div>
                            </div>
                            <hr class="my-4 border-white">
                            <div class="text-center">
                                <button type="submit" class="btn btn-light btn-lg w-100" id="submit-btn" enabled>
                                    <i class="fas fa-save me-2"></i> Simpan Peminjaman
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
                                <div id="empty-preview" class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="fas fa-book-open fa-3x text-light"></i>
                                    </div>
                                    <p class="text-muted mb-0">Pilih buku untuk melihat preview</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Penting -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-white p-2 me-3">
                                    <i class="fas fa-info-circle text-info fs-5"></i>
                                </div>
                                <h5 class="mb-0">Informasi Penting</h5>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Anggota yang sedang meminjam tidak bisa meminjam lagi</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Minimal 1 buku, maksimal 5 buku</small>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Stok buku akan otomatis berkurang</small>
                                </li>
                            </ul>
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
        let bookRows = 0;
        let bookCounter = 0;
        const maxBooks = 5;
        const minBooks = 1;
        let bukuDipilih = [];

        // Generate kode peminjaman
        function generateKode() {
            const randomNum = Math.floor(Math.random() * 900) + 100;
            const newKode = 'PJM' + randomNum;
            document.getElementById('kode_peminjaman').value = newKode;
            updateSummary();
            updateSubmitButton();
        }

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
                                <img id="cover-image-${bookCounter}" class="book-cover d-none" src="" alt="Cover Buku"
                                     onerror="this.style.display='none'; document.getElementById('cover-placeholder-${bookCounter}').style.display='flex';">
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
                
                // Cek stok
                const jumlahBukuSama = document.querySelectorAll(`.buku-select[value="${bukuId}"]`).length;
                if (stok < jumlahBukuSama) {
                    alert(`Stok buku "${judul}" tidak cukup. Hanya tersedia ${stok} eksemplar.`);
                    select.value = '';
                    updateBookInfo(rowId);
                    return;
                }
                
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
                                            `<img src="../uploads/cover/${book.cover}" class="book-preview-cover" alt="${book.judul}">` :
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
            const namaAnggota = document.getElementById('nama_anggota').value;
            
            // Update kode peminjaman
            document.getElementById('summary-kode').textContent = kodePeminjaman;
            
            // Update total buku
            document.getElementById('summary-total-buku').textContent = `${selectedCount} buku`;
            
            // Update status form
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
            
            // Update status anggota
            const statusAnggotaElement = document.getElementById('summary-status-anggota');
            if (namaAnggota) {
                const selectedOption = document.querySelector(`#nama_anggota option[value="${namaAnggota}"]`);
                const isMeminjam = selectedOption ? selectedOption.getAttribute('data-status') === 'meminjam' : false;
                if (isMeminjam) {
                    statusAnggotaElement.textContent = 'Sedang Meminjam';
                    statusAnggotaElement.className = 'badge bg-danger';
                } else {
                    statusAnggotaElement.textContent = 'Bisa Meminjam';
                    statusAnggotaElement.className = 'badge bg-success';
                }
            } else {
                statusAnggotaElement.textContent = 'Belum Dipilih';
                statusAnggotaElement.className = 'badge bg-secondary';
            }
            
            // Update lama pinjam
            if (tanggalPinjam && tanggalKembali) {
                const date1 = new Date(tanggalPinjam);
                const date2 = new Date(tanggalKembali);
                const diffTime = Math.abs(date2 - date1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                document.getElementById('summary-lama').textContent = `${diffDays} hari`;
            } else {
                document.getElementById('summary-lama').textContent = '0 hari';
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
            
            // Cek apakah anggota sedang meminjam
            let isAnggotaAvailable = true;
            if (namaAnggota) {
                const selectedOption = document.querySelector(`#nama_anggota option[value="${namaAnggota}"]`);
                if (selectedOption && selectedOption.getAttribute('data-status') === 'meminjam') {
                    isAnggotaAvailable = false;
                }
            }
            
            // Enable/disable button
            if (isNamaValid && isKodeValid && isTanggalValid && isBookCountValid && isAnggotaAvailable) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Simpan Peminjaman';
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
            
            // Validasi form sebelum submit
            document.querySelector('form').addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.buku-select[value!=""]').length;
                const namaAnggota = document.getElementById('nama_anggota').value;
                
                // Validasi anggota
                if (!namaAnggota) {
                    e.preventDefault();
                    alert('Silakan pilih anggota terlebih dahulu.');
                    return false;
                }
                
                // Validasi stok buku
                let isValid = true;
                document.querySelectorAll('.buku-select').forEach(select => {
                    if (select.value) {
                        const selectedOption = select.options[select.selectedIndex];
                        const bukuId = select.value;
                        const judul = selectedOption.getAttribute('data-judul');
                        const stok = parseInt(selectedOption.getAttribute('data-stok'));
                        
                        // Hitung berapa kali buku ini dipilih
                        const jumlahBukuSama = document.querySelectorAll(`.buku-select[value="${bukuId}"]`).length;
                        
                        if (stok < jumlahBukuSama) {
                            e.preventDefault();
                            alert(`Stok buku "${judul}" tidak cukup. Hanya tersedia ${stok} eksemplar.`);
                            isValid = false;
                            return false;
                        }
                    }
                });
                
                if (!isValid) return false;
                
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
                if (!confirm('Apakah Anda yakin ingin menyimpan data peminjaman ini?')) {
                    e.preventDefault();
                    return false;
                }
                
                // Disable submit button untuk mencegah double submit
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Menyimpan...';
            });
            
            // Initial update
            updateBookCounter();
            updateSummary();
            updateSubmitButton();
            
            // Tambah buku pertama secara otomatis jika ada data buku
            <?php if (!empty($buku_list)): ?>
                setTimeout(() => {
                    addBookRow();
                }, 500);
            <?php endif; ?>
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