<?php
session_start();

// Only authenticated users may access the community experience page.
if (empty($_SESSION['user_logged_in']) || empty($_SESSION['user_email'])) {
    header('Location: user-login.php');
    exit;
}

$host = 'localhost';
$db   = 'seasonverse_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Always read identity from the authenticated database account, not user-supplied form data.
    $accountStmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1');
    $accountStmt->execute([$_SESSION['user_email']]);
    $account = $accountStmt->fetch();

    if (!$account) {
        $_SESSION = [];
        session_destroy();
        header('Location: user-login.php');
        exit;
    }

    $userEmail = $account['email'];
    $userName = $account['full_name'];
    $_SESSION['user_name'] = $userName;
    $userInitial = strtoupper(substr(trim($userName), 0, 1));

    $pdo->exec("CREATE TABLE IF NOT EXISTS community_experiences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_name VARCHAR(100) NOT NULL,
        user_email VARCHAR(150) NOT NULL,
        destination VARCHAR(100) NOT NULL,
        story TEXT NOT NULL,
        rating INT DEFAULT 5,
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_experience_email (user_email),
        INDEX idx_experience_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add a CSRF token for all story-changing requests.
    if (empty($_SESSION['experience_csrf'])) {
        $_SESSION['experience_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['experience_csrf'];

    $presets = [
        'Swiss Alps' => 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=800&q=80',
        'Kyoto, Japan' => 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=800&q=80',
        'Maldives' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=800&q=80',
        'Bali, Indonesia' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=800&q=80',
        'Santorini, Greece' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=800&q=80'
    ];

    // Keep the two starter community examples only when the table is genuinely empty.
    $count = (int)$pdo->query('SELECT COUNT(*) FROM community_experiences')->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare('INSERT INTO community_experiences (user_name, user_email, destination, story, rating, image_url) VALUES (?, ?, ?, ?, ?, ?)');
        $seed->execute(['Elena Rostova', 'elena@example.com', 'Swiss Alps', 'Waking up to a silent blanket of fresh snow in Zermatt was magical. The local winter cabins offer incredible warmth and traditional fondue right after a day on the slopes.', 5, $presets['Swiss Alps']]);
        $seed->execute(['Kenji Sato', 'kenji@example.com', 'Kyoto, Japan', 'Visited the bamboo forests at dawn before the crowds arrived. The morning mist combined with the sound of rustling stalks is a meditative experience I will never forget.', 5, $presets['Kyoto, Japan']]);
    }

    // AJAX: create, edit, and delete only the signed-in user's own stories.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($csrfToken, $submittedToken)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Your session expired. Refresh the page and try again.']);
            exit;
        }

        $action = $_POST['action'];
        if ($action === 'delete_story') {
            $storyId = filter_input(INPUT_POST, 'story_id', FILTER_VALIDATE_INT);
            if (!$storyId) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid story selected.']);
                exit;
            }
            $delete = $pdo->prepare('DELETE FROM community_experiences WHERE id = ? AND user_email = ?');
            $delete->execute([$storyId, $userEmail]);
            echo json_encode($delete->rowCount() ? ['status' => 'success', 'message' => 'Your story was deleted.'] : ['status' => 'error', 'message' => 'Story not found or you do not have permission to delete it.']);
            exit;
        }

        if ($action === 'add_story' || $action === 'update_story') {
            $destination = trim($_POST['destination'] ?? '');
            $story = trim($_POST['story'] ?? '');
            $rating = filter_var($_POST['rating'] ?? 5, FILTER_VALIDATE_INT);
            $allowedDestinations = array_keys($presets);

            if (!in_array($destination, $allowedDestinations, true)) {
                echo json_encode(['status' => 'error', 'message' => 'Please choose a valid destination.']);
                exit;
            }
            if ($story === '' || mb_strlen($story) < 20 || mb_strlen($story) > 3000) {
                echo json_encode(['status' => 'error', 'message' => 'Your story must contain 20 to 3,000 characters.']);
                exit;
            }
            if ($rating === false || $rating < 1 || $rating > 5) {
                echo json_encode(['status' => 'error', 'message' => 'Please select a rating from 1 to 5.']);
                exit;
            }

            $imageUrl = $presets[$destination];
            if ($action === 'add_story') {
                $insert = $pdo->prepare('INSERT INTO community_experiences (user_name, user_email, destination, story, rating, image_url) VALUES (?, ?, ?, ?, ?, ?)');
                $insert->execute([$userName, $userEmail, $destination, $story, $rating, $imageUrl]);
                echo json_encode(['status' => 'success', 'message' => 'Your travel story has been published.']);
                exit;
            }

            $storyId = filter_var($_POST['story_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$storyId) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid story selected.']);
                exit;
            }
            $ownership = $pdo->prepare('SELECT id FROM community_experiences WHERE id = ? AND user_email = ? LIMIT 1');
            $ownership->execute([$storyId, $userEmail]);
            if (!$ownership->fetchColumn()) {
                echo json_encode(['status' => 'error', 'message' => 'Story not found or you do not have permission to edit it.']);
                exit;
            }
            $update = $pdo->prepare('UPDATE community_experiences SET destination = ?, story = ?, rating = ?, image_url = ? WHERE id = ? AND user_email = ?');
            $update->execute([$destination, $story, $rating, $imageUrl, $storyId, $userEmail]);
            echo json_encode(['status' => 'success', 'message' => 'Your story has been updated.']);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Unsupported request.']);
        exit;
    }

    $stories = $pdo->query('SELECT * FROM community_experiences ORDER BY created_at DESC, id DESC')->fetchAll();
    $myStoriesStmt = $pdo->prepare('SELECT COUNT(*) FROM community_experiences WHERE user_email = ?');
    $myStoriesStmt->execute([$userEmail]);
    $myStoryCount = (int)$myStoriesStmt->fetchColumn();

} catch (PDOException $e) {
    error_log('SeasonVerse experiences database error: ' . $e->getMessage());
    http_response_code(500);
    die('Unable to load community experiences right now. Please check your database connection.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Community Experiences</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FDFBF7; color: #1A1A1A; overflow-x: hidden; }

        /* GLASSMORPHISM NAVBAR */
        .user-navbar { position: sticky; top: 0; z-index: 1000; background: rgba(253, 251, 247, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); display: flex; justify-content: space-between; align-items: center; padding: 18px 50px; border-bottom: 1px solid rgba(0, 0, 0, 0.04); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02); }
        .nav-brand { font-size: 1.3rem; font-weight: 700; font-family: serif; color: #1B2E23; }
        .nav-links { display: flex; gap: 30px; list-style: none; font-size: 0.85rem; font-weight: 500; color: #555; }
        .nav-links a { text-decoration: none; color: inherit; transition: color 0.2s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #1B2E23; font-weight: 600; }
        .nav-right-area { display: flex; align-items: center; gap: 15px; }
        .profile-letter { width: 32px; height: 32px; border-radius: 50%; background: #1B2E23; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; cursor: pointer; }

        .main-content { max-width: 1200px; margin: 0 auto; padding: 50px 20px; }

        /* HEADER */
        .page-header { text-align: center; margin-bottom: 60px; }
        .page-header h4 { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 2px; color: #B45309; font-weight: 700; margin-bottom: 15px; background: #FDE8C4; padding: 6px 16px; border-radius: 20px; display: inline-block; }
        .page-header h1 { font-family: serif; font-size: 3rem; color: #1B2E23; line-height: 1.1; margin-bottom: 15px; }
        .page-header p { font-size: 0.9rem; color: #666; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.6; }
        
        .btn-primary-custom { background: #1B2E23; color: #fff; padding: 14px 32px; border-radius: 30px; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 10px 25px rgba(27,46,35,0.2); transition: transform 0.2s; }
        .btn-primary-custom:hover { transform: translateY(-2px); }

        /* FEED GRID */
        .feed-section-title { font-family: serif; font-size: 1.8rem; color: #1B2E23; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #EAE5DD; padding-bottom: 15px; }
        .feed-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; margin-bottom: 60px; }
        
        .story-card { background: #fff; border-radius: 24px; overflow: hidden; border: 1px solid rgba(0,0,0,0.04); box-shadow: 0 15px 35px rgba(0,0,0,0.03); display: flex; flex-direction: column; transition: transform 0.3s; }
        .story-card:hover { transform: translateY(-4px); }
        .story-card-img { height: 220px; background-size: cover; background-position: center; position: relative; }
        .story-badge { position: absolute; top: 15px; right: 15px; background: rgba(253, 251, 247, 0.9); backdrop-filter: blur(4px); padding: 5px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; color: #1B2E23; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .story-body { padding: 30px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .story-author-row { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .author-avatar { width: 40px; height: 40px; border-radius: 50%; background: #C5A880; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
        .author-info h5 { font-size: 0.9rem; font-weight: 700; color: #1A1A1A; }
        .author-info p { font-size: 0.7rem; color: #888; }
        
        .story-text { font-size: 0.88rem; color: #444; line-height: 1.6; margin-bottom: 20px; font-style: italic; }
        
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

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-box { background: #FDFBF7; width: 100%; max-width: 520px; border-radius: 24px; padding: 40px; box-shadow: 0 25px 50px rgba(0,0,0,0.3); position: relative; animation: popIn 0.3s ease-out; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #666; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px; }
        .form-group select, .form-group textarea { width: 100%; padding: 14px 18px; border: 1px solid #EAE5DD; background: #fff; border-radius: 12px; font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; }
        .form-group textarea { height: 120px; resize: vertical; }

        /* TOAST */
        .toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: #1B2E23; color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; box-shadow: 0 10px 25px rgba(27, 46, 35, 0.3); transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; align-items: center; gap: 10px; }
        .toast.show { transform: translateX(0); }

        .main-footer { background: #1B2E23; color: #fff; padding: 40px 50px; text-align: center; font-size: 0.75rem; letter-spacing: 1px; color: #A3B899; }
    </style>
</head>
<body>

<nav class="user-navbar">
        <div class="nav-brand">SeasonVerse</div>
        <ul class="nav-links">
            <li><a href="user-home.php">Home</a></li>
            <li><a href="user-ai.php">AI Planner</a></li>
            <li><a href="user-destinations.php">Destinations</a></li>
            <li><a href="user-experiences.php"class="active">Experiences</a></li>
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
        <!-- HEADER -->
        <div class="page-header">
            <h4>Community Journals</h4>
            <h1>Real Traveler Stories & Experiences.</h1>
            <p>Read authentic personal journals, insider tips, and reviews shared by fellow travelers around the world.</p>
            <button class="btn-primary-custom" onclick="openModal()"><i class="fa-solid fa-pen-nib"></i> Share Your Story</button>
        </div>

        <div id="pageMessage" role="status" aria-live="polite" style="display:none; padding:14px 18px; margin-bottom:25px; border-radius:12px; font-size:0.85rem;"></div>

        <!-- FEED -->
        <div class="feed-section-title">
            <h3>Recent Traveler Reviews</h3>
            <span style="font-size: 0.8rem; color: #888; font-weight: 500;"><?php echo count($stories); ?> Public Stories · <?php echo $myStoryCount; ?> Your Stories</span>
        </div>

        <?php if(empty($stories)): ?>
            <div style="text-align: center; padding: 50px; background: #fff; border-radius: 20px; color: #888; margin-bottom: 60px;">
                No stories shared yet. Be the first to add your experience!
            </div>
        <?php else: ?>
            <div class="feed-grid">
                <?php foreach($stories as $s): ?>
                    <div class="story-card">
                        <div class="story-card-img" style="background-image: url('<?php echo htmlspecialchars($s['image_url']); ?>');">
                            <div class="story-badge"><?php echo htmlspecialchars($s['destination']); ?></div>
                        </div>
                        <div class="story-body">
                            <div>
                                <div class="story-author-row">
                                    <div class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($s['user_name'], 0, 1))); ?></div>
                                    <div class="author-info">
                                        <h5><?php echo htmlspecialchars($s['user_name']); ?></h5>
                                        <p><?php echo date('F d, Y', strtotime($s['created_at'])); ?></p>
                                    </div>
                                </div>
                                <p class="story-text">"<?php echo nl2br(htmlspecialchars($s['story'])); ?>"</p>
                            </div>
                            
                            <div class="story-footer">
                                <div class="stars">
                                    <?php for($i=0; $i<$s['rating']; $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?>
                                </div>
                                <span>Community Experience</span>
                            </div>
                            <?php if (strtolower($s['user_email']) === strtolower($userEmail)): ?>
                                <div style="display:flex; gap:10px; margin-top:18px;">
                                    <button type="button" class="btn-primary-custom" style="padding:9px 16px; font-size:0.72rem;" onclick='editStory(<?php echo json_encode(["id" => (int)$s["id"], "destination" => $s["destination"], "story" => $s["story"], "rating" => (int)$s["rating"]], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <button type="button" style="background:#FDF2F2;color:#C53030;padding:9px 16px;border:1px solid #FED7D7;border-radius:30px;font-size:0.72rem;font-weight:700;cursor:pointer;" onclick="deleteStory(<?php echo (int)$s['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- SHARE MODAL -->
    <div id="storyModal" class="modal-overlay" onclick="closeModal(event)">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModalBtn()">&times;</button>
            <h2 style="font-family: serif; font-size: 1.8rem; color: #1B2E23; margin-bottom: 25px;">Share Your Travel Story</h2>
            
            <form id="storyForm" onsubmit="submitStory(event)">
                <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="storyIdInput" value="">
                <div class="form-group">
                    <label>Select Destination</label>
                    <select id="destInput" required>
                        <option value="">Choose a location...</option>
                        <option value="Swiss Alps">Swiss Alps (Winter Escape)</option>
                        <option value="Kyoto, Japan">Kyoto, Japan (Cultural & Temples)</option>
                        <option value="Maldives">Maldives (Luxury & Beaches)</option>
                        <option value="Bali, Indonesia">Bali, Indonesia (Wellness & Nature)</option>
                        <option value="Santorini, Greece">Santorini, Greece (Coastal Sunset)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Your Personal Experience & Tips</label>
                    <textarea id="storyInput" maxlength="3000" minlength="20" placeholder="Share what made this trip special, hidden spots, or local recommendations... (20–3,000 characters)" required></textarea>
                </div>
                <div class="form-group">
                    <label>Overall Rating</label>
                    <select id="ratingInput">
                        <option value="5">★★★★★ (5/5 - Unforgettable)</option>
                        <option value="4">★★★★☆ (4/5 - Wonderful)</option>
                        <option value="3">★★★☆☆ (3/5 - Good)</option>
                        <option value="2">★★☆☆☆ (2/5 - Average)</option>
                        <option value="1">★☆☆☆☆ (1/5 - Needs improvement)</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary-custom" style="width: 100%; justify-content: center; margin-top: 10px;">Publish Story</button>
            </form>
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

    <script>
        const experienceEndpoint = 'user-experiences.php';
        const csrfToken = document.getElementById('csrfToken').value;

        function showMessage(message, type = 'success') {
            const box = document.getElementById('pageMessage');
            box.textContent = message;
            box.style.display = 'block';
            box.style.background = type === 'success' ? '#F0FDF4' : '#FDF2F2';
            box.style.color = type === 'success' ? '#166534' : '#C53030';
            box.style.border = type === 'success' ? '1px solid #DCFCE7' : '1px solid #FED7D7';
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function openModal() {
            document.getElementById('storyForm').reset();
            document.getElementById('storyIdInput').value = '';
            document.querySelector('#storyModal h2').textContent = 'Share Your Travel Story';
            document.querySelector('#storyForm button[type="submit"]').textContent = 'Publish Story';
            document.getElementById('storyModal').style.display = 'flex';
        }
        function closeModalBtn() { document.getElementById('storyModal').style.display = 'none'; }
        function closeModal(e) { if (e.target.id === 'storyModal') closeModalBtn(); }

        function editStory(story) {
            document.getElementById('storyForm').reset();
            document.getElementById('storyIdInput').value = story.id;
            document.getElementById('destInput').value = story.destination;
            document.getElementById('storyInput').value = story.story;
            document.getElementById('ratingInput').value = story.rating;
            document.querySelector('#storyModal h2').textContent = 'Edit Your Travel Story';
            document.querySelector('#storyForm button[type="submit"]').textContent = 'Save Changes';
            document.getElementById('storyModal').style.display = 'flex';
        }

        async function sendStoryRequest(formData) {
            const response = await fetch(experienceEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Request failed.');
            return data;
        }

        async function submitStory(e) {
            e.preventDefault();
            const destination = document.getElementById('destInput').value;
            const story = document.getElementById('storyInput').value.trim();
            const rating = document.getElementById('ratingInput').value;
            const storyId = document.getElementById('storyIdInput').value;

            if (story.length < 20 || story.length > 3000) {
                showMessage('Please write between 20 and 3,000 characters for your experience.', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', storyId ? 'update_story' : 'add_story');
            formData.append('csrf_token', csrfToken);
            formData.append('story_id', storyId);
            formData.append('destination', destination);
            formData.append('story', story);
            formData.append('rating', rating);

            const submitButton = document.querySelector('#storyForm button[type="submit"]');
            submitButton.disabled = true;
            try {
                const data = await sendStoryRequest(formData);
                closeModalBtn();
                showMessage(data.message || 'Your story has been saved.');
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                showMessage(error.message || 'Unable to save your story. Please try again.', 'error');
            } finally {
                submitButton.disabled = false;
            }
        }

        async function deleteStory(storyId) {
            const formData = new URLSearchParams();
            formData.append('action', 'delete_story');
            formData.append('csrf_token', csrfToken);
            formData.append('story_id', storyId);
            try {
                const data = await sendStoryRequest(formData);
                showMessage(data.message || 'Your story was deleted.');
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                showMessage(error.message || 'Unable to delete this story.', 'error');
            }
        }
    </script>
</body>
</html>