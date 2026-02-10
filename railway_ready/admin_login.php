<?php
require_once 'auth.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - WeTrack</title>
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #1a365d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .error {
            color: #f56565;
            background: #fed7d7;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if (isset($_GET['error'])): ?>
            <div class="error">Invalid credentials</div>
        <?php endif; ?>
        <form method="POST" action="admin_login_process.php">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
            <div style="margin-top:15px; padding:10px; background:#f0f8ff; border-radius:6px;"> 
                <strong>Demo credentials:</strong>
                <div>Username: <code><?php echo getenv('ADMIN_USER') ?: 'admin'; ?></code></div>
                <div>Password: <code><?php echo getenv('ADMIN_PASS') ?: 'admin123'; ?></code></div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>