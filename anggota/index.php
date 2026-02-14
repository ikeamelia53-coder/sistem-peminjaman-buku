<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('anggota');
require_once '../config/database.php';

// Ambil parameter pencarian dan filter
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Query untuk mengambil data anggota dengan filter
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(nama_anggota LIKE ? OR kode_anggota LIKE ? OR email LIKE ? OR telepon LIKE ?)";
    $search_param = "%{$search}%";
    for ($i = 0; $i < 4; $i++) {
        $params[] = $search_param;
        $types .= 's';
    }
}

if (!empty($status)) {
    $where_clauses[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Query untuk data anggota
$query = "SELECT SQL_CALC_FOUND_ROWS * FROM anggota {$where_sql} ORDER BY id DESC LIMIT ?, ?";
$types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

$stmt = mysqli_prepare($connection, $query);
if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$anggota_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Hitung total data
$total_result = mysqli_query($connection, "SELECT FOUND_ROWS() as total");
$total_data = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_data / $per_page);

// Daftar status
$status_options = [
    'aktif' => 'Aktif',
    'nonaktif' => 'Nonaktif',
    'diblokir' => 'Diblokir'
];

// Ambil statistik
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
    SUM(CASE WHEN status = 'diblokir' THEN 1 ELSE 0 END) as diblokir
    FROM anggota";
$stats_result = mysqli_query($connection, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Pesan dari session
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Anggota - Perpustakaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #8B4513;
            --primary-dark: #654321;
            --primary-light: #A0522D;
            --success: #2E7D32;
            --success-light: #4CAF50;
            --warning: #FF8C00;
            --warning-light: #FF9800;
            --danger: #C62828;
            --danger-light: #F44336;
            --info: #1565C0;
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
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(139, 69, 19, 0.2);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
            margin-right: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        .bg-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; }
        .bg-success-custom { background: linear-gradient(135deg, var(--success), var(--success-light)) !important; }
        .bg-warning-custom { background: linear-gradient(135deg, var(--warning), var(--warning-light)) !important; }
        .bg-danger-custom { background: linear-gradient(135deg, var(--danger), var(--danger-light)) !important; }
        
        .table-hover tbody tr:hover {
            background-color: rgba(139, 69, 19, 0.05);
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
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
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
                        <li class="breadcrumb-item"><a href="../admin/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Daftar Anggota</li>
                    </ol>
                </nav>
                <h2>
                    <i class="fas fa-users me-2 text-primary"></i>
                    Daftar Anggota
                </h2>
            </div>
            <div class="col-md-4 text-end">
                <a href="add.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i> Tambah Anggota
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filter dan Pencarian -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i> Filter dan Pencarian
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Cari Anggota</label>
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Nama, kode, email, atau telepon..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Semua Status</option>
                            <?php foreach ($status_options as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                    <?= ($status == $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Filter
                            </button>
                            <?php if ($search || $status): ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Reset
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistik Anggota -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary-custom">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <small class="text-muted">Total Anggota</small>
                            <h3 class="mb-0 fw-bold"><?= $stats['total'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-custom">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <small class="text-muted">Aktif</small>
                            <h3 class="mb-0 fw-bold text-success"><?= $stats['aktif'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-custom">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div>
                            <small class="text-muted">Nonaktif</small>
                            <h3 class="mb-0 fw-bold text-warning"><?= $stats['nonaktif'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger-custom">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div>
                            <small class="text-muted">Diblokir</small>
                            <h3 class="mb-0 fw-bold text-danger"><?= $stats['diblokir'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Daftar Anggota -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i> Daftar Anggota</span>
                <span class="badge bg-primary">Total: <?= $total_data ?> anggota</span>
            </div>
            <div class="card-body">
                <?php if (empty($anggota_list)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Tidak ada data anggota</h4>
                    <p class="text-muted">Mulai dengan menambahkan anggota baru</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Tambah Anggota Pertama
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th width="60">Avatar</th>
                                <th>Kode Anggota</th>
                                <th>Nama Anggota</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Status</th>
                                <th width="180">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; ?>
                            <?php foreach ($anggota_list as $anggota): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="avatar">
                                        <?= strtoupper(substr($anggota['nama_anggota'], 0, 1)) ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($anggota['kode_anggota']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($anggota['nama_anggota']) ?></td>
                                <td>
                                    <?php if (!empty($anggota['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($anggota['email']) ?>" 
                                       class="text-decoration-none text-primary">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?= htmlspecialchars($anggota['email']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($anggota['telepon'])): ?>
                                    <a href="tel:<?= htmlspecialchars($anggota['telepon']) ?>" 
                                       class="text-decoration-none text-success">
                                        <i class="fas fa-phone me-1"></i>
                                        <?= htmlspecialchars($anggota['telepon']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $anggota['status'] == 'aktif' ? 'success' : ($anggota['status'] == 'nonaktif' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($anggota['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit.php?id=<?= $anggota['id'] ?>"
                                           class="btn btn-warning" title="Edit Anggota">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete.php?id=<?= $anggota['id'] ?>"
                                           class="btn btn-danger"
                                           title="Hapus Anggota"
                                           onclick="return confirm('Hapus anggota <?= htmlspecialchars(addslashes($anggota['nama_anggota'])) ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif;
                        endif;
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor;
                        if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                <?= $total_pages ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>