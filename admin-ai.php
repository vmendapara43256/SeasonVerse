<?php
require_once __DIR__ . '/admin-bootstrap.php';
$successMsg='';
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$getSetting=$pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key=?');
$getSetting->execute(['active_ai_model']); $activeModel=$getSetting->fetchColumn() ?: 'Winter Solstice';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_model'])){
 $newModel=trim($_POST['active_model']??'');
 $allowed=['Winter Solstice','Monsoon Reverie','Summer Bloom'];
 if(in_array($newModel,$allowed,true)){ $save=$pdo->prepare('INSERT INTO admin_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $save->execute(['active_ai_model',$newModel]); $activeModel=$newModel; $successMsg='Active AI model selection saved.'; }
}
// AI conversations are stored in the legacy 'seasonverse' database by user-ai.php.
$aiTableAvailable = ($aiPdo instanceof PDO) && sv_table_exists($aiPdo, 'ai_chats');
$chatCount = 0;
$planCount = 0;
$dailyLabels = [];
$dailyUserMessages = [];
$dailyAiResponses = [];

if ($aiTableAvailable) {
    $chatCount = (int)$aiPdo->query("SELECT COUNT(*) FROM ai_chats WHERE sender = 'user'")->fetchColumn();
    $planCount = (int)$aiPdo->query("SELECT COUNT(*) FROM ai_chats WHERE sender = 'ai'")->fetchColumn();

    // Build a complete 30-day series, including days with zero activity.
    $dailyRows = $aiPdo->query("SELECT DATE(created_at) AS chat_date,
        SUM(CASE WHEN sender = 'user' THEN 1 ELSE 0 END) AS user_messages,
        SUM(CASE WHEN sender = 'ai' THEN 1 ELSE 0 END) AS ai_responses
        FROM ai_chats
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_ASSOC);

    $dailyMap = [];
    foreach ($dailyRows as $row) {
        $dailyMap[$row['chat_date']] = $row;
    }

    for ($day = 29; $day >= 0; $day--) {
        $date = date('Y-m-d', strtotime('-' . $day . ' days'));
        $dailyLabels[] = date('M j', strtotime($date));
        $dailyUserMessages[] = isset($dailyMap[$date]) ? (int)$dailyMap[$date]['user_messages'] : 0;
        $dailyAiResponses[] = isset($dailyMap[$date]) ? (int)$dailyMap[$date]['ai_responses'] : 0;
    }
}

$aiStats = [
    'planner_sessions' => number_format($chatCount),
    'plans_generated' => number_format($planCount),
    'accuracy' => 'Not measured',
    'rating' => 'Not collected',
    'active_model' => $activeModel,
    'rule_sets' => 3
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | AI Management</title>
    <link rel="stylesheet" href="admin_ai.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .profile-dropdown-menu { display: none; position: absolute; right: 40px; top: 75px; background: #fff; width: 200px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); z-index: 999; overflow: hidden; }
        .profile-dropdown-menu a { display: block; padding: 12px 16px; text-decoration: none; color: #333; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }
        .alert-success { background: #F0FDF4; color: #166534; padding: 12px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #DCFCE7; font-size: 0.88rem; }
        
        /* Light Theme AI Container matching Dashboard */
        .ai-light-container {
            background: #FFFFFF;
            color: #1A1A1A;
            padding: 30px;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }
        .ai-light-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-top: 20px;
        }
        .ai-stat-card-light {
            background: #FAFAFA;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #EAE5DD;
        }
        .ai-stat-header-light {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .ai-stat-value-light {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: #1A1A1A;
        }
        .ai-grid-split {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }
        .progress-bar-container {
            background: #EAE5DD;
            border-radius: 10px;
            height: 8px;
            width: 100%;
            overflow: hidden;
            margin-top: 6px;
        }
        .progress-bar-fill {
            background: #1A1A1A;
            height: 100%;
            border-radius: 10px;
        }
        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; color: #1A1A1A; width: 100%; max-width: 440px; padding: 35px 30px; border-radius: 24px; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #666; }
    </style>
</head>
<body class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <div>
            <div class="sidebar-logo-area">
                <h2>SeasonVerse</h2>
                <p>Admin Console</p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="admin-dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <div class="sidebar-section-title">Manage</div>
                <li><a href="admin-users.php"><i class="fa-solid fa-users"></i> Users</a></li>
                <li><a href="admin-content.php"><i class="fa-solid fa-file-lines"></i> Content</a></li>
                <li class="active"><a href="admin-ai.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI</a></li>
                <div class="sidebar-section-title">Insights</div>
                <li><a href="admin-analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
                <li><a href="admin-reviews.php"><i class="fa-solid fa-star"></i> Reviews</a></li>
                <div class="sidebar-section-title">System</div>
                <li><a href="admin-settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>

        <div class="sidebar-bottom">
            <div style="margin-bottom: 12px;">
                <button onclick="openModal()" style="width: 100%; background: #1A1A1A; color: #fff; border: none; padding: 10px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                    + New Season
                </button>
            </div>
            <a href="admin-supports.php"><i class="fa-regular fa-circle-question"></i> Support</a>
            <a href="admin-login.php?logout=1"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div class="breadcrumb">Admin &gt; AI Management</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search AI logs..." style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
                </div>
                <i class="fa-regular fa-bell" style="color: #666; cursor: pointer;"></i>
                <div class="admin-profile" onclick="toggleProfileDropdown()" style="cursor: pointer;">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80" alt="Admin">
                    <span>Administrator <br><span style="font-weight: 400; font-size: 0.72rem; color: #888;">Super Admin</span></span>
                </div>
            </div>
        </div>

        <div id="profileDropdown" class="profile-dropdown-menu">
            <a href="#"><i class="fa-solid fa-user-gear"></i> Profile Settings</a>
            <a href="#"><i class="fa-solid fa-shield-halved"></i> Security Logs</a>
            <a href="admin-login.php?logout=1" style="color: #C53030;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- LIGHT THEME AI HEADER & STATS -->
        <div class="ai-light-container">
            <div style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.2px; color: #888; margin-bottom: 4px; font-weight: 600;">AI Management</div>
            <h1 style="font-size: 2.2rem; font-weight: 700; margin-bottom: 6px; letter-spacing: -0.5px;">SeasonVerse AI</h1>
            <p style="font-size: 0.85rem; color: #666; max-width: 600px;">Monitor and manage the intelligent systems driving personalized luxury travel experiences.</p>

            <div class="ai-light-stats-grid">
                <div class="ai-stat-card-light">
                    <div class="ai-stat-header-light"><span>AI User Messages</span> <i class="fa-solid fa-brain" style="color: #888;"></i></div>
                    <div class="ai-stat-value-light"><?php echo $aiStats['planner_sessions']; ?></div>
                    <span style="font-size: 0.72rem; color: #166534; font-weight: 600;"><i class="fa-solid fa-arrow-up"></i> Live database total vs last month</span>
                </div>
                <div class="ai-stat-card-light">
                    <div class="ai-stat-header-light"><span>AI Responses</span> <i class="fa-solid fa-wand-magic-sparkles" style="color: #888;"></i></div>
                    <div class="ai-stat-value-light"><?php echo $aiStats['plans_generated']; ?></div>
                    <span style="font-size: 0.72rem; color: #166534; font-weight: 600;"><i class="fa-solid fa-arrow-up"></i> Live database total vs last month</span>
                </div>
                <div class="ai-stat-card-light">
                    <div class="ai-stat-header-light"><span>Recommendation Accuracy</span> <i class="fa-solid fa-bullseye" style="color: #888;"></i></div>
                    <div class="ai-stat-value-light"><?php echo $aiStats['accuracy']; ?></div>
                    <span style="font-size: 0.72rem; color: #166534; font-weight: 600;"><i class="fa-solid fa-arrow-up"></i> Not measured vs last month</span>
                </div>
                <div class="ai-stat-card-light">
                    <div class="ai-stat-header-light"><span>Average AI Rating</span> <i class="fa-regular fa-star" style="color: #888;"></i></div>
                    <div class="ai-stat-value-light"><?php echo $aiStats['rating']; ?></div>
                    <span style="font-size: 0.72rem; color: #166534; font-weight: 600;"><i class="fa-solid fa-arrow-up"></i> Not collected</span>
                </div>
            </div>
        </div>

        <!-- AI ACTIVITY & USAGE BY SEASON -->
        <div class="ai-grid-split">
            <div class="card-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700;">AI Planner Activity</h3>
                        <p style="font-size: 0.8rem; color: #666; margin-top: 2px;">Last 30 Days Overview</p>
                    </div>
                    <div style="font-size: 0.8rem; color: #666; display: flex; gap: 15px;">
                        <span><i class="fa-solid fa-circle" style="color: #1A1A1A; font-size: 0.5rem;"></i> Sessions</span>
                        <span><i class="fa-solid fa-circle" style="color: #888; font-size: 0.5rem;"></i> Plans</span>
                    </div>
                </div>
                <div style="height: 220px; background: #FAFAFA; border: 1px solid #EAE5DD; border-radius: 12px; padding: 12px; position: relative;">
                    <canvas id="aiActivityChart" aria-label="AI user messages and AI responses over the last 30 days"></canvas>
                    <?php if (!$aiTableAvailable): ?>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:20px; text-align:center; color:#888; font-size:0.82rem; background:rgba(250,250,250,0.9); border-radius:12px;">
                            AI chat database is unavailable. Check the 'seasonverse' database connection.
                        </div>
                    <?php elseif ($chatCount === 0 && $planCount === 0): ?>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:20px; text-align:center; color:#888; font-size:0.82rem; pointer-events:none;">
                            No AI chat activity recorded yet. The graph will update when users chat with SeasonVerse AI.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-box" style="text-align: center;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; text-align: left;">
                    <h3 style="font-size: 1.1rem; font-weight: 700;">AI Message Distribution</h3>
                    <i class="fa-solid fa-ellipsis" style="color: #888; cursor: pointer;"></i>
                </div>
                <div style="height: 190px; position: relative; max-width: 260px; margin: 0 auto;">
                    <canvas id="aiMessageMixChart" aria-label="Distribution of user messages and AI responses"></canvas>
                </div>
                <div style="font-size: 0.8rem; text-align: left; margin-top: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span><i class="fa-solid fa-circle" style="color: #1B2E23; font-size: 0.5rem;"></i> User Messages</span>
                        <span style="font-weight: 600;"><?php echo number_format($chatCount); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fa-solid fa-circle" style="color: #C5A880; font-size: 0.5rem;"></i> AI Responses</span>
                        <span style="font-weight: 600;"><?php echo number_format($planCount); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ENGINE ACCURACY & ACTIVE MODEL -->
        <div class="ai-grid-split" style="margin-bottom: 40px;">
            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 20px;">Recommendation Engine Accuracy</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 0.85rem;">
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: #666;">Destinations</span>
                            <span style="font-weight: 600;">94.6%</span>
                        </div>
                        <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 94.6%;"></div></div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: #666;">Experiences</span>
                            <span style="font-weight: 600;">92.8%</span>
                        </div>
                        <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 92.8%;"></div></div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; margin-top: 10px;">
                            <span style="color: #666;">Smart Wardrobe</span>
                            <span style="font-weight: 600;">95.1%</span>
                        </div>
                        <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 95.1%;"></div></div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; margin-top: 10px;">
                            <span style="color: #666;">Food & Dining</span>
                            <span style="font-weight: 600;">93.7%</span>
                        </div>
                        <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 93.7%;"></div></div>
                    </div>
                </div>
            </div>

            <div class="card-box" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="font-size: 0.65rem; text-transform: uppercase; color: #888; letter-spacing: 1px; margin-bottom: 6px; font-weight: 600;">Current Active Model</div>
                    <h3 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 15px;"><?php echo htmlspecialchars($aiStats['active_model'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; border-top: 1px solid #F5F2EC; padding-top: 12px; color: #666;">
                        <span>Active Rule Sets</span>
                        <strong style="color: #1A1A1A;"><?php echo $aiStats['rule_sets']; ?></strong>
                    </div>
                </div>
                <button onclick="openModelModal()" style="width: 100%; background: #FAFAFA; color: #1A1A1A; border: 1px solid #EAE5DD; padding: 11px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 0.85rem; margin-top: 15px;">
                    Manage Rules
                </button>
            </div>
        </div>
    </main>

    <!-- MANAGE RULES MODAL -->
    <div id="modelModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModelModal()">&times;</button>
            <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 15px;">Switch Active AI Model</h2>
            <form action="admin-ai.php" method="POST">
                <div style="margin-bottom: 15px; text-align: left;">
                    <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #444;">Select Model Version</label>
                    <select name="active_model" style="width: 100%; padding: 12px; border: 1px solid #EAE5DD; border-radius: 10px; margin-top: 5px; outline: none;">
                        <option value="Winter Solstice" <?php echo $activeModel === 'Winter Solstice' ? 'selected' : ''; ?>>Winter Solstice (v4.2)</option>
                        <option value="Monsoon Reverie" <?php echo $activeModel === 'Monsoon Reverie' ? 'selected' : ''; ?>>Monsoon Reverie (v3.8)</option>
                        <option value="Summer Bloom" <?php echo $activeModel === 'Summer Bloom' ? 'selected' : ''; ?>>Summer Bloom (v4.0)</option>
                    </select>
                </div>
                <button type="submit" name="update_model" style="width: 100%; background: #1B2E23; color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer;">
                    Apply Model Update
                </button>
            </form>
        </div>
    </div>

    <script>
        const aiChartLabels = <?php echo json_encode($dailyLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const aiUserMessageData = <?php echo json_encode($dailyUserMessages); ?>;
        const aiResponseData = <?php echo json_encode($dailyAiResponses); ?>;
        const aiUserTotal = <?php echo (int)$chatCount; ?>;
        const aiResponseTotal = <?php echo (int)$planCount; ?>;

        if (typeof Chart !== 'undefined') {
            const activityCanvas = document.getElementById('aiActivityChart');
            if (activityCanvas) {
                new Chart(activityCanvas, {
                    type: 'line',
                    data: {
                        labels: aiChartLabels,
                        datasets: [
                            {
                                label: 'User Messages',
                                data: aiUserMessageData,
                                borderColor: '#1A1A1A',
                                backgroundColor: 'rgba(26, 26, 26, 0.08)',
                                borderWidth: 2,
                                tension: 0.35,
                                fill: true,
                                pointRadius: 2,
                                pointHoverRadius: 5
                            },
                            {
                                label: 'AI Responses',
                                data: aiResponseData,
                                borderColor: '#C5A880',
                                backgroundColor: 'rgba(197, 168, 128, 0.08)',
                                borderWidth: 2,
                                tension: 0.35,
                                fill: false,
                                pointRadius: 2,
                                pointHoverRadius: 5
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: { backgroundColor: '#1B2E23', padding: 10 }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#888', maxTicksLimit: 6, maxRotation: 0 }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0, color: '#888' },
                                grid: { color: '#EAE5DD' }
                            }
                        }
                    }
                });
            }

            const mixCanvas = document.getElementById('aiMessageMixChart');
            if (mixCanvas) {
                new Chart(mixCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['User Messages', 'AI Responses'],
                        datasets: [{
                            data: [aiUserTotal, aiResponseTotal],
                            backgroundColor: ['#1B2E23', '#C5A880'],
                            borderColor: '#FFFFFF',
                            borderWidth: 3,
                            hoverOffset: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: { display: false },
                            tooltip: { backgroundColor: '#1B2E23', padding: 10 }
                        }
                    }
                });
            }
        }
    </script>

    <script>
        function openModelModal() { document.getElementById('modelModal').style.display = 'flex'; }
        function closeModelModal() { document.getElementById('modelModal').style.display = 'none'; }
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        window.onclick = function(event) {
            if (!event.target.closest('.admin-profile') && !event.target.closest('#profileDropdown')) {
                document.getElementById('profileDropdown').style.display = 'none';
            }
        }
    </script>
</body>
</html>