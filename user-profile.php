<?php
session_start();

/* A profile must always belong to the authenticated account. */
if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_email'])) {
    header('Location: user-login.php');
    exit;
}

$host = 'localhost';
$db = 'seasonverse_db';
$dbUser = 'root';
$dbPass = '';
$defaultAvatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    /* Read identity and signup fields from the real registration record.
       Do not use hard-coded/demo identity values or a separate profile copy. */
    $userStmt = $pdo->prepare("SELECT id, full_name, email, phone, preferred_season, travel_style, created_at FROM users WHERE email = ? LIMIT 1");
    $userStmt->execute([$_SESSION['user_email']]);
    $user = $userStmt->fetch();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: user-login.php');
        exit;
    }

    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $userEmail = $user['email'];
    $userName = $user['full_name'];

    /* Keep optional profile-only fields separately; signup-owned fields stay in users. */
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(150) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        phone_number VARCHAR(30) DEFAULT NULL,
        current_location VARCHAR(150) DEFAULT NULL,
        birth_date VARCHAR(50) DEFAULT NULL,
        profile_pic VARCHAR(255) DEFAULT NULL,
        preferred_season VARCHAR(30) DEFAULT 'Winter',
        travel_style VARCHAR(30) DEFAULT 'Solo',
        travel_pace VARCHAR(30) DEFAULT 'Balanced',
        budget_style VARCHAR(30) DEFAULT 'Mid-range',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $profileStmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_email = ?");
    $profileStmt->execute([$userEmail]);
    $profile = $profileStmt->fetch();

    /* Remove placeholder values created by the earlier demo profile implementation. */
    if ($profile) {
        $pdo->prepare("UPDATE user_profiles SET current_location = NULL WHERE user_email = ? AND current_location = 'London, United Kingdom'")->execute([$userEmail]);
        $pdo->prepare("UPDATE user_profiles SET birth_date = NULL WHERE user_email = ? AND birth_date = 'March 12, 1992'")->execute([$userEmail]);
        $profileStmt->execute([$userEmail]);
        $profile = $profileStmt->fetch();
    }

    if (!$profile) {
        $createProfile = $pdo->prepare("INSERT INTO user_profiles (user_email, full_name, phone_number, preferred_season, travel_style) VALUES (?, ?, ?, ?, ?)");
        $createProfile->execute([$userEmail, $user['full_name'], $user['phone'], $user['preferred_season'], $user['travel_style']]);
        $profileStmt->execute([$userEmail]);
        $profile = $profileStmt->fetch();
    }

    $profilePic = !empty($profile['profile_pic']) ? $profile['profile_pic'] : $defaultAvatar;
    $userInitial = strtoupper(substr(trim($userName), 0, 1));

    /* AJAX profile update: updates signup data in users and extra data in user_profiles. */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
        header('Content-Type: application/json; charset=utf-8');

        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $location = trim($_POST['current_location'] ?? '');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $season = trim($_POST['preferred_season'] ?? '');
        $style = trim($_POST['travel_style'] ?? '');

        $allowedSeasons = ['Winter', 'Summer', 'Monsoon'];
        $allowedStyles = ['Solo', 'Couple', 'Family', 'Friends'];

        if ($fullName === '' || strlen($fullName) > 100) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Enter a valid name (maximum 100 characters).']);
            exit;
        }
        if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Enter a valid phone number.']);
            exit;
        }
        if ($birthDate !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) || strtotime($birthDate) === false || $birthDate > date('Y-m-d'))) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Enter a valid date of birth.']);
            exit;
        }
        if (!in_array($season, $allowedSeasons, true) || !in_array($style, $allowedStyles, true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Choose a valid travel preference.']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $updateUser = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, preferred_season = ?, travel_style = ? WHERE email = ?");
            $updateUser->execute([$fullName, $phone, $season, $style, $userEmail]);

            $updateProfile = $pdo->prepare("UPDATE user_profiles SET full_name = ?, phone_number = ?, preferred_season = ?, travel_style = ?, current_location = ?, birth_date = ? WHERE user_email = ?");
            $updateProfile->execute([$fullName, $phone, $season, $style, $location !== '' ? $location : null, $birthDate !== '' ? $birthDate : null, $userEmail]);
            $pdo->commit();

            $_SESSION['user_name'] = $fullName;
            echo json_encode(['status' => 'success', 'message' => 'Your profile has been saved.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    /* Secure image upload: validate actual MIME type, extension and size. */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
        $file = $_FILES['profile_pic'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            header('Location: user-profile.php?status=upload_error');
            exit;
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            header('Location: user-profile.php?status=too_large');
            exit;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
            header('Location: user-profile.php?status=invalid_image');
            exit;
        }
        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            header('Location: user-profile.php?status=upload_error');
            exit;
        }
        $fileName = 'profile_' . (int)$user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $absolutePath = $uploadDir . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            header('Location: user-profile.php?status=upload_error');
            exit;
        }
        $relativePath = 'uploads/profiles/' . $fileName;
        $picUpdate = $pdo->prepare("UPDATE user_profiles SET profile_pic = ? WHERE user_email = ?");
        $picUpdate->execute([$relativePath, $userEmail]);
        if (!empty($profile['profile_pic']) && strpos($profile['profile_pic'], 'uploads/profiles/') === 0) {
            $oldFile = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $profile['profile_pic']);
            if (is_file($oldFile)) { @unlink($oldFile); }
        }
        $_SESSION['user_pic'] = $relativePath;
        header('Location: user-profile.php?status=photo_saved');
        exit;
    }

    /* Refresh the user row after any previous update. */
    $userStmt->execute([$userEmail]);
    $user = $userStmt->fetch();
    $profileStmt->execute([$userEmail]);
    $profile = $profileStmt->fetch();
    $profilePic = !empty($profile['profile_pic']) ? $profile['profile_pic'] : $defaultAvatar;
    $userName = $user['full_name'];
    $userInitial = strtoupper(substr(trim($userName), 0, 1));

    $savedCount = 0;
    try {
        $savedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM saved_trips WHERE user_email = ?");
        $savedCountStmt->execute([$userEmail]);
        $savedCount = (int)$savedCountStmt->fetchColumn();
    } catch (PDOException $ignored) { $savedCount = 0; }

    $experienceCount = 0;
    try {
        $experienceCountStmt = $pdo->prepare("SELECT COUNT(*) FROM community_experiences WHERE user_email = ?");
        $experienceCountStmt->execute([$userEmail]);
        $experienceCount = (int)$experienceCountStmt->fetchColumn();
    } catch (PDOException $ignored) { $experienceCount = 0; }

    $planCount = 0;
    try {
        $planCountStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chats WHERE user_email = ?");
        $planCountStmt->execute([$userEmail]);
        $planCount = (int)$planCountStmt->fetchColumn();
    } catch (PDOException $ignored) {
        $planCount = 0;
    }

    $recentSaves = [];
    try {
        $recentStmt = $pdo->prepare("SELECT d.id, d.title, d.image_url, d.season, d.terrain FROM saved_trips s INNER JOIN destinations d ON d.id = s.destination_id WHERE s.user_email = ? ORDER BY s.created_at DESC LIMIT 4");
        $recentStmt->execute([$userEmail]);
        $recentSaves = $recentStmt->fetchAll();
    } catch (PDOException $ignored) { $recentSaves = []; }

    $memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'SeasonVerse Member';

} catch (PDOException $e) {
    error_log('SeasonVerse profile error: ' . $e->getMessage());
    http_response_code(500);
    die('Unable to load your profile right now. Please check the database connection and try again.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Your Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FDFBF7; color: #1A1A1A; overflow-x: hidden; scroll-behavior: smooth; }

        /* GLASSMORPHISM NAVBAR */
        .user-navbar { position: sticky; top: 0; z-index: 1000; background: rgba(253, 251, 247, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); display: flex; justify-content: space-between; align-items: center; padding: 18px 50px; border-bottom: 1px solid rgba(0, 0, 0, 0.04); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02); }
        .nav-brand { font-size: 1.3rem; font-weight: 700; font-family: serif; color: #1B2E23; }
        .nav-links { display: flex; gap: 30px; list-style: none; font-size: 0.85rem; font-weight: 500; color: #555; }
        .nav-links a { text-decoration: none; color: inherit; transition: color 0.2s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #1B2E23; font-weight: 600; }
        .nav-right-area { display: flex; align-items: center; gap: 15px; }
        
        .profile-letter { width:34px;height:34px;border-radius:50%;background:#1B2E23;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;cursor:pointer; }
        .profile-avatar-nav { width: 34px; height: 34px; border-radius: 50%; background-size: cover; background-position: center; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); background-color: #1B2E23; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }

        .main-content { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        .page-header h4 { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: #888; font-weight: 700; margin-bottom: 8px; }
        .page-header h1 { font-family: serif; font-size: 3rem; color: #1B2E23; margin-bottom: 40px; }

        .profile-grid { display: grid; grid-template-columns: 340px 1fr; gap: 40px; margin-bottom: 60px; }

        /* PROFILE CARD & UPLOAD BUTTON */
        .profile-card { background: #fff; border: 1px solid #EAE5DD; border-radius: 24px; padding: 30px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .avatar-lg { width: 100px; height: 100px; border-radius: 50%; background-image: url('<?php echo h($profilePic); ?>'); background-size: cover; background-position: center; margin: 0 auto 15px auto; border: 3px solid #FDFBF7; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative; }
        
        .upload-btn-wrapper { position: relative; display: inline-block; margin-bottom: 15px; }
        .btn-upload-file { background: #F4EBE1; color: #1B2E23; padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; }
        .upload-btn-wrapper input[type=file] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; }

        .profile-card h3 { font-family: serif; font-size: 1.4rem; color: #1B2E23; margin-bottom: 4px; }
        .profile-card p { font-size: 0.8rem; color: #666; margin-bottom: 15px; }
        .member-badge { background: #F4EBE1; color: #1B2E23; font-size: 0.65rem; font-weight: 700; padding: 5px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; display: inline-block; }

        .journey-stats { background: #fff; border: 1px solid #EAE5DD; border-radius: 20px; padding: 20px; display: grid; grid-template-columns: repeat(3, 1fr); text-align: center; margin-bottom: 30px; }
        .stat-item h4 { font-family: serif; font-size: 1.2rem; color: #1B2E23; margin-bottom: 2px; }
        .stat-item span { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; font-weight: 700; }

        .recent-saves-box { background: #fff; border: 1px solid #EAE5DD; border-radius: 20px; padding: 25px; }
        .box-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; }
        .recent-save-item { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; cursor: pointer; }
        .recent-save-item:last-child { margin-bottom: 0; }
        .recent-save-img { width: 45px; height: 45px; border-radius: 10px; background-size: cover; background-position: center; }
        .recent-save-info h6 { font-size: 0.85rem; font-weight: 700; color: #1A1A1A; }
        .recent-save-info p { font-size: 0.7rem; color: #888; }

        .panel-card { background: #fff; border: 1px solid #EAE5DD; border-radius: 24px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .panel-title { font-family: serif; font-size: 1.4rem; color: #1B2E23; margin-bottom: 25px; }
        
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #888; font-weight: 700; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 14px 18px; border: 1px solid #EAE5DD; background: #FFF9F2; border-radius: 12px; font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; color: #1A1A1A; }
        
        .btn-save { background: #1B2E23; color: #fff; padding: 12px 28px; border-radius: 30px; font-weight: 700; font-size: 0.75rem; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; transition: transform 0.2s; }
        .btn-save:hover { transform: scale(1.02); }

        .pref-category { margin-bottom: 20px; }
        .pref-category label { font-size: 0.75rem; font-weight: 700; color: #555; display: block; margin-bottom: 10px; }
        .pills-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .pill { padding: 8px 18px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #F4EBE1; color: #555; border: 1px solid transparent; cursor: pointer; transition: all 0.2s; }
        .pill.active { background: #1B2E23; color: #fff; }

        .fav-dest-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .fav-card { height: 140px; border-radius: 16px; background-size: cover; background-position: center; padding: 15px; display: flex; flex-direction: column; justify-content: flex-end; color: #fff; position: relative; overflow: hidden; cursor: pointer; }
        .fav-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); }
        .fav-card h6 { position: relative; z-index: 2; font-family: serif; font-size: 1rem; }

        .settings-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .setting-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; font-size: 0.85rem; color: #333; }
        .setting-item span { font-size: 0.7rem; color: #888; display: block; }
        
        .switch { position: relative; display: inline-block; width: 40px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background-color: #ccc; transition: .3s; border-radius: 22px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #1B2E23; }
        input:checked + .slider:before { transform: translateX(18px); }

        .settings-link-list { list-style: none; }
        .settings-link-list li { margin-bottom: 15px; }
        .settings-link-list li a { text-decoration: none; color: #1A1A1A; font-size: 0.85rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; transition: color 0.2s; cursor: pointer; }
        .settings-link-list li a:hover { color: #B45309; }
        .settings-link-list li.danger a { color: #C53030; }

        .toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: #1B2E23; color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; box-shadow: 0 10px 25px rgba(27, 46, 35, 0.3); transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; align-items: center; gap: 10px; }
        .toast.show { transform: translateX(0); }

        /* FOOTER */
        .dark-footer { background: #1B2E23; color: #fff; padding: 60px 50px 30px 50px; display: grid; grid-template-columns: 2fr 1fr 1fr 2fr; gap: 40px; }
        .footer-brand { font-family: serif; font-size: 1.5rem; margin-bottom: 15px; font-weight: 700; }
        .dark-footer p { font-size: 0.8rem; color: #A3B899; line-height: 1.6; }
        .footer-col h6 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #A3B899; margin-bottom: 15px; font-weight: 700; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 10px; }
        .footer-col ul li a { color: #fff; text-decoration: none; font-size: 0.8rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: #FDE8C4; }
        .newsletter-box { display: flex; background: rgba(255,255,255,0.1); border-radius: 30px; padding: 5px; }
        .newsletter-box input { flex: 1; background: transparent; border: none; padding: 10px 15px; color: #fff; outline: none; font-size: 0.8rem; font-family: 'Plus Jakarta Sans', sans-serif; }
        .newsletter-box input::placeholder { color: #A3B899; }
        .newsletter-box button { background: #FDE8C4; color: #1B2E23; border: none; padding: 8px 18px; border-radius: 25px; font-weight: 700; font-size: 0.7rem; cursor: pointer; }
        @media (max-width: 900px) { .user-navbar {padding:16px 20px;flex-wrap:wrap;gap:15px;} .nav-links {gap:14px;flex-wrap:wrap;} .profile-grid {grid-template-columns:1fr;gap:20px;} .fav-dest-grid {grid-template-columns:repeat(2,1fr);} .dark-footer {grid-template-columns:1fr 1fr;padding:40px 25px;} }
        @media (max-width: 600px) { .user-navbar {padding:14px;} .nav-links {font-size:0.75rem;gap:10px;} .nav-right-area {gap:8px;} .main-content {padding:28px 14px;} .page-header h1 {font-size:2.2rem;} .panel-card {padding:22px;} .form-grid-2 {grid-template-columns:1fr;gap:12px;} .journey-stats {padding:14px 8px;} .stat-item span {font-size:0.55rem;} .dark-footer {grid-template-columns:1fr;} }
    </style>
</head>
<body>

<nav class="user-navbar">
        <div class="nav-brand">SeasonVerse</div>
        <ul class="nav-links">
            <li><a href="user-home.php">Home</a></li>
            <li><a href="user-ai.php">AI Planner</a></li>
            <li><a href="user-destinations.php">Destinations</a></li>
            <li><a href="user-experiences.php">Experiences</a></li>
            <li><a href="user-wardrobe.php">Smart Wardrobe</a></li>
            <li><a href="user-travel.php">Travel & Packing</a></li>
        </ul>
        <div class="nav-right-area">
    <span style="font-size: 0.85rem; font-weight: 600;"><?php echo h($userName); ?></span>
    <div class="profile-letter" onclick="window.location.href='user-profile.php'" title="View Profile"><?php echo h($userInitial); ?></div>
    
    <!-- CHANGE login.php TO MATCH YOUR ACTUAL LOGIN FILE (e.g. index.php) -->
    <a href="/SeasonVerse2/logout.php" style="font-size: 0.75rem; color: #C53030; text-decoration: none; font-weight: 700; margin-left: 10px;">LOGOUT</a>
</div>
    </nav>


    <div class="main-content">
        <div class="page-header">
            <h4>Your SeasonVerse</h4>
            <h1>Your Profile</h1>
        </div>

        <div class="profile-grid">
            
            <div>
                <div class="profile-card">
                    <div class="avatar-lg"></div>
                    
                    <!-- UPLOAD FORM FOR PROFILE PICTURE -->
                    <form action="user-profile.php" method="POST" enctype="multipart/form-data">
                        <div class="upload-btn-wrapper">
                            <button type="button" class="btn-upload-file"><i class="fa-solid fa-camera"></i> Change Photo</button>
                            <input type="file" name="profile_pic" accept="image/*" onchange="this.form.submit()">
                        </div>
                    </form>

                    <h3><?php echo h($user['full_name']); ?></h3>
                    <p><?php echo h($user['email']); ?></p>
                    <span class="member-badge">Member Since <?php echo h($memberSince); ?></span>
                </div>

                <div class="journey-stats">
                    <div class="stat-item">
                        <h4><?php echo $savedCount; ?></h4>
                        <span>Saved Places</span>
                    </div>
                    <div class="stat-item">
                        <h4><?php echo $experienceCount; ?></h4>
                        <span>Experiences</span>
                    </div>
                    <div class="stat-item">
                        <h4><?php echo $planCount; ?></h4>
                        <span>Plans</span>
                    </div>
                </div>

                <div class="recent-saves-box">
                    <div class="box-header-row">
                        <span>Recent Saves</span>
                        <a href="user-destinations.php" style="color: #B45309; text-decoration: none;">View All</a>
                    </div>
                    <?php if (!empty($recentSaves)): ?>
                        <?php foreach ($recentSaves as $save): ?>
                            <a class="recent-save-item" href="user-destinations.php" style="text-decoration:none;color:inherit;">
                                <div class="recent-save-img" style="background-image:url('<?php echo h($save['image_url'] ?: $defaultAvatar); ?>');"></div>
                                <div class="recent-save-info">
                                    <h6><?php echo h($save['title']); ?></h6>
                                    <p><?php echo h($save['season']); ?> · <?php echo h($save['terrain']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size:0.8rem;color:#888;">You have not saved any destinations yet. Explore destinations to build your travel list.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="panel-card">
                    <h3 class="panel-title">Personal Information</h3>
                    <form id="profileForm" onsubmit="saveProfile(event)">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" id="fullName" maxlength="100" value="<?php echo h($user['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" value="<?php echo h($user['email']); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" id="phone" maxlength="30" value="<?php echo h($user['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Current Location</label>
                                <input type="text" id="location" value="<?php echo h($profile['current_location']); ?>">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Date of Birth</label>
                            <input type="date" id="birthDate" value="<?php echo h($profile['birth_date']); ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>

                <div class="panel-card">
                    <h3 class="panel-title">Travel Preferences</h3>
                    <div class="pref-category">
                        <label>Preferred Season</label>
                        <div class="pills-row" data-field="preferred_season">
                            <?php foreach (['Summer', 'Monsoon', 'Winter'] as $seasonOption): ?>
                                <button type="button" class="pill <?php echo ($user['preferred_season'] === $seasonOption) ? 'active' : ''; ?>" data-value="<?php echo h($seasonOption); ?>" onclick="selectPill(this)"><?php echo h($seasonOption); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="pref-category">
                        <label>Travel Style</label>
                        <div class="pills-row" data-field="travel_style">
                            <?php foreach (['Solo', 'Couple', 'Family', 'Friends'] as $styleOption): ?>
                                <button type="button" class="pill <?php echo ($user['travel_style'] === $styleOption) ? 'active' : ''; ?>" data-value="<?php echo h($styleOption); ?>" onclick="selectPill(this)"><?php echo h($styleOption); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="panel-card">
                    <h3 class="panel-title">Favorite Destinations</h3>
                    <div class="fav-dest-grid">
                        <?php if (!empty($recentSaves)): ?>
                            <?php foreach (array_slice($recentSaves, 0, 4) as $save): ?>
                                <a class="fav-card" href="user-destinations.php" style="background-image:url('<?php echo h($save['image_url'] ?: $defaultAvatar); ?>');text-decoration:none;">
                                    <h6><?php echo h($save['title']); ?></h6>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size:0.8rem;color:#888;grid-column:1/-1;">Your saved destinations will appear here.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <footer class="dark-footer">
        <div>
            <div class="footer-brand">SeasonVerse</div>
            <p>Curating temporal luxury through seasonal discovery and conscious exploration.</p>
        </div>
        <div class="footer-col">
            <h6>Company</h6>
            <ul>
                <li><a href="#">Seasonal Manifesto</a></li>
                <li><a href="#">Press Enquiries</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h6>Legal</h6>
            <ul>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h6>Newsletter</h6>
            <div class="newsletter-box">
                <input type="email" placeholder="Email address">
                <button>JOIN</button>
            </div>
        </div>
    </footer>

    <div id="profileMessage" role="status" aria-live="polite" style="display:none;max-width:1200px;margin:0 auto 20px;padding:12px 18px;border-radius:10px;font-size:0.85rem;"></div>

    <script>
        const profileMessage = document.getElementById('profileMessage');
        let selectedSeason = <?php echo json_encode($user['preferred_season'] ?: ''); ?>;
        let selectedStyle = <?php echo json_encode($user['travel_style'] ?: ''); ?>;

        function showMessage(message, success = true) {
            profileMessage.textContent = message;
            profileMessage.style.display = 'block';
            profileMessage.style.background = success ? '#F0FDF4' : '#FDF2F2';
            profileMessage.style.color = success ? '#166534' : '#C53030';
            profileMessage.style.border = success ? '1px solid #DCFCE7' : '1px solid #FED7D7';
            profileMessage.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }

        function selectPill(element) {
            const parent = element.parentElement;
            parent.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
            element.classList.add('active');
            if (parent.dataset.field === 'preferred_season') selectedSeason = element.dataset.value;
            if (parent.dataset.field === 'travel_style') selectedStyle = element.dataset.value;
        }

        async function saveProfile(event) {
            event.preventDefault();
            const formData = new URLSearchParams({
                action: 'save_profile',
                full_name: document.getElementById('fullName').value.trim(),
                phone_number: document.getElementById('phone').value.trim(),
                current_location: document.getElementById('location').value.trim(),
                birth_date: document.getElementById('birthDate').value,
                preferred_season: selectedSeason,
                travel_style: selectedStyle
            });

            const saveButton = document.querySelector('#profileForm .btn-save');
            saveButton.disabled = true;
            saveButton.textContent = 'SAVING...';

            try {
                const response = await fetch('user-profile.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: formData.toString()
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Unable to save your profile.');
                }
                showMessage(result.message || 'Profile saved successfully.');
                const name = document.getElementById('fullName').value.trim();
                document.querySelector('.profile-card h3').textContent = name;
                document.querySelector('.nav-right-area span').textContent = name;
                document.querySelector('.profile-letter').textContent = name.charAt(0).toUpperCase();
            } catch (error) {
                showMessage(error.message || 'A connection error occurred. Please try again.', false);
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = 'SAVE CHANGES';
            }
        }

        const uploadInput = document.querySelector('input[name="profile_pic"]');
        if (uploadInput) {
            uploadInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    if (file.size > 3 * 1024 * 1024) {
                        showMessage('Please choose an image smaller than 3 MB.', false);
                        this.value = '';
                        return;
                    }
                    this.form.submit();
                }
            });
        }

        const status = new URLSearchParams(window.location.search).get('status');
        const messages = {
            photo_saved: ['Profile photo updated successfully.', true],
            too_large: ['Image must be 3 MB or smaller.', false],
            invalid_image: ['Choose a valid JPG, PNG or WEBP image.', false],
            upload_error: ['Photo upload failed. Please try again.', false]
        };
        if (status && messages[status]) showMessage(messages[status][0], messages[status][1]);
    </script>
</body>
</html>