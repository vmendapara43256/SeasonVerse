<?php
session_start();

$userEmail = $_SESSION['user_email'] ?? 'traveler@seasonverse.com';
$userName = $_SESSION['user_name'] ?? 'Traveler';
$userInitial = strtoupper(substr(trim($userName), 0, 1));

$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create Tables for Packing Checklist
    $pdo->exec("CREATE TABLE IF NOT EXISTS packing_items_v2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(150) NOT NULL,
        category VARCHAR(50) NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        is_packed TINYINT DEFAULT 0
    )");

    // 2. Populate Default Items if empty for user
    $count = $pdo->prepare("SELECT COUNT(*) FROM packing_items_v2 WHERE user_email = ?");
    $count->execute([$userEmail]);
    if ($count->fetchColumn() == 0) {
        $defaults = [
            ['Clothing', 'Thermal base layers', 1],
            ['Clothing', 'Cashmere sweater', 1],
            ['Clothing', 'Wool winter coat', 0],
            ['Clothing', 'Denim active', 0],
            ['Clothing', 'Lounge wear', 0],
            ['Footwear', 'Walking shoes', 1],
            ['Footwear', 'Insulated winter boots', 0],
            ['Footwear', 'Hotel slippers', 0],
            ['Electronics', 'Phones & Charger', 1],
            ['Electronics', 'Power bank', 1],
            ['Electronics', 'Travel adapter (Type J)', 0],
            ['Electronics', 'Noise-canceling headphones', 0]
        ];
        $stmt = $pdo->prepare("INSERT INTO packing_items_v2 (user_email, category, item_name, is_packed) VALUES (?, ?, ?, ?)");
        foreach($defaults as $d) {
            $stmt->execute([$userEmail, $d[0], $d[1], $d[2]]);
        }
    }

    // --- AJAX HANDLER FOR CHECKLIST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_item') {
        $itemId = intval($_POST['item_id']);
        $status = intval($_POST['status']);
        $pdo->prepare("UPDATE packing_items_v2 SET is_packed = ? WHERE id = ? AND user_email = ?")->execute([$status, $itemId, $userEmail]);
        
        // Calculate new progress
        $total = $pdo->prepare("SELECT COUNT(*) FROM packing_items_v2 WHERE user_email = ?");
        $total->execute([$userEmail]);
        $totalCount = $total->fetchColumn();

        $packed = $pdo->prepare("SELECT COUNT(*) FROM packing_items_v2 WHERE user_email = ? AND is_packed = 1");
        $packed->execute([$userEmail]);
        $packedCount = $packed->fetchColumn();

        echo json_encode(['status' => 'success', 'packed' => $packedCount, 'total' => $totalCount]);
        exit;
    }

    $itemsStmt = $pdo->prepare("SELECT * FROM packing_items_v2 WHERE user_email = ?");
    $itemsStmt->execute([$userEmail]);
    $allItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $clothing = array_filter($allItems, fn($i) => $i['category'] === 'Clothing');
    $footwear = array_filter($allItems, fn($i) => $i['category'] === 'Footwear');
    $electronics = array_filter($allItems, fn($i) => $i['category'] === 'Electronics');

    $totalPacked = count(array_filter($allItems, fn($i) => $i['is_packed'] == 1));
    $totalItemsCount = count($allItems);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Travel & Packing</title>
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
        .profile-letter { width: 32px; height: 32px; border-radius: 50%; background: #1B2E23; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; cursor: pointer; }

        .main-content { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        /* HEADER */
        .page-header { margin-bottom: 40px; }
        .page-header h4 { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: #888; font-weight: 700; margin-bottom: 10px; }
        .page-header h1 { font-family: serif; font-size: 3rem; color: #1B2E23; line-height: 1.1; margin-bottom: 12px; }
        .page-header p { font-size: 0.85rem; color: #666; max-width: 500px; line-height: 1.5; }

        /* HERO SWITZERLAND BANNER */
        .hero-banner { background: #FFF9F2; border: 1px solid #F4EBE1; border-radius: 24px; padding: 25px; display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: center; margin-bottom: 50px; box-shadow: 0 15px 35px rgba(180,83,9,0.03); }
        .hero-img { height: 180px; border-radius: 16px; background-size: cover; background-position: center; }
        .hero-info { display: flex; flex-direction: column; justify-content: center; }
        .hero-info-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .hero-info-top span { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; }
        .hero-info-top h3 { font-family: serif; font-size: 1.8rem; color: #1B2E23; margin-top: 2px; }
        .hero-progress-bar { background: #EAE5DD; height: 6px; border-radius: 3px; overflow: hidden; margin: 12px 0; }
        .hero-progress-fill { background: #1B2E23; height: 100%; width: <?php echo ($totalItemsCount > 0) ? round(($totalPacked/$totalItemsCount)*100) : 0; ?>%; transition: width 0.3s; }
        .btn-start-trip { background: #1B2E23; color: #fff; padding: 10px 24px; border-radius: 25px; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; text-transform: uppercase; width: max-content; }

        /* SMART PACKING LIST SECTION */
        .section-title h3 { font-family: serif; font-size: 1.5rem; color: #1B2E23; margin-bottom: 6px; }
        .section-title p { font-size: 0.8rem; color: #666; margin-bottom: 25px; }

        /* AI SUGGESTION BOX */
        .ai-suggestion-box { background: #FFF3E2; border: 1px solid #F7DEC2; border-radius: 16px; padding: 18px 25px; display: flex; gap: 15px; align-items: flex-start; margin-bottom: 30px; font-size: 0.82rem; color: #78350F; line-height: 1.5; }
        .ai-suggestion-box i { font-size: 1.1rem; color: #D97706; margin-top: 2px; }

        /* CHECKLIST CATEGORY CARDS */
        .categories-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 60px; }
        .cat-card { background: #fff; border-radius: 20px; padding: 25px; border: 1px solid #EAE5DD; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
        .cat-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #F5F2EC; padding-bottom: 12px; }
        .cat-card-header h5 { font-family: serif; font-size: 1.1rem; color: #1B2E23; }
        .cat-card-header span { font-size: 0.7rem; color: #888; font-weight: 700; }
        
        .cat-item { display: flex; align-items: center; gap: 12px; font-size: 0.82rem; color: #333; margin-bottom: 14px; cursor: pointer; }
        .cat-item input { accent-color: #1B2E23; width: 16px; height: 16px; cursor: pointer; }
        .cat-item.checked span { text-decoration: line-through; color: #999; }

        /* EVERYTHING IN ITS PLACE */
        .place-section { text-align: center; margin-bottom: 60px; }
        .place-section h3 { font-family: serif; font-size: 1.6rem; color: #1B2E23; margin-bottom: 35px; }
        .place-grid { display: flex; justify-content: space-between; max-width: 900px; margin: 0 auto; }
        .place-item { background: #fff; border: 1px solid #EAE5DD; border-radius: 16px; padding: 20px; width: 160px; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); cursor: pointer; transition: transform 0.2s; }
        .place-item:hover { transform: translateY(-3px); }
        .place-item i { font-size: 1.2rem; color: #1B2E23; }
        .place-item h6 { font-size: 0.8rem; font-weight: 700; color: #1A1A1A; }
        .place-item span { font-size: 0.7rem; color: #888; }

        /* PACK FOR THE WEATHER BANNER */
        .weather-banner { width: 100%; height: 350px; border-radius: 24px; background-image: linear-gradient(to right, rgba(0,0,0,0.65), rgba(0,0,0,0.2)), url('https://images.unsplash.com/photo-1517824806704-9040b037703b?auto=format&fit=crop&w=1600&q=80'); background-size: cover; background-position: center; padding: 50px; display: flex; flex-direction: column; justify-content: center; margin-bottom: 60px; }
        .weather-glass { background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); padding: 30px; border-radius: 20px; max-width: 480px; border: 1px solid rgba(255,255,255,0.25); color: #fff; }
        .weather-glass h3 { font-family: serif; font-size: 1.8rem; margin-bottom: 15px; }
        .weather-lists { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 0.78rem; line-height: 1.6; color: #eee; }
        .weather-lists h6 { font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; color: #FDE8C4; }

        /* TRAVEL SMARTER */
        .smart-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 50px; }
        .smart-card { background: #fff; border: 1px solid #EAE5DD; border-radius: 18px; padding: 25px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); }
        .smart-card h6 { font-family: serif; font-size: 0.95rem; font-weight: 700; color: #1B2E23; margin-bottom: 10px; }
        .smart-card p { font-size: 0.75rem; color: #666; line-height: 1.5; }

        /* TRAVEL DAY CHECKLIST BAR */
        .travel-day-bar { background: #1B2E23; border-radius: 35px; padding: 16px 30px; display: flex; justify-content: space-between; align-items: center; color: #fff; margin-bottom: 60px; font-size: 0.8rem; }
        .travel-day-items { display: flex; gap: 30px; color: #A3B899; }
        .travel-day-items span { color: #fff; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .btn-ready { background: #FDE8C4; color: #1B2E23; padding: 8px 22px; border-radius: 20px; font-weight: 700; font-size: 0.7rem; border: none; cursor: pointer; text-transform: uppercase; }

        /* AI ADVICE BOX */
        .advice-box { background: #F4EBE1; border-radius: 24px; padding: 40px; text-align: center; margin-bottom: 60px; }
        .advice-box h3 { font-family: serif; font-size: 1.8rem; color: #1B2E23; margin-bottom: 10px; }
        .advice-box p { font-size: 0.85rem; color: #666; max-width: 500px; margin: 0 auto 25px auto; line-height: 1.5; }
        .btn-ask-ai { background: #1B2E23; color: #fff; padding: 12px 30px; border-radius: 30px; font-weight: 700; font-size: 0.75rem; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; }

        /* TOAST */
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
            <li><a href="user-travel.php"class="active">Travel & Packing</a></li>
        </ul>
        <div class="nav-right-area">
    <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($userName); ?></span>
    <div class="profile-letter" onclick="window.location.href='user-profile.php'" title="View Profile"><?php echo htmlspecialchars($userInitial); ?></div>
    
    <!-- CHANGE login.php TO MATCH YOUR ACTUAL LOGIN FILE (e.g. index.php) -->
    <a href="/SeasonVerse2/logout.php" style="font-size: 0.75rem; color: #C53030; text-decoration: none; font-weight: 700; margin-left: 10px;">LOGOUT</a>
</div>
    </nav>

    <div class="main-content">
        <!-- HEADER -->
        <div class="page-header">
            <h4>Travel & Packing</h4>
            <h1>Pack Less. Prepare Better.</h1>
            <p>Everything you need for a smoother journey, organized around your destination, season, and travel style.</p>
        </div>

        <!-- HERO SWITZERLAND BANNER -->
        <div class="hero-banner">
            <div class="hero-img" style="background-image: url('https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=600&q=80');"></div>
            <div class="hero-info">
                <div class="hero-info-top">
                    <div>
                        <span>YOUR NEXT JOURNEY</span>
                        <h3>SWITZERLAND</h3>
                    </div>
                    <span style="font-size: 0.8rem; color: #555;">Winter Zermatt • 15 Dec — 21 Dec • 7 Days • 2 Travelers</span>
                </div>
                <div style="font-size: 0.75rem; color: #666; display: flex; justify-content: space-between;">
                    <span>Packing Progress</span>
                    <span id="progressCountText"><?php echo $totalPacked; ?> / <?php echo $totalItemsCount; ?> items</span>
                </div>
                <div class="hero-progress-bar">
                    <div class="hero-progress-fill" id="heroProgressFill" style="width: <?php echo ($totalItemsCount > 0) ? round(($totalPacked/$totalItemsCount)*100) : 0; ?>%;"></div>
                </div>
                <button class="btn-start-trip" onclick="showToast('Loading trip summary...')">Start Trip</button>
            </div>
        </div>

        <!-- YOUR SMART PACKING LIST -->
        <div class="section-title">
            <h3>Your Smart Packing List</h3>
            <p>A personalized checklist curated around your destination, season, and trip.</p>
        </div>

        <!-- AI ADVICE BANNER -->
        <div class="ai-suggestion-box">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <div>
                <strong>Season Winter Upgrade:</strong><br>
                Based on your itinerary to Switzerland, don't forget thermal base layers, waterproof footwear, merino accessories (gloves, beanies), and SPF 50+ for high-altitude sun protection.
            </div>
        </div>

        <!-- CATEGORIES GRID -->
        <div class="categories-grid">
            
            <!-- CLOTHING -->
            <div class="cat-card">
                <div class="cat-card-header">
                    <h5><i class="fa-solid fa-shirt" style="margin-right: 8px; color: #B45309;"></i> Clothing</h5>
                    <span><?php echo count($clothing); ?> items</span>
                </div>
                <div>
                    <?php foreach($clothing as $item): ?>
                        <label class="cat-item <?php echo $item['is_packed'] ? 'checked' : ''; ?>">
                            <input type="checkbox" <?php echo $item['is_packed'] ? 'checked' : ''; ?> onchange="toggleItem(this, <?php echo $item['id']; ?>)">
                            <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- FOOTWEAR -->
            <div class="cat-card">
                <div class="cat-card-header">
                    <h5><i class="fa-solid fa-shoe-prints" style="margin-right: 8px; color: #B45309;"></i> Footwear</h5>
                    <span><?php echo count($footwear); ?> items</span>
                </div>
                <div>
                    <?php foreach($footwear as $item): ?>
                        <label class="cat-item <?php echo $item['is_packed'] ? 'checked' : ''; ?>">
                            <input type="checkbox" <?php echo $item['is_packed'] ? 'checked' : ''; ?> onchange="toggleItem(this, <?php echo $item['id']; ?>)">
                            <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ELECTRONICS -->
            <div class="cat-card">
                <div class="cat-card-header">
                    <h5><i class="fa-solid fa-plug" style="margin-right: 8px; color: #B45309;"></i> Electronics</h5>
                    <span><?php echo count($electronics); ?> items</span>
                </div>
                <div>
                    <?php foreach($electronics as $item): ?>
                        <label class="cat-item <?php echo $item['is_packed'] ? 'checked' : ''; ?>">
                            <input type="checkbox" <?php echo $item['is_packed'] ? 'checked' : ''; ?> onchange="toggleItem(this, <?php echo $item['id']; ?>)">
                            <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- EVERYTHING IN ITS PLACE -->
        <div class="place-section">
            <h3>Everything In Its Place</h3>
            <div class="place-grid">
                <div class="place-item" onclick="showToast('Viewing Toiletries...')">
                    <i class="fa-solid fa-bottle-droplet"></i>
                    <h6>Toiletries</h6>
                    <span>4 items</span>
                </div>
                <div class="place-item" onclick="showToast('Viewing Documents...')">
                    <i class="fa-solid fa-passport"></i>
                    <h6>Documents</h6>
                    <span>3 items</span>
                </div>
                <div class="place-item" onclick="showToast('Viewing Health...')">
                    <i class="fa-solid fa-kit-medical"></i>
                    <h6>Health</h6>
                    <span>2 items</span>
                </div>
                <div class="place-item" onclick="showToast('Viewing Essentials...')">
                    <i class="fa-solid fa-suitcase"></i>
                    <h6>Essentials</h6>
                    <span>5 items</span>
                </div>
                <div class="place-item" onclick="showToast('Viewing Gear...')">
                    <i class="fa-solid fa-camera-retro"></i>
                    <h6>Gear</h6>
                    <span>3 items</span>
                </div>
            </div>
        </div>

        <!-- PACK FOR THE WEATHER BANNER -->
        <div class="weather-banner">
            <div class="weather-glass">
                <h3>Pack for the Weather</h3>
                <div class="weather-lists">
                    <div>
                        <h6>PROS</h6>
                        <p>• Built-in layer management<br>• Zero bulky garments<br>• Optimized for altitude</p>
                    </div>
                    <div>
                        <h6>CLIMATE ADAPTIVE</h6>
                        <p>• Weather-sealed zippers<br>• Moisture-wicking base layers<br>• Wind-resistant outer shells</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRAVEL SMARTER -->
        <div class="smart-grid">
            <div class="smart-card">
                <h6>Roll, Don't Fold</h6>
                <p>Space optimization is achieved by rolling garments tightly, eliminating creases and increasing packing capacity by 30%.</p>
            </div>
            <div class="smart-card">
                <h6>Layer Smart</h6>
                <p>Choose versatile pieces that can be layered for fluctuating temperatures from cold mountain air to warm indoor cafes.</p>
            </div>
            <div class="smart-card">
                <h6>Pro-Grade Essentials</h6>
                <p>Keep your passport, lounge passes, and currency in an RFID-shielded day bag for seamless transit.</p>
            </div>
            <div class="smart-card">
                <h6>Pack Purpose</h6>
                <p>Opt for a refined color palette where every piece works together for effortless styling.</p>
            </div>
        </div>

        <!-- TRAVEL DAY CHECKLIST BAR -->
        <div class="travel-day-bar">
            <div style="font-weight: 700; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plane-departure" style="color: #FDE8C4;"></i> TRAVEL DAY CHECKLIST
            </div>
            <div class="travel-day-items">
                <span><i class="fa-solid fa-check" style="color: #FDE8C4;"></i> Passport</span>
                <span><i class="fa-solid fa-check" style="color: #FDE8C4;"></i> Printed Visas</span>
                <span><i class="fa-solid fa-check" style="color: #FDE8C4;"></i> Boarding Passes</span>
                <span><i class="fa-solid fa-check" style="color: #FDE8C4;"></i> Travel Tokens</span>
            </div>
            <button class="btn-ready" onclick="showToast('All items verified!')">Verify All</button>
        </div>

        <!-- NEED PERSONALIZED PACKING ADVICE -->
        <div class="advice-box">
            <h3>Need personalized packing advice?</h3>
            <p>Our AI concierge can refine your list based on live event forecasts and your personal wardrobe style.</p>
            <button class="btn-ask-ai" onclick="window.location.href='user-ai.php'">Ask SeasonVerse AI</button>
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

    <div id="toastContainer" class="toast-container"></div>

    <script>
        function showToast(message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 400); 
            }, 3000);
        }

        async function toggleItem(checkbox, itemId) {
            const status = checkbox.checked ? 1 : 0;
            const itemRow = checkbox.closest('.cat-item');
            if(checkbox.checked) {
                itemRow.classList.add('checked');
            } else {
                itemRow.classList.remove('checked');
            }

            const formData = new URLSearchParams();
            formData.append('action', 'toggle_item');
            formData.append('item_id', itemId);
            formData.append('status', status);

            try {
                const response = await fetch('user-travel-packing.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                });
                const data = await response.json();
                if(data.status === 'success') {
                    document.getElementById('progressCountText').innerText = `${data.packed} / ${data.total} items`;
                    const percent = Math.round((data.packed / data.total) * 100);
                    document.getElementById('heroProgressFill').style.width = percent + '%';
                    showToast("Checklist synchronized.");
                }
            } catch(err) {
                console.error(err);
            }
        }
    </script>
</body>
</html>