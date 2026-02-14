<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('pengembalian');
require_once '../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

// Ambil data pengembalian dari database
$query_master = "SELECT p.*, pm.kode_peminjaman, pm.nama_anggota, pm.tanggal_pinjam, pm.tanggal_harus_kembali
FROM pengembalian p
LEFT JOIN peminjaman pm ON p.peminjaman_id = pm.id
WHERE p.id = ?";
$stmt_master = mysqli_prepare($connection, $query_master);
mysqli_stmt_bind_param($stmt_master, "i", $id);
mysqli_stmt_execute($stmt_master);
$result_master = mysqli_stmt_get_result($stmt_master);
$pengembalian = mysqli_fetch_assoc($result_master);
mysqli_stmt_close($stmt_master);

if (!$pengembalian) {
    $_SESSION['error'] = "Data pengembalian tidak ditemukan.";
    redirect('index.php');
}

// Ambil data detail pengembalian
$query_detail = "SELECT pd.*, pmd.judul_buku, pmd.kode_buku, pmd.pengarang, pmd.cover as cover_buku, pmd.buku_id,
b.stok_tersedia
FROM pengembalian_detail pd
LEFT JOIN peminjaman_detail pmd ON pd.peminjaman_detail_id = pmd.id
LEFT JOIN buku b ON pmd.buku_id = b.id
WHERE pd.pengembalian_id = ?
ORDER BY pd.id";
$stmt_detail = mysqli_prepare($connection, $query_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);
$detail_pengembalian = [];
while ($row = mysqli_fetch_assoc($result_detail)) {
    $detail_pengembalian[] = $row;
}
mysqli_stmt_close($stmt_detail);

$error = $success = '';

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_pengembalian = trim($_POST['kode_pengembalian'] ?? '');
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    // Validasi
    if (empty($kode_pengembalian) || empty($tanggal_kembali)) {
        $error = "Kode Pengembalian dan Tanggal Kembali wajib diisi.";
    }
    
    if (!$error) {
        mysqli_begin_transaction($connection);
        try {
            // Update master pengembalian
            $sql_update = "UPDATE pengembalian 
                          SET kode_pengembalian = ?, tanggal_kembali = ?, keterangan = ?
                          WHERE id = ?";
            $stmt_update = mysqli_prepare($connection, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "sssi", 
                $kode_pengembalian, $tanggal_kembali, $keterangan, $id);
            
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Gagal update pengembalian: " . mysqli_error($connection));
            }
            mysqli_stmt_close($stmt_update);
            
            // Update detail pengembalian
            $total_denda = 0;
            $max_hari_terlambat = 0;
            
            foreach ($detail_pengembalian as $index => $detail) {
                $kondisi_buku = $_POST['kondisi_buku'][$index] ?? 'baik';
                $denda = (float)($_POST['denda'][$index] ?? 0);
                $jumlah_hari_terlambat = (int)($_POST['jumlah_hari_terlambat'][$index] ?? 0);
                $keterangan_detail = $_POST['keterangan_detail'][$index] ?? '';
                
                $total_denda += $denda;
                if ($jumlah_hari_terlambat > $max_hari_terlambat) {
                    $max_hari_terlambat = $jumlah_hari_terlambat;
                }
                
                // Update detail
                $sql_update_detail = "UPDATE pengembalian_detail 
                                     SET kondisi_buku = ?, denda = ?, jumlah_hari_terlambat = ?, keterangan = ?
                                     WHERE id = ?";
                $stmt_detail_update = mysqli_prepare($connection, $sql_update_detail);
                mysqli_stmt_bind_param($stmt_detail_update, "sdiss", 
                    $kondisi_buku, $denda, $jumlah_hari_terlambat, $keterangan_detail, $detail['id']);
                mysqli_stmt_execute($stmt_detail_update);
                mysqli_stmt_close($stmt_detail_update);
            }
            
            // Update total denda di master
            $sql_update_total = "UPDATE pengembalian 
                                SET denda = ?, jumlah_hari_terlambat = ?
                                WHERE id = ?";
            $stmt_total = mysqli_prepare($connection, $sql_update_total);
            mysqli_stmt_bind_param($stmt_total, "dii", $total_denda, $max_hari_terlambat, $id);
            mysqli_stmt_execute($stmt_total);
            mysqli_stmt_close($stmt_total);
            
            mysqli_commit($connection);
            $success = "Data pengembalian berhasil diperbarui!";
            
            // Refresh data
            $stmt_master = mysqli_prepare($connection, $query_master);
            mysqli_stmt_bind_param($stmt_master, "i", $id);
            mysqli_stmt_execute($stmt_master);
            $result_master = mysqli_stmt_get_result($stmt_master);
            $pengembalian = mysqli_fetch_assoc($result_master);
            mysqli_stmt_close($stmt_master);
            
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
    <title>Edit Pengembalian - Perpustakaan</title>
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
                        <li class="breadcrumb-item active">Edit Pengembalian</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-edit me-2 text-warning"></i>
                    Edit Pengembalian: <?= htmlspecialchars($pengembalian['kode_pengembalian']) ?>
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
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- Kolom Kiri -->
                <div class="col-lg-8">
                    <!-- Informasi Pengembalian -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Informasi Pengembalian
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Kode Pengembalian*</label>
                                    <input type="text" name="kode_pengembalian" class="form-control"
                                           value="<?= htmlspecialchars($pengembalian['kode_pengembalian']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Kode Peminjaman</label>
                                    <input type="text" class="form-control bg-light"
                                           value="<?= htmlspecialchars($pengembalian['kode_peminjaman']) ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Tanggal Kembali*</label>
                                    <input type="date" name="tanggal_kembali" class="form-control"
                                           value="<?= date('Y-m-d', strtotime($pengembalian['tanggal_kembali'])) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Petugas ID</label>
                                    <input type="text" class="form-control bg-light"
                                           value="<?= $_SESSION['user_id'] ?? 1 ?>" readonly>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Keterangan</label>
                                    <textarea name="keterangan" class="form-control" rows="3"
                                              placeholder="Keterangan pengembalian"><?= htmlspecialchars($pengembalian['keterangan'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Buku yang Dikembalikan -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-book me-2"></i> Daftar Buku yang Dikembalikan</span>
                            <span class="badge bg-primary">
                                <i class="fas fa-book me-1"></i><?= count($detail_pengembalian) ?> buku
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Buku</th>
                                            <th>Kondisi</th>
                                            <th>Terlambat</th>
                                            <th>Denda</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detail_pengembalian as $index => $detail): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($detail['cover_buku'])): ?>
                                                        <img src="../uploads/cover/<?= htmlspecialchars($detail['cover_buku']) ?>"
                                                             class="rounded me-3 book-cover"
                                                             width="40" height="50"
                                                             style="object-fit: cover;"
                                                             onclick="showCoverPreview('<?= htmlspecialchars($detail['cover_buku']) ?>', '<?= htmlspecialchars($detail['judul_buku']) ?>')"
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
                                                    <option value="baik" <?= $detail['kondisi_buku'] == 'baik' ? 'selected' : '' ?>>Baik</option>
                                                    <option value="rusak_ringan" <?= $detail['kondisi_buku'] == 'rusak_ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
                                                    <option value="rusak_berat" <?= $detail['kondisi_buku'] == 'rusak_berat' ? 'selected' : '' ?>>Rusak Berat</option>
                                                    <option value="hilang" <?= $detail['kondisi_buku'] == 'hilang' ? 'selected' : '' ?>>Hilang</option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" name="jumlah_hari_terlambat[<?= $index ?>]"
                                                           class="form-control" value="<?= $detail['jumlah_hari_terlambat'] ?>"
                                                           min="0" onchange="updateDenda(this, <?= $index ?>)"
                                                           style="width: 80px;">
                                                    <span class="input-group-text">hari</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" name="denda[<?= $index ?>]"
                                                           class="form-control denda-input"
                                                           value="<?= $detail['denda'] ?>" min="0" step="1000"
                                                           style="width: 120px;">
                                                </div>
                                            </td>
                                            <td>
                                                <textarea name="keterangan_detail[<?= $index ?>]"
                                                          class="form-control form-control-sm"
                                                          rows="1" placeholder="Keterangan"><?= htmlspecialchars($detail['keterangan'] ?? '') ?></textarea>
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
                                            <strong>Rp <?= number_format($pengembalian['denda'], 0, ',', '.') ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan -->
                <div class="col-lg-4">
                    <!-- Informasi Anggota -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-user me-2"></i> Informasi Anggota
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nama Anggota</label>
                                <p class="form-control bg-light"><?= htmlspecialchars($pengembalian['nama_anggota']) ?></p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Tanggal Pinjam</label>
                                    <p class="form-control bg-light">
                                        <?= date('d/m/Y', strtotime($pengembalian['tanggal_pinjam'])) ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Harus Kembali</label>
                                    <p class="form-control bg-light">
                                        <?= date('d/m/Y', strtotime($pengembalian['tanggal_harus_kembali'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="alert <?= $pengembalian['jumlah_hari_terlambat'] > 0 ? 'alert-danger' : 'alert-success' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Status:</span>
                                    <strong>
                                        <?php if ($pengembalian['jumlah_hari_terlambat'] > 0): ?>
                                            Terlambat <?= $pengembalian['jumlah_hari_terlambat'] ?> hari
                                        <?php else: ?>
                                            Tepat waktu
                                        <?php endif; ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Petunjuk Edit -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-2"></i> Petunjuk Edit
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Edit informasi pengembalian</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Update kondisi buku jika diperlukan</small>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Denda akan otomatis dihitung</small>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Klik gambar cover untuk preview</small>
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
                        <button type="submit" class="btn btn-lg btn-warning">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </form>
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
        }

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