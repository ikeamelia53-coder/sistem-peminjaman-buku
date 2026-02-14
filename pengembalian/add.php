<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('pengembalian');
require_once '../config/database.php';

$error = $success = '';

// ========== GENERATE KODE OTOMATIS ==========
$query = "SELECT MAX(kode_pengembalian) as max_kode FROM pengembalian WHERE kode_pengembalian LIKE 'KMB%'";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$next_number = 1;
if ($row['max_kode']) {
    $last_number = intval(substr($row['max_kode'], 3));
    $next_number = $last_number + 1;
}
$kode_pengembalian_default = 'KMB' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

// ========== AMBIL DATA PEMINJAMAN ==========
$peminjaman_list = [];
$query_peminjaman = "SELECT p.id as peminjaman_id, p.kode_peminjaman, p.nama_anggota,
                     COUNT(pd.id) as jumlah_buku
                     FROM peminjaman p
                     JOIN peminjaman_detail pd ON p.id = pd.peminjaman_id
                     WHERE p.status IN ('dipinjam', 'terlambat')
                     AND pd.status_buku = 'dipinjam'
                     GROUP BY p.id
                     HAVING jumlah_buku > 0
                     ORDER BY p.id DESC
                     LIMIT 20";
$result_peminjaman = mysqli_query($connection, $query_peminjaman);
if ($result_peminjaman) {
    while ($row = mysqli_fetch_assoc($result_peminjaman)) {
        $peminjaman_list[] = $row;
    }
    mysqli_free_result($result_peminjaman);
}

// ========== AMBIL DETAIL BUKU DARI PEMINJAMAN ==========
$peminjaman_details = [];
if (isset($_GET['peminjaman_id']) && is_numeric($_GET['peminjaman_id'])) {
    $peminjaman_id = (int)$_GET['peminjaman_id'];
    $query_details = "SELECT pd.id as peminjaman_detail_id, pd.judul_buku, pd.kode_buku,
                      pd.cover, pd.pengarang, pd.status_buku, b.stok_tersedia
                      FROM peminjaman_detail pd
                      LEFT JOIN buku b ON pd.buku_id = b.id
                      WHERE pd.peminjaman_id = ?
                      AND pd.status_buku = 'dipinjam'
                      ORDER BY pd.id";
    $stmt = mysqli_prepare($connection, $query_details);
    mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $peminjaman_details[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ========== PROSES FORM SUBMISSION ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_pengembalian = trim($_POST['kode_pengembalian'] ?? '');
    $peminjaman_id = (int)($_POST['peminjaman_id'] ?? 0);
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $petugas_id = (int)($_SESSION['user_id'] ?? 1);
    
    // Validasi wajib
    if (empty($kode_pengembalian) || empty($peminjaman_id) || empty($tanggal_kembali)) {
        $error = "Kode Pengembalian, ID Peminjaman, dan Tanggal Kembali wajib diisi.";
    }
    
    // Validasi kode unik
    if (!$error) {
        $check_query = "SELECT COUNT(*) as count FROM pengembalian WHERE kode_pengembalian = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $kode_pengembalian);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        if ($count > 0) {
            $error = "Kode Pengembalian sudah digunakan.";
        }
    }
    
    // Validasi ada buku yang dikembalikan
    if (!$error) {
        $buku_dikembalikan = false;
        foreach ($_POST['detail_id'] ?? [] as $detail_id) {
            if (!empty($detail_id)) {
                $buku_dikembalikan = true;
                break;
            }
        }
        if (!$buku_dikembalikan) {
            $error = "Pilih minimal 1 buku untuk dikembalikan.";
        }
    }
    
    if (!$error) {
        mysqli_begin_transaction($connection);
        try {
            // 1. Insert ke tabel pengembalian (master)
            $sql_pengembalian = "INSERT INTO `pengembalian`
                (kode_pengembalian, peminjaman_id, tanggal_kembali, petugas_id)
                VALUES (?, ?, ?, ?)";
            $stmt_pengembalian = mysqli_prepare($connection, $sql_pengembalian);
            mysqli_stmt_bind_param($stmt_pengembalian, "sisi",
                $kode_pengembalian,
                $peminjaman_id,
                $tanggal_kembali,
                $petugas_id
            );
            if (!mysqli_stmt_execute($stmt_pengembalian)) {
                throw new Exception("Gagal menyimpan pengembalian: " . mysqli_error($connection));
            }
            $pengembalian_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt_pengembalian);
            
            $total_denda = 0;
            $buku_dikembalikan_count = 0;
            
            // 2. Proses setiap buku yang dikembalikan
            if (isset($_POST['detail_id']) && is_array($_POST['detail_id'])) {
                foreach ($_POST['detail_id'] as $index => $peminjaman_detail_id) {
                    if (empty($peminjaman_detail_id)) continue;
                    
                    $peminjaman_detail_id = (int)$peminjaman_detail_id;
                    $kondisi_buku = $_POST['kondisi_buku'][$index] ?? 'baik';
                    $keterangan = $_POST['keterangan'][$index] ?? '';
                    $denda = (float)($_POST['denda'][$index] ?? 0);
                    $jumlah_hari_terlambat = (int)($_POST['jumlah_hari_terlambat'][$index] ?? 0);
                    
                    $total_denda += $denda;
                    $buku_dikembalikan_count++;
                    
                    // Insert ke detail pengembalian
                    $sql_detail = "INSERT INTO `pengembalian_detail`
                        (pengembalian_id, peminjaman_detail_id, kondisi_buku, keterangan, denda, jumlah_hari_terlambat)
                        VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_detail = mysqli_prepare($connection, $sql_detail);
                    mysqli_stmt_bind_param($stmt_detail, "iissdi",
                        $pengembalian_id,
                        $peminjaman_detail_id,
                        $kondisi_buku,
                        $keterangan,
                        $denda,
                        $jumlah_hari_terlambat
                    );
                    if (!mysqli_stmt_execute($stmt_detail)) {
                        throw new Exception("Gagal menyimpan detail pengembalian: " . mysqli_error($connection));
                    }
                    mysqli_stmt_close($stmt_detail);
                    
                    // Update status di peminjaman_detail
                    $sql_update_detail = "UPDATE peminjaman_detail
                        SET status_buku = 'dikembalikan'
                        WHERE id = ?";
                    $stmt_update = mysqli_prepare($connection, $sql_update_detail);
                    mysqli_stmt_bind_param($stmt_update, "i", $peminjaman_detail_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                    
                    // Update stok buku jika kondisinya baik atau ringan
                    if (in_array($kondisi_buku, ['baik', 'rusak_ringan'])) {
                        $sql_update_stok = "UPDATE buku b
                            JOIN peminjaman_detail pd ON b.id = pd.buku_id
                            SET b.stok_tersedia = b.stok_tersedia + 1
                            WHERE pd.id = ?";
                        $stmt_stok = mysqli_prepare($connection, $sql_update_stok);
                        mysqli_stmt_bind_param($stmt_stok, "i", $peminjaman_detail_id);
                        mysqli_stmt_execute($stmt_stok);
                        mysqli_stmt_close($stmt_stok);
                    }
                }
            }
            
            // 3. Update total denda di tabel pengembalian
            $sql_update_denda = "UPDATE pengembalian
                SET denda = ?, jumlah_hari_terlambat = GREATEST(jumlah_hari_terlambat, ?)
                WHERE id = ?";
            $stmt_denda = mysqli_prepare($connection, $sql_update_denda);
            $max_hari_terlambat = (int)($_POST['max_hari_terlambat'] ?? 0);
            mysqli_stmt_bind_param($stmt_denda, "dii", $total_denda, $max_hari_terlambat, $pengembalian_id);
            mysqli_stmt_execute($stmt_denda);
            mysqli_stmt_close($stmt_denda);
            
            // 4. Cek apakah semua buku sudah dikembalikan
            $sql_cek_sisa = "SELECT COUNT(*) as sisa
                FROM peminjaman_detail
                WHERE peminjaman_id = ? AND status_buku = 'dipinjam'";
            $stmt_sisa = mysqli_prepare($connection, $sql_cek_sisa);
            mysqli_stmt_bind_param($stmt_sisa, "i", $peminjaman_id);
            mysqli_stmt_execute($stmt_sisa);
            mysqli_stmt_bind_result($stmt_sisa, $sisa_buku);
            mysqli_stmt_fetch($stmt_sisa);
            mysqli_stmt_close($stmt_sisa);
            
            // 5. Update status peminjaman
            if ($sisa_buku == 0) {
                $sql_update_peminjaman = "UPDATE peminjaman
                    SET status = 'dikembalikan'
                    WHERE id = ?";
                $stmt_update_peminjaman = mysqli_prepare($connection, $sql_update_peminjaman);
                mysqli_stmt_bind_param($stmt_update_peminjaman, "i", $peminjaman_id);
                mysqli_stmt_execute($stmt_update_peminjaman);
                mysqli_stmt_close($stmt_update_peminjaman);
            }
            
            mysqli_commit($connection);
            $success = "Berhasil mengembalikan $buku_dikembalikan_count buku!";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 1500);
            </script>";
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengembalian - Perpustakaan</title>
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
        
        .book-row {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .book-cover {
            width: 60px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .book-cover:hover {
            transform: scale(1.05);
        }
        
        .book-cover-placeholder {
            width: 60px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .book-cover-placeholder:hover {
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
                        <li class="breadcrumb-item active">Tambah Pengembalian</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Tambah Pengembalian Baru
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
        
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php else: ?>
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- Kolom Kiri -->
                <div class="col-lg-8">
                    <!-- Informasi Kode & Peminjaman -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Informasi Pengembalian
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Kode Pengembalian*</label>
                                    <input type="text" name="kode_pengembalian" class="form-control"
                                           value="<?= htmlspecialchars($kode_pengembalian_default) ?>" required>
                                    <small class="form-text text-muted">Kode unik untuk pengembalian</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Peminjaman*</label>
                                    <select name="peminjaman_id" id="peminjaman_id" class="form-select"
                                            onchange="if(this.value) window.location.href='add.php?peminjaman_id='+this.value" required>
                                        <option value="">-- Pilih Peminjaman --</option>
                                        <?php foreach ($peminjaman_list as $pm): ?>
                                        <option value="<?= $pm['peminjaman_id'] ?>"
                                                <?= (isset($_GET['peminjaman_id']) && $_GET['peminjaman_id'] == $pm['peminjaman_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pm['kode_peminjaman']) ?>
                                            - <?= htmlspecialchars($pm['nama_anggota']) ?>
                                            (<?= $pm['jumlah_buku'] ?> buku)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Tanggal Kembali*</label>
                                    <input type="date" name="tanggal_kembali" class="form-control"
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Petugas ID</label>
                                    <input type="text" class="form-control bg-light"
                                           value="<?= $_SESSION['user_id'] ?? 1 ?>" readonly>
                                    <input type="hidden" name="petugas_id" value="<?= $_SESSION['user_id'] ?? 1 ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Daftar Buku yang Dikembalikan -->
                    <?php if (!empty($peminjaman_details)): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-book me-2"></i> Daftar Buku yang Dikembalikan</span>
                            <div>
                                <span class="badge bg-primary" id="book-counter">
                                    <span id="selected-count">0</span>/<?= count($peminjaman_details) ?> buku
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="select-all" onclick="toggleAllBooks()">
                                            </th>
                                            <th>Buku</th>
                                            <th>Kondisi</th>
                                            <th>Terlambat</th>
                                            <th>Denda</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_details as $index => $detail): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_books[]"
                                                       value="<?= $detail['peminjaman_detail_id'] ?>"
                                                       class="book-checkbox"
                                                       onchange="updateBookCounter()"
                                                       checked>
                                                <input type="hidden" name="detail_id[<?= $index ?>]"
                                                       value="<?= $detail['peminjaman_detail_id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($detail['cover'])): ?>
                                                    <img src="../uploads/cover/<?= htmlspecialchars($detail['cover']) ?>"
                                                         class="rounded me-3 book-cover"
                                                         width="40" height="50"
                                                         style="object-fit: cover;"
                                                         onclick="showCoverPreview('<?= htmlspecialchars($detail['cover']) ?>', '<?= htmlspecialchars($detail['judul_buku']) ?>')"
                                                         onerror="this.src='../assets/img/default-book.png'">
                                                    <?php else: ?>
                                                    <div class="rounded me-3 bg-light d-flex align-items-center justify-content-center book-cover-placeholder"
                                                         style="width:40px; height:50px;"
                                                         onclick="showCoverPreview(null, '<?= htmlspecialchars($detail['judul_buku']) ?>')">
                                                        <i class="fas fa-book text-muted"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong class="d-block"><?= htmlspecialchars($detail['judul_buku']) ?></strong>
                                                        <small class="text-muted"><?= htmlspecialchars($detail['kode_buku']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="kondisi_buku[<?= $index ?>]" class="form-select form-select-sm"
                                                        onchange="updateDenda(this, <?= $index ?>)" required>
                                                    <option value="baik">Baik</option>
                                                    <option value="rusak_ringan">Rusak Ringan</option>
                                                    <option value="rusak_berat">Rusak Berat</option>
                                                    <option value="hilang">Hilang</option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" name="jumlah_hari_terlambat[<?= $index ?>]"
                                                           class="form-control" value="0" min="0"
                                                           onchange="updateDenda(this, <?= $index ?>)"
                                                           style="width: 80px;">
                                                    <span class="input-group-text">hari</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" name="denda[<?= $index ?>]"
                                                           class="form-control denda-input"
                                                           value="0" min="0" step="1000"
                                                           style="width: 120px;">
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="keterangan[<?= $index ?>]"
                                                          class="form-control form-control-sm"
                                                          rows="1" placeholder="Keterangan"></textarea>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Total Denda -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Total Denda:</span>
                                            <strong id="total-denda">Rp 0</strong>
                                            <input type="hidden" name="max_hari_terlambat" id="max-hari-terlambat" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php elseif (isset($_GET['peminjaman_id'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Tidak ada buku yang perlu dikembalikan untuk peminjaman ini.
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Kolom Kanan -->
                <div class="col-lg-4">
                    <!-- Informasi Anggota -->
                    <?php if (!empty($peminjaman_details)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-user me-2"></i> Informasi Anggota
                        </div>
                        <div class="card-body">
                            <?php
                            if (isset($_GET['peminjaman_id'])) {
                                $query_anggota = "SELECT p.nama_anggota, p.tanggal_pinjam, p.tanggal_harus_kembali
                                                  FROM peminjaman p WHERE p.id = ?";
                                $stmt = mysqli_prepare($connection, $query_anggota);
                                mysqli_stmt_bind_param($stmt, "i", $_GET['peminjaman_id']);
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_stmt_get_result($stmt);
                                $anggota = mysqli_fetch_assoc($result);
                                mysqli_stmt_close($stmt);
                                
                                if ($anggota) {
                                    $tgl_pinjam = new DateTime($anggota['tanggal_pinjam']);
                                    $tgl_harus_kembali = new DateTime($anggota['tanggal_harus_kembali']);
                                    $tgl_sekarang = new DateTime();
                                    $selisih = $tgl_harus_kembali->diff($tgl_sekarang);
                                    $terlambat_hari = $selisih->invert ? 0 : $selisih->days;
                            ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nama Anggota</label>
                                <p class="form-control bg-light"><?= htmlspecialchars($anggota['nama_anggota']) ?></p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Tanggal Pinjam</label>
                                    <p class="form-control bg-light"><?= date('d/m/Y', strtotime($anggota['tanggal_pinjam'])) ?></p>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Harus Kembali</label>
                                    <p class="form-control bg-light"><?= date('d/m/Y', strtotime($anggota['tanggal_harus_kembali'])) ?></p>
                                </div>
                            </div>
                            <div class="alert <?= $terlambat_hari > 0 ? 'alert-danger' : 'alert-success' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Status:</span>
                                    <strong>
                                        <?php if ($terlambat_hari > 0): ?>
                                        Terlambat <?= $terlambat_hari ?> hari
                                        <?php else: ?>
                                        Tepat waktu
                                        <?php endif; ?>
                                    </strong>
                                </div>
                            </div>
                            <?php } } ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Petunjuk Pengembalian -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Petunjuk Pengembalian
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Centang buku yang akan dikembalikan</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Isi kondisi buku saat dikembalikan</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Denda akan otomatis dihitung</small>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Bisa mengembalikan sebagian buku</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tombol Aksi -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-lg btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Kembali
                        </a>
                        <div>
                            <button type="button" class="btn btn-lg btn-warning me-2" onclick="resetForm()">
                                <i class="fas fa-redo me-1"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-lg btn-primary" 
                                    id="submit-btn" <?= empty($peminjaman_details) ? 'disabled' : '' ?>>
                                <i class="fas fa-save me-2"></i> Simpan Pengembalian
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
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
        
        // Hitung total denda
        function calculateTotalDenda() {
            let total = 0;
            let maxHariTerlambat = 0;
            document.querySelectorAll('.denda-input').forEach((input, index) => {
                const checkbox = document.querySelector(`input[name="selected_books[]"][value="${input.dataset.bookId || ''}"]`);
                if (!checkbox || checkbox.checked) {
                    const denda = parseFloat(input.value) || 0;
                    total += denda;
                    const hariInput = document.querySelector(`input[name="jumlah_hari_terlambat[${index}]"]`);
                    if (hariInput) {
                        const hari = parseInt(hariInput.value) || 0;
                        if (hari > maxHariTerlambat) {
                            maxHariTerlambat = hari;
                        }
                    }
                }
            });
            document.getElementById('total-denda').textContent = 'Rp ' + total.toLocaleString('id-ID');
            document.getElementById('max-hari-terlambat').value = maxHariTerlambat;
            updateSubmitButton();
        }
        
        // Update denda berdasarkan kondisi dan keterlambatan
        function updateDenda(element, index) {
            const kondisiSelect = document.querySelector(`select[name="kondisi_buku[${index}]"]`);
            const hariInput = document.querySelector(`input[name="jumlah_hari_terlambat[${index}]"]`);
            const dendaInput = document.querySelector(`input[name="denda[${index}]"]`);
            let denda = 0;
            const hari = parseInt(hariInput.value) || 0;
            const kondisi = kondisiSelect.value;
            
            // Hitung denda keterlambatan (contoh: Rp 1000 per hari)
            denda += hari * 1000;
            
            // Tambah denda berdasarkan kondisi
            switch (kondisi) {
                case 'rusak_ringan':
                    denda += 5000;
                    break;
                case 'rusak_berat':
                    denda += 20000;
                    break;
                case 'hilang':
                    denda += 50000;
                    break;
            }
            dendaInput.value = denda;
            calculateTotalDenda();
        }
        
        // Update counter buku yang dipilih
        function updateBookCounter() {
            const checkboxes = document.querySelectorAll('.book-checkbox:checked');
            const totalBooks = <?= count($peminjaman_details) ?>;
            document.getElementById('selected-count').textContent = checkboxes.length;
            
            // Update checkbox select-all
            const selectAll = document.getElementById('select-all');
            if (checkboxes.length === totalBooks) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else if (checkboxes.length === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
            
            // Update status form
            updateSubmitButton();
        }
        
        // Toggle semua buku
        function toggleAllBooks() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.book-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBookCounter();
            calculateTotalDenda();
        }
        
        // Update submit button
        function updateSubmitButton() {
            const checkboxes = document.querySelectorAll('.book-checkbox:checked');
            const submitBtn = document.getElementById('submit-btn');
            if (checkboxes.length > 0) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-lg btn-primary';
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Simpan Pengembalian';
            } else {
                submitBtn.disabled = true;
                submitBtn.className = 'btn btn-lg btn-secondary';
                submitBtn.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i> Pilih Buku';
            }
        }
        
        // Reset form
        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset form?')) {
                document.querySelectorAll('.book-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                });
                document.querySelectorAll('select[name^="kondisi_buku"]').forEach(select => {
                    select.value = 'baik';
                });
                document.querySelectorAll('input[name^="jumlah_hari_terlambat"]').forEach(input => {
                    input.value = '0';
                });
                document.querySelectorAll('input[name^="denda"]').forEach(input => {
                    input.value = '0';
                });
                document.querySelectorAll('textarea[name^="keterangan"]').forEach(textarea => {
                    textarea.value = '';
                });
                updateBookCounter();
                calculateTotalDenda();
            }
        }
        
        // Inisialisasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Set data-book-id untuk setiap input denda
            document.querySelectorAll('.denda-input').forEach((input, index) => {
                const checkbox = document.querySelector(`input[name="selected_books[]"]:nth-child(${index + 1})`);
                if (checkbox) {
                    input.dataset.bookId = checkbox.value;
                }
            });
            
            // Event listener untuk perubahan checkbox
            document.querySelectorAll('.book-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', calculateTotalDenda);
            });
            
            // Event listener untuk form submit
            document.querySelector('form').addEventListener('submit', function(e) {
                const checkboxes = document.querySelectorAll('.book-checkbox:checked');
                if (checkboxes.length === 0) {
                    e.preventDefault();
                    alert('Pilih minimal 1 buku untuk dikembalikan.');
                    return false;
                }
                
                // Validasi kondisi buku
                let isValid = true;
                checkboxes.forEach(checkbox => {
                    const row = checkbox.closest('tr');
                    const kondisiSelect = row.querySelector('select[name^="kondisi_buku"]');
                    if (!kondisiSelect.value) {
                        isValid = false;
                        kondisiSelect.focus();
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Isi kondisi buku untuk semua buku yang dipilih.');
                    return false;
                }
                
                // Konfirmasi
                if (!confirm(`Apakah Anda yakin ingin mengembalikan ${checkboxes.length} buku?`)) {
                    e.preventDefault();
                    return false;
                }
                
                // Disable button untuk mencegah double submit
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Menyimpan...';
            });
            
            // Initial update
            updateBookCounter();
            calculateTotalDenda();
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