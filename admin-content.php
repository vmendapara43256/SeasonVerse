<?php
require_once __DIR__ . '/admin-bootstrap.php';

// Database connection parameters

$successMsg = "";
$errorMsg = "";

try {
    // 1. Connect & Auto-create DB/Table
    
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_management (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        subtitle VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        season VARCHAR(50) NOT NULL,
        author VARCHAR(100) NOT NULL,
        status VARCHAR(20) DEFAULT 'Published',
        updated_time VARCHAR(50) DEFAULT 'Just now'
    )");


    // 2. Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
        $title = trim($_POST['title']);
        $subtitle = trim($_POST['subtitle']);
        $category = trim($_POST['category']);
        $season = trim($_POST['season']);
        $author = trim($_POST['author']);
        $status = trim($_POST['status']);

        if (!empty($title) && !empty($category)) {
            $stmt = $pdo->prepare("INSERT INTO content_management (title, subtitle, category, season, author, status, updated_time) VALUES (?, ?, ?, ?, ?, ?, 'Just now')");
            $stmt->execute([$title, $subtitle, $category, $season, $author, $status]);
            $successMsg = "Content successfully published!";
        } else {
            $errorMsg = "Please fill in the required fields.";
        }
    }

    $contentList = $pdo->query("SELECT * FROM content_management ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorMsg = "Database Connection Error: " . $e->getMessage();
    $contentList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Content Management</title>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8F5F0; color: #1A1A1A; display: flex; min-height: 100vh; }

        /* Sidebar */
        .admin-sidebar { width: 250px; min-width: 250px; background: #FFFFFF; border-right: 1px solid rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; padding: 25px 15px; position: fixed; height: 100vh; left: 0; top: 0; z-index: 100; }
        .sidebar-logo-area h2 { font-size: 1.3rem; font-weight: 700; color: #1A1A1A; }
        .sidebar-logo-area p { font-size: 0.6rem; color: #888; text-transform: uppercase; letter-spacing: 1.2px; margin-top: 2px; }
        .sidebar-menu { margin-top: 20px; list-style: none; }
        .sidebar-section-title { font-size: 0.58rem; text-transform: uppercase; letter-spacing: 1.2px; color: #999; margin: 18px 0 8px 10px; font-weight: 600; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 10px; text-decoration: none; color: #555; font-size: 0.82rem; font-weight: 500; }
        .sidebar-menu li.active a, .sidebar-menu li a:hover { background: #1A1A1A; color: #fff; }
        .sidebar-bottom { border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px; }
        .sidebar-bottom a { display: flex; align-items: center; gap: 12px; padding: 8px 10px; text-decoration: none; color: #666; font-size: 0.8rem; border-radius: 8px; }

        /* Main Content */
        .admin-main { flex: 1; margin-left: 250px; padding: 25px 40px; }
        .admin-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 18px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .search-bar { background: #FFFFFF; border: 1px solid #EAE5DD; padding: 7px 15px; border-radius: 20px; display: flex; align-items: center; gap: 10px; width: 220px; font-size: 0.82rem; color: #888; }
        
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .dash-header h1 { font-size: 2.2rem; font-weight: 700; }
        .dash-header p { font-size: 0.85rem; color: #666; margin-top: 3px; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 24px; }
        .stat-card { background: #FFFFFF; padding: 22px; border-radius: 18px; border: 1px solid rgba(0,0,0,0.04); }
        .stat-header { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 10px; font-weight: 600; display: flex; justify-content: space-between; }
        .stat-value { font-size: 1.7rem; font-weight: 700; }

        .card-box { background: #FFFFFF; padding: 22px; border-radius: 18px; border: 1px solid rgba(0,0,0,0.04); }
        .content-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; }
        .content-table th { font-size: 0.65rem; text-transform: uppercase; color: #888; padding: 12px 15px; border-bottom: 1px solid #eee; }
        .content-table td { padding: 14px 15px; border-bottom: 1px solid #F5F2EC; vertical-align: middle; }

        .badge-published { color: #166534; font-weight: 600; }
        .badge-review { color: #B45309; font-weight: 600; }
        .badge-draft { color: #6B7280; font-weight: 600; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 100%; max-width: 440px; padding: 35px 30px; border-radius: 24px; position: relative; }
        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.2rem; cursor: pointer; }
        .form-control { width: 100%; padding: 12px 14px; background: #FAFAFA; border: 1px solid #EAE5DD; border-radius: 10px; font-size: 0.9rem; margin-top: 5px; outline: none; }
        .alert-success { background: #F0FDF4; color: #166534; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #FDF2F2; color: #C53030; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>

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
                <li><li class="active"><a href="admin-content.php"><i class="fa-solid fa-file-lines"></i> Content</a></li>
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
    <!-- MAIN -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div class="breadcrumb">Admin &gt; Content</div>
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="liveSearch" placeholder="Search content..." onkeyup="filterTable()" style="border:none; outline:none; background:transparent; width:100%;">
            </div>
        </div>

        <div class="dash-header">
            <div>
                <h1>Content</h1>
                <p>Manage, organize and publish seasonal travel content[cite: 10].</p>
            </div>
            <button onclick="openModal()" style="background: #1A1A1A; color: #fff; border: none; padding: 12px 24px; border-radius: 30px; font-weight: 600; cursor: pointer;">
                + ADD CONTENT
            </button>
        </div>

        <?php if (!empty($successMsg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $successMsg; ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
            <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header"><span>Total Content</span></div>
                <div class="stat-value"><?php echo count($contentList); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Published</span></div>
                <div class="stat-value">246</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Drafts</span></div>
                <div class="stat-value">32</div>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Needs Review</span></div>
                <div class="stat-value">9</div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card-box">
            <h3 style="margin-bottom: 15px; font-size: 1.1rem;">Content Library[cite: 10]</h3>
            <table class="content-table" id="contentTable">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th>Category</th>
                        <th>Season</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contentList as $c): ?>
                    <tr class="content-row">
                        <td>
                            <strong><?php echo htmlspecialchars($c['title']); ?></strong><br>
                            <span style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($c['subtitle']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($c['category']); ?></td>
                        <td><?php echo htmlspecialchars($c['season']); ?></td>
                        <td><?php echo htmlspecialchars($c['author']); ?></td>
                        <td><span class="badge-<?php echo strtolower($c['status']); ?>"><?php echo htmlspecialchars($c['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($c['updated_time']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- MODAL -->
    <div id="contentModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2 style="margin-bottom: 15px; font-size: 1.3rem;">Publish New Content</h2>
            <form action="admin-content.php" method="POST">
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem; font-weight: 700;">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem; font-weight: 700;">Subtitle / Location</label>
                    <input type="text" name="subtitle" class="form-control" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem; font-weight: 700;">Category</label>
                    <input type="text" name="category" class="form-control" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem; font-weight: 700;">Season</label>
                    <input type="text" name="season" class="form-control" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem; font-weight: 700;">Author</label>
                    <input type="text" name="author" class="form-control" required>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 0.7rem; font-weight: 700;">Status</label>
                    <select name="status" class="form-control">
                        <option value="Published">Published</option>
                        <option value="Review">Review</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
                <button type="submit" name="add_content" style="width: 100%; background: #1B2E23; color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer;">
                    Publish Content
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('contentModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('contentModal').style.display = 'none'; }

        function filterTable() {
            let query = document.getElementById('liveSearch').value.toLowerCase();
            document.querySelectorAll('.content-row').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        }
    </script>
</body>
</html>