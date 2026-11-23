<?php
ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
session_start();

$userEmail = $_SESSION['user_email'] ?? 'traveler@seasonverse.com';
$userName = $_SESSION['user_name'] ?? 'Traveler';
$userInitial = strtoupper(substr(trim($userName), 0, 1));

$host = '127.0.0.1';
$port = 3306;
$db = 'seasonverse';
$user = 'root';
$pass = '';

$isAjaxChat = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(150) NOT NULL,
        sender VARCHAR(10) NOT NULL,
        message TEXT NOT NULL,
        image_url VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_stats (
        id INT PRIMARY KEY, total_users INT, ai_sessions INT,
        active_travel_plans INT, published_content INT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS recent_activity (
        id INT AUTO_INCREMENT PRIMARY KEY, icon_class VARCHAR(50),
        description TEXT, time_ago VARCHAR(50)
    )");

    if ((int)$pdo->query("SELECT COUNT(*) FROM platform_stats")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO platform_stats
            (id, total_users, ai_sessions, active_travel_plans, published_content)
            VALUES (1, 1200, 800, 500, 150)");
    }

    $count = $pdo->prepare("SELECT COUNT(*) FROM ai_chats WHERE user_email = ?");
    $count->execute([$userEmail]);
    if ((int)$count->fetchColumn() === 0) {
        $welcome = "Tell me about the trip you're imagining — even if you only have a destination, a mood, or a season in mind.";
        $init = $pdo->prepare("INSERT INTO ai_chats (user_email, sender, message) VALUES (?, 'ai', ?)");
        $init->execute([$userEmail, $welcome]);
    }

    if ($isAjaxChat) {
        header('Content-Type: application/json; charset=utf-8');
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            http_response_code(400);
            if (ob_get_length()) ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Please enter a message.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO ai_chats (user_email, sender, message) VALUES (?, 'user', ?)");
        $stmt->execute([$userEmail, $message]);
        $pdo->exec("UPDATE platform_stats SET ai_sessions = ai_sessions + 1 WHERE id = 1");

        $firstName = explode(' ', trim($userName))[0] ?: 'User';
        $log = $pdo->prepare("INSERT INTO recent_activity (icon_class, description, time_ago)
            VALUES ('fa-solid fa-wand-magic-sparkles', ?, 'Just now')");
        $log->execute([$firstName . " generated a new AI itinerary."]);

        // Set GROQ_API_KEY in Apache's environment; do not hardcode API keys in this file.
        $apiKey = getenv('GROQ_API_KEY');
        if (!$apiKey) {
            throw new RuntimeException('Groq API key is missing. Configure GROQ_API_KEY in Apache httpd.conf and restart Apache.');
        }

        
        $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
        
        
        $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
        
        $systemPrompt = <<<'PROMPT'
        You are SeasonVerse AI, an exclusive luxury seasonal travel and lifestyle concierge.
        
        YOUR ROLE:
        Help users plan personalized luxury and budget-friendly trips, discover destinations, create itineraries, compare hotels, estimate budgets, explore activities, and prepare seasonal travel recommendations.
        
        RESPONSE QUALITY RULES:
        1. Always provide accurate, useful and well-organized answers.
        2. Understand the user's actual intent.
        3. Never invent hotels, resorts, restaurants, destinations, prices, facilities or travel routes.
        4. Clearly distinguish verified facts from estimates.
        5. Never claim information has been verified unless it has actually been checked.
        6. Avoid repetition and unnecessary filler.
        7. Use clear headings, short paragraphs, bullet points and tables where useful.
        8. Give practical recommendations.
        9. State when information is unknown.
        10. Adapt answer length to the question.
        11. Maintain a professional, welcoming and elegant tone.
        
        TRAVEL RESPONSE FORMAT:
        
        1. TRIP OVERVIEW
        - Destination
        - Duration
        - Best travel period
        - Estimated budget
        - Travel style
        
        2. DESTINATION HIGHLIGHTS
        - Real attractions
        - Landscapes and landmarks
        - Wellness experiences
        - Local activities
        
        3. DAY-BY-DAY ITINERARY
        For each day include:
        - Morning
        - Afternoon
        - Evening
        - Travel and transfer details
        
        4. ACCOMMODATION
        - Real properties only when verified
        - Location
        - Facilities
        - Estimated prices when available
        - Clearly identify unverified prices and availability
        
        5. FOOD AND EXPERIENCES
        - Local cuisine
        - Dining recommendations
        - Cultural experiences
        
        6. ESTIMATED BUDGET
        - Accommodation
        - Transport
        - Food
        - Activities
        - Other expenses
        - Total estimated cost
        - Currency and assumptions
        
        7. IMPORTANT TRAVEL TIPS
        - Weather and clothing
        - Transport
        - Reservations
        - Safety
        - Documents and practical advice
        
        FORMATTING RULES:
        
        1. Never use hashtag symbols (#, ##, ###) in section headings.
        2. Write headings as plain text.
        3. Use bold formatting for section headings when appropriate.
        4. Keep headings clean, professional and consistent.
        5. Use short paragraphs and organized bullet points.
        6. Use tables only when they improve readability.
        7. Avoid unnecessary emojis and decorative symbols.
        8. Never output broken or incomplete formatting.
        9. Maintain clear spacing between sections.
        10. Do not use Markdown heading syntax anywhere in the response.
        
        
        IMAGE RULES:
        - Recommend images relevant to actual destinations.
        - Never invent image URLs.
        - Never claim an image was retrieved if no image retrieval tool was used.
        - Never use unrelated generic images.
        - Associate images with their actual destinations.
        
        ACCURACY:
        A realistic and factually accurate answer is more important than an impressive-looking answer. Never invent properties, prices, facilities, routes or activities,dont use hashtags.
        
        GENERAL QUESTIONS:
        For programming, education, technology, business and lifestyle questions, use an appropriate professional response structure instead of forcing the travel itinerary format.

        
        IMAGE GENERATION AND RETRIEVAL RULES:
        
        Your responses should be visually engaging whenever images add value.
        
        1. IMAGE RELEVANCE
        - Use images that directly match the destination, hotel, attraction, activity or experience being discussed.
        - Never use unrelated stock photographs.
        - Never associate an image with a location it does not depict.
        - Prefer authentic destination photography over generic illustrations.
        
        2. IMAGE SELECTION
        - Destination overview: Use one beautiful landscape photograph.
        - Hotels and resorts: Use actual property photographs.
        - Attractions: Use photographs of the specific attraction.
        - Activities: Use photographs showing the actual activity.
        - Food: Use relevant local cuisine photographs.
        - Nature and seasonal travel: Match the actual season and weather.
        
        3. IMAGE QUALITY
        - Prefer high-resolution photographs.
        - Prefer landscape images for destination banners.
        - Prefer 4:3 images for attraction and hotel cards.
        - Avoid blurry, watermarked, distorted or duplicate images.
        - Maintain consistent image quality throughout the response.
        
        4. IMAGE QUANTITY
        - Short answers: 0–2 images.
        - Destination recommendations: 2–4 images.
        - Detailed travel itineraries: 4–8 images.
        - Hotel comparisons: One or more relevant images per verified property.
        - Do not overload the response with unnecessary photographs.
        
        5. IMAGE ACCURACY
        - Never invent image URLs.
        - Never fabricate image sources.
        - Never claim an image is authentic or verified without checking it.
        - Never use a photograph of one hotel to represent another hotel.
        - If a relevant image cannot be found, omit it.
        
        6. IMAGE PRESENTATION
        - Display images close to the relevant section.
        - Give every image a short descriptive caption.
        - Identify the destination or property represented.
        - Use attractive image cards or galleries when supported by the website.
        - Do not display raw image URLs as ordinary text.
        
        7. IMAGE SOURCES
        - Prefer official tourism websites, official hotel websites and reliable image providers.
        - Follow image licensing and attribution requirements.
        - Validate image URLs before displaying them.
        - Use the actual image retrieval system rather than expecting the language model to create photographs.
        
        8. IMPORTANT TECHNICAL LIMITATION
        You cannot independently retrieve or generate images through text instructions alone.
        If no image retrieval or generation tool is connected, provide the text response without pretending images have been retrieved.
        
        
        PROMPT;
        
        $payload = json_encode([
            'model' => 'openai/gpt-oss-120b',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message]
            ],
            'temperature' => 0.5,
            'max_completion_tokens' => 3000
        ]);
        
        if ($payload === false) {
            throw new RuntimeException(
                'Could not prepare Groq request: ' . json_last_error_msg()
            );
        }
        
        if ($payload === false) {
            throw new RuntimeException('Could not prepare Groq request: ' . json_last_error_msg());
        }
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        $httpCode = 0;
        $body = false;
        $transportError = '';
        
        // Use cURL if enabled; otherwise use PHP HTTPS streams.
        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            $body = curl_exec($ch);
            $transportError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
                throw new RuntimeException('PHP cURL is disabled and allow_url_fopen is also disabled. Enable allow_url_fopen in the php.ini loaded by Apache, then restart Apache.');
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $payload,
                    'timeout' => 60,
                    'ignore_errors' => true
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
            ]);
            $body = @file_get_contents($apiUrl, false, $ctx);
            if ($body === false) {
                $last = error_get_last();
                $transportError = $last['message'] ?? 'HTTPS request failed.';
            }
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $line) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) $httpCode = (int)$m[1];
                }
            }
        }

        if ($body === false) throw new RuntimeException('Could not connect to Groq: ' . $transportError);
        $result = json_decode($body, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $detail = $result['error']['message'] ?? ('Groq returned HTTP ' . $httpCode . '.');
            throw new RuntimeException('Groq API error: ' . $detail);
        }

        $aiText = trim($result['choices'][0]['message']['content'] ?? '');
        if ($aiText === '') throw new RuntimeException('Groq returned an empty response. Please try again.');

        $aiHtml = htmlspecialchars($aiText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $aiHtml = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $aiHtml);
        $aiHtml = nl2br($aiHtml);

        // if (stripos($message, 'switzerland') !== false || stripos($message, 'winter') !== false || stripos($message, 'snow') !== false) {
        //     $image = "https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=800&q=80";
        // } elseif (stripos($message, 'bali') !== false || stripos($message, 'beach') !== false || stripos($message, 'monsoon') !== false) {
        //     $image = "https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=800&q=80";
        // } elseif (stripos($message, 'japan') !== false || stripos($message, 'kyoto') !== false || stripos($message, 'tokyo') !== false) {
        //     $image = "https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=800&q=80";
        // } else {
        //     $image = "https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80";
        // }

        $save = $pdo->prepare("INSERT INTO ai_chats (user_email, sender, message, image_url) VALUES (?, 'ai', ?, ?)");
        $save->execute([$userEmail, $aiHtml, $image]);

        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'success', 'message' => $aiHtml, 'image' => $image]);
        exit;
    }

    $chatHistory = $pdo->prepare("SELECT * FROM ai_chats WHERE user_email = ? ORDER BY id ASC");
    $chatHistory->execute([$userEmail]);
    $chats = $chatHistory->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('SeasonVerse user-ai.php: ' . $e->getMessage());
    if ($isAjaxChat) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    http_response_code(500);
    if (ob_get_length()) ob_clean();
    echo 'SeasonVerse could not load. Check the Apache PHP error log.';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse AI | Plan Your Journey</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FDFBF7; color: #1A1A1A; overflow-x: hidden; }

        /* GLASSMORPHISM NAVBAR */
        .user-navbar { position: sticky; top: 0; z-index: 1000; background: rgba(253, 251, 247, 0.45); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); display: flex; justify-content: space-between; align-items: center; padding: 18px 50px; border-bottom: 1px solid rgba(0, 0, 0, 0.03); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02); }
        .nav-brand { font-size: 1.3rem; font-weight: 700; font-family: serif; color: #1B2E23; letter-spacing: -0.5px; }
        .nav-links { display: flex; gap: 30px; list-style: none; font-size: 0.85rem; font-weight: 500; color: #555; }
        .nav-links a { text-decoration: none; color: inherit; transition: color 0.2s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #1B2E23; font-weight: 600; }
        .nav-right-area { display: flex; align-items: center; gap: 15px; }
        .profile-letter { width: 32px; height: 32px; border-radius: 50%; background: #1B2E23; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; cursor: pointer; }

        /* HEADER */
        .ai-header-text { text-align: center; margin: 40px 0; }
        .ai-badge { background: #FDE8C4; color: #B45309; font-size: 0.7rem; font-weight: 700; padding: 6px 14px; border-radius: 20px; display: inline-block; margin-bottom: 20px; letter-spacing: 1px; text-transform: uppercase; }
        .ai-header-text h1 { font-family: serif; font-size: 3rem; color: #1B2E23; line-height: 1.1; margin-bottom: 15px; }
        .ai-header-text p { font-size: 0.9rem; color: #666; max-width: 500px; margin: 0 auto; line-height: 1.6; }

        /* LAYOUT GRID */
        .ai-layout-grid { display: grid; grid-template-columns: 280px 1fr; gap: 40px; max-width: 1200px; margin: 0 auto 60px auto; padding: 0 40px; }

        /* SIDEBAR */
        .season-box { background: #F4EBE1; padding: 25px; border-radius: 20px; margin-bottom: 30px; }
        .season-box h4 { font-family: serif; font-size: 1.4rem; margin: 10px 0; color: #1B2E23; }
        .season-box p { font-size: 0.8rem; color: #555; line-height: 1.5; margin-bottom: 20px; }
        .btn-outline-dark { background: #1B2E23; color: #fff; padding: 10px 20px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; width: 100%; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
        .btn-outline-dark:hover { background: #d4af37; color: #000; }

        .recent-plans-box { background: #FFFFFF; padding: 20px; border-radius: 20px; border: 1px solid rgba(0,0,0,0.04); }
        .recent-plans-box h5 { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; font-weight: 700; }
        .recent-plan-item { display: flex; gap: 12px; align-items: center; margin-bottom: 15px; cursor: pointer; }
        .recent-plan-img { width: 45px; height: 45px; border-radius: 10px; background-size: cover; background-position: center; }
        .recent-plan-title { font-size: 0.85rem; font-weight: 700; color: #1A1A1A; }
        .recent-plan-meta { font-size: 0.7rem; color: #888; margin-top: 2px; }

        /* CHAT INTERFACE */
        .chat-container { background: #FBF9F6; border-radius: 24px; display: flex; flex-direction: column; height: 650px; overflow: hidden; position: relative; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .chat-header { padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.03); background: #fff; z-index: 2; }
        .ai-persona { display: flex; align-items: center; gap: 12px; }
        .ai-icon { width: 36px; height: 36px; background: #1B2E23; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
        .ai-persona h3 { font-size: 0.95rem; font-weight: 700; color: #1B2E23; }
        .ai-persona p { font-size: 0.75rem; color: #888; }
        .chat-actions i { color: #555; margin-left: 15px; cursor: pointer; font-size: 1.1rem; }

        .chat-history { flex: 1; overflow-y: auto; padding: 30px; padding-bottom: 100px; display: flex; flex-direction: column; gap: 20px; scroll-behavior: smooth; }

        .msg-ai, .msg-user { display: flex; gap: 15px; max-width: 85%; }
        .msg-ai { align-self: flex-start; }
        .msg-user { align-self: flex-end; flex-direction: row-reverse; }

        .msg-ai-icon { width: 28px; height: 28px; background: #FDE8C4; color: #B45309; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0; }
        .msg-user-icon { width: 28px; height: 28px; background: #EAE5DD; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; color: #1B2E23; }

        .msg-bubble-ai { font-size: 0.9rem; line-height: 1.5; color: #333; padding-top: 4px; }
        .msg-bubble-user { background: #1B2E23; color: #fff; padding: 16px 20px; border-radius: 16px 16px 0 16px; font-size: 0.9rem; line-height: 1.5; }

        .suggestion-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .chip { border: 1px solid #EAE5DD; background: #fff; padding: 8px 16px; border-radius: 20px; font-size: 0.75rem; color: #555; cursor: pointer; transition: all 0.2s; }
        .chip:hover { border-color: #1B2E23; color: #1B2E23; }

        .chat-image { width: 100%; max-width: 400px; height: 120px; border-radius: 12px; object-fit: cover; margin-top: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

        .chat-input-area { padding: 20px 30px; background: linear-gradient(to top, #FBF9F6 60%, rgba(251,249,246,0)); position: absolute; bottom: 0; width: 100%; }
        .chat-input-wrapper { background: #fff; display: flex; align-items: center; padding: 8px 8px 8px 20px; border-radius: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.03); }
        .chat-input-wrapper i.fa-plus { color: #888; cursor: pointer; }
        .chat-input-wrapper input { flex: 1; border: none; outline: none; padding: 10px 15px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.9rem; background: transparent; }
        .chat-input-tools { display: flex; align-items: center; gap: 12px; color: #888; }
        .btn-send { background: #1B2E23; color: #fff; width: 36px; height: 36px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s; }
        .btn-send:active { transform: scale(0.9); }

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
        
        /* Loading Animation */
        .typing-indicator { display: flex; gap: 4px; padding-top: 8px; }
        .typing-indicator span { width: 6px; height: 6px; background: #B45309; border-radius: 50%; animation: typing 1.4s infinite ease-in-out both; }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="user-navbar">
        <div class="nav-brand">SeasonVerse</div>
        <ul class="nav-links">
            <li><a href="user-home.php">Home</a></li>
            <li><a href="user-ai.php" class="active">AI Planner</a></li>
            <li><a href="user-destinations.php">Destinations</a></li>
            <li><a href="user-experiences.php">Experiences</a></li>
            <li><a href="user-wardrobe.php">Smart Wardrobe</a></li>
            <li><a href="user-travel.php">Travel & Packing</a></li>
        </ul>
        <div class="nav-right-area">
            <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($userName); ?></span>
            <div class="profile-letter" onclick="window.location.href='user-profile.php'" title="View Profile"><?php echo htmlspecialchars($userInitial); ?></div>
            <a href="/SeasonVerse2/logout.php" style="font-size: 0.75rem; color: #C53030; text-decoration: none; font-weight: 700; margin-left: 10px;">LOGOUT</a>
        </div>
    </nav>


    <!-- HEADER TITLE -->
    <div class="ai-header-text">
        <span class="ai-badge">SEASONVERSE AI</span>
        <h1>Plan a journey that feels like<br>you.</h1>
        <p>Tell me where you want to go, when you're traveling, and what kind of experience you're looking for. I'll shape the rest around you.</p>
    </div>

    <!-- MAIN LAYOUT GRID -->
    <div class="ai-layout-grid">
        
        <!-- LEFT SIDEBAR -->
        <div>
            <div class="season-box">
                <div style="font-size: 0.65rem; font-weight: 700; letter-spacing: 1px; color: #1B2E23;"><i class="fa-regular fa-snowflake"></i> THIS SEASON</div>
                <h4>The Winter<br>Solstice</h4>
                <p>Currently trending: Alpine escapes and restorative mineral springs in Hokkaido. Perfect for inward reflection.</p>
                
                <!-- Added onclick to seamlessly trigger the AI chat below -->
                <button class="btn-outline-dark" onclick="triggerExploreWinter()">Explore Winter</button>
            </div>

            <div class="recent-plans-box">
                <h5>Recent Plans</h5>
                <div class="recent-plan-item">
                    <div class="recent-plan-img" style="background-image: url('https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=150&q=80');"></div>
                    <div>
                        <div class="recent-plan-title">Bali Wellness</div>
                        <div class="recent-plan-meta">7 Days • Summer</div>
                    </div>
                </div>
                <div class="recent-plan-item">
                    <div class="recent-plan-img" style="background-image: url('https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=150&q=80');"></div>
                    <div>
                        <div class="recent-plan-title">Kyoto Serenity</div>
                        <div class="recent-plan-meta">4 Days • Spring</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT CHAT INTERFACE -->
        <div class="chat-container">
            <div class="chat-header">
                <div class="ai-persona">
                    <div class="ai-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <div>
                        <h3>SeasonVerse AI</h3>
                        <p>Your personal seasonal travel concierge</p>
                    </div>
                </div>
                <div class="chat-actions">
                    <i class="fa-solid fa-gear"></i>
                    <i class="fa-solid fa-share-nodes"></i>
                </div>
            </div>

            <!-- CHAT HISTORY -->
            <div class="chat-history" id="chatHistory">
                <?php foreach ($chats as $i => $chat): ?>
                    <?php if ($chat['sender'] === 'ai'): ?>
                        <div class="msg-ai">
                            <div class="msg-ai-icon"><i class="fa-solid fa-chevron-left"></i></div>
                            <div class="msg-bubble-ai">
                                <?php echo htmlspecialchars_decode(nl2br($chat['message'])); ?>
                                
                                <?php if ($i === 0): ?>
                                <div class="suggestion-chips">
                                    <div class="chip" onclick="fillInput(this)">Plan me a 5-day winter trip to Switzerland</div>
                                    <div class="chip" onclick="fillInput(this)">Find a peaceful monsoon getaway to Bali</div>
                                    <div class="chip" onclick="fillInput(this)">Plan a 5-day trip to Tokyo</div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($chat['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($chat['image_url']); ?>" class="chat-image" alt="Trip Visualization">
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="msg-user">
                            <div class="msg-user-icon"><?php echo htmlspecialchars($userInitial); ?></div>
                            <div class="msg-bubble-user"><?php echo htmlspecialchars($chat['message']); ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div id="typingIndicator" class="msg-ai" style="display: none;">
                    <div class="msg-ai-icon"><i class="fa-solid fa-chevron-left"></i></div>
                    <div class="msg-bubble-ai"><div class="typing-indicator"><span></span><span></span><span></span></div></div>
                </div>
            </div>

            <!-- CHAT INPUT -->
            <div class="chat-input-area">
                <form id="aiChatForm" onsubmit="handleSend(event)" class="chat-input-wrapper">
                    <i class="fa-solid fa-plus"></i>
                    <input type="text" id="userInput" placeholder="Tell SeasonVerse what you're dreaming about..." autocomplete="off">
                    <div class="chat-input-tools">
                        <i class="fa-solid fa-microphone"></i>
                        <button type="submit" class="btn-send"><i class="fa-solid fa-play"></i></button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- DARK FOOTER -->
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
    <div style="background: #1B2E23; text-align: center; font-size: 0.7rem; color: #A3B899; padding-bottom: 20px; letter-spacing: 1px;">© 2026 SEASONVERSE. ALL RIGHTS RESERVED.</div>

    <!-- JAVASCRIPT FOR REAL-TIME CHAT -->
    <script>
        const chatHistory = document.getElementById('chatHistory');
        const userInput = document.getElementById('userInput');
        const typingIndicator = document.getElementById('typingIndicator');
        const userInitial = "<?php echo $userInitial; ?>";

        // Scroll to bottom on load
        chatHistory.scrollTop = chatHistory.scrollHeight;

        function fillInput(chip) {
            userInput.value = chip.innerText;
            userInput.focus();
        }

        // Triggered by the "Explore Winter" button
        function triggerExploreWinter() {
            userInput.value = "Plan me a luxury winter getaway with snowy alpine peaks, restorative mineral springs, and cozy cabins.";
            handleSend({ preventDefault: () => {} });
        }

        async function handleSend(e) {
            e.preventDefault();

            const message = userInput.value.trim();
            if (!message) return;

            // Render the user's message safely (do not inject raw user text as HTML).
            const userRow = document.createElement('div');
            userRow.className = 'msg-user';

            const userIcon = document.createElement('div');
            userIcon.className = 'msg-user-icon';
            userIcon.textContent = userInitial;

            const userBubble = document.createElement('div');
            userBubble.className = 'msg-bubble-user';
            userBubble.textContent = message;

            userRow.appendChild(userIcon);
            userRow.appendChild(userBubble);
            typingIndicator.insertAdjacentElement('beforebegin', userRow);

            userInput.value = '';
            typingIndicator.style.display = 'flex';
            chatHistory.scrollTop = chatHistory.scrollHeight;

            const formData = new URLSearchParams();
            formData.append('action', 'send_message');
            formData.append('message', message);

            try {
                const response = await fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData.toString()
                });

                const responseText = await response.text();
                console.log('SeasonVerse server response:', response.status, responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(
                        'The PHP page returned a non-JSON response. Check the PHP error shown in the server response/console.'
                    );
                }

                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || ('Request failed with HTTP ' + response.status));
                }

                const aiRow = document.createElement('div');
                aiRow.className = 'msg-ai';

                const aiIcon = document.createElement('div');
                aiIcon.className = 'msg-ai-icon';
                aiIcon.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';

                const aiBubble = document.createElement('div');
                aiBubble.className = 'msg-bubble-ai';
                // The PHP endpoint HTML-escapes AI output before adding <strong>/<br>.
                aiBubble.innerHTML = data.message || '';

                if (data.image) {
                    const img = document.createElement('img');
                    img.src = data.image;
                    img.className = 'chat-image';
                    img.alt = 'Trip Visualization';
                    aiBubble.appendChild(img);
                }

                aiRow.appendChild(aiIcon);
                aiRow.appendChild(aiBubble);
                typingIndicator.insertAdjacentElement('beforebegin', aiRow);
                chatHistory.scrollTop = chatHistory.scrollHeight;

            } catch (error) {
                console.error('SeasonVerse AI error:', error);
                const errorRow = document.createElement('div');
                errorRow.className = 'msg-ai';

                const errorIcon = document.createElement('div');
                errorIcon.className = 'msg-ai-icon';
                errorIcon.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';

                const errorBubble = document.createElement('div');
                errorBubble.className = 'msg-bubble-ai';
                errorBubble.style.color = 'red';
                errorBubble.textContent = error.message || 'Unable to connect to SeasonVerse AI.';

                errorRow.appendChild(errorIcon);
                errorRow.appendChild(errorBubble);
                typingIndicator.insertAdjacentElement('beforebegin', errorRow);
                chatHistory.scrollTop = chatHistory.scrollHeight;

            } finally {
                typingIndicator.style.display = 'none';
            }
        }

        // Check if destination was passed from user-destinations.php via URL
        const urlParams = new URLSearchParams(window.location.search);
        const autoDest = urlParams.get('auto_dest');
        
        if (autoDest) {
            setTimeout(() => {
                userInput.value = "Plan me a luxury trip to " + autoDest;
                handleSend({ preventDefault: () => {} });
            }, 500); 
        }
    </script>
</body>
</html>