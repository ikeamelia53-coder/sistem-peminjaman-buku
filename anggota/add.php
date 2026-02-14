<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('anggota');
require_once '../config/database.php';

$error = $success = '';

// Generate kode anggota otomatis
$query = "SELECT MAX(kode_anggota) as max_kode FROM anggota WHERE kode_anggota LIKE 'AGT%'";
$result = mysqli_query($connection, $query);
$row = mysqli_fetch_assoc($result);
$next_number = 1;
if ($row['max_kode']) {
    $last_number = intval(substr($row['max_kode'], 3));
    $next_number = $last_number + 1;
}
$kode_anggota_default = 'AGT' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

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
    
    // Cek duplikasi kode anggota
    if (!$error) {
        $check_query = "SELECT COUNT(*) as count FROM anggota WHERE kode_anggota = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $kode_anggota);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        if ($count > 0) {
            $error = "Kode Anggota sudah digunakan.";
        }
    }
    
    // Cek duplikasi email
    if (!$error && !empty($email)) {
        $check_query = "SELECT COUNT(*) as count FROM anggota WHERE email = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
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
            "INSERT INTO `anggota` (
                kode_anggota, nama_anggota, jenis_kelamin, tempat_lahir, 
                tanggal_lahir, alamat, telepon, email, pekerjaan, status, 
                tanggal_daftar, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        mysqli_stmt_bind_param($stmt, "sssssssssss",
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
            $tanggal_daftar
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Anggota berhasil ditambahkan.";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            $error = "Gagal menyimpan: " . mysqli_error($connection);
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
    <title>Tambah Anggota - Perpustakaan</title>
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
                        <li class="breadcrumb-item"><a href="index.php">Daftar Anggota</a></li>
                        <li class="breadcrumb-item active">Tambah Anggota</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Tambah Anggota Baru
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
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="kode_anggota" class="form-label fw-bold">Kode Anggota*</label>
                                <input type="text" name="kode_anggota" id="kode_anggota" class="form-control"
                                       value="<?= htmlspecialchars($kode_anggota_default) ?>" required>
                                <div class="form-text">Kode unik untuk anggota</div>
                            </div>
                            <div class="mb-3">
                                <label for="nama_anggota" class="form-label fw-bold">Nama Anggota*</label>
                                <input type="text" name="nama_anggota" id="nama_anggota" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="jenis_kelamin" class="form-select">
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label fw-bold">Status*</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Nonaktif</option>
                                        <option value="diblokir">Diblokir</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="tanggal_daftar" class="form-label">Tanggal Daftar</label>
                                <input type="date" name="tanggal_daftar" id="tanggal_daftar" class="form-control"
                                       value="<?= date('Y-m-d') ?>">
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
                                    <input type="text" name="tempat_lahir" id="tempat_lahir" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat Lengkap</label>
                                <textarea name="alamat" id="alamat" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="pekerjaan" class="form-label">Pekerjaan</label>
                                <input type="text" name="pekerjaan" id="pekerjaan" class="form-control">
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
                                       placeholder="081234567890">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control"
                                       placeholder="email@example.com">
                                <div class="form-text">Email harus unik dan valid</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Petunjuk -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Petunjuk
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Kode Anggota harus unik</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Email harus valid dan belum terdaftar</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Status "Diblokir" tidak dapat meminjam buku</small>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Semua field bertanda * wajib diisi</small>
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
                        <button type="submit" class="btn btn-lg btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan Anggota
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
        
        // Validasi email unik saat input
        document.getElementById('email')?.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email) {
                fetch('cek_email.php?email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        this.setCustomValidity('Email sudah terdaftar');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>