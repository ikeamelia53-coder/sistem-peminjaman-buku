<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
requireAuth();
if (getUserRole() !== 'admin') {
    redirect('../login.php');
}

// Load database configuration
require_once '../config/database.php';

// Pastikan koneksi database berhasil
try {
    // Jika menggunakan PDO
    if (!isset($pdo) && isset($conn)) {
        $pdo = $conn; // Alias jika menggunakan $conn
    } elseif (!isset($pdo) && class_exists('PDO')) {
        // Jika database.php tidak membuat instance, buat manual
        $host = 'localhost';
        $dbname = 'peminjaman_buku';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Query untuk statistik
try {
    $total_buku = $pdo->query("SELECT COUNT(*) as total FROM buku")->fetch()['total'];
    $total_anggota = $pdo->query("SELECT COUNT(*) as total FROM anggota WHERE status='aktif'")->fetch()['total'];
    $peminjaman_aktif = $pdo->query("SELECT COUNT(*) as total FROM peminjaman WHERE status='dipinjam'")->fetch()['total'];
    $terlambat = $pdo->query("SELECT COUNT(*) as total FROM peminjaman WHERE status='terlambat'")->fetch()['total'];
} catch (PDOException $e) {
    // Set default values jika query gagal
    $total_buku = 0;
    $total_anggota = 0;
    $peminjaman_aktif = 0;
    $terlambat = 0;
}

// Query untuk distribusi kategori buku
try {
    $kategori_query = $pdo->query("
        SELECT kategori, COUNT(*) as jumlah 
        FROM buku 
        GROUP BY kategori 
        ORDER BY jumlah DESC 
        LIMIT 6
    ");
    $kategori_data = $kategori_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kategori_data = [];
}

// Query untuk status buku
try {
    $status_buku = $pdo->query("
        SELECT status, COUNT(*) as jumlah 
        FROM buku 
        GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $status_buku = [];
}

// Query untuk tren peminjaman 6 bulan terakhir
try {
    $tren_query = $pdo->query("
        SELECT 
            DATE_FORMAT(tanggal_pinjam, '%Y-%m') as bulan,
            COUNT(*) as jumlah
        FROM peminjaman 
        WHERE tanggal_pinjam >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(tanggal_pinjam, '%Y-%m')
        ORDER BY bulan ASC
    ");
    $tren_data = $tren_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tren_data = [];
}

// Query untuk buku terlaris (paling sering dipinjam)
try {
    $buku_terlaris = $pdo->query("
        SELECT 
            b.judul_buku, 
            COUNT(pd.buku_id) as jumlah_pinjam
        FROM peminjaman_detail pd
        JOIN buku b ON pd.buku_id = b.id
        GROUP BY pd.buku_id, b.judul_buku
        ORDER BY jumlah_pinjam DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $buku_terlaris = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Peminjaman Buku</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8B4513;
            --primary-dark: #654321;
            --primary-light: #A0522D;
            --secondary: #A0522D;
            --success: #2E7D32;
            --success-light: #4CAF50;
            --warning: #FF8C00;
            --warning-light: #FF9800;
            --danger: #C62828;
            --danger-light: #F44336;
            --info: #1565C0;
            --light: #f8f9fa;
            --dark: #2c2c2c;
            --gray: #6c757d;
            --border-radius: 16px;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f5f5dc 0%, #e8e4d4 100%);
            font-family: 'Poppins', sans-serif;
            color: #333;
            overflow-x: hidden;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            z-index: 100;
            padding-top: 20px;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 1.2rem 1.5rem;
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: block;
            text-align: center;
            letter-spacing: -0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
        }
        
        .sidebar-brand i {
            margin-right: 8px;
            font-size: 1.5rem;
        }
        
        .sidebar-menu {
            padding: 0 10px;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
            border-left: 4px solid white;
        }
        
        .sidebar-menu .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        .sidebar-menu .nav-link.text-danger:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .topnav {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 75px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f5f0 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            z-index: 99;
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .main-content {
            margin-left: 260px;
            padding: 95px 25px 25px 25px;
            min-height: 100vh;
        }
        
        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1.2rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
        }
        
        .card-header i {
            margin-right: 8px;
            font-size: 1.2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 22px;
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
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-right: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        .bg-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; }
        .bg-success-custom { background: linear-gradient(135deg, var(--success), var(--success-light)) !important; }
        .bg-warning-custom { background: linear-gradient(135deg, var(--warning), var(--warning-light)) !important; }
        .bg-danger-custom { background: linear-gradient(135deg, var(--danger), var(--danger-light)) !important; }
        .bg-info-custom { background: linear-gradient(135deg, var(--info), #1976D2) !important; }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }
        
        .welcome-card h1 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            opacity: 0.95;
            font-size: 1.05rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .progress-custom {
            height: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            background-color: #e9ecef;
        }
        
        .progress-bar {
            border-radius: 6px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .topnav {
                left: 0;
                width: 100%;
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 85px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-book-reader"></i>
            Perpustakaan Admin
        </a>
        <div class="sidebar-menu">
            <a href="index.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="../buku/index.php" class="nav-link">
                <i class="fas fa-book"></i> Buku
            </a>
            <a href="../peminjaman/index.php" class="nav-link">
                <i class="fas fa-clipboard-list"></i> Peminjaman
            </a>
            <a href="../pengembalian/index.php" class="nav-link">
                <i class="fas fa-undo"></i> Pengembalian
            </a>
            <a href="../anggota/index.php" class="nav-link">
                <i class="fas fa-users"></i> Anggota
            </a>
            <a href="../backup.php" class="nav-link">
                <i class="fas fa-database"></i> Backup Data
            </a>
            <a href="../logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Top Navigation -->
    <nav class="topnav">
        <button class="btn btn-link d-lg-none sidebar-toggle">
            <i class="fas fa-bars fa-2x"></i>
        </button>
        <div class="navbar-brand d-none d-lg-block">
            <h5 class="mb-0 fw-bold" style="color: var(--primary);">Dashboard Admin</h5>
            <small class="text-muted"><?= date('d F Y') ?></small>
        </div>
        <div class="user-menu">
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown" style="color: var(--primary-dark);">
                    <i class="fas fa-user-circle fa-2x me-2"></i>
                    <span class="d-none d-md-inline"><?= $_SESSION['username'] ?? 'Admin' ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profil</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Pengaturan</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid">
            <!-- Welcome Header -->
            <div class="welcome-card">
                <div class="d-flex align-items-center">
                    <div>
                        <h1>
                            <i class="fas fa-user-shield me-3"></i>
                            Selamat Datang, <?= $_SESSION['username'] ?? 'Admin' ?>!
                        </h1>
                        <p class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Anda login sebagai Administrator sistem peminjaman buku
                        </p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary-custom">
                                <i class="fas fa-book"></i>
                            </div>
                            <div>
                                <h3 class="mb-1 fw-bold"><?= $total_buku ?></h3>
                                <p class="text-muted mb-1" style="font-size: 0.9rem;">Total Buku</p>
                                <small class="text-primary-custom" style="font-size: 0.8rem;">
                                    <i class="fas fa-database me-1"></i>Data terupdate
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success-custom">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h3 class="mb-1 fw-bold"><?= $total_anggota ?></h3>
                                <p class="text-muted mb-1" style="font-size: 0.9rem;">Total Anggota</p>
                                <small class="text-success-custom" style="font-size: 0.8rem;">
                                    <i class="fas fa-check-circle me-1"></i>Semua aktif
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning-custom">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div>
                                <h3 class="mb-1 fw-bold"><?= $peminjaman_aktif ?></h3>
                                <p class="text-muted mb-1" style="font-size: 0.9rem;">Peminjaman Aktif</p>
                                <small class="text-warning-custom" style="font-size: 0.8rem;">
                                    <i class="fas fa-clock me-1"></i>Sedang dipinjam
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-danger-custom">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="mb-1 fw-bold"><?= $terlambat ?></h3>
                                <p class="text-muted mb-1" style="font-size: 0.9rem;">Terlambat</p>
                                <small class="text-danger-custom" style="font-size: 0.8rem;">
                                    <i class="fas fa-exclamation-circle me-1"></i>Butuh perhatian
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <!-- Pie Chart - Kategori Buku -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-pie"></i> Distribusi Kategori Buku
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Bar Chart - Status Buku -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-bar"></i> Status Buku
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Chart & Top Books -->
            <div class="row">
                <!-- Line Chart - Tren Peminjaman -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-line"></i> Tren Peminjaman 6 Bulan Terakhir
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Books -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-trophy"></i> Buku Terlaris
                        </div>
                        <div class="card-body">
                            <?php if (count($buku_terlaris) > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach($buku_terlaris as $index => $buku): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= $index + 1 ?>. <?= htmlspecialchars($buku['judul_buku']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= $buku['jumlah_pinjam'] ?>x dipinjam</small>
                                    </div>
                                    <span class="badge <?= $index == 0 ? 'bg-warning' : ($index == 1 ? 'bg-success' : 'bg-info') ?>">
                                        <?= $buku['jumlah_pinjam'] ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-center text-muted">Belum ada data peminjaman</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
        
        // Auto-close sidebar on mobile when clicking menu item
        document.querySelectorAll('.sidebar-menu .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                }
            });
        });
        
        // Prepare data for charts
        const kategoriLabels = <?= json_encode(array_column($kategori_data, 'kategori')) ?>;
        const kategoriJumlah = <?= json_encode(array_column($kategori_data, 'jumlah')) ?>;
        const statusLabels = <?= json_encode(array_column($status_buku, 'status')) ?>;
        const statusJumlah = <?= json_encode(array_column($status_buku, 'jumlah')) ?>;
        const trenLabels = <?= json_encode(array_column($tren_data, 'bulan')) ?>;
        const trenJumlah = <?= json_encode(array_column($tren_data, 'jumlah')) ?>;
        
        // Category Chart - Pie Chart
        const ctxCategory = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxCategory, {
            type: 'pie',
            data: {
                labels: kategoriLabels,
                datasets: [{
                    data: kategoriJumlah,
                    backgroundColor: [
                        'rgba(139, 69, 19, 0.9)',
                        'rgba(160, 82, 45, 0.9)',
                        'rgba(178, 34, 34, 0.9)',
                        'rgba(46, 125, 50, 0.9)',
                        'rgba(255, 140, 0, 0.9)',
                        'rgba(21, 101, 192, 0.9)'
                    ],
                    borderColor: [
                        'rgba(139, 69, 19, 1)',
                        'rgba(160, 82, 45, 1)',
                        'rgba(178, 34, 34, 1)',
                        'rgba(46, 125, 50, 1)',
                        'rgba(255, 140, 0, 1)',
                        'rgba(21, 101, 192, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(139, 69, 19, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((context.parsed * 100) / total);
                                return `${context.label}: ${context.parsed} buku (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Status Chart - Bar Chart
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'bar',
            data: {
                labels: statusLabels.map(label => {
                    if(label === 'tersedia') return 'Tersedia';
                    if(label === 'dipinjam') return 'Dipinjam';
                    if(label === 'rusak') return 'Rusak';
                    if(label === 'hilang') return 'Hilang';
                    return label;
                }),
                datasets: [{
                    label: 'Jumlah Buku',
                    data: statusJumlah,
                    backgroundColor: [
                        'rgba(46, 125, 50, 0.8)',
                        'rgba(139, 69, 19, 0.8)',
                        'rgba(255, 140, 0, 0.8)',
                        'rgba(198, 40, 40, 0.8)'
                    ],
                    borderColor: [
                        'rgba(46, 125, 50, 1)',
                        'rgba(139, 69, 19, 1)',
                        'rgba(255, 140, 0, 1)',
                        'rgba(198, 40, 40, 1)'
                    ],
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(139, 69, 19, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#666'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666'
                        }
                    }
                }
            }
        });
        
        // Trend Chart - Line Chart
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: trenLabels.map(label => {
                    const date = new Date(label + '-01');
                    return date.toLocaleString('id-ID', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Jumlah Peminjaman',
                    data: trenJumlah,
                    borderColor: 'rgba(139, 69, 19, 1)',
                    backgroundColor: 'rgba(139, 69, 19, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(139, 69, 19, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(139, 69, 19, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#666'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#666'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>