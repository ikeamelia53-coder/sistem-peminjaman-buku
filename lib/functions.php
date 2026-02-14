<?php
// ============================================
// KONSTANTA DAN KONEKSI DATABASE
// ============================================

// HAPUS definisi konstanta di sini - biarkan hanya di config/database.php
// Gunakan initDatabaseConnection() untuk memastikan konstanta terdefinisi

// Inisialisasi koneksi database jika belum ada
function initDatabaseConnection() {
    static $connection_initialized = false;
    
    if (!$connection_initialized) {
        global $connection;
        
        // Load config/database.php menggunakan require_once
        $config_file = __DIR__ . '/../config/database.php';
        if (file_exists($config_file)) {
            require_once $config_file;
        }
        
        // Jika konstanta belum terdefinisi (file config tidak ada), definisikan default
        if (!defined('DB_HOST')) {
            define('DB_HOST', 'localhost');
            define('DB_USER', 'root');
            define('DB_PASS', '');
            define('DB_NAME', 'sistem_peminjaman_buku');
            define('BASE_URL', 'http://localhost/sistem_peminjaman_buku');
        }
        
        // Buat koneksi jika belum ada
        if (!isset($connection)) {
            $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (!$connection) {
                die("Koneksi database gagal: " . mysqli_connect_error());
            }
            mysqli_set_charset($connection, "utf8mb4");
        }
        
        $connection_initialized = true;
    }
}

// ============================================
// FUNGSI UTILITAS UMUM
// ============================================

/**
 * Sanitize input data untuk mencegah XSS
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect ke URL tertentu
 */
function redirect($url) {
    // Pastikan BASE_URL sudah terdefinisi
    if (!defined('BASE_URL')) {
        initDatabaseConnection();
    }
    
    // Jika URL relatif, tambahkan BASE_URL
    if (!preg_match('~^(https?://|//)~', $url) && !str_starts_with($url, '/')) {
        $url = BASE_URL . '/' . ltrim($url, '/');
    }
    header("Location: " . $url);
    exit();
}

/**
 * Tampilkan alert Bootstrap
 */
function showAlert($message, $type = 'danger') {
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
    $safeMessage
    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Ambil role user dari session
 */
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// ============================================
// FUNGSI KEAMANAN
// ============================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token
 */
function validateCSRFToken($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Validasi password strength
 */
function validatePassword($password, $enabled = true) {
    if (!$enabled) {
        return []; // Always valid when disabled
    }
    
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password harus minimal 8 karakter.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password harus mengandung minimal 1 huruf besar.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password harus mengandung minimal 1 huruf kecil.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password harus mengandung minimal 1 angka.";
    }
    
    return $errors; // empty array = valid
}

// ============================================
// FUNGSI AKSES CONTROL
// ============================================

/**
 * Cek apakah user dapat mengakses halaman berdasarkan role
 */
function userCanAccess($allowedRoles = ['admin']) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $userRole = $_SESSION['role'] ?? '';
    return in_array($userRole, $allowedRoles);
}

/**
 * Tampilkan halaman akses ditolak
 */
function showAccessDenied($allowedRoles = ['admin']) {
    $roleLabels = getRoleLabels();
    $allowedLabels = array_map(fn($r) => $roleLabels[$r] ?? $r, $allowedRoles);
    $allowedText = implode(' atau ', $allowedLabels);
    
    // Pastikan BASE_URL terdefinisi
    if (!defined('BASE_URL')) {
        initDatabaseConnection();
    }
    
    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Akses Ditolak</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-6'>
                    <div class='card shadow'>
                        <div class='card-header bg-danger text-white'>
                            <h4 class='mb-0'>🛑 Akses Ditolak</h4>
                        </div>
                        <div class='card-body'>
                            <p>Halaman ini hanya dapat diakses oleh: <strong>$allowedText</strong>.</p>
                            <p>Anda login sebagai <strong>" . htmlspecialchars(getRoleLabel($_SESSION['role'] ?? 'user')) . "</strong>.</p>
                            <a href='" . BASE_URL . "/admin/index.php' class='btn btn-primary'>
                                Kembali ke Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

/**
 * Wajibkan akses berdasarkan role
 */
function requireRoleAccess($allowedRoles = ['admin', 'dosen'], $redirectUrl = null) {
    if (!userCanAccess($allowedRoles)) {
        if ($redirectUrl) {
            redirect($redirectUrl);
        } else {
            showAccessDenied($allowedRoles);
        }
    }
}

// ============================================
// FUNGSI MANAJEMEN MENU
// ============================================

/**
 * Load konfigurasi menu dari file JSON
 */
function loadMenuConfig() {
    $configFile = __DIR__ . '/../config/menu.json';
    if (file_exists($configFile)) {
        $jsonContent = file_get_contents($configFile);
        return json_decode($jsonContent, true) ?: [];
    }
    return [
        'roles' => [
            'admin' => ['label' => 'Administrator'],
            'user' => ['label' => 'Pengguna']
        ],
        'modules' => [
            'dashboard' => ['allowed_roles' => ['admin', 'user']],
            'backup' => ['allowed_roles' => ['admin']]
        ]
    ];
}

/**
 * Ambil label untuk role tertentu
 */
function getRoleLabel($role) {
    $menuConfig = loadMenuConfig();
    return $menuConfig['roles'][$role]['label'] ?? $role;
}

/**
 * Ambil semua label role
 */
function getRoleLabels() {
    $menuConfig = loadMenuConfig();
    $labels = [];
    foreach ($menuConfig['roles'] as $role => $config) {
        $labels[$role] = $config['label'] ?? $role;
    }
    return $labels;
}

/**
 * Ambil role yang diizinkan untuk modul tertentu
 */
function getAllowedRolesForModule($moduleName) {
    $menuConfig = loadMenuConfig();
    return $menuConfig['modules'][$moduleName]['allowed_roles'] ?? ['admin'];
}

/**
 * Cek apakah user dapat mengakses modul tertentu
 */
function userCanAccessModule($moduleName) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $userRole = $_SESSION['role'] ?? '';
    $allowedRoles = getAllowedRolesForModule($moduleName);
    return in_array($userRole, $allowedRoles);
}

/**
 * Wajibkan akses untuk modul tertentu
 */
function requireModuleAccess($moduleName, $redirectUrl = null) {
    $allowedRoles = getAllowedRolesForModule($moduleName);
    if (!userCanAccessModule($moduleName)) {
        if ($redirectUrl) {
            redirect($redirectUrl);
        } else {
            showAccessDenied($allowedRoles);
        }
    }
}

// ============================================
// FUNGSI HELPER UI
// ============================================

/**
 * Ambil warna badge berdasarkan status
 */
function getStatusBadgeColor($status) {
    switch ($status) {
        case 'tersedia':
        case 'aktif':
        case 'success':
        case 'selesai':
            return 'success';
        case 'dipinjam':
        case 'pending':
        case 'warning':
            return 'warning';
        case 'hilang':
        case 'nonaktif':
        case 'danger':
        case 'diblokir':
            return 'danger';
        case 'rusak':
        case 'secondary':
            return 'secondary';
        case 'dalam_perawatan':
        case 'info':
            return 'info';
        default:
            return 'light';
    }
}

/**
 * Generate URL lengkap dengan base_url
 */
function base_url($path = '') {
    // Pastikan BASE_URL terdefinisi
    if (!defined('BASE_URL')) {
        initDatabaseConnection();
    }
    return BASE_URL . '/' . ltrim($path, '/');
}

// ============================================
// FUNGSI HELPER FORM
// ============================================

/**
 * Buat dropdown dari tabel database
 */
function dropdownFromTable($table, $value_field = 'id', $label_field = 'name',
                          $selected = '', $name = '', $placeholder = '-- Pilih --',
                          $order_by = '', $where = '', $class = 'form-control') {
    initDatabaseConnection();
    global $connection;
    
    // Validasi dan sanitasi identifier
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $value_field = preg_replace('/[^a-zA-Z0-9_]/', '', $value_field);
    $label_field = preg_replace('/[^a-zA-Z0-9_]/', '', $label_field);
    
    // Build query dengan prepared statement
    $sql = "SELECT `$value_field`, `$label_field` FROM `$table`";
    $params = [];
    $types = '';
    
    if ($where) {
        $sql .= " WHERE $where";
        // Note: $where harus sudah aman dari SQL injection
    }
    
    if ($order_by) {
        $order_by = preg_replace('/[^a-zA-Z0-9_, ]/', '', $order_by);
        $sql .= " ORDER BY $order_by";
    } else {
        $sql .= " ORDER BY `$label_field` ASC";
    }
    
    $result = mysqli_query($connection, $sql);
    
    $html = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" ';
    $html .= 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $value = htmlspecialchars($row[$value_field], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($row[$label_field], ENT_QUOTES, 'UTF-8');
            $selected_attr = ((string)$row[$value_field] === (string)$selected) ? 'selected' : '';
            $html .= "<option value=\"$value\" $selected_attr>$label</option>";
        }
    } else {
        $html .= '<option value="">-- Tidak ada data --</option>';
    }
    
    $html .= '</select>';
    return $html;
}

/**
 * Ambil nilai field dari tabel
 */
function getFieldValue($table, $field, $where_field, $where_value) {
    initDatabaseConnection();
    global $connection;
    
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
    $where_field = preg_replace('/[^a-zA-Z0-9_]/', '', $where_field);
    
    $sql = "SELECT `$field` FROM `$table` WHERE `$where_field` = ? LIMIT 1";
    $stmt = mysqli_prepare($connection, $sql);
    
    if (!$stmt) {
        error_log("SQL prepare error: " . mysqli_error($connection));
        return null;
    }
    
    $type = is_int($where_value) ? 'i' : 's';
    mysqli_stmt_bind_param($stmt, $type, $where_value);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_row($result);
    mysqli_stmt_close($stmt);
    
    return $row ? $row[0] : null;
}

/**
 * Update total di master table dari detail table
 */
function updateMasterTotalFromDetail(
    $connection,
    $detail_table,
    $sum_field,
    $detail_fk_field,
    $master_table,
    $master_pk_field,
    $master_total_field,
    $master_id
) {
    // Sanitasi identifier
    $detail_table = preg_replace('/[^a-zA-Z0-9_]/', '', $detail_table);
    $sum_field = preg_replace('/[^a-zA-Z0-9_]/', '', $sum_field);
    $detail_fk_field = preg_replace('/[^a-zA-Z0-9_]/', '', $detail_fk_field);
    $master_table = preg_replace('/[^a-zA-Z0-9_]/', '', $master_table);
    $master_pk_field = preg_replace('/[^a-zA-Z0-9_]/', '', $master_pk_field);
    $master_total_field = preg_replace('/[^a-zA-Z0-9_]/', '', $master_total_field);
    
    // Step 1: Hitung total dari detail
    $sql_sum = "SELECT COALESCE(SUM(`$sum_field`), 0) AS total
                FROM `$detail_table`
                WHERE `$detail_fk_field` = ?";
    $stmt_sum = mysqli_prepare($connection, $sql_sum);
    
    if (!$stmt_sum) {
        error_log("updateMasterTotalFromDetail (SUM) error: " . mysqli_error($connection));
        return false;
    }
    
    $type = is_int($master_id) ? 'i' : 's';
    mysqli_stmt_bind_param($stmt_sum, $type, $master_id);
    mysqli_stmt_execute($stmt_sum);
    $result = mysqli_stmt_get_result($stmt_sum);
    $row = mysqli_fetch_assoc($result);
    $total = (float)($row['total'] ?? 0.0);
    mysqli_stmt_close($stmt_sum);
    
    // Step 2: Update master table
    $sql_update = "UPDATE `$master_table`
                   SET `$master_total_field` = ?
                   WHERE `$master_pk_field` = ?";
    $stmt_update = mysqli_prepare($connection, $sql_update);
    
    if (!$stmt_update) {
        error_log("updateMasterTotalFromDetail (UPDATE) error: " . mysqli_error($connection));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt_update, "d" . $type, $total, $master_id);
    $success = mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);
    
    return $success;
}

// ============================================
// FUNGSI BACKUP DATABASE (FULL VERSION)
// ============================================

/**
 * Backup database menggunakan mysqldump atau fallback PHP
 */
function backupDatabase($backupDir = 'backups/') {
    initDatabaseConnection();
    
    // Gunakan konstanta yang sudah didefinisikan
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
    
    // Pastikan direktori backup ada (path relatif dari root)
    $absoluteBackupDir = realpath(__DIR__ . '/..') . '/' . ltrim($backupDir, '/');
    
    if (!file_exists($absoluteBackupDir)) {
        if (!mkdir($absoluteBackupDir, 0777, true)) {
            return [
                'success' => false,
                'message' => 'Gagal membuat direktori backup'
            ];
        }
    }
    
    // Nama file backup
    $timestamp = date('Ymd_His');
    $backupFile = $absoluteBackupDir . '/backup_' . $timestamp . '.sql';
    $logFile = $absoluteBackupDir . '/backup_log.txt';
    
    // Coba metode mysqldump terlebih dahulu
    $result = backupWithMysqldump($db_host, $db_user, $db_pass, $db_name, $backupFile, $logFile);
    
    if (!$result['success']) {
        // Fallback ke metode PHP
        $result = backupWithPHP($db_host, $db_user, $db_pass, $db_name, $backupFile, $logFile);
    }
    
    // Catat log
    if ($result['success']) {
        $logMessage = date('Y-m-d H:i:s') . " - Backup berhasil: " . basename($backupFile) . 
                     " (" . filesize($backupFile) . " bytes)" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    return $result;
}

/**
 * Backup menggunakan mysqldump
 */
function backupWithMysqldump($host, $user, $pass, $dbname, $backupFile, $logFile) {
    // Cari path mysqldump
    $mysqldumpPath = '';
    $possiblePaths = [
        'mysqldump', // Jika ada di PATH
        'C:\\xampp\\mysql\\bin\\mysqldump.exe', // XAMPP Windows
        'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe', // WAMP
        '/usr/bin/mysqldump', // Linux
        '/usr/local/mysql/bin/mysqldump', // Mac
    ];
    
    foreach ($possiblePaths as $path) {
        if (@is_executable($path) || @file_exists($path)) {
            $mysqldumpPath = $path;
            break;
        }
    }
    
    if (empty($mysqldumpPath)) {
        return ['success' => false, 'message' => 'mysqldump tidak ditemukan'];
    }
    
    // Bangun command
    $command = '"' . $mysqldumpPath . '" --host=' . escapeshellarg($host) . 
               ' --user=' . escapeshellarg($user);
    
    if (!empty($pass)) {
        $command .= ' --password=' . escapeshellarg($pass);
    }
    
    $command .= ' ' . escapeshellarg($dbname) . 
                ' > "' . $backupFile . '"' . 
                ' 2>> "' . $logFile . '"';
    
    // Eksekusi command
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
        return [
            'success' => true,
            'message' => 'Database telah berhasil dibackup',
            'file' => $backupFile,
            'filename' => basename($backupFile),
            'size' => filesize($backupFile),
            'timestamp' => date('Ymd_His')
        ];
    }
    
    return ['success' => false, 'message' => 'mysqldump gagal (code: ' . $returnVar . ')'];
}

/**
 * Backup menggunakan PHP (fallback method)
 */
function backupWithPHP($host, $user, $pass, $dbname, $backupFile, $logFile) {
    global $connection;
    
    // Gunakan koneksi yang ada atau buat baru
    if (!isset($connection)) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        if ($conn->connect_error) {
            file_put_contents($logFile, "Connection failed: " . $conn->connect_error . PHP_EOL, FILE_APPEND);
            return ['success' => false, 'message' => 'Koneksi database gagal'];
        }
        $use_existing = false;
    } else {
        $conn = $connection;
        $use_existing = true;
    }
    
    $conn->query("SET NAMES 'utf8mb4'");
    
    // Mulai backup
    $sqlScript = "-- Backup Database: $dbname\n";
    $sqlScript .= "-- Waktu Backup: " . date('Y-m-d H:i:s') . "\n";
    $sqlScript .= "-- Metode: PHP\n";
    $sqlScript .= "-- ============================================\n\n";
    
    // Dapatkan semua tabel
    $tablesQuery = $conn->query("SHOW TABLES");
    if (!$tablesQuery) {
        file_put_contents($logFile, "Error getting tables: " . $conn->error . PHP_EOL, FILE_APPEND);
        if (!$use_existing) $conn->close();
        return ['success' => false, 'message' => 'Gagal mendapatkan daftar tabel'];
    }
    
    $tableCount = 0;
    
    while ($tableRow = $tablesQuery->fetch_row()) {
        $table = $tableRow[0];
        $tableCount++;
        
        // CREATE TABLE statement
        $createTable = $conn->query("SHOW CREATE TABLE `$table`");
        if (!$createTable) {
            file_put_contents($logFile, "Error getting create table for $table: " . $conn->error . PHP_EOL, FILE_APPEND);
            continue;
        }
        
        $createRow = $createTable->fetch_row();
        $sqlScript .= "\n-- \n-- Struktur tabel: `$table`\n-- \n\n";
        $sqlScript .= $createRow[1] . ";\n\n";
        
        // INSERT data
        $sqlScript .= "-- \n-- Data untuk tabel: `$table`\n-- \n\n";
        
        $dataQuery = $conn->query("SELECT * FROM `$table`");
        if (!$dataQuery) {
            file_put_contents($logFile, "Error getting data from $table: " . $conn->error . PHP_EOL, FILE_APPEND);
            continue;
        }
        
        while ($row = $dataQuery->fetch_assoc()) {
            $columns = array_map(function($col) {
                return "`$col`";
            }, array_keys($row));
            
            $values = array_map(function($value) use ($conn) {
                if (is_null($value)) {
                    return "NULL";
                } else {
                    return "'" . $conn->real_escape_string($value) . "'";
                }
            }, array_values($row));
            
            $sqlScript .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") ";
            $sqlScript .= "VALUES (" . implode(', ', $values) . ");\n";
        }
        
        $sqlScript .= "\n";
    }
    
    $sqlScript .= "-- \n-- Statistik Backup:\n";
    $sqlScript .= "-- Total Tabel: $tableCount\n";
    $sqlScript .= "-- Waktu Selesai: " . date('Y-m-d H:i:s') . "\n";
    
    // Tulis ke file
    $bytesWritten = file_put_contents($backupFile, $sqlScript);
    
    if (!$use_existing) {
        $conn->close();
    }
    
    if ($bytesWritten > 0) {
        return [
            'success' => true,
            'message' => 'Database telah berhasil dibackup (PHP Method)',
            'file' => $backupFile,
            'filename' => basename($backupFile),
            'size' => $bytesWritten,
            'timestamp' => date('Ymd_His')
        ];
    }
    
    return ['success' => false, 'message' => 'Gagal menulis file backup'];
}

/**
 * Dapatkan daftar file backup
 */
function getBackupFiles($backupDir = 'backups/') {
    $absoluteBackupDir = realpath(__DIR__ . '/..') . '/' . ltrim($backupDir, '/');
    $backups = [];
    
    if (file_exists($absoluteBackupDir) && is_dir($absoluteBackupDir)) {
        $files = scandir($absoluteBackupDir);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $filePath = $absoluteBackupDir . '/' . $file;
                $backups[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'path' => $filePath
                ];
            }
        }
        
        // Urutkan berdasarkan tanggal modifikasi (terbaru dulu)
        usort($backups, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
    }
    
    return $backups;
}

/**
 * Format ukuran file menjadi readable
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Hapus file backup lama
 */
function cleanupOldBackups($backupDir = 'backups/', $daysToKeep = 30) {
    $backups = getBackupFiles($backupDir);
    $deleted = 0;
    $now = time();
    $cutoff = $daysToKeep * 24 * 60 * 60;
    
    foreach ($backups as $backup) {
        $fileAge = $now - filemtime($backup['path']);
        if ($fileAge > $cutoff) {
            if (unlink($backup['path'])) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

/**
 * Cek apakah backup otomatis diaktifkan
 */
function isAutoBackupEnabled() {
    $configFile = __DIR__ . '/../config/backup_config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return $config['auto_backup'] ?? false;
    }
    return false;
}

// ============================================
// FUNGSI TAMBAHAN YANG SERING DIPAKAI
// ============================================

/**
 * Generate pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern = '?page=%d') {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . sprintf($urlPattern, $currentPage - 1) . '" aria-label="Previous">';
        $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . sprintf($urlPattern, $currentPage + 1) . '" aria-label="Next">';
        $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Format tanggal Indonesia
 */
function formatDateIndonesian($date, $includeTime = false) {
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = $days[date('w', $timestamp)];
    $dateNum = date('j', $timestamp);
    $month = $months[date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    $formatted = "$day, $dateNum $month $year";
    
    if ($includeTime) {
        $time = date('H:i', $timestamp);
        $formatted .= " pukul $time";
    }
    
    return $formatted;
}

/**
 * Cek apakah request adalah AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

/**
 * Validasi email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Log activity
 */
function logActivity($activity, $userId = null) {
    initDatabaseConnection();
    global $connection;
    
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO activity_logs (user_id, activity, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $userId, $activity, $ip, $userAgent);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>