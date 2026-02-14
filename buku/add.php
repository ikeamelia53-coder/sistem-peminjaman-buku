<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('buku');
require_once '../config/database.php';

// --- FUNGSI ---
function generateKodeBuku() {
    return 'BK' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function handle_file_upload($file) {
    if (!isset($file['name']) || empty($file['name'])) {
        return '';
    }
    
    $target_dir = '../uploads/cover/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'buku_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
    $target_file = $target_dir . $filename;
    
    if ($file['size'] > 2097152) {
        return false;
    }
    
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_extension, $allowed_types)) {
        return false;
    }
    
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return false;
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $filename;
    }
    return false;
}

function generateUniqueKodeBuku($connection) {
    $max_attempts = 10;
    $attempt = 0;
    while ($attempt < $max_attempts) {
        $kode_buku = generateKodeBuku();
        $check_query = "SELECT COUNT(*) as count FROM buku WHERE kode_buku = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $kode_buku);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        if ($count == 0) {
            return $kode_buku;
        }
        $attempt++;
    }
    return 'BK' . date('YmdHis') . mt_rand(100, 999);
}

// --- AKHIR FUNGSI ---
$error = $success = '';
$kategori_list = [
    'Fiksi', 'Sejarah', 'Romantis', 'Inspiratif', 'Sastra', 'Religi',
    'Fantasi', 'Misteri', 'Komedi', 'Filsafat', 'Drama', 'Dystopian',
    'Teknologi', 'Pengembangan Diri', 'Psikologi', 'Keuangan', 'Biografi',
    'Klasik', 'Fiksi Sains', 'Spiritual', 'Kesehatan', 'Futuristik'
];

$kode_buku_form = generateUniqueKodeBuku($connection);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_buku = generateUniqueKodeBuku($connection);
    $isbn = trim($_POST['isbn'] ?? '');
    $judul_buku = trim($_POST['judul_buku'] ?? '');
    $pengarang = trim($_POST['pengarang'] ?? '');
    $penerbit = trim($_POST['penerbit'] ?? '');
    $tahun_terbit = trim($_POST['tahun_terbit'] ?? '');
    $kategori = trim($_POST['kategori'] ?? 'Fiksi');
    $bahasa = trim($_POST['bahasa'] ?? 'Indonesia');
    $halaman = (int)($_POST['halaman'] ?? 0);
    $sinopsis = trim($_POST['sinopsis'] ?? '');
    $stok_total = (int)($_POST['stok_total'] ?? 1);
    $lokasi_rak = trim($_POST['lokasi_rak'] ?? '');
    
    $cover = 'default.jpg';
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = handle_file_upload($_FILES['cover']);
        if ($upload_result === false) {
            $error = "Gagal mengupload cover. Pastikan file adalah gambar (JPG, PNG, GIF, WebP) dan ukuran maksimal 2MB.";
        } elseif ($upload_result !== '') {
            $cover = $upload_result;
        }
    }
    
    if (!$error && empty($judul_buku)) {
        $error = "Judul buku wajib diisi.";
    }
    if (!$error && empty($pengarang)) {
        $error = "Pengarang wajib diisi.";
    }
    if (!$error && empty($penerbit)) {
        $error = "Penerbit wajib diisi.";
    }
    if (!$error && !empty($tahun_terbit) && ($tahun_terbit < 1000 || $tahun_terbit > date('Y'))) {
        $error = "Tahun terbit tidak valid.";
    }
    if (!$error && $stok_total < 1) {
        $error = "Stok total minimal 1.";
    }
    
    if (!$error && !empty($isbn)) {
        $check_query = "SELECT COUNT(*) as count FROM buku WHERE isbn = ?";
        $check_stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $isbn);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_bind_result($check_stmt, $count);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        if ($count > 0) {
            $error = "ISBN sudah terdaftar untuk buku lain.";
        }
    }
    
    if (!$error) {
        $stok_tersedia = $stok_total;
        $status = 'tersedia';
        
        $stmt = mysqli_prepare($connection,
            "INSERT INTO `buku` (
                kode_buku, isbn, judul_buku, pengarang, penerbit, tahun_terbit,
                kategori, bahasa, halaman, sinopsis, stok_total, stok_tersedia,
                status, lokasi_rak, cover
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        mysqli_stmt_bind_param($stmt, "sssssisssiiisss",
            $kode_buku, $isbn, $judul_buku, $pengarang, $penerbit, $tahun_terbit,
            $kategori, $bahasa, $halaman, $sinopsis, $stok_total, $stok_tersedia,
            $status, $lokasi_rak, $cover
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Buku berhasil ditambahkan dengan kode: " . $kode_buku;
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            if ($cover !== 'default.jpg' && file_exists('../uploads/cover/' . $cover)) {
                unlink('../uploads/cover/' . $cover);
            }
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
    <title>Tambah Buku - Perpustakaan</title>
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
                        <li class="breadcrumb-item"><a href="index.php">Daftar Buku</a></li>
                        <li class="breadcrumb-item active">Tambah Buku</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Tambah Buku Baru
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
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row">
                <!-- Kolom Kiri -->
                <div class="col-md-6">
                    <!-- Kode Buku -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-barcode me-2"></i> Informasi Kode Buku
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="kode_buku" class="form-label fw-bold">Kode Buku*</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-lg"
                                           id="kode_buku" value="<?= htmlspecialchars($kode_buku_form) ?>" readonly>
                                    <input type="hidden" name="kode_buku" value="<?= htmlspecialchars($kode_buku_form) ?>">
                                    <button type="button" class="btn btn-outline-primary" onclick="generateNewCode()">
                                        <i class="fas fa-sync-alt me-1"></i> Generate Baru
                                    </button>
                                </div>
                                <div class="form-text">
                                    Kode buku digenerate otomatis oleh sistem. Klik tombol untuk generate kode baru.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Dasar Buku -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-book me-2"></i> Informasi Dasar Buku
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="judul_buku" class="form-label fw-bold">Judul Buku*</label>
                                <input type="text" name="judul_buku" id="judul_buku"
                                       class="form-control" required maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" name="isbn" id="isbn"
                                       class="form-control" maxlength="20">
                                <div class="form-text">Contoh: 9786020384390</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pengarang" class="form-label fw-bold">Pengarang*</label>
                                    <input type="text" name="pengarang" id="pengarang"
                                           class="form-control" required maxlength="255">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="penerbit" class="form-label fw-bold">Penerbit*</label>
                                    <input type="text" name="penerbit" id="penerbit"
                                           class="form-control" required maxlength="255">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tahun_terbit" class="form-label">Tahun Terbit</label>
                                    <input type="number" name="tahun_terbit" id="tahun_terbit"
                                           class="form-control" min="1000" max="<?= date('Y') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="kategori" class="form-label fw-bold">Kategori*</label>
                                    <select name="kategori" id="kategori" class="form-select" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php foreach ($kategori_list as $kat): ?>
                                        <option value="<?= htmlspecialchars($kat) ?>" <?= ($kat == 'Fiksi') ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kat) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="bahasa" class="form-label">Bahasa</label>
                                <select name="bahasa" id="bahasa" class="form-select">
                                    <option value="Indonesia" selected>Indonesia</option>
                                    <option value="Inggris">Inggris</option>
                                    <option value="Jawa">Jawa</option>
                                    <option value="Sunda">Sunda</option>
                                    <option value="Arab">Arab</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Kolom Kanan -->
                <div class="col-md-6">
                    <!-- Detail Buku -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Detail Buku
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="halaman" class="form-label">Jumlah Halaman</label>
                                    <input type="number" name="halaman" id="halaman"
                                           class="form-control" min="1">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="stok_total" class="form-label fw-bold">Stok Total*</label>
                                    <input type="number" name="stok_total" id="stok_total"
                                           class="form-control" value="1" min="1" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="lokasi_rak" class="form-label">Lokasi Rak</label>
                                <input type="text" name="lokasi_rak" id="lokasi_rak"
                                       class="form-control" maxlength="50">
                                <div class="form-text">Contoh: RAK-A1</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sinopsis -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-align-left me-2"></i> Sinopsis
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="sinopsis" class="form-label">Sinopsis Buku</label>
                                <textarea name="sinopsis" id="sinopsis"
                                          class="form-control" rows="6" maxlength="2000"></textarea>
                                <div class="form-text">Maksimal 2000 karakter</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cover Buku -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-image me-2"></i> Cover Buku
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="cover" class="form-label">Upload Cover</label>
                                <input type="file" name="cover" id="cover"
                                       class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">
                                    Ukuran maksimal 2MB. Format: JPG, PNG, GIF, WebP.
                                    Jika tidak diupload, akan digunakan cover default.
                                </div>
                                <!-- Preview Cover -->
                                <div class="mt-3">
                                    <div class="border rounded p-3 text-center" id="coverPreview" 
                                         style="height: 150px; background-color: #f8f9fa;">
                                        <i class="fas fa-book fa-3x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Preview cover akan tampil di sini</p>
                                    </div>
                                </div>
                            </div>
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
                            <i class="fas fa-save me-2"></i> Simpan Buku
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk generate kode baru
        function generateNewCode() {
            const button = event.currentTarget;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';
            button.disabled = true;
            
            const randomCode = 'BK' + Math.floor(Math.random() * 900 + 100).toString().padStart(3, '0');
            document.querySelector('#kode_buku').value = randomCode;
            document.querySelector('input[name="kode_buku"]').value = randomCode;
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 1000);
        }
        
        // Preview cover image
        document.getElementById('cover').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('coverPreview');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" class="img-fluid h-100" style="object-fit: contain;">
                        <div class="mt-2">
                            <small class="text-success">${file.name}</small>
                        </div>
                    `;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = `
                    <i class="fas fa-book fa-3x text-muted mb-2"></i>
                    <p class="text-muted mb-0">Preview cover akan tampil di sini</p>
                `;
            }
        });
        
        // Validasi tahun terbit
        document.getElementById('tahun_terbit').addEventListener('change', function() {
            const year = parseInt(this.value);
            const currentYear = new Date().getFullYear();
            if (this.value && (year < 1000 || year > currentYear)) {
                alert('Tahun terbit harus antara 1000 dan ' + currentYear);
                this.value = '';
                this.focus();
            }
        });
        
        // Validasi stok
        document.getElementById('stok_total').addEventListener('change', function() {
            if (this.value < 1) {
                alert('Stok total minimal 1');
                this.value = 1;
                this.focus();
            }
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