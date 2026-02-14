<?php
/**
 * File: auth.php
 * Fungsi autentikasi user
 */

// Gunakan require_once untuk menghindari duplikasi
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Authenticates user and sets session data.
 * Assumes session_start() was called BEFORE this function.
 */
function login($username, $password) {
    global $connection;
    
    $username = mysqli_real_escape_string($connection, sanitize($username));
    $sql = "SELECT id, username, password, role FROM users WHERE username=?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user && password_verify($password, $user['password'])) {
        // DO NOT call session_start() here!
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        mysqli_stmt_close($stmt);
        return $user['role'];
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

function registerUser($username, $password, $role) {
    global $connection;
    
    // Get allowed roles from menu.json
    $menuConfig = loadMenuConfig();
    $allowedRoles = array_keys($menuConfig['roles'] ?? []);
    
    $username = mysqli_real_escape_string($connection, sanitize($username));
    $role = in_array($role, $allowedRoles) ? $role : 'mahasiswa'; // Use roles from config
    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $username, $hashedPass, $role);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function redirectBasedOnRole($role) {
    $menuConfig = loadMenuConfig();
    
    if (isset($menuConfig['roles'][$role])) {
        $dashboard = $menuConfig['roles'][$role]['dashboard'] ?? 'login.php';
        // Gunakan BASE_URL yang sudah didefinisikan
        $fullUrl = BASE_URL . '/' . ltrim($dashboard, '/');
        header('Location: ' . $fullUrl);
        exit();
    } else {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}
?>