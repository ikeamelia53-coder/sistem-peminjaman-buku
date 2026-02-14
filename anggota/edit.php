<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('anggota');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

$stmt = mysqli_prepare($connection, "SELECT * FROM `anggota` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$anggota = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$anggota) {
    redirect('index.php');
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_anggota = trim($_POST['kode_anggota'] ?? '');
    $nama_anggota = trim($_POST['nama_anggota'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? 'L');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $status = trim($_POST['status'] ?? 'aktif');
    $tanggal_daftar = trim($_POST['tanggal_daftar'] ?? date('Y-m-d'));
    
    // Validasi
    if (empty($kode_anggota) || empty($nama_anggota)) {
        $error = "Kode Anggota dan Nama Anggota wajib diisi.";
    }
    
    // Cek duplikasi kode anggota (kecuali untuk data ini)
    if (!$error && $kode_anggota != $anggota['kode_anggota']) {
        $check_query = "SELECT COUNT(*) as count FROM anggota WHERE kode_anggota = ? AND id != ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "si", $kode_anggota, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        if ($count > 0) {
            $error = "Kode Anggota sudah digunakan.";
        }
    }
    
    // Cek duplikasi email (kecuali untuk data ini)
    if (!$error && !empty($email) && $email != $anggota['email']) {
        $check_query = "SELECT COUNT(*) as count FROM anggota WHERE email = ? AND id != ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "si", $email, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        if ($count > 0) {
            $error = "Email sudah digunakan oleh anggota lain.";
        }
    }
    
    if (!$error) {
        $stmt = mysqli_prepare($connection, 
            "UPDATE `anggota` SET
                kode_anggota = ?,
                nama_anggota = ?,
                jenis_kelamin = ?,
                tempat_lahir = ?,
                tanggal_lahir = ?,
                alamat = ?,
                telepon = ?,
                email = ?,
                pekerjaan = ?,
                status = ?,
                tanggal_daftar = ?
            WHERE id = ?");
        
        mysqli_stmt_bind_param($stmt, "sssssssssssi",
            $kode_anggota,
            $nama_anggota,
            $jenis_kelamin,
            $tempat_lahir,
            $tanggal_lahir,
            $alamat,
            $telepon,
            $email,
            $pekerjaan,
            $status,
            $tanggal_daftar,
            $id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Anggota berhasil diperbarui.";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            $error = "Gagal memperbarui: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Anggota - Perpustakaan</title>
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
        
        .avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
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
                        <li class="breadcrumb-item"><a href="index.php">Daftar Anggota</a></li>
                        <li class="breadcrumb-item active">Edit Anggota</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-edit me-2 text-warning"></i>
                    Edit Anggota: <?= htmlspecialchars($anggota['nama_anggota']) ?>
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
                <div class="col-md-6">
                    <!-- Informasi Dasar -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-user me-2"></i> Informasi Dasar
                        </div>
                        <div class="card-body text-center">
                            <div class="avatar-large">
                                <?= strtoupper(substr($anggota['nama_anggota'], 0, 1)) ?>
                            </div>
                            <p class="text-muted mb-0">ID: <?= $anggota['id'] ?></p>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="kode_anggota" class="form-label fw-bold">Kode Anggota*</label>
                                <input type="text" name="kode_anggota" id="kode_anggota" class="form-control"
                                       value="<?= htmlspecialchars($anggota['kode_anggota']) ?>" required>
                                <div class="form-text">Kode unik untuk anggota</div>
                            </div>
                            <div class="mb-3">
                                <label for="nama_anggota" class="form-label fw-bold">Nama Anggota*</label>
                                <input type="text" name="nama_anggota" id="nama_anggota" class="form-control"
                                       value="<?= htmlspecialchars($anggota['nama_anggota']) ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="jenis_kelamin" class="form-select">
                                        <option value="L" <?= $anggota['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                        <option value="P" <?= $anggota['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label fw-bold">Status*</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="aktif" <?= $anggota['status'] == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="nonaktif" <?= $anggota['status'] == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                        <option value="diblokir" <?= $anggota['status'] == 'diblokir' ? 'selected' : '' ?>>Diblokir</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="tanggal_daftar" class="form-label">Tanggal Daftar</label>
                                <input type="date" name="tanggal_daftar" id="tanggal_daftar" class="form-control"
                                       value="<?= htmlspecialchars($anggota['tanggal_daftar']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Pribadi -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-id-card me-2"></i> Data Pribadi
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                                    <input type="text" name="tempat_lahir" id="tempat_lahir" class="form-control"
                                           value="<?= htmlspecialchars($anggota['tempat_lahir']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control"
                                           value="<?= htmlspecialchars($anggota['tanggal_lahir']) ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat Lengkap</label>
                                <textarea name="alamat" id="alamat" class="form-control" rows="3"><?= htmlspecialchars($anggota['alamat']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="pekerjaan" class="form-label">Pekerjaan</label>
                                <input type="text" name="pekerjaan" id="pekerjaan" class="form-control"
                                       value="<?= htmlspecialchars($anggota['pekerjaan']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Kolom Kanan -->
                <div class="col-md-6">
                    <!-- Kontak -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-phone me-2"></i> Kontak
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="telepon" class="form-label">Nomor Telepon</label>
                                <input type="text" name="telepon" id="telepon" class="form-control"
                                       value="<?= htmlspecialchars($anggota['telepon']) ?>"
                                       placeholder="081234567890">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control"
                                       value="<?= htmlspecialchars($anggota['email']) ?>"
                                       placeholder="email@example.com">
                                <div class="form-text">Email harus unik dan valid</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistik Peminjaman -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-chart-bar me-2"></i> Statistik Peminjaman
                        </div>
                        <div class="card-body">
                            <?php
                            // Ambil statistik peminjaman anggota ini
                            $query_stats = "SELECT 
                                COUNT(*) as total_pinjam,
                                SUM(CASE WHEN p.status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_pinjam,
                                SUM(CASE WHEN p.status = 'terlambat' THEN 1 ELSE 0 END) as terlambat
                                FROM peminjaman p
                                WHERE p.nama_anggota = ?";
                            $stmt_stats = mysqli_prepare($connection, $query_stats);
                            mysqli_stmt_bind_param($stmt_stats, "s", $anggota['nama_anggota']);
                            mysqli_stmt_execute($stmt_stats);
                            $stats = mysqli_stmt_get_result($stmt_stats)->fetch_assoc();
                            mysqli_stmt_close($stmt_stats);
                            ?>
                            <div class="row text-center">
                                <div class="col-4 mb-3">
                                    <div class="fw-bold text-primary fs-4"><?= $stats['total_pinjam'] ?? 0 ?></div>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4 mb-3">
                                    <div class="fw-bold text-warning fs-4"><?= $stats['sedang_pinjam'] ?? 0 ?></div>
                                    <small class="text-muted">Dipinjam</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-danger fs-4"><?= $stats['terlambat'] ?? 0 ?></div>
                                    <small class="text-muted">Terlambat</small>
                                </div>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Data peminjaman anggota ini
                            </small>
                        </div>
                    </div>
                    
                    <!-- Status Anggota -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-check me-2"></i> Status Anggota Saat Ini
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <span class="badge bg-<?= $anggota['status'] == 'aktif' ? 'success' : ($anggota['status'] == 'nonaktif' ? 'warning' : 'danger') ?> px-4 py-2 fs-5">
                                    <?= ucfirst($anggota['status']) ?>
                                </span>
                            </div>
                            <p class="text-muted mb-0">
                                <?php if ($anggota['status'] == 'aktif'): ?>
                                Anggota dapat meminjam buku
                                <?php elseif ($anggota['status'] == 'nonaktif'): ?>
                                Anggota tidak aktif sementara
                                <?php else: ?>
                                Anggota diblokir dari sistem
                                <?php endif; ?>
                            </p>
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
                        <button type="submit" class="btn btn-lg btn-warning">
                            <i class="fas fa-save me-2"></i> Perbarui Anggota
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validasi form
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