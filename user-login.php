<?php

session_start();

$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

$errorMsg = '';
$successMsg = '';

if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $successMsg = "Account created successfully! Please sign in.";
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $errorMsg = "Please enter both email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRecord && password_verify($password, $userRecord['password']) && (!isset($userRecord['status']) || $userRecord['status'] === 'Active')) {
                session_regenerate_id(true);
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_name'] = $userRecord['full_name'];
                $_SESSION['user_email'] = $userRecord['email'];
                
                // Redirect user to home page after login
                header("Location: user-home.php");
                exit;
            } else {
                $errorMsg = "Invalid email or password.";
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
    <title>SeasonVerse | Sign In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #1A2E23; min-height: 100vh; display: flex; align-items: center; justify-content: flex-end; position: relative; background-image: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; }
        body::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(15,28,21,0.85) 0%, rgba(15,28,21,0.4) 60%); z-index: 1; }

        .left-hero { position: absolute; left: 80px; top: 80px; z-index: 2; color: #fff; max-width: 500px; }
        .left-hero h1 { font-size: 3.5rem; font-weight: 700; line-height: 1.1; margin-bottom: 20px; font-family: serif; }
        .left-hero p { font-size: 0.95rem; color: #d0d7d1; line-height: 1.6; margin-bottom: 40px; }
        .hero-feature { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px; }
        .hero-feature i { background: rgba(255,255,255,0.1); padding: 12px; border-radius: 12px; font-size: 1.1rem; }
        .hero-feature h4 { font-size: 0.95rem; font-weight: 600; margin-bottom: 3px; }
        .hero-feature p { font-size: 0.8rem; color: #aaa; margin: 0; }

        .auth-card-container { position: relative; z-index: 2; width: 100%; max-width: 520px; background: #FFFFFF; min-height: 100vh; padding: 50px 45px; display: flex; flex-direction: column; justify-content: center; box-shadow: -20px 0 50px rgba(0,0,0,0.3); }
        
        .brand-title { font-size: 1.8rem; font-weight: 700; text-align: center; font-family: serif; color: #1A2E23; margin-bottom: 5px; }
        .brand-subtitle { font-size: 0.82rem; color: #666; text-align: center; margin-bottom: 30px; }

        .auth-tabs { display: flex; background: #F5F2EC; border-radius: 30px; padding: 4px; margin-bottom: 30px; width: 220px; margin-left: auto; margin-right: auto; }
        .auth-tab { flex: 1; text-align: center; padding: 8px 0; font-size: 0.82rem; font-weight: 600; border-radius: 25px; text-decoration: none; color: #666; transition: all 0.3s; }
        .auth-tab.active { background: #1B2E23; color: #fff; }

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
        
        .top-nav { position: absolute; top: 30px; right: 40px; z-index: 3; display: flex; gap: 25px; align-items: center; }
        .top-nav a { color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.5px; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="user-signup.php">Create Account</a>
        <a href="#">BACK TO HOME</a>
    </div>

    <div class="left-hero">
        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: #A3B899; margin-bottom: 10px; font-weight: 600;">Welcome Back</div>
        <h1>Continue Your Journey</h1>
        <p>Sign in to access your saved travel itineraries, personalized seasonal recommendations, and smart packing guides.</p>
        
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
    </div>

    <!-- SIGN IN CARD -->
    <div class="auth-card-container">
        <h2 class="brand-title">SeasonVerse</h2>
        <p class="brand-subtitle">Sign in to your SeasonVerse account.</p>

        <div class="auth-tabs">
            <a href="user-login.php" class="auth-tab active">Sign In</a>
            <a href="user-signup.php" class="auth-tab">Create Account</a>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <form action="user-login.php" method="POST">
            <div class="form-group">
                <i class="fa-regular fa-envelope"></i>
                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            </div>
            <div class="form-group">
                <i class="fa-regular fa-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <div style="text-align: right; margin-bottom: 20px; font-size: 0.78rem;">
                <a href="forgot-password.php" style="color: #666; text-decoration: none;">Forgot password?</a>
                </div>
            </div>

            <button type="submit" name="login" class="btn-submit">Sign In</button>
        </form>

        <div class="bottom-links">
            Don't have an account? <a href="user-signup.php">Create Account</a><br>
            <span style="display: inline-block; margin-top: 12px;"><a href="admin-login.php">ADMINISTRATOR? ADMIN PORTAL &rarr;</a></span>
        </div>
    </div>
</body>
</html>