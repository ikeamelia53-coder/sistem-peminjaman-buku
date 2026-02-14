<?php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 1 in production with HTTPS
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', 1);
session_start();
if (isset($_SESSION['user_id']) && empty($_SESSION['role'])) {
    session_destroy();
}
require_once 'lib/functions.php';
require_once 'lib/auth.php';
if (isset($_SESSION['user_id'])) {
    redirectBasedOnRole($_SESSION['role']);
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request. CSRF token mismatch.');
    }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $role = login($username, $password);
        if ($role) {
            redirectBasedOnRole($role);
        } else {
            $error = "Invalid username or password.";
        }
    }
}
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Book Lending System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #3e2723 0%, #2e1b1b 100%);
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255,255,255,0.05) 0%, transparent 25%),
                radial-gradient(circle at 90% 80%, rgba(255,255,255,0.05) 0%, transparent 25%),
                url('https://i.pinimg.com/1200x/a3/eb/1e/a3eb1e5d87229ecdea29bfcd04453123.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.75;
            z-index: -1;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }
        
        .card-header {
            background: linear-gradient(120deg, #76380b, #b8936e);
            color: white;
            text-align: center;
            padding: 35px 20px;
            position: relative;
        }
        
        .card-header h2 {
            font-weight: 700;
            font-size: 1.9rem;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }
        
        .card-header p {
            opacity: 0.95;
            font-size: 1.05rem;
            font-weight: 300;
            max-width: 85%;
            margin: 0 auto;
        }
        
        .card-body {
            padding: 40px 35px;
        }
        
        .form-group {
            margin-bottom: 28px;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 9px;
            font-weight: 500;
            color: #333;
            font-size: 0.98rem;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 14px;
            font-size: 1.02rem;
            transition: all 0.3s ease;
            background-color: #f9fbfd;
            font-weight: 400;
        }
        
        .form-control:focus {
            border-color: #8B4513;
            box-shadow: 0 0 0 4px rgba(139, 69, 19, 0.18);
            background-color: white;
        }
        
        .form-control::placeholder {
            color: #aab3bc;
        }
        
        .btn-login {
            background: linear-gradient(120deg, #8B4513, #654321);
            color: white;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 18px rgba(139, 69, 19, 0.35);
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(139, 69, 19, 0.45);
        }
        
        .btn-login:active {
            transform: translateY(1px);
        }
        
        .btn-login::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(rgba(255,255,255,0.2), rgba(255,255,255,0));
            transform: rotate(30deg);
            transition: var(--transition);
            opacity: 0;
        }
        
        .btn-login:hover::after {
            opacity: 1;
            left: 100%;
        }
        
        .alert-danger {
            background-color: #fff8f8;
            border-color: #f5c6cb;
            color: #721c24;
            border-radius: 14px;
            padding: 16px 20px;
            font-weight: 500;
            margin-bottom: 28px;
            border-left: 4px solid #dc3545;
            font-size: 0.98rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 28px;
            color: #666;
            font-size: 1.02rem;
        }
        
        .register-link a {
            color: #654321;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.25s;
            position: relative;
            padding-bottom: 2px;
        }
        
        .register-link a::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #8B4513;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            color: #8B4513;
        }
        
        .register-link a:hover::after {
            width: 100%;
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 30px 0;
            color: #cbd5e1;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .divider::before {
            margin-right: 15px;
        }
        
        .divider::after {
            margin-left: 15px;
        }
        
        .logo {
            width: 75px;
            height: 75px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 22px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border: 2px solid rgba(139, 69, 19, 0.15);
        }
        
        .logo i {
            font-size: 32px;
            color: #8B4513;
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(139, 69, 19, 0.4); }
            70% { box-shadow: 0 0 0 12px rgba(139, 69, 19, 0); }
            100% { box-shadow: 0 0 0 0 rgba(139, 69, 19, 0); }
        }
        
        @media (max-width: 450px) {
            .card-body {
                padding: 35px 28px;
            }
            
            .card-header {
                padding: 30px 18px;
            }
            
            .card-header h2 {
                font-size: 1.7rem;
            }
            
            .btn-login {
                padding: 14px;
                font-size: 1.05rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                <div class="logo floating">
                    <i class="fas fa-book-open"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to access the book lending system</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-login pulse">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                    
                    <div class="divider">or</div>
                    
                    <div class="register-link">
                        Don't have an account? <a href="register.php">Create Account</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add subtle animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const loginCard = document.querySelector('.login-card');
            loginCard.style.opacity = '0';
            loginCard.style.transform = 'translateY(25px)';
            
            setTimeout(() => {
                loginCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                loginCard.style.opacity = '1';
                loginCard.style.transform = 'translateY(0)';
            }, 150);
            
            // Input field focus animation
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', () => {
                    input.parentElement.style.transform = 'scale(1)';
                });
            });
            
            // Remove pulse animation after first cycle
            setTimeout(() => {
                document.querySelector('.pulse').classList.remove('pulse');
            }, 2500);
        });
    </script>
</body>
</html>