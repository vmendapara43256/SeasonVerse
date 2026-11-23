<?php
session_start();

$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

$errorMsg = '';
$successMsg = '';
$token = $_GET['token'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($token)) {
        $errorMsg = "Invalid or missing reset token.";
    } else {
        // Verify token exists in database
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            $errorMsg = "Invalid or expired password reset link.";
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirm_password'];
                $email = $resetRecord['email'];

                $hasSpecial = preg_match('/[\W_]/', $password);

                if ($password !== $confirmPassword) {
                    $errorMsg = "Passwords do not match.";
                } elseif (strlen($password) < 8 || !$hasSpecial) {
                    $errorMsg = "Password must be at least 8 characters long and contain at least one special character.";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update user password
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->execute([$hashedPassword, $email]);

                    // Delete the used token
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->execute([$token]);

                    $successMsg = "Password successfully updated! <a href='user-login.php' style='color: #166534; font-weight:700;'>Click here to sign in</a>";
                }
            }
        }
    }
} catch (PDOException $e) {
    $errorMsg = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | New Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #1A2E23; min-height: 100vh; display: flex; align-items: center; justify-content: flex-end; position: relative; background-image: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; }
        body::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(15,28,21,0.85) 0%, rgba(15,28,21,0.4) 60%); z-index: 1; }

        .left-hero { position: absolute; left: 80px; top: 80px; z-index: 2; color: #fff; max-width: 500px; }
        .left-hero h1 { font-size: 3.5rem; font-weight: 700; line-height: 1.1; margin-bottom: 20px; font-family: serif; }
        .left-hero p { font-size: 0.95rem; color: #d0d7d1; line-height: 1.6; margin-bottom: 40px; }

        .auth-card-container { position: relative; z-index: 2; width: 100%; max-width: 520px; background: #FFFFFF; min-height: 100vh; padding: 50px 45px; display: flex; flex-direction: column; justify-content: center; box-shadow: -20px 0 50px rgba(0,0,0,0.3); }
        
        .brand-title { font-size: 1.8rem; font-weight: 700; text-align: center; font-family: serif; color: #1A2E23; margin-bottom: 5px; }
        .brand-subtitle { font-size: 0.82rem; color: #666; text-align: center; margin-bottom: 30px; }

        .form-group { position: relative; margin-bottom: 20px; }
        .form-group i { position: absolute; left: 16px; top: 16px; color: #888; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 12px 16px 12px 45px; background: #FAFAFA; border: 1px solid #EAE5DD; border-radius: 12px; font-size: 0.88rem; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; }
        .form-control:focus { border-color: #1B2E23; background: #fff; }

        .btn-submit { width: 100%; background: #1B2E23; color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #2C4A3A; }

        .bottom-links { text-align: center; margin-top: 25px; font-size: 0.82rem; color: #666; }
        .bottom-links a { color: #1B2E23; font-weight: 600; text-decoration: none; }

        .alert-error { background: #FDF2F2; color: #C53030; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.82rem; border: 1px solid #FED7D7; }
        .alert-success { background: #F0FDF4; color: #166534; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.82rem; border: 1px solid #DCFCE7; }
    </style>
</head>
<body>

    <div class="left-hero">
        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: #A3B899; margin-bottom: 10px; font-weight: 600;">Secure Reset</div>
        <h1>Create New Password</h1>
        <p>Ensure your new password is at least 8 characters long and includes a special character.</p>
    </div>

    <div class="auth-card-container">
        <h2 class="brand-title">SeasonVerse</h2>
        <p class="brand-subtitle">Enter your new secure password.</p>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $successMsg; ?></div>
        <?php elseif (!empty($errorMsg)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php else: ?>

        <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
            <div class="form-group">
                <i class="fa-regular fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="New Password (8+ chars & special)" required>
            </div>
            <div class="form-group">
                <i class="fa-regular fa-lock"></i>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New Password" required>
            </div>

            <button type="submit" name="update_password" class="btn-submit">Update Password</button>
        </form>

        <?php endif; ?>

        <div class="bottom-links">
            <a href="user-login.php">Back to Sign In</a>
        </div>
    </div>
</body>
</html>