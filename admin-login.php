<?php
session_start();
if (isset($_GET['logout'])) { unset($_SESSION['admin_logged_in'], $_SESSION['admin_email']); session_regenerate_id(true); header('Location: admin-login.php'); exit; }

// Database Connection configuration
$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

$error = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database automatically if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
    $pdo->exec("USE `$db`");

    // Create admin table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default static single admin if not already present
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = ?");
    $stmt->execute(['admin@seasonverse.com']);
    if ($stmt->fetchColumn() == 0) {
        // Password is 'admin123' hashed securely
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare("INSERT INTO admin_users (email, password) VALUES (?, ?)");
        $insertStmt->execute(['admin@seasonverse.com', $hashedPassword]);
    }

    // Handle Login Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $admin['email'];
            header("Location: admin-dashboard.php");
            exit;
        } else {
            $error = "Invalid administrator credentials.";
        }
    }

} catch (PDOException $e) {
    $error = "Database Connection Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Admin Portal</title>
    <!-- Link to External CSS -->
    <link rel="stylesheet" href="admin_auth.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="login-container">
        <h1 class="brand-title">SeasonVerse</h1>
        <div class="brand-subtitle">Admin Portal</div>

        <h2 class="welcome-heading">Welcome Back, Administrator.</h2>
        <p class="welcome-subtext">Sign in to manage the SeasonVerse platform.</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="security-badge">
            <i class="fa-solid fa-shield-halved"></i>
            <div><strong>ADMINISTRATOR ACCESS</strong> | SECURE MANAGEMENT PORTAL</div>
        </div>

        <form action="admin-login.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" class="form-control" placeholder="Admin email" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="submit-btn">
                Sign in to Admin Panel <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="footer-note">
            <i class="fa-solid fa-lock"></i> Secure administrator access. Your administrative credentials are protected.
        </div>

        <div>
            <a href="user-signup.php" class="back-link">&larr; Back to SeasonVerse</a>
        </div>
    </div>

    <div class="page-footer">
        <div class="page-footer-links">
            <a href="#">Privacy Policy</a>
            <span>|</span>
            <a href="#">Security Standards</a>
            <span>|</span>
            <a href="index.php">Back to SeasonVerse</a>
        </div>
        <div class="copyright">
            &copy; 2026 SeasonVerse Admin Portal. All rights reserved.
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById('password');
            const icon = document.querySelector('.toggle-password');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>