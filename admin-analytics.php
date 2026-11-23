<?php
require_once __DIR__ . '/admin-bootstrap.php';

$analyticsData = [
    'total_users' => '0',
    'travel_plans' => '0',
    'ai_sessions' => '0',
    'avg_session_time' => 'Not tracked'
];

// Prepare the last 30 days, including days with zero activity.
$dailyLabels = [];
$dailyUsers = [];
$dailySaves = [];
$dailyAiMessages = [];
$dayKeys = [];
$today = new DateTimeImmutable('today');
for ($i = 29; $i >= 0; $i--) {
    $day = $today->sub(new DateInterval('P' . $i . 'D'));
    $key = $day->format('Y-m-d');
    $dayKeys[] = $key;
    $dailyLabels[] = $day->format('M j');
    $dailyUsers[$key] = 0;
    $dailySaves[$key] = 0;
    $dailyAiMessages[$key] = 0;
}

$seasonCounts = ['Winter' => 0, 'Monsoon' => 0, 'Summer' => 0, 'Spring' => 0, 'Autumn' => 0, 'Other' => 0];
$travelStyleCounts = ['Couple' => 0, 'Solo' => 0, 'Family' => 0, 'Friends' => 0, 'Other' => 0];
$topDestinations = [];

try {
    if (sv_table_exists($pdo, 'users')) {
        $analyticsData['total_users'] = number_format((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());

        if (sv_column_exists($pdo, 'users', 'created_at')) {
            $stmt = $pdo->prepare('SELECT DATE(created_at) AS activity_day, COUNT(*) AS total FROM users WHERE created_at >= CURDATE() - INTERVAL 29 DAY AND created_at < CURDATE() + INTERVAL 1 DAY GROUP BY DATE(created_at)');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                if (isset($dailyUsers[$row['activity_day']])) $dailyUsers[$row['activity_day']] = (int)$row['total'];
            }
        }

        if (sv_column_exists($pdo, 'users', 'preferred_season')) {
            foreach ($pdo->query("SELECT COALESCE(NULLIF(TRIM(preferred_season), ''), 'Other') AS label, COUNT(*) AS total FROM users GROUP BY label")->fetchAll() as $row) {
                $label = ucfirst(strtolower(trim($row['label'])));
                if (!isset($seasonCounts[$label])) $label = 'Other';
                $seasonCounts[$label] += (int)$row['total'];
            }
        }

        if (sv_column_exists($pdo, 'users', 'travel_style')) {
            foreach ($pdo->query("SELECT COALESCE(NULLIF(TRIM(travel_style), ''), 'Other') AS label, COUNT(*) AS total FROM users GROUP BY label")->fetchAll() as $row) {
                $label = ucfirst(strtolower(trim($row['label'])));
                if (!isset($travelStyleCounts[$label])) $label = 'Other';
                $travelStyleCounts[$label] += (int)$row['total'];
            }
        }
    }

    if (sv_table_exists($pdo, 'saved_trips')) {
        $analyticsData['travel_plans'] = number_format((int)$pdo->query('SELECT COUNT(*) FROM saved_trips')->fetchColumn());
        if (sv_column_exists($pdo, 'saved_trips', 'created_at')) {
            $stmt = $pdo->prepare('SELECT DATE(created_at) AS activity_day, COUNT(*) AS total FROM saved_trips WHERE created_at >= CURDATE() - INTERVAL 29 DAY AND created_at < CURDATE() + INTERVAL 1 DAY GROUP BY DATE(created_at)');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                if (isset($dailySaves[$row['activity_day']])) $dailySaves[$row['activity_day']] = (int)$row['total'];
            }
        }
        if (sv_table_exists($pdo, 'destinations')) {
            $topDestinations = $pdo->query('SELECT d.title, COUNT(st.id) AS total FROM saved_trips st INNER JOIN destinations d ON d.id = st.destination_id GROUP BY d.id, d.title ORDER BY total DESC, d.title ASC LIMIT 5')->fetchAll();
        }
    }

    if ($aiPdo && sv_table_exists($aiPdo, 'ai_chats')) {
        $analyticsData['ai_sessions'] = number_format((int)$aiPdo->query("SELECT COUNT(*) FROM ai_chats WHERE sender = 'user'")->fetchColumn());
        if (sv_column_exists($aiPdo, 'ai_chats', 'created_at')) {
            $stmt = $aiPdo->prepare("SELECT DATE(created_at) AS activity_day, COUNT(*) AS total FROM ai_chats WHERE sender = 'user' AND created_at >= CURDATE() - INTERVAL 29 DAY AND created_at < CURDATE() + INTERVAL 1 DAY GROUP BY DATE(created_at)");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                if (isset($dailyAiMessages[$row['activity_day']])) $dailyAiMessages[$row['activity_day']] = (int)$row['total'];
            }
        }
    }
} catch (Throwable $e) {
    error_log('admin-analytics: ' . $e->getMessage());
}

$dailyUsersValues = array_values($dailyUsers);
$dailySavesValues = array_values($dailySaves);
$dailyAiValues = array_values($dailyAiMessages);
$seasonLabels = array_keys(array_filter($seasonCounts, static fn($v) => $v > 0));
$seasonValues = array_values(array_filter($seasonCounts, static fn($v) => $v > 0));
if (!$seasonLabels) { $seasonLabels = ['No signup preferences yet']; $seasonValues = [1]; }
$travelStyleTotal = array_sum($travelStyleCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Analytics</title>
    <link rel="stylesheet" href="admin_analytics.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .profile-dropdown-menu { display: none; position: absolute; right: 40px; top: 75px; background: #fff; width: 200px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); z-index: 999; overflow: hidden; }
        .profile-dropdown-menu a { display: block; padding: 12px 16px; text-decoration: none; color: #333; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }

        /* Analytics Specific Layout */
        .analytics-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }
        .analytics-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }
        .analytics-insights-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 40px;
        }
        .progress-bar-container { background: #EAE5DD; border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 6px; }
        .progress-bar-fill { background: #1A1A1A; height: 100%; border-radius: 10px; }
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
                <li><a href="admin-ai.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI</a></li>
                <div class="sidebar-section-title">Insights</div>
                <li class="active"><a href="admin-analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
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
            <div class="breadcrumb">Admin &gt; Analytics</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search analytics..." style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
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

        <!-- PAGE HEADER -->
        <div class="dash-header" style="align-items: center;">
            <div>
                <div style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.2px; color: #888; margin-bottom: 4px; font-weight: 600;">Platform Analytics</div>
                <h1 style="font-size: 2.2rem; font-weight: 700; letter-spacing: -0.5px;">Analytics</h1>
                <p style="font-size: 0.85rem; color: #666; margin-top: 3px;">Understand how travelers use SeasonVerse and track the performance of the platform.</p>
            </div>
            <div class="date-filter">
                <span>Last 30 Days</span> <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header"><span>Total Users</span> <i class="fa-solid fa-users" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo $analyticsData['total_users']; ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Live database total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Saved Destinations</span> <i class="fa-solid fa-plane" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo $analyticsData['travel_plans']; ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Live database total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>AI Planner Messages</span> <i class="fa-solid fa-wand-magic-sparkles" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo $analyticsData['ai_sessions']; ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Live database total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Avg. Session Time</span> <i class="fa-regular fa-clock" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo $analyticsData['avg_session_time']; ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Not tracked</span>
            </div>
        </div>

        <!-- PLATFORM ACTIVITY -->
        <div class="card-box" style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">Platform Activity</h3>
                    <p style="font-size: 0.8rem; color: #666; margin-top: 2px;">User Activity, Saved Destinations, and AI Sessions</p>
                </div>
                <div style="font-weight: 700; font-size: 0.85rem; color: #166534;">
                    TOTAL ACTIVITY 18.6% <i class="fa-solid fa-arrow-up"></i>
                </div>
            </div>
            <div style="height: 220px; position: relative; width: 100%;">
                <canvas id="platformActivityChart" aria-label="Platform activity over the last 30 days"></canvas>
            </div>
        </div>

        <!-- TRAVELER GROWTH & SEASON PREFERENCES -->
        <div class="analytics-grid-2">
            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 4px;">Traveler Growth</h3>
                <div style="font-size: 1.6rem; font-weight: 700; margin-bottom: 2px;"><?php echo $analyticsData['total_users']; ?></div>
                <div style="font-size: 0.75rem; color: #666; margin-bottom: 15px;">Live database total Total Travelers</div>
                <div style="height: 140px; position: relative; width: 100%;">
                    <canvas id="travelerGrowthChart" aria-label="New traveler registrations over the last 30 days"></canvas>
                </div>
            </div>

            <div class="card-box" style="text-align: center;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; text-align: left;">
                    <h3 style="font-size: 1.1rem; font-weight: 700;">Season Preferences</h3>
                </div>
                <div style="height: 145px; max-width: 190px; margin: 0 auto; position: relative;">
                    <canvas id="seasonPreferencesChart" aria-label="User preferred seasons"></canvas>
                </div>
                <div id="seasonPreferencesLegend" style="font-size: 0.8rem; text-align: left; margin-top: 12px;"></div>
            </div>
        </div>

        <!-- TRAVEL STYLE & TOP DESTINATIONS -->
        <div class="analytics-grid-2">
            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 20px;">Travel Style</h3>
                <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($travelStyleCounts as $style => $count): if ($count <= 0) continue; $percent = $travelStyleTotal > 0 ? (int)round(($count / $travelStyleTotal) * 100) : 0; ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;"><span><?php echo htmlspecialchars($style); ?></span><span style="font-weight: 600;"><?php echo $percent; ?>%</span></div>
                        <div class="progress-bar-container"><div class="progress-bar-fill" style="width: <?php echo $percent; ?>%;"></div></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($travelStyleTotal === 0): ?><div style="color:#888;">No travel preferences have been registered yet.</div><?php endif; ?>
                </div>
            </div>

            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 20px;">Top Destinations</h3>
                <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 16px;">
                    <?php if (!empty($topDestinations)): foreach ($topDestinations as $index => $destination): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F5F2EC; padding-bottom: 10px;">
                        <div><strong style="color: #888; margin-right: 10px;"><?php echo str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></strong> <?php echo htmlspecialchars($destination['title']); ?></div>
                        <div style="text-align: right;"><span style="font-weight: 600;"><?php echo number_format((int)$destination['total']); ?> saves</span></div>
                    </div>
                    <?php endforeach; else: ?><div style="color:#888;">No saved destination data available yet.</div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- CONTENT PERFORMANCE, AI PERFORMANCE, USER ENGAGEMENT -->
        <div class="analytics-grid-3">
            <div class="card-box">
                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 15px; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; color: #888;">Content Performance</h3>
                <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; justify-content: space-between;"><span>Destinations</span><strong style="font-weight: 600;">94%</strong></div>
                    <div style="display: flex; justify-content: space-between;"><span>Experiences</span><strong style="font-weight: 600;">88%</strong></div>
                    <div style="display: flex; justify-content: space-between;"><span>Wardrobe</span><strong style="font-weight: 600;">91%</strong></div>
                </div>
            </div>

            <div class="card-box">
                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 15px; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; color: #888;">AI Performance</h3>
                <div style="display: flex; justify-content: space-around; text-align: center; margin-top: 10px;">
                    <div>
                        <div style="font-size: 0.7rem; color: #666; margin-bottom: 2px;">Completion</div>
                        <div style="font-size: 1.4rem; font-weight: 700;">73%</div>
                    </div>
                    <div style="border-left: 1px solid #eee; padding-left: 20px;">
                        <div style="font-size: 0.7rem; color: #666; margin-bottom: 2px;">Rating</div>
                        <div style="font-size: 1.4rem; font-weight: 700;">4.8/5</div>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 15px; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; color: #888;">User Engagement</h3>
                <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; justify-content: space-between;"><span>DAU</span><strong style="font-weight: 600;">4,286</strong></div>
                    <div style="display: flex; justify-content: space-between;"><span>WAU</span><strong style="font-weight: 600;">7,842</strong></div>
                    <div style="display: flex; justify-content: space-between;"><span>Retention</span><strong style="font-weight: 600;">68%</strong></div>
                </div>
            </div>
        </div>

        <!-- INSIGHTS FOOTER BANNERS -->
        <div class="analytics-insights-grid">
            <div class="card-box" style="font-size: 0.8rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-snowflake" style="color: #7A6C58; margin-top: 2px;"></i>
                <div>Winter travel is currently the most popular season, accounting for 42% of all plans.</div>
            </div>
            <div class="card-box" style="font-size: 0.8rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-heart" style="color: #C53030; margin-top: 2px;"></i>
                <div>Couple-based travel drives show the highest engagement rate across all demographics.</div>
            </div>
            <div class="card-box" style="font-size: 0.8rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-wand-magic-sparkles" style="color: #7A6C58; margin-top: 2px;"></i>
                <div>AI Planner usage has grown by 18.4% this month, driven by new wardrobe features.</div>
            </div>
        </div>
    </main>

    <!-- ADD SEASON MODAL (Sidebar Trigger) -->
    <div id="newSeasonModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 18px;">Add New Season</h2>
            <form action="admin-dashboard.php" method="POST">
                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Season Name</label>
                    <input type="text" name="season_name" class="form-control" placeholder="e.g. Autumn Equinox 2026" required>
                </div>
                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Target Month / Window</label>
                    <input type="text" name="season_month" class="form-control" placeholder="e.g. October 2026" required>
                </div>
                <div style="margin-bottom: 20px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Description</label>
                    <textarea name="season_desc" class="form-control" placeholder="Brief summary..."></textarea>
                </div>
                <button type="submit" name="add_season" style="width: 100%; background: #1B2E23; color: #fff; border: none; padding: 13px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 0.88rem;">
                    Save Season to Database
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('newSeasonModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('newSeasonModal').style.display = 'none'; }
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        window.onclick = function(event) {
            if (!event.target.closest('.admin-profile') && !event.target.closest('#profileDropdown')) {
                document.getElementById('profileDropdown').style.display = 'none';
            }
        }

        // Render charts from values queried by PHP from the SeasonVerse databases.
        const analyticsLabels = <?php echo json_encode($dailyLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const userSeries = <?php echo json_encode($dailyUsersValues); ?>;
        const savedSeries = <?php echo json_encode($dailySavesValues); ?>;
        const aiSeries = <?php echo json_encode($dailyAiValues); ?>;
        const seasonLabels = <?php echo json_encode($seasonLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const seasonValues = <?php echo json_encode($seasonValues); ?>;

        if (typeof Chart !== 'undefined') {
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 7, padding: 16 } } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#F0ECE6' } }
                }
            };

            const activityCanvas = document.getElementById('platformActivityChart');
            if (activityCanvas) new Chart(activityCanvas, {
                type: 'line',
                data: { labels: analyticsLabels, datasets: [
                    { label: 'New users', data: userSeries, borderColor: '#1B2E23', backgroundColor: 'rgba(27,46,35,0.08)', fill: true, tension: 0.35, borderWidth: 2, pointRadius: 2 },
                    { label: 'Saved destinations', data: savedSeries, borderColor: '#C5A880', backgroundColor: 'rgba(197,168,128,0.08)', fill: false, tension: 0.35, borderWidth: 2, pointRadius: 2 },
                    { label: 'AI messages', data: aiSeries, borderColor: '#8A9B85', backgroundColor: 'rgba(138,155,133,0.08)', fill: false, tension: 0.35, borderWidth: 2, pointRadius: 2 }
                ] },
                options: commonOptions
            });

            const growthCanvas = document.getElementById('travelerGrowthChart');
            if (growthCanvas) new Chart(growthCanvas, {
                type: 'line',
                data: { labels: analyticsLabels, datasets: [{ label: 'New registrations', data: userSeries, borderColor: '#1B2E23', backgroundColor: 'rgba(27,46,35,0.15)', fill: true, tension: 0.35, borderWidth: 2, pointRadius: 1 }] },
                options: { ...commonOptions, plugins: { legend: { display: false } } }
            });

            const seasonCanvas = document.getElementById('seasonPreferencesChart');
            if (seasonCanvas) new Chart(seasonCanvas, {
                type: 'doughnut',
                data: { labels: seasonLabels, datasets: [{ data: seasonValues, backgroundColor: ['#1B2E23', '#C5A880', '#D4D0C7', '#8A9B85', '#A7B6C2', '#E8DED0'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { enabled: true } } }
            });

            const seasonTotal = seasonValues.reduce((sum, value) => sum + Number(value), 0);
            const seasonLegend = document.getElementById('seasonPreferencesLegend');
            if (seasonLegend) {
                seasonLegend.innerHTML = seasonLabels.map((label, index) => {
                    const percent = seasonTotal ? Math.round((Number(seasonValues[index]) / seasonTotal) * 100) : 0;
                    return `<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>${escapeHtml(label)}</span><strong>${percent}%</strong></div>`;
                }).join('');
            }
        } else {
            ['platformActivityChart', 'travelerGrowthChart', 'seasonPreferencesChart'].forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas && canvas.parentElement) canvas.parentElement.innerHTML = '<div style="padding:20px;text-align:center;color:#888;font-size:0.8rem;">Chart library could not load. Check your internet connection and refresh.</div>';
            });
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character]));
        }
    </script>
</body>
</html>