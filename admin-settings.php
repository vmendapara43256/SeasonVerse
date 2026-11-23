<?php
require_once __DIR__ . '/admin-bootstrap.php';
$successMsg='';
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
function sv_setting(PDO $pdo,string $key,string $default=''): string { $q=$pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key=?'); $q->execute([$key]); $v=$q->fetchColumn(); return $v===false?$default:(string)$v; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reset_settings'])) { $pdo->exec('DELETE FROM admin_settings'); $successMsg='Platform settings restored to defaults.'; }
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])){
 $values=['platform_name'=>trim($_POST['platform_name']??''),'platform_desc'=>trim($_POST['platform_desc']??''),'default_language'=>trim($_POST['default_language']??'English'),'default_currency'=>trim($_POST['default_currency']??'INR'),'default_timezone'=>trim($_POST['default_timezone']??'Asia/Kolkata'),'allow_registrations'=>isset($_POST['allow_registrations'])?'1':'0'];
 if($values['platform_name']!=='') { $q=$pdo->prepare('INSERT INTO admin_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); foreach($values as $k=>$v){$q->execute([$k,$v]);} $successMsg='Platform settings saved successfully.'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | System Settings</title>
    <link rel="stylesheet" href="admin_settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-dropdown-menu { display: none; position: absolute; right: 40px; top: 75px; background: #fff; width: 200px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); z-index: 999; overflow: hidden; }
        .profile-dropdown-menu a { display: block; padding: 12px 16px; text-decoration: none; color: #333; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }

        /* Settings Specific Layout */
        .settings-container {
            max-width: 800px;
        }
        .settings-section-box {
            background: #FFFFFF;
            padding: 30px;
            border-radius: 18px;
            border: 1px solid rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }
        .settings-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #F5F2EC;
            font-size: 0.85rem;
        }
        .settings-row:last-child {
            border-bottom: none;
        }
        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;
        }
        input:checked + .slider { background-color: #1A1A1A; }
        input:checked + .slider:before { transform: translateX(20px); }
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
                <li><a href="admin-analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
                <li><a href="admin-reviews.php"><i class="fa-solid fa-star"></i> Reviews</a></li>
                <div class="sidebar-section-title">System</div>
                <li class="active"><a href="admin-settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
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
            <div class="breadcrumb">Admin &gt; Settings</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search settings..." style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
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
        <div class="dash-header" style="align-items: center; margin-bottom: 25px;">
            <div>
                <div style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.2px; color: #888; margin-bottom: 4px; font-weight: 600;">System Settings</div>
                <h1 style="font-size: 2.2rem; font-weight: 700; letter-spacing: -0.5px;">Settings</h1>
                <p style="font-size: 0.85rem; color: #666; margin-top: 3px;">Manage your SeasonVerse platform preferences, security, notifications, and system configuration[cite: 12].</p>
            </div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- GENERAL SETTINGS FORM -->
            <div class="settings-section-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 20px;">General Settings</h3>
                <form action="admin-settings.php" method="POST">
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Platform Name</label>
                        <input type="text" name="platform_name" class="form-control" value="<?php echo htmlspecialchars(sv_setting($pdo, 'platform_name', 'SeasonVerse')); ?>" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Platform Description</label>
                        <input type="text" name="platform_desc" class="form-control" value="<?php echo htmlspecialchars(sv_setting($pdo, 'platform_desc', 'Seasonal travel planning and personalized experiences.')); ?>">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Default Language</label>
                            <select class="form-control" name="default_language"><option value="English" <?php echo sv_setting($pdo, 'default_language', 'English')==='English'?'selected':''; ?>>English</option></select>
                        </div>
                        <div>
                            <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Default Currency</label>
                            <select class="form-control" name="default_currency"><option value="INR" <?php echo sv_setting($pdo, 'default_currency', 'INR')==='INR'?'selected':''; ?>>INR — Indian Rupee</option></select>
                        </div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Default Time Zone</label>
                        <select class="form-control" name="default_timezone"><option value="Asia/Kolkata" <?php echo sv_setting($pdo, 'default_timezone', 'Asia/Kolkata')==='Asia/Kolkata'?'selected':''; ?>>India Standard Time (IST)</option></select>
                    </div>
                    <div class="settings-row" style="margin-bottom: 20px;">
                        <div>
                            <strong style="font-size: 0.9rem;">Allow New Registrations</strong>
                            <div style="font-size: 0.75rem; color: #888;">Enable or disable public sign-ups.</div>
                        </div>
                        <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                    </div>
                    <button type="submit" name="save_settings" style="background: #1A1A1A; color: #fff; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 0.88rem;">
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- NOTIFICATIONS -->
            <div class="settings-section-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;">Notifications</h3>
                <div class="settings-row">
                    <div>
                        <strong style="font-size: 0.9rem;">Admin Email Notifications</strong>
                        <div style="font-size: 0.75rem; color: #888;">Receive daily summaries.</div>
                    </div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div class="settings-row">
                    <div>
                        <strong style="font-size: 0.9rem;">New User Notifications</strong>
                        <div style="font-size: 0.75rem; color: #888;">Alerts for new sign-ups.</div>
                    </div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div class="settings-row">
                    <div>
                        <strong style="font-size: 0.9rem;">Review Notifications</strong>
                        <div style="font-size: 0.75rem; color: #888;">Alerts when new reviews are posted.</div>
                    </div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div class="settings-row">
                    <div>
                        <strong style="font-size: 0.9rem;">AI System Alerts</strong>
                        <div style="font-size: 0.75rem; color: #888;">Critical system notifications.</div>
                    </div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
            </div>

            <!-- AI & RECOMMENDATIONS -->
            <div class="settings-section-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;">AI & Recommendations</h3>
                <div class="settings-row">
                    <div><strong>AI Travel Planner</strong></div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div class="settings-row">
                    <div><strong>Seasonal Recommendations</strong></div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div class="settings-row">
                    <div><strong>Smart Wardrobe</strong></div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div class="settings-row">
                    <div><strong>Personalized Food</strong></div>
                    <label class="switch"><input type="checkbox" name="allow_registrations" value="1" <?php echo sv_setting($pdo, 'allow_registrations', '1')==='1'?'checked':''; ?>><span class="slider"></span></label>
                </div>
                <div style="margin-top: 15px; font-size: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="color: #666;">AI Recommendation Confidence</span>
                        <strong>80%</strong>
                    </div>
                    <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 80%;"></div></div>
                </div>
                <div style="margin-top: 20px;">
                    <a href="admin-ai.php" style="color: #1A1A1A; font-weight: 600; font-size: 0.85rem; text-decoration: none;">Manage AI Rules &rarr;</a>
                </div>
            </div>

            <!-- RESET PLATFORM PREFERENCES -->
            <div class="settings-section-box" style="border: 1px solid #FEB2B2; background: #FFF5F5;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #C53030; margin-bottom: 5px;">Reset Platform Preferences</h3>
                <p style="font-size: 0.8rem; color: #718096; margin-bottom: 15px;">Restore default platform settings. This cannot be undone.</p>
                <form action="admin-settings.php" method="POST" onsubmit="return confirm('Are you sure you want to reset all settings?');">
                    <button type="submit" name="reset_settings" style="background: #C53030; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 0.82rem;">Reset Settings</button>
                </form>
            </div>
        </div>
    </main>

    <!-- ADD SEASON MODAL -->
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
    </script>
</body>
</html>