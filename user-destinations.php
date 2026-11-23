<?php
session_start();

$userEmail = $_SESSION['user_email'] ?? ('guest-' . session_id() . '@seasonverse.local');
$userName = $_SESSION['user_name'] ?? 'Traveler';
$userInitial = strtoupper(substr(trim($userName), 0, 1));

$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

$savedTrips = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS destinations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        season VARCHAR(50) NOT NULL,
        terrain VARCHAR(50) NOT NULL,
        tag VARCHAR(50),
        grid_class VARCHAR(50),
        image_url VARCHAR(255),
        description TEXT,
        trip_type VARCHAR(50) NOT NULL DEFAULT 'Leisure',
        budget_level VARCHAR(30) NOT NULL DEFAULT 'Mid-range'
    )");

    // Upgrade existing installations without changing the page design.
    $destinationColumns = $pdo->query("SHOW COLUMNS FROM destinations")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('trip_type', $destinationColumns, true)) {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN trip_type VARCHAR(50) NOT NULL DEFAULT 'Leisure'");
    }
    if (!in_array('budget_level', $destinationColumns, true)) {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN budget_level VARCHAR(30) NOT NULL DEFAULT 'Mid-range'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_trips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(150) NOT NULL,
        destination_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_save (user_email, destination_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $destCount = $pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn();
    if ($destCount == 0) {
        $pdo->exec("INSERT INTO destinations (title, season, terrain, tag, grid_class, image_url, description) VALUES 
        ('Kyoto', 'Spring', 'Heritage', 'ACTIVE', 'tall', 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=800&q=80', 'Delve into Japan\'s cultural heartland. Experience the profound tranquility of ancient Zen gardens and traditional tea ceremonies.'),
        ('Santorini', 'Summer', 'Coastal', '', '', 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=600&q=80', 'Iconic whitewashed buildings, blue domes, and breathtaking Mediterranean sunsets.'),
        ('Coorg', 'Monsoon', 'Mountains', '', '', 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80', 'Lush coffee plantations, misty hills, and beautiful rain-washed nature trails.'),
        ('Maldives', 'Summer', 'Coastal', '', '', 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80', 'Overwater luxury villas, crystal clear waters, and serene beach relaxation.'),
        ('Swiss Chalets', 'Winter', 'Mountains', 'FEATURED', 'wide', 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=800&q=80', 'Cozy alpine retreats, world-class skiing, and panoramic snowy mountain views.')");
    }

    $extraDestinations = [
        ['Bali', 'Summer', 'Coastal', 'POPULAR', '', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=900&q=85', 'Tropical temples, emerald rice terraces, beach clubs and restorative island retreats.', 'Relaxation', 'Luxury'],
        ['Paris', 'Spring', 'Urban', '', '', 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=900&q=85', 'Classic boulevards, celebrated museums, intimate cafés and memorable city experiences.', 'Culture', 'Luxury'],
        ['Tokyo', 'Autumn', 'Urban', '', '', 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=900&q=85', 'A dynamic blend of contemporary design, food culture, gardens and historic neighbourhoods.', 'City Break', 'Mid-range'],
        ['Iceland', 'Winter', 'Mountains', 'ADVENTURE', '', 'https://images.unsplash.com/photo-1476610182048-b716b8518aae?auto=format&fit=crop&w=900&q=85', 'Volcanic landscapes, waterfalls, geothermal bathing and opportunities to see the northern lights.', 'Adventure', 'Luxury'],
        ['Banff', 'Winter', 'Mountains', '', '', 'https://images.unsplash.com/photo-1503614472-8c93d56cd2b3?auto=format&fit=crop&w=900&q=85', 'Turquoise lakes, dramatic Canadian Rockies, alpine trails and winter mountain experiences.', 'Adventure', 'Mid-range'],
        ['Dubai', 'Winter', 'Urban', '', '', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=900&q=85', 'Modern architecture, desert experiences, luxury stays and lively dining destinations.', 'City Break', 'Luxury'],
        ['Cappadocia', 'Autumn', 'Heritage', '', '', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?auto=format&fit=crop&w=900&q=85', 'Distinctive rock formations, cave hotels, heritage villages and sunrise balloon flights.', 'Adventure', 'Mid-range'],
        ['Amalfi Coast', 'Summer', 'Coastal', '', '', 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?auto=format&fit=crop&w=900&q=85', 'Cliffside villages, Mediterranean views, coastal walks and relaxed seaside dining.', 'Relaxation', 'Luxury'],
        ['Kerala', 'Monsoon', 'Nature', '', '', 'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?auto=format&fit=crop&w=900&q=85', 'Backwaters, tea-covered hills, tropical forests and restorative wellness experiences.', 'Nature', 'Mid-range'],
        ['Rajasthan', 'Winter', 'Heritage', '', '', 'https://images.unsplash.com/photo-1599661046827-dacff0c0f09a?auto=format&fit=crop&w=900&q=85', 'Historic forts, palace hotels, desert landscapes and rich regional culture.', 'Culture', 'Mid-range'],
        ['Dolomites', 'Winter', 'Mountains', 'FEATURED', '', 'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=900&q=85', 'Dramatic alpine peaks, scenic winter trails, mountain lodges and ski villages.', 'Adventure', 'Luxury'],
        ['New York', 'Autumn', 'Urban', '', '', 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?auto=format&fit=crop&w=900&q=85', 'Iconic skyline views, neighbourhood discoveries, galleries, shopping and world-class dining.', 'City Break', 'Luxury']
    ];

    $findDestination = $pdo->prepare("SELECT id FROM destinations WHERE title = ? LIMIT 1");
    $insertDestination = $pdo->prepare("INSERT INTO destinations
        (title, season, terrain, tag, grid_class, image_url, description, trip_type, budget_level)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($extraDestinations as $destination) {
        $findDestination->execute([$destination[0]]);
        if (!$findDestination->fetchColumn()) {
            $insertDestination->execute($destination);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subscribe') {
        header('Content-Type: application/json; charset=utf-8');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
            exit;
        }
        try {
            $subscribe = $pdo->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
            $subscribe->execute([$email]);
            echo json_encode(['status' => 'success', 'message' => 'You are subscribed to SeasonVerse updates.']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                echo json_encode(['status' => 'exists', 'message' => 'This email is already subscribed.']);
            } else {
                throw $e;
            }
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_save') {
        header('Content-Type: application/json; charset=utf-8');
        $destId = filter_input(INPUT_POST, 'destination_id', FILTER_VALIDATE_INT);
        if (!$destId || $destId < 1) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Invalid destination.']);
            exit;
        }

        $exists = $pdo->prepare('SELECT id FROM destinations WHERE id = ? LIMIT 1');
        $exists->execute([$destId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Destination not found.']);
            exit;
        }

        $delete = $pdo->prepare('DELETE FROM saved_trips WHERE user_email = ? AND destination_id = ?');
        $delete->execute([$userEmail, $destId]);
        if ($delete->rowCount() > 0) {
            echo json_encode(['status' => 'removed']);
        } else {
            try {
                $insert = $pdo->prepare('INSERT INTO saved_trips (user_email, destination_id) VALUES (?, ?)');
                $insert->execute([$userEmail, $destId]);
                echo json_encode(['status' => 'added']);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    echo json_encode(['status' => 'added']);
                } else {
                    throw $e;
                }
            }
        }
        exit;
    }

    $destinations = $pdo->query("SELECT * FROM destinations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // FETCHING FULL DETAILS SO SAVED SIDEBAR CAN OPEN MODAL
    $stmt = $pdo->prepare("
        SELECT d.id, d.title, d.season, d.terrain, d.description, d.image_url 
        FROM saved_trips st 
        JOIN destinations d ON st.destination_id = d.id 
        WHERE st.user_email = ? 
        ORDER BY st.created_at DESC LIMIT 3
    ");
    $stmt->execute([$userEmail]);
    $savedTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $savedIdsStmt = $pdo->prepare("SELECT destination_id FROM saved_trips WHERE user_email = ?");
    $savedIdsStmt->execute([$userEmail]);
    $savedIds = $savedIdsStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Destinations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FDFBF7; color: #1A1A1A; overflow-x: hidden; scroll-behavior: smooth; }
        .user-navbar { position: sticky; top: 0; z-index: 1000; background: rgba(253, 251, 247, 0.6); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); display: flex; justify-content: space-between; align-items: center; padding: 18px 50px; border-bottom: 1px solid rgba(0, 0, 0, 0.04); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02); }
        .nav-brand { font-size: 1.3rem; font-weight: 700; font-family: serif; color: #1B2E23; letter-spacing: -0.5px; }
        .nav-links { display: flex; gap: 30px; list-style: none; font-size: 0.85rem; font-weight: 500; color: #555; }
        .nav-links a { text-decoration: none; color: inherit; transition: color 0.2s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #1B2E23; font-weight: 600; }
        .nav-right-area { display: flex; align-items: center; gap: 15px; }
        .profile-letter { width: 32px; height: 32px; border-radius: 50%; background: #1B2E23; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; cursor: pointer; }
        .main-content { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .page-header { text-align: center; margin-bottom: 40px; }
        .page-header h4 { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: #888; font-weight: 700; margin-bottom: 15px; }
        .page-header h1 { font-family: serif; font-size: 3.2rem; color: #1B2E23; line-height: 1.1; margin-bottom: 15px; letter-spacing: -1px; }
        .page-header p { font-size: 0.9rem; color: #666; margin-bottom: 30px; }
        .search-bar-container { max-width: 700px; margin: 0 auto; position: relative; margin-bottom: 25px; }
        .search-bar-container i { position: absolute; left: 25px; top: 50%; transform: translateY(-50%); color: #B45309; font-size: 1.1rem; }
        .search-bar-container input { width: 100%; padding: 20px 20px 20px 60px; border-radius: 40px; border: 1px solid #F4EBE1; background: #FFF9F2; font-size: 1rem; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; box-shadow: 0 10px 30px rgba(180,83,9,0.05); transition: border 0.3s; }
        .search-bar-container input:focus { border-color: #C5A880; }
        .filter-options { display: flex; justify-content: center; gap: 30px; font-size: 0.75rem; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-options span { cursor: pointer; display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .filter-options span:hover, .filter-options span.active-filter { color: #1B2E23; }
        .hero-banner { width: 100%; height: 400px; border-radius: 24px; background-image: linear-gradient(to right, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.2) 60%, transparent 100%), url('https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=1600&q=80'); background-size: cover; background-position: center; margin-bottom: 60px; display: flex; flex-direction: column; justify-content: center; padding: 60px; color: #fff; box-shadow: 0 20px 40px rgba(0,0,0,0.1); cursor: pointer; }
        .hero-banner .tag { background: #FDE8C4; color: #B45309; font-size: 0.65rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; display: inline-block; margin-bottom: 15px; letter-spacing: 1px; text-transform: uppercase; width: max-content; }
        .hero-banner h2 { font-family: serif; font-size: 2.8rem; line-height: 1.1; margin-bottom: 15px; max-width: 400px; }
        .hero-banner p { font-size: 0.85rem; max-width: 400px; line-height: 1.6; margin-bottom: 25px; color: #EAE5DD; }
        .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; }
        .section-header h3 { font-family: serif; font-size: 1.6rem; color: #1B2E23; font-weight: 700; }
        .trending-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 180px; gap: 20px; margin-bottom: 60px; }
        .trend-card { border-radius: 20px; background-size: cover; background-position: center; position: relative; overflow: hidden; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; color: #fff; cursor: pointer; transition: transform 0.3s, opacity 0.3s; }
        .trend-card:hover { transform: scale(1.02); z-index: 2; box-shadow: 0 15px 30px rgba(0,0,0,0.2); }
        .trend-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%); pointer-events: none; }
        .trend-card.tall { grid-row: span 2; }
        .trend-card.wide { grid-column: span 2; }
        .trend-card.hidden { display: none; } 
        .trend-card-top { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-start; }
        .trend-card-tag { background: rgba(255,255,255,0.25); backdrop-filter: blur(4px); padding: 4px 10px; border-radius: 12px; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .trend-card-heart { width: 32px; height: 32px; padding: 0; border: 0; border-radius: 50%; background: rgba(255,255,255,0.25); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: inherit; cursor: pointer; transition: all 0.2s; }
        .trend-card-heart:hover { background: rgba(255,255,255,0.9); color: #C53030; transform: scale(1.1); }
        .trend-card-heart.saved { background: #C53030; color: #fff; border: none; }
        .trend-card-bottom { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
        .trend-card-bottom h4 { font-family: serif; font-size: 1.2rem; margin-bottom: 2px; }
        .trend-card-bottom p { font-size: 0.7rem; color: #eee; letter-spacing: 0.5px; }
        .mood-section { text-align: center; margin-bottom: 60px; }
        .mood-section h3 { font-family: serif; font-size: 1.6rem; color: #1B2E23; font-weight: 700; margin-bottom: 30px; }
        .mood-grid { display: flex; justify-content: space-between; max-width: 900px; margin: 0 auto; }
        .mood-item { display: flex; flex-direction: column; align-items: center; gap: 12px; cursor: pointer; transition: transform 0.2s; }
        .mood-item:hover { transform: translateY(-5px); }
        .mood-icon { width: 65px; height: 65px; border-radius: 50%; border: 1px solid #EAE5DD; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #1B2E23; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: all 0.2s; }
        .mood-item:hover .mood-icon { background: #1B2E23; color: #fff; border-color: #1B2E23; }
        .mood-item span { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #555; }
        .map-saved-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-bottom: 60px; }
        .map-container { background: #FAEDDF; padding: 30px; border-radius: 24px; display: flex; align-items: center; justify-content: center; position: relative; }
        .map-container img { width: 100%; opacity: 0.7; mix-blend-mode: multiply; }
        .saved-box { display: flex; flex-direction: column; gap: 15px; }
        .saved-item { display: flex; align-items: center; gap: 15px; cursor: pointer; padding: 10px; border-radius: 12px; transition: background 0.2s; }
        .saved-item:hover { background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .saved-img { width: 55px; height: 55px; border-radius: 12px; background-size: cover; background-position: center; }
        .saved-info h6 { font-size: 0.9rem; font-weight: 700; color: #1A1A1A; margin-bottom: 2px; }
        .saved-info p { font-size: 0.75rem; color: #888; }
        .empty-saved { text-align: center; padding: 20px; font-size: 0.85rem; color: #888; background: #fff; border-radius: 12px; border: 1px dashed #ddd; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-box { background: #FDFBF7; width: 100%; max-width: 550px; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.3); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-img-header { width: 100%; height: 250px; background-size: cover; background-position: center; position: relative; }
        .modal-close { position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.5); border: none; width: 36px; height: 36px; border-radius: 50%; color: #fff; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); transition: background 0.2s; }
        .modal-close:hover { background: #C53030; }
        .modal-body { padding: 35px; }
        .modal-meta { display: flex; gap: 15px; font-size: 0.7rem; font-weight: 700; color: #B45309; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .modal-title { font-family: serif; font-size: 2.2rem; color: #1B2E23; margin-bottom: 15px; line-height: 1.1; }
        .modal-desc { font-size: 0.9rem; color: #555; line-height: 1.6; margin-bottom: 30px; }
        .modal-actions { display: flex; gap: 15px; }
        .btn-primary { background: #1B2E23; color: #fff; padding: 14px 24px; border-radius: 14px; font-weight: 600; font-size: 0.85rem; flex: 1; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; }
        .btn-primary:hover { background: #0F1C15; }
        .btn-secondary { background: transparent; color: #1B2E23; padding: 14px 24px; border-radius: 14px; font-weight: 600; font-size: 0.85rem; border: 1px solid #1B2E23; cursor: pointer; transition: background 0.2s; }
        .btn-secondary:hover { background: #F4EBE1; }
        .filter-options { position: relative; }
        .filter-options span { position: relative; }
        .filter-options span.filter-active { color: #B45309; }
        .filter-popover { position: absolute; top: calc(100% + 14px); left: 50%; transform: translateX(-50%); z-index: 1500; width: 210px; padding: 8px; background: #fff; border: 1px solid #F0E5D8; border-radius: 14px; box-shadow: 0 16px 38px rgba(26,26,26,.14); text-align: left; text-transform: none; letter-spacing: 0; }
        .filter-popover[hidden] { display: none; }
        .filter-option { display: flex; width: 100%; align-items: center; justify-content: space-between; padding: 10px 12px; border: 0; border-radius: 9px; background: transparent; color: #444; font: 500 .78rem 'Plus Jakarta Sans',sans-serif; cursor: pointer; text-align: left; }
        .filter-option:hover, .filter-option[aria-checked='true'] { background: #FDF5EA; color: #1B2E23; }
        .filter-option[aria-checked='true']::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: #B45309; }
        .newsletter-status { min-height: 16px; margin: 8px 12px 0; font-size: .72rem; color: #FDE8C4; }
        .newsletter-status.error { color: #FFC3B8; }
        .newsletter-status.success { color: #C7E7C1; }
        .trend-card-heart[aria-busy='true'] { opacity: .55; pointer-events: none; }
        .trend-card-heart[aria-invalid='true'], #modalSaveButton[aria-invalid='true'] { outline: 2px solid #C53030; outline-offset: 3px; }
        .trend-card-heart:focus-visible, .mood-item:focus-visible, .filter-options span:focus-visible { outline: 2px solid #B45309; outline-offset: 4px; }
        .filter-summary { margin: 12px auto 0; min-height: 18px; color: #888; font-size: .72rem; text-align: center; }
        @media (max-width: 900px) { .user-navbar { padding: 16px 22px; } .nav-links { gap: 14px; } .trending-grid { grid-template-columns: repeat(2,1fr); } .map-saved-grid { grid-template-columns: 1fr; gap: 22px; } .dark-footer { grid-template-columns: repeat(2,1fr); padding: 45px 25px 25px; } }
        @media (max-width: 600px) { .user-navbar { flex-wrap: wrap; gap: 14px; padding: 14px 16px; } .nav-links { order: 3; width: 100%; overflow-x: auto; gap: 18px; padding-bottom: 4px; } .nav-links li { white-space: nowrap; } .main-content { padding: 28px 14px; } .page-header h1 { font-size: 2.35rem; } .filter-options { flex-wrap: wrap; gap: 16px; } .hero-banner { height: 330px; padding: 28px; } .hero-banner h2 { font-size: 2.15rem; } .trending-grid { grid-template-columns: 1fr 1fr; grid-auto-rows: 155px; gap: 12px; } .trend-card { padding: 13px; } .trend-card-bottom h4 { font-size: 1rem; } .mood-grid { flex-wrap: wrap; justify-content: center; gap: 22px; } .mood-item { min-width: 76px; } .modal-body { padding: 24px; } .modal-title { font-size: 1.8rem; } .dark-footer { grid-template-columns: 1fr; gap: 28px; padding: 40px 20px 25px; } }
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
    </style>
</head>
<body>

<nav class="user-navbar">
        <div class="nav-brand">SeasonVerse</div>
        <ul class="nav-links">
            <li><a href="user-home.php">Home</a></li>
            <li><a href="user-ai.php">AI Planner</a></li>
            <li><a href="user-destinations.php"class="active">Destinations</a></li>
            <li><a href="user-experiences.php">Experiences</a></li>
            <li><a href="user-wardrobe.php">Smart Wardrobe</a></li>
            <li><a href="user-travel.php">Travel & Packing</a></li>
        </ul>
        <div class="nav-right-area">
    <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($userName); ?></span>
    <div class="profile-letter" onclick="window.location.href='user-profile.php'" title="View Profile"><?php echo htmlspecialchars($userInitial); ?></div>
    
    <!-- CHANGE login.php TO MATCH YOUR ACTUAL LOGIN FILE (e.g. index.php) -->
    <a href="/SeasonVerse2/logout.php" style="font-size: 0.75rem; color: #C53030; text-decoration: none; font-weight: 700; margin-left: 10px;">LOGOUT</a>
</div>
    </nav>


    <div class="main-content">
        <div class="page-header">
            <h4>Curated Destinations</h4>
            <h1>Find Somewhere Worth Remembering.</h1>
            <p>Explore handpicked destinations curated for the current season based on your personal travel style.</p>
            <div class="search-bar-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search destinations, regions, or experiences..." onkeyup="filterDestinations()">
            </div>
            <div class="filter-options" aria-label="Destination filters">
                <span role="button" tabindex="0" data-filter="season" onclick="openFilter(event, 'season')" onkeydown="filterKeydown(event, 'season')">Season <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i></span>
                <span role="button" tabindex="0" data-filter="tripType" onclick="openFilter(event, 'tripType')" onkeydown="filterKeydown(event, 'tripType')">Trip Type <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i></span>
                <span role="button" tabindex="0" data-filter="terrain" onclick="openFilter(event, 'terrain')" onkeydown="filterKeydown(event, 'terrain')">Terrain <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i></span>
                <span role="button" tabindex="0" data-filter="budget" onclick="openFilter(event, 'budget')" onkeydown="filterKeydown(event, 'budget')">Budget <i class="fa-solid fa-chevron-down" style="font-size: 0.6rem;"></i></span>
                <div id="filterPopover" class="filter-popover" role="group" aria-label="Filter options" hidden></div>
            </div>
            <div class="filter-summary" id="filterSummary" aria-live="polite"></div>
        </div>

        <div class="hero-banner" onclick="openModal('Swiss Alps', 'Winter', 'Mountains', 'Cozy alpine retreats, world-class skiing, and panoramic snowy mountain views.', 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=1600&q=80', 0)">
            <span class="tag">WINTER ESCAPE</span>
            <h2>SWISS ALPS: Winter's Quiet Escape</h2>
            <p>Experience the serene snow-capped peaks, luxury chalets, and world-class skiing destinations handpicked for your next getaway.</p>
        </div>

        <div class="mood-section">
            <h3>What Are You In The Mood For?</h3>
            <div class="mood-grid">
                <div class="mood-item" onclick="filterByMood('Mountains')">
                    <div class="mood-icon"><i class="fa-solid fa-mountain-sun"></i></div>
                    <span>Mountains</span>
                </div>
                <div class="mood-item" onclick="filterByMood('Coastal')">
                    <div class="mood-icon"><i class="fa-solid fa-water"></i></div>
                    <span>Coastal</span>
                </div>
                <div class="mood-item" onclick="filterByMood('Urban')">
                    <div class="mood-icon"><i class="fa-solid fa-city"></i></div>
                    <span>Urban</span>
                </div>
                <div class="mood-item" onclick="filterByMood('Heritage')">
                    <div class="mood-icon"><i class="fa-solid fa-gopuram"></i></div>
                    <span>Heritage</span>
                </div>
                <div class="mood-item" onclick="filterByMood('')">
                    <div class="mood-icon" style="background: #1B2E23; color: #fff;"><i class="fa-solid fa-rotate-left"></i></div>
                    <span>View All</span>
                </div>
            </div>
        </div>

        <div class="section-header">
            <h3 id="gridTitle">Trending Destinations</h3>
        </div>
        
        <div class="trending-grid" id="destGrid">
            <?php foreach($destinations as $dest): ?>
                <?php 
                    $isSaved = in_array($dest['id'], $savedIds);
                    $heartClass = $isSaved ? 'fa-solid saved' : 'fa-regular';
                ?>
                <div class="trend-card <?php echo htmlspecialchars($dest['grid_class']); ?>" 
                     data-id="<?php echo (int)$dest['id']; ?>"
                     data-title="<?php echo htmlspecialchars(strtolower($dest['title'] . ' ' . $dest['description'] . ' ' . $dest['season'] . ' ' . $dest['terrain']), ENT_QUOTES, 'UTF-8'); ?>"
                     data-terrain="<?php echo htmlspecialchars($dest['terrain'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-season="<?php echo htmlspecialchars($dest['season'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-trip-type="<?php echo htmlspecialchars($dest['trip_type'] ?? 'Leisure', ENT_QUOTES, 'UTF-8'); ?>"
                     data-budget="<?php echo htmlspecialchars($dest['budget_level'] ?? 'Mid-range', ENT_QUOTES, 'UTF-8'); ?>"
                     style="background-image: url('<?php echo htmlspecialchars($dest['image_url'], ENT_QUOTES, 'UTF-8'); ?>');"
                     onclick='openModal(<?php echo json_encode($dest["title"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($dest["season"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($dest["terrain"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($dest["description"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($dest["image_url"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo (int)$dest["id"]; ?>, <?php echo $isSaved ? 'true' : 'false'; ?>)'>
                    
                    <div class="trend-card-top">
                        <?php if(!empty($dest['tag'])): ?>
                            <span class="trend-card-tag"><?php echo htmlspecialchars($dest['tag']); ?></span>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        
                        <button type="button" class="trend-card-heart <?php echo $isSaved ? 'saved' : ''; ?>" aria-label="<?php echo $isSaved ? 'Remove from saved destinations' : 'Save destination'; ?>" aria-pressed="<?php echo $isSaved ? 'true' : 'false'; ?>" onclick="toggleSave(event, this, <?php echo $dest['id']; ?>)">
                            <i class="<?php echo $heartClass; ?> fa-heart"></i>
                        </button>
                    </div>
                    
                    <div class="trend-card-bottom">
                        <div>
                            <h4><?php echo htmlspecialchars($dest['title']); ?></h4>
                            <p><?php echo htmlspecialchars($dest['season']); ?> • <?php echo htmlspecialchars($dest['terrain']); ?></p>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square" style="color: #fff; font-size: 0.9rem; opacity: 0.7;"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="section-header">
            <h3>Explore Around the World</h3>
            <div style="width: 32%;">
                <h3 style="font-size: 1.2rem;">Your Saved Places</h3>
            </div>
        </div>
        <div class="map-saved-grid">
            <div class="map-container">
                <img src="https://upload.wikimedia.org/wikipedia/commons/8/80/World_map_-_low_resolution.svg" alt="World Map">
            </div>
            
            <div class="saved-box" id="savedTripsSidebar">
                <?php if(empty($savedTrips)): ?>
                    <div class="empty-saved">You haven't saved any destinations yet. Click the heart icon on a destination to save it!</div>
                <?php else: ?>
                    <?php foreach($savedTrips as $trip): ?>
                        <!-- NOW CLICKING A SAVED TRIP OPENS ITS FULL MODAL DETAILS -->
                        <div class="saved-item" onclick='openModal(<?php echo json_encode($trip["title"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($trip["season"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($trip["terrain"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($trip["description"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo json_encode($trip["image_url"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, <?php echo (int)$trip["id"]; ?>, true)'>
                            <div class="saved-img" style="background-image: url('<?php echo htmlspecialchars($trip['image_url']); ?>');"></div>
                            <div class="saved-info">
                                <h6><?php echo htmlspecialchars($trip['title']); ?></h6>
                                <p><?php echo htmlspecialchars($trip['season']); ?> • <?php echo htmlspecialchars($trip['terrain']); ?></p>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="margin-left: auto; color: #bbb; padding: 5px;"></i>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                <input type="email" id="newsletterEmail" placeholder="Email address" aria-label="Email address" autocomplete="email" required>
                <button type="button" id="newsletterButton" onclick="subscribeNewsletter()">JOIN</button>
            </div>
            <p id="newsletterStatus" class="newsletter-status" role="status" aria-live="polite"></p>
        </div>
    </footer>


    <!-- DYNAMIC DESTINATION MODAL -->
    <div id="destModal" class="modal-overlay" onclick="closeModal(event)">
        <div class="modal-box">
            <div class="modal-img-header" id="modalImg">
                <button class="modal-close" onclick="closeModalBtn()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-meta">
                    <span id="modalSeason"><i class="fa-regular fa-calendar"></i> Season</span>
                    <span id="modalTerrain"><i class="fa-solid fa-mountain-sun"></i> Terrain</span>
                </div>
                <h2 class="modal-title" id="modalTitle">Destination Title</h2>
                <p class="modal-desc" id="modalDesc">Detailed description of the location.</p>
                <div class="modal-actions">
                    <!-- SENDS THE DESTINATION TEXT DIRECTLY TO AI PLANNER -->
                    <button class="btn-primary" onclick="sendToAI()"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Plan</button>
                    <button class="btn-secondary" id="modalSaveButton" onclick="saveCurrentDestination()"><i class="fa-regular fa-bookmark"></i> Bookmark</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const destinationFilters = { season: '', tripType: '', terrain: '', budget: '' };
        let currentDestinationId = 0;
        let currentDestinationSaved = false;
        let activeFilter = null;

        const filterOptions = {
            season: ['All', 'Spring', 'Summer', 'Monsoon', 'Autumn', 'Winter'],
            tripType: ['All', 'Adventure', 'Relaxation', 'Culture', 'City Break', 'Nature', 'Leisure'],
            terrain: ['All', 'Mountains', 'Coastal', 'Urban', 'Heritage', 'Nature'],
            budget: ['All', 'Budget', 'Mid-range', 'Luxury']
        };
        const filterLabels = { season: 'Season', tripType: 'Trip Type', terrain: 'Terrain', budget: 'Budget' };

        function applyDestinationFilters() {
            const input = document.getElementById('searchInput').value.trim().toLowerCase();
            const cards = [...document.querySelectorAll('.trend-card')];
            let visible = 0;

            cards.forEach(card => {
                const searchText = card.dataset.title || '';
                const matchesSearch = !input || searchText.includes(input);
                const matchesSeason = !destinationFilters.season || card.dataset.season === destinationFilters.season;
                const matchesType = !destinationFilters.tripType || card.dataset.tripType === destinationFilters.tripType;
                const matchesTerrain = !destinationFilters.terrain || card.dataset.terrain === destinationFilters.terrain;
                const matchesBudget = !destinationFilters.budget || card.dataset.budget === destinationFilters.budget;
                const show = matchesSearch && matchesSeason && matchesType && matchesTerrain && matchesBudget;
                card.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            const hasFilters = Boolean(input) || Object.values(destinationFilters).some(Boolean);
            document.getElementById('gridTitle').textContent =
                visible ? (hasFilters ? 'Matching Destinations' : 'Trending Destinations') : 'No Destinations Found';
            const summary = document.getElementById('filterSummary');
            const active = Object.entries(destinationFilters).filter(([, value]) => value);
            summary.textContent = `${visible} destination${visible === 1 ? '' : 's'} found` +
                (active.length ? ' · ' + active.map(([key, value]) => `${filterLabels[key]}: ${value}`).join(' · ') : '');
            updateFilterLabels();
        }

        function updateFilterLabels() {
            document.querySelectorAll('[data-filter]').forEach(element => {
                const key = element.dataset.filter;
                const value = destinationFilters[key];
                element.classList.toggle('filter-active', Boolean(value));
                const label = filterLabels[key] + (value ? `: ${value}` : '');
                element.setAttribute('aria-label', label);
            });
        }

        function filterDestinations() { applyDestinationFilters(); }

        function openFilter(event, filterName) {
            event.stopPropagation();
            const popover = document.getElementById('filterPopover');
            if (activeFilter === filterName && !popover.hidden) {
                closeFilterPopover();
                return;
            }
            activeFilter = filterName;
            popover.replaceChildren();
            filterOptions[filterName].forEach(option => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'filter-option';
                button.textContent = option;
                button.setAttribute('role', 'radio');
                button.setAttribute('aria-checked', String((destinationFilters[filterName] || 'All') === option));
                button.addEventListener('click', () => {
                    destinationFilters[filterName] = option === 'All' ? '' : option;
                    closeFilterPopover();
                    applyDestinationFilters();
                });
                popover.appendChild(button);
            });
            popover.hidden = false;
            document.querySelectorAll('[data-filter]').forEach(item => item.setAttribute('aria-expanded', String(item.dataset.filter === filterName)));
        }

        function closeFilterPopover() {
            const popover = document.getElementById('filterPopover');
            if (popover) popover.hidden = true;
            activeFilter = null;
            document.querySelectorAll('[data-filter]').forEach(item => item.setAttribute('aria-expanded', 'false'));
        }

        function filterKeydown(event, filterName) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openFilter(event, filterName);
            }
        }

        document.addEventListener('click', event => {
            if (!event.target.closest('.filter-options')) closeFilterPopover();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeFilterPopover();
                closeModalBtn();
            }
        });

        function filterByMood(mood) {
            destinationFilters.terrain = mood || '';
            applyDestinationFilters();
            document.getElementById('gridTitle').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function setModalSaveState(saved) {
            currentDestinationSaved = Boolean(saved);
            const button = document.getElementById('modalSaveButton');
            button.innerHTML = currentDestinationSaved
                ? '<i class="fa-solid fa-bookmark"></i> Saved'
                : '<i class="fa-regular fa-bookmark"></i> Bookmark';
            button.disabled = currentDestinationId < 1;
            button.style.opacity = currentDestinationId < 1 ? '0.55' : '1';
        }

        function openModal(title, season, terrain, desc, imgUrl, destinationId = 0, isSaved = false) {
            currentDestinationId = Number(destinationId) || 0;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalSeason').innerHTML = `<i class="fa-regular fa-calendar"></i> ${escapeHtml(season)}`;
            document.getElementById('modalTerrain').innerHTML = `<i class="fa-solid fa-location-dot"></i> ${escapeHtml(terrain)}`;
            document.getElementById('modalDesc').textContent = desc;
            document.getElementById('modalImg').style.backgroundImage = `url("${String(imgUrl).replace(/["']/g, '')}")`;
            setModalSaveState(isSaved);
            document.getElementById('destModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[character]));
        }

        function closeModalBtn() {
            const modal = document.getElementById('destModal');
            if (modal && modal.style.display === 'flex') {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        function closeModal(event) {
            if (event.target.id === 'destModal') closeModalBtn();
        }

        async function postAction(fields) {
            const response = await fetch('user-destinations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(fields).toString(),
                credentials: 'same-origin'
            });
            const raw = await response.text();
            let data;
            try { data = JSON.parse(raw); }
            catch (_) { throw new Error('The server returned an unexpected response. Check the PHP error log.'); }
            if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
            return data;
        }

        function updateCardSavedState(destinationId, saved) {
            const card = document.querySelector(`.trend-card[data-id="${Number(destinationId)}"]`);
            if (!card) return;
            const heart = card.querySelector('.trend-card-heart');
            const icon = heart?.querySelector('i');
            heart?.classList.toggle('saved', saved);
            heart?.setAttribute('aria-pressed', String(saved));
            heart?.setAttribute('aria-label', saved ? 'Remove from saved destinations' : 'Save destination');
            if (icon) {
                icon.classList.toggle('fa-solid', saved);
                icon.classList.toggle('fa-regular', !saved);
            }
        }

        function renderSavedSidebar() {
            const sidebar = document.getElementById('savedTripsSidebar');
            const savedCards = [...document.querySelectorAll('.trend-card .trend-card-heart[aria-pressed="true"]')]
                .map(heart => heart.closest('.trend-card')).filter(Boolean).slice(0, 3);
            sidebar.replaceChildren();
            if (!savedCards.length) {
                const empty = document.createElement('div');
                empty.className = 'empty-saved';
                empty.textContent = "You haven't saved any destinations yet. Click the heart icon on a destination to save it!";
                sidebar.appendChild(empty);
                return;
            }
            savedCards.forEach(card => {
                const item = document.createElement('div');
                item.className = 'saved-item';
                item.tabIndex = 0;
                item.setAttribute('role', 'button');
                const image = document.createElement('div');
                image.className = 'saved-img';
                image.style.backgroundImage = card.style.backgroundImage;
                const info = document.createElement('div');
                info.className = 'saved-info';
                const title = document.createElement('h6');
                title.textContent = card.querySelector('h4')?.textContent || '';
                const meta = document.createElement('p');
                meta.textContent = `${card.dataset.season} • ${card.dataset.terrain}`;
                info.append(title, meta);
                const arrow = document.createElement('i');
                arrow.className = 'fa-solid fa-chevron-right';
                arrow.style.cssText = 'margin-left:auto;color:#bbb;padding:5px';
                item.append(image, info, arrow);
                const openSaved = () => {
                    const heart = card.querySelector('.trend-card-heart');
                    card.click();
                };
                item.addEventListener('click', openSaved);
                item.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openSaved(); } });
                sidebar.appendChild(item);
            });
        }

        async function toggleSave(event, heartElement, destId) {
            event.stopPropagation();
            if (heartElement.getAttribute('aria-busy') === 'true') return;
            heartElement.setAttribute('aria-busy', 'true');
            try {
                const data = await postAction({ action: 'toggle_save', destination_id: String(destId) });
                if (!['added', 'removed'].includes(data.status)) throw new Error(data.message || 'Unable to update saved places.');
                const saved = data.status === 'added';
                updateCardSavedState(destId, saved);
                if (currentDestinationId === Number(destId)) setModalSaveState(saved);
                renderSavedSidebar();
            } catch (error) {
                console.error('Save destination failed:', error);
                heartElement.setAttribute('aria-invalid', 'true');
                window.setTimeout(() => heartElement.removeAttribute('aria-invalid'), 1800);
            } finally {
                heartElement.removeAttribute('aria-busy');
            }
        }

        async function saveCurrentDestination() {
            if (!currentDestinationId) return;
            const button = document.getElementById('modalSaveButton');
            if (button.disabled) return;
            button.disabled = true;
            try {
                const data = await postAction({ action: 'toggle_save', destination_id: String(currentDestinationId) });
                if (!['added', 'removed'].includes(data.status)) throw new Error(data.message || 'Unable to update saved places.');
                const saved = data.status === 'added';
                updateCardSavedState(currentDestinationId, saved);
                setModalSaveState(saved);
                renderSavedSidebar();
            } catch (error) {
                console.error('Save destination failed:', error);
                button.disabled = false;
                button.setAttribute('aria-invalid', 'true');
                window.setTimeout(() => button.removeAttribute('aria-invalid'), 1800);
            }
        }

        async function subscribeNewsletter() {
            const input = document.getElementById('newsletterEmail');
            const button = document.getElementById('newsletterButton');
            const status = document.getElementById('newsletterStatus');
            const email = input.value.trim();
            status.textContent = '';
            status.className = 'newsletter-status';
            if (!input.checkValidity() || !email) {
                status.textContent = 'Please enter a valid email address.';
                status.classList.add('error');
                input.focus();
                return;
            }
            button.disabled = true;
            button.textContent = '...';
            try {
                const data = await postAction({ action: 'subscribe', email });
                status.textContent = data.message || 'Subscription complete.';
                status.classList.add(['success', 'exists'].includes(data.status) ? 'success' : 'error');
                if (data.status === 'success' || data.status === 'exists') input.value = '';
            } catch (error) {
                status.textContent = error.message || 'Unable to subscribe right now.';
                status.classList.add('error');
            } finally {
                button.disabled = false;
                button.textContent = 'JOIN';
            }
        }

        function sendToAI() {
            const destination = document.getElementById('modalTitle').textContent;
            const description = document.getElementById('modalDesc').textContent;
            const query = `Plan a personalized trip to ${destination}. Destination details: ${description}`;
            window.location.href = 'user-ai.php?auto_dest=' + encodeURIComponent(destination) + '&prompt=' + encodeURIComponent(query);
        }

        document.getElementById('newsletterEmail')?.addEventListener('keydown', event => {
            if (event.key === 'Enter') subscribeNewsletter();
        });
        applyDestinationFilters();

    </script>
</body>
</html>