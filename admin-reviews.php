<?php
require_once __DIR__ . '/admin-bootstrap.php';
$reviewsList=[];
try {
 if(sv_table_exists($pdo,'community_experiences')) {
   sv_add_column_if_missing($pdo,'community_experiences','status',"VARCHAR(30) NOT NULL DEFAULT 'PUBLISHED'");
   if(isset($_GET['moderate'],$_GET['id'])) { $status=$_GET['moderate']; if(in_array($status,['PUBLISHED','NEEDS REVIEW','HIDDEN'],true)){ $q=$pdo->prepare('UPDATE community_experiences SET status=? WHERE id=?'); $q->execute([$status,(int)$_GET['id']]); header('Location: admin-reviews.php'); exit; } }
   $sql="SELECT id,user_name AS reviewer_name,rating,destination AS trip_tag,story AS comment,UPPER(REPLACE(status,'_',' ')) AS status,DATE_FORMAT(created_at,'%d %b %Y') AS time_ago FROM community_experiences ORDER BY id DESC";
   if(isset($_GET['export']) && $_GET['export']==='csv') { header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=seasonverse_reviews_'.date('Y-m-d').'.csv'); $out=fopen('php://output','w'); fputcsv($out,['ID','Reviewer Name','Rating','Destination','Story','Status','Date']); foreach($pdo->query($sql) as $r){fputcsv($out,[$r['id'],$r['reviewer_name'],$r['rating'],$r['trip_tag'],$r['comment'],$r['status'],$r['time_ago']]);} fclose($out); exit; }
   $reviewsList=$pdo->query($sql)->fetchAll();
 }
} catch(Throwable $e){ error_log('admin-reviews: '.$e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Reviews Management</title>
    <link rel="stylesheet" href="admin_reviews.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-dropdown-menu { display: none; position: absolute; right: 40px; top: 75px; background: #fff; width: 200px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); z-index: 999; overflow: hidden; }
        .profile-dropdown-menu a { display: block; padding: 12px 16px; text-decoration: none; color: #333; font-size: 0.85rem; border-bottom: 1px solid #f5f2ec; }
        .profile-dropdown-menu a:hover { background: #f8f5f0; }

        .reviews-grid-main {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 18px;
            margin-bottom: 24px;
        }
        .review-item {
            padding: 18px 0;
            border-bottom: 1px solid #F5F2EC;
        }
        .review-item:last-child {
            border-bottom: none;
        }
        .badge-published-rev {
            background: #F0FDF4; color: #166534; font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-block; letter-spacing: 0.5px;
        }
        .badge-review-rev {
            background: #FEF3C7; color: #B45309; font-size: 0.65rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-block; letter-spacing: 0.5px;
        }
        .progress-bar-container { background: #F5F2EC; border-radius: 6px; height: 6px; width: 100%; overflow: hidden; }
        .progress-bar-fill { background: #1A1A1A; height: 100%; border-radius: 6px; }
        .love-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 24px;
        }
        .insights-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }
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
                <li class="active"><a href="admin-reviews.php"><i class="fa-solid fa-star"></i> Reviews</a></li>
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
            <div class="breadcrumb">Admin &gt; Reviews</div>
            <div class="topbar-right">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="topSearch" placeholder="Search reviews..." onkeyup="filterReviews()" style="border:none; outline:none; background:transparent; width:100%; font-family:'Plus Jakarta Sans',sans-serif; font-size:0.82rem;">
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
                <div style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.2px; color: #888; margin-bottom: 4px; font-weight: 600;">Community Feedback</div>
                <h1 style="font-size: 2.2rem; font-weight: 700; letter-spacing: -0.5px;">Reviews</h1>
                <p style="font-size: 0.85rem; color: #666; margin-top: 3px;">Read traveler feedback, monitor satisfaction, and manage reviews across SeasonVerse.</p>
            </div>
            <a href="admin-reviews.php?export=csv" style="background: #1A1A1A; color: #fff; text-decoration: none; padding: 11px 22px; border-radius: 30px; font-weight: 600; font-size: 0.82rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-download"></i> Export Reviews
            </a>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header"><span>Total Reviews</span> <i class="fa-regular fa-comment-dots" style="color: #888;"></i></div>
                <div class="stat-value"><?php echo count($reviewsList); ?></div>
                <span class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> +16.4%</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Average Rating</span> <i class="fa-solid fa-star" style="color: #888;"></i></div>
                <div class="stat-value">4.8 / 5</div>
                <span style="font-size: 0.72rem; color: #166534; font-weight: 600;">+0.3 vs last month</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>5-Star Reviews</span> <i class="fa-solid fa-face-smile" style="color: #888;"></i></div>
                <div class="stat-value">3,214</div>
                <span style="font-size: 0.72rem; color: #666; font-weight: 600;">75% of total</span>
            </div>
            <div class="stat-card">
                <div class="stat-header"><span>Needs Attention</span> <i class="fa-solid fa-triangle-exclamation" style="color: #888;"></i></div>
                <div class="stat-value">18</div>
                <span class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> -6.2%</span>
            </div>
        </div>

        <!-- RATING OVERVIEW & ALL REVIEWS -->
        <div class="reviews-grid-main">
            <div class="card-box">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 15px;">Rating Overview</h3>
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 2.5rem; font-weight: 700;">4.8</div>
                    <div style="color: #D97706; font-size: 0.9rem; margin: 4px 0;">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
                    </div>
                    <div style="font-size: 0.75rem; color: #888;">Based on database reviews</div>
                </div>
                <div style="font-size: 0.8rem; display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;"><span>5</span> <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 75%;"></div></div> <span style="font-size: 0.75rem; color: #888; width: 30px;">75%</span></div>
                    <div style="display: flex; align-items: center; gap: 10px;"><span>4</span> <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 18%;"></div></div> <span style="font-size: 0.75rem; color: #888; width: 30px;">18%</span></div>
                    <div style="display: flex; align-items: center; gap: 10px;"><span>3</span> <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 4%;"></div></div> <span style="font-size: 0.75rem; color: #888; width: 30px;">4%</span></div>
                    <div style="display: flex; align-items: center; gap: 10px;"><span>2</span> <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 2%;"></div></div> <span style="font-size: 0.75rem; color: #888; width: 30px;">2%</span></div>
                    <div style="display: flex; align-items: center; gap: 10px;"><span>1</span> <div class="progress-bar-container"><div class="progress-bar-fill" style="width: 1%;"></div></div> <span style="font-size: 0.75rem; color: #888; width: 30px;">1%</span></div>
                </div>
            </div>

            <div class="card-box" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700;">All Reviews</h3>
                        <p style="font-size: 0.8rem; color: #666; margin-top: 2px;">Browse and manage traveler feedback.</p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="tableSearch" placeholder="Search reviews..." onkeyup="filterReviews()" style="background: #FAFAFA; border: 1px solid #EAE5DD; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; outline: none;">
                        <select id="ratingFilter" onchange="filterReviews()" style="background: #FAFAFA; border: 1px solid #EAE5DD; padding: 6px 10px; border-radius: 8px; font-size: 0.8rem; outline: none; color: #555;">
                            <option value="">All Ratings</option>
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>
                </div>

                <div id="reviewsContainer">
                    <?php foreach ($reviewsList as $rev): ?>
                    <div class="review-item" data-text="<?php echo strtolower(htmlspecialchars($rev['reviewer_name'] . ' ' . $rev['comment'] . ' ' . $rev['trip_tag'])); ?>" data-rating="<?php echo $rev['rating']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #EFECE6; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem;">
                                    <?php echo substr($rev['reviewer_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; font-size: 0.88rem;"><?php echo htmlspecialchars($rev['reviewer_name']); ?></div>
                                    <div style="font-size: 0.72rem; color: #D97706;">
                                        <?php for($i=0; $i<$rev['rating']; $i++): ?><i class="fa-solid fa-star"></i><?php endfor; ?> 
                                        <span style="color: #888; margin-left: 5px;"><?php echo htmlspecialchars($rev['trip_tag']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <span style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($rev['time_ago']); ?></span>
                        </div>
                        <p style="font-size: 0.83rem; color: #444; margin: 8px 0; line-height: 1.4;"><?php echo htmlspecialchars($rev['comment']); ?></p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <?php if ($rev['status'] === 'PUBLISHED'): ?>
                                <span class="badge-published-rev">PUBLISHED</span>
                            <?php else: ?>
                                <span class="badge-review-rev">NEEDS REVIEW</span>
                            <?php endif; ?>
                            <div style="font-size:11px"><a href="admin-reviews.php?id=<?php echo (int)$rev['id']; ?>&moderate=PUBLISHED">Publish</a> · <a href="admin-reviews.php?id=<?php echo (int)$rev['id']; ?>&moderate=NEEDS%20REVIEW">Review</a> · <a href="admin-reviews.php?id=<?php echo (int)$rev['id']; ?>&moderate=HIDDEN">Hide</a></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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

        // Live Search & Rating Filter Function
        function filterReviews() {
            let searchVal = document.getElementById('tableSearch').value.toLowerCase();
            let ratingVal = document.getElementById('ratingFilter').value;
            let rows = document.querySelectorAll('.review-item');

            rows.forEach(row => {
                let text = row.getAttribute('data-text');
                let rating = row.getAttribute('data-rating');
                
                let matchesSearch = text.includes(searchVal);
                let matchesRating = (ratingVal === "" || rating === ratingVal);

                if (matchesSearch && matchesRating) {
                    row.style.display = "block";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>