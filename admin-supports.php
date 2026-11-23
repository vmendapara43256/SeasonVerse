<?php
require_once __DIR__ . '/admin-bootstrap.php';
$successMsg=''; $ticketsList=[];
try {
 $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (id INT AUTO_INCREMENT PRIMARY KEY,ticket_subject VARCHAR(150) NOT NULL,priority VARCHAR(50) NOT NULL,message TEXT NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'Open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
 if(isset($_GET['update_status'],$_GET['id'])) { $allowed=['Open','In Progress','Resolved','Closed']; $st=$_GET['update_status']; if(in_array($st,$allowed,true)){ $q=$pdo->prepare('UPDATE support_tickets SET status=? WHERE id=?'); $q->execute([$st,(int)$_GET['id']]); $successMsg='Ticket status updated.'; } }
 if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_ticket'])) { $subject=trim($_POST['subject']??''); $priority=trim($_POST['priority']??'Normal'); $message=trim($_POST['message']??''); if($subject!=='' && $message!==''){ $q=$pdo->prepare('INSERT INTO support_tickets(ticket_subject,priority,message) VALUES(?,?,?)'); $q->execute([$subject,$priority,$message]); $successMsg='Support ticket submitted successfully.'; } }
 $ticketsList=$pdo->query('SELECT * FROM support_tickets ORDER BY id DESC')->fetchAll();
} catch(Throwable $e){ error_log('admin-supports: '.$e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Admin Support</title>
    <link rel="stylesheet" href="admin_supports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-dropdown-menu { display: none; position: absolute; right: 40px; top: 75px; background: #fff; width: 200px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); z-index: 999; overflow: hidden; }
        .profile-dropdown-menu a { display: block; padding: 12px 16px; text-decoration: none; color: #333; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }

        .support-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        .faq-item {
            padding: 14px 0;
            border-bottom: 1px solid #F5F2EC;
            font-size: 0.85rem;
        }
        .faq-item:last-child { border-bottom: none; }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #F5F2EC;
            font-size: 0.82rem;
        }
        .badge-open { background: #FEF3C7; color: #B45309; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 0.7rem; }
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
            <div class="breadcrumb">Admin &gt; Support</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search help topics..." style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
                </div>
                <i class="fa-regular fa-bell" style="color: #666; cursor: pointer;"></i>
                <div class="admin-profile" onclick="toggleProfileDropdown()" style="cursor: pointer;">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80" alt="Admin">
                    <span>Administrator <br><span style="font-weight: 400; font-size: 0.72rem; color: #888;">Super Admin</span></span>
                </div>
            </div>
        </div>

        <div id="profileDropdown" class="profile-dropdown-menu">
            <a href="admin-settings.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a>
            <a href="admin-supports.php"><i class="fa-solid fa-shield-halved"></i> Security Logs</a>
            <a href="admin-login.php?logout=1" style="color: #C53030;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>

        <!-- PAGE HEADER -->
        <div class="dash-header" style="align-items: center; margin-bottom: 25px;">
            <div>
                <div style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.2px; color: #888; margin-bottom: 4px; font-weight: 600;">Help & Assistance</div>
                <h1 style="font-size: 2.2rem; font-weight: 700; letter-spacing: -0.5px;">Support Center</h1>
                <p style="font-size: 0.85rem; color: #666; margin-top: 3px;">Submit technical tickets, review system documentation, and connect with the SeasonVerse dev team.</p>
            </div>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- SUPPORT GRID -->
        <div class="support-grid">
            <!-- SUBMIT TICKET FORM -->
            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;"><i class="fa-solid fa-ticket" style="margin-right: 8px;"></i> Open a Support Ticket</h3>
                <form action="admin-supports.php" method="POST">
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Subject / Issue</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g., Database synchronization error" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Priority Level</label>
                        <select name="priority" class="form-control">
                            <option value="Normal">Normal</option>
                            <option value="High">High</option>
                            <option value="Critical (Exam/Submission)">Critical (Exam / Submission)</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #666; display: block; margin-bottom: 5px;">Detailed Description</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Describe the issue you are facing..." required></textarea>
                    </div>
                    <button type="submit" name="submit_ticket" style="background: #1A1A1A; color: #fff; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 0.88rem; width: 100%;">
                        Submit Support Ticket
                    </button>
                </form>
            </div>

            <!-- QUICK CONTACT & RESOURCES -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="card-box">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 12px;"><i class="fa-solid fa-headset" style="margin-right: 8px;"></i> Direct Contacts</h3>
                    <div style="font-size: 0.82rem; color: #555; display: flex; flex-direction: column; gap: 10px;">
                        <div><strong>Technical Lead:</strong> admin@seasonverse.com</div>
                        <div><strong>Emergency Helpline:</strong> +91 98765 43210</div>
                        <div><strong>Response Time:</strong> Under 15 minutes</div>
                    </div>
                </div>

                <div class="card-box">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 12px;"><i class="fa-solid fa-book" style="margin-right: 8px;"></i> Documentation</h3>
                    <div style="font-size: 0.82rem; display: flex; flex-direction: column; gap: 8px;">
                        <a href="#" style="color: #1A1A1A; text-decoration: none;"><i class="fa-solid fa-file-pdf" style="color: #888; margin-right: 5px;"></i> Database Schema Guide</a>
                        <a href="#" style="color: #1A1A1A; text-decoration: none;"><i class="fa-solid fa-code" style="color: #888; margin-right: 5px;"></i> PHP PDO Security Docs</a>
                        <a href="#" style="color: #1A1A1A; text-decoration: none;"><i class="fa-solid fa-palette" style="color: #888; margin-right: 5px;"></i> Figma UI Specs</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- FREQUENTLY ASKED QUESTIONS & ACTIVE TICKETS -->
        <div class="support-grid">
    <div class="card-box">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;">Frequently Asked Questions</h3>
        <div class="faq-item">
            <strong>Q: How do I link new pages toemporal navigation?</strong>
            <div style="color: #666; margin-top: 3px;">A: Simply include the sidebar component across all your admin PHP files.</div>
        </div>
        <div class="faq-item">
            <strong>Q: What if MySQL connection drops during submission?</strong>
            <div style="color: #666; margin-top: 3px;">A: Our self-healing PDO blocks automatically check and recreate databases and tables dynamically.</div>
        </div>
        <div class="faq-item">
            <strong>Q: How are admin sessions secured?</strong>
            <div style="color: #666; margin-top: 3px;">A: Using `session_start()` and checking `$_SESSION['admin_logged_in'] === true` on every protected page.</div>
        </div>
    </div>
</div>

            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;">Your Recent Tickets</h3>
                <?php if (empty($ticketsList)): ?>
                    <div style="font-size: 0.82rem; color: #888;">No support tickets submitted yet.</div>
                <?php else: ?>
                    <?php foreach ($ticketsList as $t): ?>
                        <div class="ticket-row">
                            <div>
                                <strong style="color: #1A1A1A;"><?php echo htmlspecialchars($t['ticket_subject']); ?></strong>
                                <div style="font-size: 0.72rem; color: #888;"><?php echo htmlspecialchars($t['created_at']); ?></div>
                            </div>
                            <div><span class="badge-open"><?php echo htmlspecialchars($t['status']); ?></span><div style="font-size:11px;margin-top:5px"><a href="admin-supports.php?id=<?php echo (int)$t['id']; ?>&update_status=In%20Progress">In progress</a> · <a href="admin-supports.php?id=<?php echo (int)$t['id']; ?>&update_status=Resolved">Resolve</a> · <a href="admin-supports.php?id=<?php echo (int)$t['id']; ?>&update_status=Closed">Close</a></div></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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