<?php
require_once __DIR__ . '/admin-bootstrap.php';
$successMsg=''; $stats=['total_users'=>0,'ai_sessions'=>0,'active_travel_plans'=>0,'published_content'=>0];
$contentList=[]; $activityList=[]; $seasonsList=[]; $notifications=[]; $unreadCount=0;
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS seasons (id INT AUTO_INCREMENT PRIMARY KEY, season_name VARCHAR(100) NOT NULL, season_month VARCHAR(100) NOT NULL, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(150) NOT NULL,message VARCHAR(255) NOT NULL,is_read TINYINT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    sv_add_column_if_missing($pdo, 'admin_notifications', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_season'])) {
        $sn=trim($_POST['season_name']??''); $sm=trim($_POST['season_month']??''); $sd=trim($_POST['season_desc']??'');
        if($sn!=='' && $sm!==''){ $q=$pdo->prepare('INSERT INTO seasons(season_name,season_month,description) VALUES(?,?,?)'); $q->execute([$sn,$sm,$sd]); $successMsg='New season saved successfully.'; }
    }
    if(isset($_GET['mark_read'])) { $q=$pdo->prepare('UPDATE admin_notifications SET is_read=1 WHERE id=?'); $q->execute([(int)$_GET['mark_read']]); header('Location: admin-dashboard.php'); exit; }
    $stats['total_users']=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if($aiPdo && sv_table_exists($aiPdo,'ai_chats')) $stats['ai_sessions']=(int)$aiPdo->query("SELECT COUNT(*) FROM ai_chats WHERE sender='user'")->fetchColumn();
    if(sv_table_exists($pdo,'saved_trips')) $stats['active_travel_plans']=(int)$pdo->query('SELECT COUNT(*) FROM saved_trips')->fetchColumn();
    if(sv_table_exists($pdo,'content_management')) $stats['published_content']=(int)$pdo->query("SELECT COUNT(*) FROM content_management WHERE status='Published'")->fetchColumn();
    if(sv_table_exists($pdo,'content_management')) $contentList=$pdo->query("SELECT category AS category_name, COUNT(*) AS item_count, 'fa-regular fa-file-lines' AS icon_class FROM content_management GROUP BY category ORDER BY category")->fetchAll();
    if(sv_table_exists($pdo,'recent_activity')) $activityList=$pdo->query('SELECT icon_class,description,time_ago FROM recent_activity ORDER BY id DESC LIMIT 10')->fetchAll();
    $seasonsList=$pdo->query('SELECT * FROM seasons ORDER BY id DESC')->fetchAll();
    $notifications=$pdo->query("SELECT id,title,message,is_read,DATE_FORMAT(created_at,'%d %b %Y %H:%i') AS time_ago FROM admin_notifications ORDER BY id DESC LIMIT 10")->fetchAll();
    $unreadCount=(int)$pdo->query('SELECT COUNT(*) FROM admin_notifications WHERE is_read=0')->fetchColumn();
} catch(Throwable $e){ error_log('admin-dashboard: '.$e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include Chart.js for real visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .profile-dropdown-menu, .notification-dropdown-menu {
            display: none;
            position: absolute;
            right: 40px;
            top: 75px;
            background: #fff;
            width: 280px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            border: 1px solid rgba(0,0,0,0.06);
            z-index: 999;
            overflow: hidden;
        }
        .notification-dropdown-menu { right: 80px; width: 320px; }
        .dropdown-header { padding: 12px 16px; font-weight: 700; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; background: #faf8f5; display: flex; justify-content: space-between; align-items: center; }
        .notif-item { padding: 12px 16px; border-bottom: 1px solid #f5f2ec; cursor: pointer; transition: background 0.2s; font-size: 0.8rem; }
        .notif-item:hover { background: #f8f5f0; }
        .notif-item.unread { background: #f0fdf4; font-weight: 600; }
        
        .widget-dropdown-menu {
            display: none;
            position: absolute;
            right: 15px;
            top: 35px;
            background: #fff;
            width: 160px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: 1px solid #eee;
            z-index: 100;
        }
        .widget-dropdown-menu a { display: block; padding: 10px 14px; text-decoration: none; color: #333; font-size: 0.78rem; border-bottom: 1px solid #f9f9f9; }
        .widget-dropdown-menu a:hover { background: #f5f5f5; }

        .profile-dropdown-menu a {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: #333;
            font-size: 0.85rem;
            border-bottom: 1px solid #f5f2ec;
        }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }
        .alert-success {
            background: #F0FDF4; color: #166534; padding: 12px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #DCFCE7; font-size: 0.88rem;
        }
        .searchable-item.hidden-search { display: none !important; }
        .bell-container { position: relative; cursor: pointer; }
        .badge-counter { position: absolute; top: -5px; right: -8px; background: #C53030; color: #fff; font-size: 0.6rem; padding: 2px 5px; border-radius: 50%; font-weight: 700; }
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
                <li class="active"><a href="admin-dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                
                <div class="sidebar-section-title">Manage</div>
                <li><a href="admin-users.php"><i class="fa-solid fa-users"></i> Users</a></li>
                <li><a href="admin-content.php"><i class="fa-solid fa-file-lines"></i> Content</a></li>
                <li><a href="admin-ai.php"><i class="fa-solid fa-wand-magic-sparkles"></i> AI</a></li>

                <div class="sidebar-section-title">Insights</div>
                <li><a href="admin-analystic.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
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
            <div class="breadcrumb">Admin &gt; Dashboard</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="liveSearch" placeholder="Search dashboard..." onkeyup="filterDashboard()" style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
                </div>
                
                <!-- REAL-TIME NOTIFICATIONS BELL -->
                <div class="bell-container" onclick="toggleNotificationDropdown()">
                    <i class="fa-regular fa-bell" style="font-size: 1.1rem; color: #666;"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span id="bellBadge" class="badge-counter"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-profile" onclick="toggleProfileDropdown()" style="cursor: pointer;">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80" alt="Admin">
                    <span>Administrator <br><span style="font-weight: 400; font-size: 0.72rem; color: #888;">Super Admin</span></span>
                </div>
            </div>
        </div>

        <!-- NOTIFICATION DROPDOWN MENU -->
        <div id="notificationDropdown" class="notification-dropdown-menu">
            <div class="dropdown-header">
                <span>Notifications</span>
                <span style="font-size: 0.72rem; color: #888; font-weight: 400;">Real-time alerts</span>
            </div>
            <div id="notifListContainer">
                <?php if (empty($notifications)): ?>
                    <div style="padding: 15px; text-align: center; color: #888; font-size: 0.8rem;">No notifications.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notif-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>" id="notif-<?php echo $notif['id']; ?>" onclick="markAsRead(<?php echo $notif['id']; ?>)">
                            <div style="font-weight: 600; color: #1A1A1A;"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div style="color: #666; margin-top: 2px;"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div style="font-size: 0.68rem; color: #999; margin-top: 4px;"><?php echo htmlspecialchars($notif['time_ago']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="profileDropdown" class="profile-dropdown-menu">
            <a href="#"><i class="fa-solid fa-user-gear"></i> Profile Settings</a>
            <a href="#"><i class="fa-solid fa-shield-halved"></i> Security Logs</a>
            <a href="admin-login.php?logout=1" style="color: #C53030;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>

        <div class="dash-header">
            <div>
                <h1>Good Afternoon, Administrator.</h1>
                <p>Here's what's happening across SeasonVerse today.</p>
            </div>
            <div class="date-filter">
                <span>Last 30 Days</span>
                <span>|</span>
                <span>August 2026</span>
            </div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header"><span>Total Users</span> <i class="fa-solid fa-users" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <span class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> Live database total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>AI Planner Messages</span> <i class="fa-solid fa-wand-magic-sparkles" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo number_format($stats['ai_sessions']); ?></div>
                <span class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> Live database total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Saved Destinations</span> <i class="fa-solid fa-plane" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo number_format($stats['active_travel_plans']); ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Live database total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Published Content</span> <i class="fa-regular fa-file-lines" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo number_format($stats['published_content']); ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> +8.7%</span>
            </div>
        </div>

        <!-- ANALYTICS WITH REAL CHART.JS -->
        <div class="analytics-grid">
            <div class="card-box" style="position: relative;">
                <div class="card-box-header">
                    <span>Platform Activity</span> 
                    <div style="position: relative; display: inline-block;">
                        <i class="fa-solid fa-ellipsis" style="color: #888; cursor: pointer;" onclick="toggleWidgetMenu('activityMenu')"></i>
                        <div id="activityMenu" class="widget-dropdown-menu">
                            <a href="#" onclick="refreshChart()"><i class="fa-solid fa-rotate"></i> Refresh Chart</a>
                            <a href="admin-dashboard.php?export=csv"><i class="fa-solid fa-download"></i> Export Data</a>
                        </div>
                    </div>
                </div>
                <!-- Real Responsive Canvas Chart -->
                <div style="height: 220px; width: 100%; position: relative; margin-top: 10px;">
                    <canvas id="platformActivityChart"></canvas>
                </div>
                <div style="display: flex; gap: 20px; font-size: 0.78rem; color: #666; margin-top: 15px;">
                    <span><i class="fa-solid fa-circle" style="color: #1A1A1A; font-size: 0.6rem;"></i> Users</span>
                    <span><i class="fa-solid fa-circle" style="color: #C5A880; font-size: 0.6rem;"></i> AI Planner</span>
                    <span><i class="fa-solid fa-circle" style="color: #D4D0C7; font-size: 0.6rem;"></i> Travel Plans</span>
                </div>
            </div>

            <div class="card-box" style="position: relative;">
                <div class="card-box-header">
                    <span>Season Preferences</span> 
                    <div style="position: relative; display: inline-block;">
                        <i class="fa-solid fa-ellipsis" style="color: #888; cursor: pointer;" onclick="toggleWidgetMenu('seasonMenu')"></i>
                        <div id="seasonMenu" class="widget-dropdown-menu">
                            <a href="#" onclick="location.reload()"><i class="fa-solid fa-calculator"></i> Recalculate</a>
                            <a href="#" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Widget</a>
                        </div>
                    </div>
                </div>
                <div style="text-align: center; padding: 5px 0;">
                    <div style="width: 110px; height: 110px; border: 10px solid #1B2E23; border-radius: 50%; margin: 0 auto; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-size: 0.85rem; font-weight: 700;">Top</span>
                        <span style="font-size: 0.65rem; color: #888;">Choices</span>
                    </div>
                </div>
                <div style="font-size: 0.8rem; margin-top: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span><i class="fa-solid fa-circle" style="color: #1B2E23; font-size: 0.55rem;"></i> Winter</span>
                        <span style="font-weight: 600;">42%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span><i class="fa-solid fa-circle" style="color: #C5A880; font-size: 0.55rem;"></i> Monsoon</span>
                        <span style="font-weight: 600;">34%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fa-solid fa-circle" style="color: #E2DBD0; font-size: 0.55rem;"></i> Summer</span>
                        <span style="font-weight: 600;">24%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTENT & ACTIVITY -->
        <div class="bottom-data-grid">
            <div class="card-box">
                <div class="card-box-header"><span>Content Overview</span></div>
                <div>
                    <?php foreach ($contentList as $row): ?>
                    <div class="content-row searchable-item">
                        <span><i class="<?php echo htmlspecialchars($row['icon_class']); ?>" style="width: 20px; color: #888;"></i> <?php echo htmlspecialchars($row['category_name']); ?></span>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($row['item_count']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 18px; border-top: 1px solid #eee; padding-top: 12px;">
                    <div style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px;">Custom Added Seasons</div>
                    <?php if (empty($seasonsList)): ?>
                        <div style="font-size: 0.8rem; color: #888;">No custom seasons added yet.</div>
                    <?php else: ?>
                        <?php foreach ($seasonsList as $season): ?>
                            <div class="content-row searchable-item" style="background: #faf7f2; padding: 8px 12px; border-radius: 8px; margin-bottom: 5px;">
                                <span><i class="fa-solid fa-seedling" style="color: #1B2E23;"></i> <?php echo htmlspecialchars($season['season_name']); ?> (<?php echo htmlspecialchars($season['season_month']); ?>)</span>
                                <span style="font-size: 0.72rem; color: #666;"><?php echo htmlspecialchars($season['description']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-box">
                <div class="card-box-header"><span>Recent Activity</span></div>
                <div>
                    <?php foreach ($activityList as $act): ?>
                    <div class="activity-item searchable-item">
                        <div class="activity-icon"><i class="<?php echo htmlspecialchars($act['icon_class']); ?>"></i></div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.82rem; font-weight: 600;"><?php echo htmlspecialchars($act['description']); ?></div>
                            <div style="font-size: 0.7rem; color: #888; margin-top: 2px;"><?php echo htmlspecialchars($act['time_ago']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- MODAL -->
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
        // Render Real Interactive Chart using Chart.js
        const ctx = document.getElementById('platformActivityChart').getContext('2d');
        let activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                datasets: [
                    {
                        label: 'Users',
                        data: [1200, 1900, 2400, 2800, 3200],
                        borderColor: '#1A1A1A',
                        backgroundColor: 'rgba(26,26,26,0.05)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'AI Planner',
                        data: [800, 1200, 1700, 2100, 2600],
                        borderColor: '#C5A880',
                        tension: 0.3
                    },
                    {
                        label: 'Travel Plans',
                        data: [500, 900, 1300, 1600, 1900],
                        borderColor: '#D4D0C7',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });

        function refreshChart() {
            activityChart.data.datasets[0].data = [1500, 2100, 2600, 3000, 3500];
            activityChart.update();
        }

        function openModal() { document.getElementById('newSeasonModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('newSeasonModal').style.display = 'none'; }
        
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            document.getElementById('notificationDropdown').style.display = 'none';
        }

        function toggleNotificationDropdown() {
            const notif = document.getElementById('notificationDropdown');
            notif.style.display = notif.style.display === 'block' ? 'none' : 'block';
            document.getElementById('profileDropdown').style.display = 'none';
        }

        function toggleWidgetMenu(menuId) {
            const menu = document.getElementById(menuId);
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        // Real-time notification mark as read via AJAX
        function markAsRead(id) {
            let item = document.getElementById('notif-' + id);
            if (item.classList.contains('unread')) {
                fetch('admin-dashboard.php?mark_read=' + id)
                    .then(response => {
                        item.classList.remove('unread');
                        let badge = document.getElementById('bellBadge');
                        if (badge) {
                            let count = parseInt(badge.innerText) - 1;
                            if (count > 0) {
                                badge.innerText = count;
                            } else {
                                badge.remove();
                            }
                        }
                    });
            }
        }

        window.onclick = function(event) {
            if (!event.target.closest('.admin-profile') && !event.target.closest('#profileDropdown')) {
                document.getElementById('profileDropdown').style.display = 'none';
            }
            if (!event.target.closest('.bell-container') && !event.target.closest('#notificationDropdown')) {
                document.getElementById('notificationDropdown').style.display = 'none';
            }
            if (!event.target.closest('.fa-ellipsis')) {
                document.querySelectorAll('.widget-dropdown-menu').forEach(m => m.style.display = 'none');
            }
        }

        function filterDashboard() {
            let input = document.getElementById('liveSearch').value.toLowerCase();
            document.querySelectorAll('.searchable-item').forEach(item => {
                if (item.textContent.toLowerCase().includes(input)) {
                    item.classList.remove('hidden-search');
                } else {
                    item.classList.add('hidden-search');
                }
            });
        }
    </script>
</body>
</html>