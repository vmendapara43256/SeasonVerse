<?php
session_start();

$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

$errorMsg = '';
$successMsg = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
    $pdo->exec("USE `$db`");
    
    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        preferred_season VARCHAR(50),
        travel_style VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Automatically add 'phone' column if it was missing from an older table version
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) AFTER email");
    }

    $registrationsAllowed = true;
    $settingsTable = $pdo->query("SHOW TABLES LIKE 'admin_settings'")->fetchColumn();
    if ($settingsTable) {
        $settingQuery = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'allow_registrations'");
        $settingQuery->execute();
        $registrationSetting = $settingQuery->fetchColumn();
        if ($registrationSetting !== false) { $registrationsAllowed = ($registrationSetting === '1'); }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
        if (!$registrationsAllowed) {
            $errorMsg = 'New registrations are currently disabled by the administrator.';
        } else {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $season = trim($_POST['preferred_season']);
        $style = trim($_POST['travel_style']);

        $hasSpecial = preg_match('/[\W_]/', $password);

        if (empty($fullName) || empty($email) || empty($password)) {
            $errorMsg = "Please fill in all required fields.";
        } elseif ($password !== $confirmPassword) {
            $errorMsg = "Passwords do not match.";
        } elseif (strlen($password) < 8 || !$hasSpecial) {
            $errorMsg = "Password must be at least 8 characters long and contain at least one special character.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errorMsg = "Email address is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, preferred_season, travel_style) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fullName, $email, $phone, $hashedPassword, $season, $style]);
                
                header("Location: user-login.php?registered=success");
                exit;
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
    <title>SeasonVerse | Create Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #1A2E23; min-height: 100vh; display: flex; align-items: center; justify-content: flex-end; position: relative; background-image: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; }
        
        /* Dark overlay gradient matching Figma */
        body::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(15,28,21,0.85) 0%, rgba(15,28,21,0.4) 60%); z-index: 1; }

        .left-hero { position: absolute; left: 80px; top: 80px; z-index: 2; color: #fff; max-width: 500px; }
        .left-hero h1 { font-size: 3.5rem; font-weight: 700; line-height: 1.1; margin-bottom: 20px; font-family: serif; }
        .left-hero p { font-size: 0.95rem; color: #d0d7d1; line-height: 1.6; margin-bottom: 40px; }
        .hero-feature { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px; }
        .hero-feature i { background: rgba(255,255,255,0.1); padding: 12px; border-radius: 12px; font-size: 1.1rem; }
        .hero-feature h4 { font-size: 0.95rem; font-weight: 600; margin-bottom: 3px; }
        .hero-feature p { font-size: 0.8rem; color: #aaa; margin: 0; }

        .auth-card-container { position: relative; z-index: 2; width: 100%; max-width: 520px; background: #FFFFFF; min-height: 100vh; padding: 50px 45px; display: flex; flex-direction: column; justify-content: center; box-shadow: -20px 0 50px rgba(0,0,0,0.3); overflow-y: auto; }
        
        .brand-title { font-size: 1.8rem; font-weight: 700; text-align: center; font-family: serif; color: #1A2E23; margin-bottom: 5px; }
        .brand-subtitle { font-size: 0.82rem; color: #666; text-align: center; margin-bottom: 30px; }

        .auth-tabs { display: flex; background: #F5F2EC; border-radius: 30px; padding: 4px; margin-bottom: 30px; width: 220px; margin-left: auto; margin-right: auto; }
        .auth-tab { flex: 1; text-align: center; padding: 8px 0; font-size: 0.82rem; font-weight: 600; border-radius: 25px; text-decoration: none; color: #666; transition: all 0.3s; }
        .auth-tab.active { background: #1B2E23; color: #fff; }

        .form-group { position: relative; margin-bottom: 15px; }
        .form-group i { position: absolute; left: 16px; top: 16px; color: #888; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 12px 16px 12px 45px; background: #FAFAFA; border: 1px solid #EAE5DD; border-radius: 12px; font-size: 0.88rem; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; transition: border 0.2s; }
        .form-control:focus { border-color: #1B2E23; background: #fff; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        select.form-control { appearance: none; padding-right: 35px; cursor: pointer; }

        .terms-row { display: flex; align-items: center; gap: 10px; font-size: 0.78rem; color: #666; margin: 15px 0 25px 0; }
        .terms-row a { color: #1B2E23; font-weight: 600; text-decoration: none; }

        .btn-submit { width: 100%; background: #1B2E23; color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #2C4A3A; }

        .bottom-links { text-align: center; margin-top: 25px; font-size: 0.82rem; color: #666; }
        .bottom-links a { color: #1B2E23; font-weight: 600; text-decoration: none; }

        .alert-error { background: #FDF2F2; color: #C53030; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.82rem; border: 1px solid #FED7D7; }
        
        .top-nav { position: absolute; top: 30px; right: 40px; z-index: 3; display: flex; gap: 25px; align-items: center; }
        .top-nav a { color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.5px; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="user-login.php">Sign In</a>
        <a href="#">BACK TO HOME</a>
    </div>

    <!-- LEFT HERO SECTION -->
    <div class="left-hero">
        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: #A3B899; margin-bottom: 10px; font-weight: 600;">Welcome to SeasonVerse</div>
        <h1>Your Journey Begins Here</h1>
        <p>Create your account and unlock personalized seasonal travel planning, destination inspiration, smart packing guidance, and bespoke travel recommendations designed around you.</p>
        
        <div class="hero-feature">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <div>
                <h4>AI Travel Planning</h4>
                <p>Bespoke itineraries curated by intelligence.</p>
            </div>
        </div>
        <div class="hero-feature">
            <i class="fa-regular fa-compass"></i>
            <div>
                <h4>Seasonal Destinations</h4>
                <p>Discover the world at its most beautiful.</p>
            </div>
        </div>
        <div class="hero-feature">
            <i class="fa-solid fa-shirt"></i>
            <div>
                <h4>Smart Wardrobe</h4>
                <p>Automated packing lists for every climate.</p>
            </div>
        </div>
    </div>

    <!-- CREATE ACCOUNT CARD -->
    <div class="auth-card-container">
        <h2 class="brand-title">SeasonVerse</h2>
        <p class="brand-subtitle">Start your personalized seasonal journey with SeasonVerse.</p>

        <div class="auth-tabs">
            <a href="user-login.php" class="auth-tab">Sign In</a>
            <a href="user-signup.php" class="auth-tab active">Create Account</a>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <form action="user-signup.php" method="POST">
            <div class="form-group">
                <i class="fa-regular fa-user"></i>
                <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <i class="fa-solid fa-phone"></i>
                    <input type="text" name="phone" class="form-control" placeholder="Phone Number">
                </div>
                <div class="form-group">
                    <i class="fa-regular fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Password (8+ chars & special)" required>
                </div>
            </div>
            <div class="form-group">
                <i class="fa-regular fa-lock"></i>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <select name="preferred_season" class="form-control">
                        <option value="">Preferred Season</option>
                        <option value="Winter">Winter</option>
                        <option value="Summer">Summer</option>
                        <option value="Monsoon">Monsoon</option>
                    </select>
                </div>
                <div class="form-group">
                    <select name="travel_style" class="form-control">
                        <option value="">Travel Style</option>
                        <option value="Couple">Couple</option>
                        <option value="Solo">Solo</option>
                        <option value="Family">Family</option>
                        <option value="Friends">Friends</option>
                    </select>
                </div>
            </div>

            <div class="terms-row">
                <input type="checkbox" required id="terms">
                <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</label>
            </div>

            <button type="submit" name="register" class="btn-submit" <?php echo (isset($registrationsAllowed) && !$registrationsAllowed) ? 'disabled' : ''; ?>>Create Account</button>
        </form>

        <div class="bottom-links">
            Already have an account? <a href="user-login.php">Sign In</a><br>
            <span style="display: inline-block; margin-top: 12px;"><a href="admin-login.php">ADMINISTRATOR? ADMIN PORTAL &rarr;</a></span>
        </div>
    </div>
</body>
</html>