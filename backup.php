<?php
session_start();

// Gunakan require_once untuk menghindari duplikasi include
require_once 'lib/functions.php';

// Inisialisasi koneksi database
initDatabaseConnection();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Cek apakah user adalah admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    echo "<script>alert('Hanya admin yang dapat mengakses halaman ini!'); window.location='index.php';</script>";
    exit();
}

$message = '';
$backups = getBackupFiles();

// Proses backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '<div class="alert alert-danger">Token CSRF tidak valid!</div>';
    } else {
        $result = backupDatabase();
        
        if ($result && $result['success']) {
            $message = '<div class="alert alert-success">' . $result['message'] . '</div>';
            $backups = getBackupFiles(); // Refresh list
        } else {
            $message = '<div class="alert alert-danger">Gagal melakukan backup database!</div>';
        }
    }
}

// Proses hapus backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '<div class="alert alert-danger">Token CSRF tidak valid!</div>';
    } elseif (isset($_POST['backup_file']) && file_exists($_POST['backup_file'])) {
        if (unlink($_POST['backup_file'])) {
            $message = '<div class="alert alert-success">File backup berhasil dihapus!</div>';
            $backups = getBackupFiles(); // Refresh list
        } else {
            $message = '<div class="alert alert-danger">Gagal menghapus file backup!</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Database - Sistem Peminjaman Buku</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            margin-top: 50px;
            max-width: 1200px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .backup-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .file-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            transition: background-color 0.3s;
        }
        
        .file-item:hover {
            background-color: #f8f9fa;
        }
        
        .btn-backup {
            background: linear-gradient(180deg, #4e73df 0%, #3a56c4 100%);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
        }
        
        .btn-backup:hover {
            background: linear-gradient(180deg, #3a56c4 0%, #2e46a8 100%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
        <div class="container">
            <a class="navbar-brand" href="admin/index.php">
                <i class="fas fa-book-reader me-2"></i>
                Perpustakaan - Backup System
            </a>
            <div class="navbar-nav ms-auto">
                <a href="admin/index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4 text-primary">
                    <i class="fas fa-database me-2"></i> Backup Database
                </h2>
                
                <?php echo $message; ?>
                
                <!-- Backup Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-save me-2"></i> Buat Backup Database</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Klik tombol di bawah untuk membuat backup database saat ini. 
                            File backup akan disimpan dalam format SQL.
                        </p>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" name="backup" class="btn btn-backup text-white">
                                <i class="fas fa-database me-2"></i> Backup Database Sekarang
                            </button>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Backup akan mencakup semua tabel dan data dalam database.
                            </small>
                        </form>
                    </div>
                </div>
                
                <!-- Backup List Card -->
                <div class="card">
                    <div class="card-header bg-info">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i> Daftar File Backup</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada file backup.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive backup-list">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Nama File</th>
                                            <th>Ukuran</th>
                                            <th>Tanggal Dibuat</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $index => $backup): ?>
                                        <tr class="file-item">
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <i class="fas fa-file-code text-primary me-2"></i>
                                                <strong><?php echo htmlspecialchars($backup['name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo formatFileSize($backup['size']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $backup['modified']; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo $backup['path']; ?>" 
                                                       class="btn btn-primary" 
                                                       download
                                                       title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $index; ?>"
                                                            title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $index; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Konfirmasi Hapus</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Apakah Anda yakin ingin menghapus file backup ini?</p>
                                                                <p><strong><?php echo htmlspecialchars($backup['name']); ?></strong></p>
                                                                <p class="text-danger">
                                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                                    Tindakan ini tidak dapat dibatalkan!
                                                                </p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <form method="POST">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['path']); ?>">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" name="delete_backup" class="btn btn-danger">Hapus</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Summary -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Total Backup:</small>
                                        <h5 class="mb-0"><?php echo count($backups); ?> file</h5>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Total Ukuran:</small>
                                        <?php
                                        $totalSize = array_sum(array_column($backups, 'size'));
                                        ?>
                                        <h5 class="mb-0"><?php echo formatFileSize($totalSize); ?></h5>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Backup Terbaru:</small>
                                        <h5 class="mb-0"><?php echo !empty($backups) ? $backups[0]['modified'] : '-'; ?></h5>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Info Box -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i> Informasi Backup</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-shield-alt text-primary me-2"></i> Keamanan</h6>
                                <ul class="small">
                                    <li>File backup hanya dapat diakses oleh Administrator</li>
                                    <li>Direkomendasikan untuk menyimpan backup di lokasi yang aman</li>
                                    <li>Regular backup membantu mencegah kehilangan data</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-lightbulb text-warning me-2"></i> Tips</h6>
                                <ul class="small">
                                    <li>Lakukan backup sebelum melakukan perubahan besar</li>
                                    <li>Simpan minimal 3 versi backup terbaru</li>
                                    <li>Test restore backup secara berkala</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="mt-4 text-center">
                    <a href="admin/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Auto-close alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    </script>
</body>
</html>