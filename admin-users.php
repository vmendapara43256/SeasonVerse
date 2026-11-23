<?php
require_once __DIR__ . '/admin-bootstrap.php';
$successMsg = ''; $errorMsg = '';
try {
    if (!sv_table_exists($pdo, 'users')) { throw new RuntimeException('The users table was not found. Register a user first.'); }
    sv_add_column_if_missing($pdo, 'users', 'status', "VARCHAR(20) NOT NULL DEFAULT 'Active'");
    if (isset($_GET['action'], $_GET['id'])) {
        $id = max(0, (int)$_GET['id']);
        if ($_GET['action'] === 'toggle') {
            $q=$pdo->prepare("UPDATE users SET status=IF(status='Active','Inactive','Active') WHERE id=?"); $q->execute([$id]);
            $successMsg = $q->rowCount() ? 'User status updated.' : 'User not found.';
        } elseif ($_GET['action'] === 'delete') {
            $q=$pdo->prepare('DELETE FROM users WHERE id=?'); $q->execute([$id]); $successMsg = $q->rowCount() ? 'User deleted.' : 'User not found.';
        }
    }
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_user'])) {
        $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??''));
        $style=trim($_POST['travel_style']??''); $season=trim($_POST['season']??''); $newPassword=$_POST['password']??'';
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($newPassword)<8) { $errorMsg='Enter a valid name, email and password of at least 8 characters.'; }
        else {
            $check=$pdo->prepare('SELECT id FROM users WHERE email=?'); $check->execute([$email]);
            if ($check->fetch()) { $errorMsg='This email is already registered.'; }
            else {
                $ins=$pdo->prepare("INSERT INTO users (full_name,email,password,preferred_season,travel_style,status) VALUES (?,?,?,?,?,'Active')");
                $ins->execute([$name,$email,password_hash($newPassword,PASSWORD_DEFAULT),$season,$style]);
                $successMsg='User account created successfully.';
            }
        }
    }
    $limit=5; $page=max(1,(int)($_GET['p']??1));
    $totalUsers=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalPages=max(1,(int)ceil($totalUsers/$limit)); $page=min($page,$totalPages); $offset=($page-1)*$limit;
    $q=$pdo->prepare("SELECT id, full_name AS name, email, COALESCE(travel_style,'—') AS travel_style, COALESCE(preferred_season,'—') AS favorite_season, 0 AS plans_count, COALESCE(status,'Active') AS status, DATE_FORMAT(created_at,'%d %b %Y') AS joined_date FROM users ORDER BY id DESC LIMIT :lim OFFSET :off");
    $q->bindValue(':lim',$limit,PDO::PARAM_INT); $q->bindValue(':off',$offset,PDO::PARAM_INT); $q->execute(); $usersList=$q->fetchAll();
} catch (Throwable $e) { error_log('admin-users: '.$e->getMessage()); $usersList=[]; $totalUsers=0; $totalPages=1; $page=1; $errorMsg=$errorMsg ?: 'Unable to load user records. Check the users table structure.'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | User Management</title>
    <link rel="stylesheet" href="admin_users.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-dropdown-menu {
            display: none; position: absolute; right: 40px; top: 75px; background: #fff; width: 200px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); z-index: 999; overflow: hidden;
        }
        .profile-dropdown-menu a { display: block; padding: 12px 16px; text-decoration: none; color: #333; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }
        .alert-success { background: #F0FDF4; color: #166534; padding: 12px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #DCFCE7; font-size: 0.88rem; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 100%; max-width: 440px; padding: 35px 30px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); position: relative; }
        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #666; }
        .modal-box .form-control { width: 100% !important; padding: 12px 14px; background: #FAFAFA; border: 1px solid #EAE5DD; border-radius: 10px; font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; margin-top: 5px; }
        
        /* Table & Action Popup */
        .users-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        .users-table th { font-size: 0.65rem; text-transform: uppercase; color: #888; letter-spacing: 1px; padding: 12px 15px; border-bottom: 1px solid #eee; font-weight: 600; }
        .users-table td { padding: 14px 15px; border-bottom: 1px solid #F5F2EC; vertical-align: middle; }
        .user-avatar-initials { width: 35px; height: 35px; border-radius: 50%; background: #EFECE6; color: #1A1A1A; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.78rem; }
        
        .badge-active { color: #166534; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; text-decoration: none; }
        .badge-active::before { content: ''; width: 6px; height: 6px; background: #166534; border-radius: 50%; display: inline-block; }
        .badge-inactive { color: #C53030; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; text-decoration: none; }
        .badge-inactive::before { content: ''; width: 6px; height: 6px; background: #C53030; border-radius: 50%; display: inline-block; }

        /* Action Menu Popup */
        .action-menu { display: none; position: absolute; right: 30px; background: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.12); border-radius: 8px; z-index: 100; border: 1px solid #eee; overflow: hidden; }
        .action-menu a { display: block; padding: 8px 15px; font-size: 0.8rem; color: #333; text-decoration: none; }
        .action-menu a:hover { background: #f8f5f0; }
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
                <li class="active"><a href="admin-users.php"><i class="fa-solid fa-users"></i> Users</a></li>
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
            <div class="breadcrumb">Admin &gt; Users</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="liveSearch" placeholder="Search users..." onkeyup="filterUsersTable()" style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
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
                <h1 style="font-size: 2.2rem;">Users</h1>
                <p>Manage and monitor the travelers registered on SeasonVerse.</p>
            </div>
            <button onclick="openAddUserModal()" style="background: #1A1A1A; color: #fff; border: none; padding: 12px 24px; border-radius: 30px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus"></i> ADD USER
            </button>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header"><span>Total Users</span> <i class="fa-solid fa-users" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> +12.8%</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Active Users</span> <i class="fa-solid fa-user-check" style="color: #888;"></i></div>
                <div class="stat-value">9,684</div>
                <span style="font-size: 0.72rem; color: #666; font-weight: 600;">75% active</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>New Users</span> <i class="fa-solid fa-user-plus" style="color: #888;"></i></div>
                <div class="stat-value">1,248</div>
                <span style="font-size: 0.72rem; color: #666; font-weight: 600;">This month</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Suspended</span> <i class="fa-solid fa-user-slash" style="color: #888;"></i></div>
                <div class="stat-value">36</div>
                <span class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> -4.2%</span>
            </div>
        </div>

        <!-- USERS TABLE -->
        <div class="card-box" style="padding: 24px; margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700;">All Users</h3>
                    <p style="font-size: 0.8rem; color: #666; margin-top: 2px;">View and manage registered SeasonVerse travelers.</p>
                </div>
            </div>

            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Travel Style</th>
                        <th>Season</th>
                        <th>Plans</th>
                        <th>Status (Click to toggle)</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersList)): ?>
                        <tr><td colspan="7" style="text-align: center; color: #888; padding: 20px;">No users found in database.</td></tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                        <tr class="user-row">
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="user-avatar-initials">
                                        <?php 
                                            $words = explode(" ", $u['name']);
                                            echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                                        ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1A1A1A;"><?php echo htmlspecialchars($u['name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($u['travel_style']); ?></td>
                            <td><?php echo htmlspecialchars($u['favorite_season']); ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($u['plans_count']); ?></td>
                            <td>
                                <!-- Clicking status toggles Active / Inactive instantly in database -->
                                <a href="admin-users.php?action=toggle&id=<?php echo $u['id']; ?>" title="Click to toggle status">
                                    <?php if ($u['status'] === 'Active'): ?>
                                        <span class="badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td style="color: #666; font-size: 0.8rem;"><?php echo htmlspecialchars($u['joined_date']); ?></td>
                            <td style="position: relative;">
                                <i class="fa-solid fa-ellipsis-vertical" style="color: #888; cursor: pointer; padding: 5px 10px;" onclick="toggleActionMenu(<?php echo $u['id']; ?>)"></i>
                                <!-- Action Popup Menu -->
                                <div id="actionMenu-<?php echo $u['id']; ?>" class="action-menu">
                                    <a href="admin-users.php?action=toggle&id=<?php echo $u['id']; ?>"><i class="fa-solid fa-shuffle"></i> Toggle Status</a>
                                    <a href="admin-users.php?action=delete&id=<?php echo $u['id']; ?>" style="color: #C53030;" onclick="return confirm('Are you sure you want to delete this user?');"><i class="fa-solid fa-trash"></i> Delete User</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- WORKING PAGINATION -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 0.82rem; color: #666;">
                <div>Showing page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></div>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <?php if ($page > 1): ?>
                        <a href="admin-users.php?p=<?php echo ($page - 1); ?>" style="padding: 5px 10px; color: #333; text-decoration: none;">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="admin-users.php?p=<?php echo $i; ?>" style="background: <?php echo ($page == $i) ? '#1A1A1A' : 'transparent'; ?>; color: <?php echo ($page == $i) ? '#fff' : '#333'; ?>; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; text-decoration: none;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="admin-users.php?p=<?php echo ($page + 1); ?>" style="padding: 5px 10px; color: #333; text-decoration: none;">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- ADD USER MODAL -->
    <div id="addUserModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeAddUserModal()">&times;</button>
            <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 18px;">Add New Traveler</h2>
            <form action="admin-users.php" method="POST">
                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Liam Smith" required>
                </div>
                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. liam@email.com" required>
                    <input type="password" name="password" class="form-control" placeholder="Temporary password (minimum 8 characters)" minlength="8" required>
                </div>
                <div style="margin-bottom: 12px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Travel Style</label>
                    <input type="text" name="travel_style" class="form-control" placeholder="e.g. Solo, Couple, Family">
                </div>
                <div style="margin-bottom: 20px; text-align: left;">
                    <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #444;">Favorite Season</label>
                    <input type="text" name="season" class="form-control" placeholder="e.g. Winter, Monsoon, Summer">
                </div>
                <button type="submit" name="add_user" style="width: 100%; background: #1B2E23; color: #fff; border: none; padding: 13px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 0.88rem;">
                    Save User to Database
                </button>
            </form>
        </div>
    </div>

    <!-- SCRIPT FOR ACTIONS & SEARCH -->
    <script>
        function openAddUserModal() { document.getElementById('addUserModal').style.display = 'flex'; }
        function closeAddUserModal() { document.getElementById('addUserModal').style.display = 'none'; }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        function toggleActionMenu(id) {
            // Close all other open menus first
            document.querySelectorAll('.action-menu').forEach(m => {
                if (m.id !== 'actionMenu-' + id) m.style.display = 'none';
            });
            let menu = document.getElementById('actionMenu-' + id);
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        window.onclick = function(event) {
            if (!event.target.closest('.admin-profile') && !event.target.closest('#profileDropdown')) {
                document.getElementById('profileDropdown').style.display = 'none';
            }
            if (!event.target.closest('.fa-ellipsis-vertical')) {
                document.querySelectorAll('.action-menu').forEach(m => m.style.display = 'none');
            }
        }

        function filterUsersTable() {
            let input = document.getElementById('liveSearch').value.toLowerCase();
            let rows = document.querySelectorAll('.user-row');

            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
    </script>
</body>
</html>