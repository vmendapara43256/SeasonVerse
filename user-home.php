<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
$userName = $_SESSION['user_name'] ?? 'Traveler';
$userInitial = strtoupper(substr(trim($userName), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Home</title>
    <link rel="stylesheet" href="user_home.css?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- STICKY NAVBAR -->
    <nav class="user-navbar">
        <div class="nav-brand">SeasonVerse</div>
        <ul class="nav-links">
            <li><a href="user-home.php"class="active">Home</a></li>
            <li><a href="user-ai.php">AI Planner</a></li>
            <li><a href="user-destinations.php">Destinations</a></li>
            <li><a href="user-experiences.php">Experiences</a></li>
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

    <!-- MAIN CONTENT PADDING -->
    <div class="main-content">

        <div class="welcome-banner">
            <div>
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>.</h1>
                <p>Ready to discover something beautiful around SeasonVerse?</p>
            </div>
            <div class="weather-badge">
                <i class="fa-solid fa-cloud-sun" style="font-size: 1.2rem; color: #D97706;"></i>
                <div>
                    <strong style="display: block; font-size: 0.85rem;">24°C • Sunny</strong>
                    <span style="font-size: 0.72rem; color: #888;">Perfect weather for alpine escape</span>
                </div>
            </div>
        </div>

        <div class="hero-image-card" onclick="openModal('Zermatt Alpine Pass', 'Winter', 'Switzerland', 'A beautiful snowy escape featuring skiing, warm cabins, and panoramic views of the Matterhorn.')">
            <span>Featured Destination • Zermatt, Switzerland</span>
        </div>

        <!-- TRIP PLANNER -->
        <div class="trip-planner-box" id="plannerBox">
            <h3>Where will your next season take you?</h3>
            <div class="planner-grid">
                <div class="planner-field">
                    <label>DESTINATION</label>
                    <input type="text" id="aiDestination" placeholder="e.g. Swiss Alps, Kyoto, Maldives">
                </div>
                <div class="planner-field">
                    <label>SEASON / MONTH</label>
                    <input type="text" id="aiDate" placeholder="e.g. Winter 2026">
                </div>
            </div>
            <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px;">Select Season</div>
            <div class="season-pills" id="seasonPills">
                <span class="pill active" onclick="selectPill(this)">Summer</span>
                <span class="pill" onclick="selectPill(this)">Monsoon</span>
                <span class="pill" onclick="selectPill(this)">Winter</span>
                <span class="pill" onclick="selectPill(this)">Spring</span>
            </div>
            <button class="btn-search" id="aiSearchBtn" onclick="runAIPlanner()">
                <i class="fa-solid fa-wand-magic-sparkles"></i> 
                <span>Generate Itinerary &rarr;</span>
            </button>
        </div>

        <!-- HIDDEN AI RESULT BLOCK (Shows after searching) -->
        <div id="aiResultBox" class="ai-result-container">
            <div class="ai-result-header">
                <div>
                    <h4 id="aiResultTitle">Your Custom Itinerary</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-top: 3px;">Curated instantly by SeasonVerse AI</p>
                </div>
                <span id="aiResultTag">AI GENERATED</span>
            </div>
            <div class="ai-timeline">
                <div class="ai-day">
                    <strong>Day 1: Arrival & Exploration</strong>
                    <p>Settle into your accommodation, explore local cafes, and enjoy a curated welcome dinner.</p>
                </div>
                <div class="ai-day">
                    <strong>Day 2: The Core Experience</strong>
                    <p>Guided tour through top scenic locations with automated wardrobe suggestions applied.</p>
                </div>
                <div class="ai-day">
                    <strong>Day 3: Relaxation & Departure</strong>
                    <p>Morning spa or nature walk, followed by comfortable transit arrangements.</p>
                </div>
            </div>
            <button class="btn-search" style="margin-top: 25px; padding: 10px 20px;" onclick="showToast('Itinerary saved to your account!')">Save to My Plans</button>
        </div>

    <!-- CHOOSE YOUR SEASON -->
<h3 class="section-title">Choose Your Season</h3>
<div class="seasons-grid">
    <div class="season-card" style="background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80');" onclick="openSeasonModal('Summer Solstice', 'Summer', 'Discover sun-drenched beaches and vibrant city nightlife curated for the perfect summer.')">
        <h4>Summer Solstice</h4>
        <i class="fa-solid fa-arrow-right" style="font-size: 0.9rem; margin-top: 4px;"></i>
    </div>
    
    <div class="season-card" style="background-image: url('https://images.unsplash.com/photo-1515694346937-94d85e41e6f0?auto=format&fit=crop&w=600&q=80');" onclick="openSeasonModal('Monsoon Rain', 'Monsoon', 'Lush green landscapes, cozy tea retreats, and beautiful rain-washed scenery.')">
        <h4>Monsoon Rain</h4>
        <i class="fa-solid fa-arrow-right" style="font-size: 0.9rem; margin-top: 4px;"></i>
    </div>
    
    <div class="season-card" style="background-image: url('https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=600&q=80');" onclick="openSeasonModal('Winter Cabin', 'Winter', 'Snowy peaks, fireplace cabins, and premium ski resort recommendations.')">
        <h4>Winter Cabin</h4>
        <i class="fa-solid fa-arrow-right" style="font-size: 0.9rem; margin-top: 4px;"></i>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h3 class="section-title" style="margin-bottom: 0;">Curated For You</h3>
    <a href="user-destinations.php" style="font-size: 0.8rem; color: #1B2E23; font-weight: 700; cursor: pointer; text-decoration: none;">View All Recommendations &rarr;</a>
</div>

<!-- THE BIG, ELEGANT LIGHT MODAL FOR DESTINATIONS -->
<div id="destinationModal" class="custom-modal">
    <div class="custom-modal-content">
        <span class="close-modal" onclick="closeSeasonModal()">&times;</span>
        <h2 id="modal-title" style="color: #1B2E23; font-family: 'Playfair Display', serif; font-size: 2.5rem; margin-bottom: 10px;">Season</h2>
        <p id="modal-desc" style="color: #555; margin-bottom: 30px; font-size: 1.1rem; max-width: 80%;"></p>
        
        <h3 style="color: #1B2E23; border-bottom: 2px solid #eaeaea; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.5rem;">Top Recommendations</h3>
        
        <!-- The Grid where big image cards will be injected -->
        <div id="modal-places-grid" class="places-grid">
            <!-- Injected by JS -->
        </div>
    </div>
</div>

<!-- CSS FOR THE VISUAL MODAL -->
<style>
.custom-modal {
    display: none; 
    position: fixed; 
    z-index: 9999; 
    left: 0; 
    top: 0; 
    width: 100%; 
    height: 100%; 
    background-color: rgba(0, 0, 0, 0.6); /* Soft dark overlay */
    backdrop-filter: blur(6px);
}
.custom-modal-content {
    background-color: #faf9f6; /* Very soft luxury cream/white */
    margin: 3% auto; 
    padding: 40px 50px;
    border-radius: 16px;
    width: 95%; 
    max-width: 1000px; /* BIG modal */
    max-height: 85vh; /* Prevents it from going off-screen */
    overflow-y: auto; /* Allows scrolling inside if needed */
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}
.close-modal {
    color: #999;
    float: right;
    font-size: 36px;
    font-weight: 300;
    cursor: pointer;
    transition: 0.3s;
    margin-top: -10px;
}
.close-modal:hover {
    color: #1B2E23;
}

/* BIG Image Grid Styling */
.places-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr); /* 2 large columns */
    gap: 25px;
}
.place-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    transition: all 0.4s ease;
    border: 1px solid #f0f0f0;
}
.place-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(27, 46, 35, 0.15);
}
.place-card img {
    width: 100%;
    height: 250px; /* BIG images */
    object-fit: cover;
}
.place-info {
    padding: 20px;
    font-size: 1.2rem;
    color: #1B2E23; /* Deep Forest Green */
    text-align: center;
    font-weight: bold;
    font-family: 'Playfair Display', serif;
}

/* Responsive adjustment for small screens */
@media (max-width: 768px) {
    .places-grid {
        grid-template-columns: 1fr; /* 1 column on tablets/phones */
    }
    .custom-modal-content {
        padding: 20px;
        margin: 5% auto;
    }
    .place-card img {
        height: 200px;
    }
}
</style>

<!-- JAVASCRIPT FOR MODAL IMAGE LOGIC -->
<!-- JAVASCRIPT FOR MODAL IMAGE LOGIC -->
<script>
const seasonDestinations = {
    'Summer': [
        { name: 'Goa, India', img: 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?w=800&q=80' },
        { name: 'Bali, Indonesia', img: 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800&q=80' },
    ],
    'Monsoon': [
        { name: 'Munnar, Kerala', img: 'https://images.unsplash.com/photo-1593693397690-362cb9666fc2?w=800&q=80' },
        { name: 'Kyoto, Japan', img: 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=800&q=80' }
    ],
    'Winter': [
        { name: 'Zermatt, Switzerland', img: 'https://images.unsplash.com/photo-1530122037265-a5f1f91d3b99?w=800&q=80' },
        { name: 'Manali, Himachal', img: 'https://images.unsplash.com/photo-1605649487212-47bdab064df7?w=800&q=80' },
    ]
};

function openSeasonModal(title, season, description) {
    document.getElementById('modal-title').innerText = title;
    document.getElementById('modal-desc').innerText = description;
    
    const placesGrid = document.getElementById('modal-places-grid');
    placesGrid.innerHTML = ''; 
    
    const places = seasonDestinations[season] || [];
    places.forEach(place => {
        placesGrid.innerHTML += `
            <div class="place-card">
                <img src="${place.img}" alt="${place.name}">
                <div class="place-info">${place.name}</div>
            </div>
        `;
    });

    document.body.style.overflow = 'hidden'; 
    document.getElementById('destinationModal').style.display = 'block';
}

function closeSeasonModal() {
    document.body.style.overflow = 'auto'; 
    document.getElementById('destinationModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('destinationModal');
    if (event.target == modal) {
        closeSeasonModal();
    }
}
</script>
        <div class="curated-grid">
            <div class="curated-card" onclick="openModal('Swiss Alps', 'Winter Escape', 'Switzerland', 'The AI planner suggested perfect hidden gems and warm wardrobe requirements.')">
                <div class="curated-img" style="background-image: url('https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=400&q=80');">
                    <span onclick="toggleHeart(event, this)"><i class="fa-regular fa-heart"></i></span>
                </div>
                <div style="font-size: 0.65rem; color: #C5A880; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Winter Escape</div>
                <h5>Swiss Alps</h5>
                <p>The AI planner suggested perfect hidden gems.</p>
            </div>
            <div class="curated-card" onclick="openModal('Maldives', 'Monsoon Retreat', 'Maldives', 'Overwater luxury during serene coastal rains with indoor spa plans.')">
                <div class="curated-img" style="background-image: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=400&q=80');">
                    <span onclick="toggleHeart(event, this)"><i class="fa-regular fa-heart"></i></span>
                </div>
                <div style="font-size: 0.65rem; color: #C5A880; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Monsoon Retreat</div>
                <h5>Maldives</h5>
                <p>Overwater luxury during serene coastal rains.</p>
            </div>
            <div class="curated-card" onclick="openModal('Coorg', 'Spring Blossom', 'India', 'Coffee plantation trails, morning mist, and cultural heritage tours.')">
                <div class="curated-img" style="background-image: url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=400&q=80');">
                    <span onclick="toggleHeart(event, this)"><i class="fa-regular fa-heart"></i></span>
                </div>
                <div style="font-size: 0.65rem; color: #C5A880; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Spring Blossom</div>
                <h5>Coorg</h5>
                <p>Coffee plantation trails and morning mist.</p>
            </div>
            <div class="curated-card" onclick="openModal('Kyoto', 'Cultural Trip', 'Japan', 'Historic temples, traditional tea ceremonies, and bamboo forests.')">
                <div class="curated-img" style="background-image: url('https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=400&q=80');">
                    <span onclick="toggleHeart(event, this)"><i class="fa-regular fa-heart"></i></span>
                </div>
                <div style="font-size: 0.65rem; color: #C5A880; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Cultural Trip</div>
                <h5>Kyoto</h5>
                <p>Historic temples and traditional tea ceremonies.</p>
            </div>
        </div>

        <div class="journey-saved-grid">
            <div>
                <h3 class="section-title">Your Upcoming Journey</h3>
                <div class="journey-card" onclick="openModal('Swiss Alps Escape', 'Upcoming Trip', 'Switzerland', 'Your packed itinerary for Dec 12 - 19. Flight details and hotel confirmations are ready.')">
                    <div class="journey-img" style="background-image: url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=400&q=80');"></div>
                    <div style="flex: 1;">
                        <h5 style="font-size: 1rem; font-weight: 700; margin-bottom: 4px;">SWISS ALPS ESCAPE</h5>
                        <div style="font-size: 0.75rem; color: #666; margin-bottom: 12px;">Winter Gateway • 7 Days</div>
                        <div style="display: flex; gap: 20px; font-size: 0.8rem; font-weight: 600;">
                            <span><i class="fa-regular fa-calendar" style="color: #888;"></i> Dec 12 - 19</span>
                            <span><i class="fa-solid fa-location-dot" style="color: #888;"></i> Zermatt</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="section-title">Saved</h3>
                <div class="saved-box">
                    <div class="saved-item" onclick="openModal('Kyoto Temple Stay', 'Saved', 'Japan', 'Saved 5-day Spring itinerary.')">
                        <i class="fa-solid fa-bookmark" style="color: #1B2E23;"></i>
                        <div><strong>Kyoto Temple Stay</strong><div style="font-size: 0.7rem; color: #888;">Spring • 5 Days</div></div>
                    </div>
                    <div class="saved-item" onclick="openModal('Maldives AI Itinerary', 'Saved', 'Maldives', 'Saved 4-day Monsoon itinerary.')">
                        <i class="fa-solid fa-bookmark" style="color: #1B2E23;"></i>
                        <div><strong>Maldives AI Itinerary</strong><div style="font-size: 0.7rem; color: #888;">Monsoon • 4 Days</div></div>
                    </div>
                    <div class="saved-item" onclick="openModal('Zermatt Ski Course', 'Saved', 'Switzerland', 'Saved 6-day Winter itinerary.')">
                        <i class="fa-solid fa-bookmark" style="color: #1B2E23;"></i>
                        <div><strong>Zermatt Ski Course</strong><div style="font-size: 0.7rem; color: #888;">Winter • 6 Days</div></div>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="section-title">Explore SeasonVerse</h3>
<div class="explore-grid">
    <div class="explore-item" onclick="window.location.href='user-destinations.php'">
        <i class="fa-solid fa-earth-americas"></i> Destinations
    </div>
    <div class="explore-item" onclick="window.location.href='user-ai.php'">
        <i class="fa-solid fa-wand-magic-sparkles"></i> AI Planner
    </div>
    <div class="explore-item" onclick="window.location.href='user-wardrobe.php'">
        <i class="fa-solid fa-shirt"></i> Smart Wardrobe
    </div>
    <div class="explore-item" onclick="window.location.href='user-experiences.php'">
        <i class="fa-solid fa-utensils"></i> Food & Dining
    </div>
    <div class="explore-item" onclick="window.location.href='user-travel.php'">
        <i class="fa-regular fa-file-lines"></i> Packing Lists
    </div>
    <div class="explore-item" onclick="window.location.href='user-insurance.php'">
        <i class="fa-solid fa-shield-heart"></i> Travel Insurance
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
    </div>

    <!-- GENERIC TRIP DETAILS MODAL -->
    <div id="tripModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div style="font-size: 0.65rem; color: #C5A880; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;" id="modalTag">Tag</div>
            <h2 id="modalTitle" style="font-size: 1.6rem; font-weight: 700; margin-bottom: 6px; font-family: serif; color: #1B2E23;">Trip Title</h2>
            <div style="font-size: 0.8rem; color: #888; margin-bottom: 20px;"><i class="fa-solid fa-location-dot"></i> <span id="modalLoc">Location</span></div>
            <p id="modalDesc" style="font-size: 0.9rem; color: #444; line-height: 1.5; margin-bottom: 25px;">Description goes here.</p>
            <button onclick="showToast('Trip added to active itinerary!'); closeModal();" class="btn-search" style="width: 100%; justify-content: center;">Start Planning This Trip</button>
        </div>
    </div>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- INTERACTIVE SCRIPTS -->
    <script>
        // Custom Toast Notification System (Replaces alerts)
        function showToast(message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `<i class="fa-solid fa-circle-info"></i> <span>${message}</span>`;
            
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 400); // wait for slide out transition
            }, 3000);
        }

        // Season Pill Selector
        function selectPill(clickedPill) {
            document.querySelectorAll('.pill').forEach(pill => pill.classList.remove('active'));
            clickedPill.classList.add('active');
        }

        // Interactive Heart Toggle (Prevents opening modal when clicking heart)
        function toggleHeart(event, element) {
            event.stopPropagation(); // Stop card click event from firing
            const icon = element.querySelector('i');
            if (icon.classList.contains('fa-regular')) {
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
                icon.style.color = '#C53030';
                showToast("Added to your Saved trips.");
            } else {
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
                icon.style.color = '#333';
                showToast("Removed from Saved trips.");
            }
        }

        // Dynamic Modal Handling
        function openModal(title, tag, loc, desc) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalTag').innerText = tag;
            document.getElementById('modalLoc').innerText = loc;
            document.getElementById('modalDesc').innerText = desc;
            document.getElementById('tripModal').style.display = 'flex';
        }
        function closeModal() { 
            document.getElementById('tripModal').style.display = 'none'; 
        }

        // AI Planner UI Injection Logic
        function runAIPlanner() {
            const destInput = document.getElementById('aiDestination');
            const dest = destInput.value.trim();
            const btn = document.getElementById('aiSearchBtn');
            const btnText = btn.querySelector('span');
            const btnIcon = btn.querySelector('i');
            const resultBox = document.getElementById('aiResultBox');

            if (!dest) {
                showToast("Please enter a destination first.");
                destInput.focus();
                return;
            }

            // Hide previous results if generating again
            resultBox.style.display = 'none';

            // Start Loading State
            btn.classList.add('loading');
            btnIcon.classList.remove('fa-wand-magic-sparkles');
            btnIcon.classList.add('fa-spinner', 'fa-spin');
            btnText.innerText = 'AI is curating...';

            // Simulate AI processing delay
            setTimeout(() => {
                // Reset Button
                btn.classList.remove('loading');
                btnIcon.classList.remove('fa-spinner', 'fa-spin');
                btnIcon.classList.add('fa-check');
                btnText.innerText = 'Regenerate Itinerary';
                
                // Show Result Box with Dynamic Content
                document.getElementById('aiResultTitle').innerText = `Your Trip to ${dest}`;
                resultBox.style.display = 'block';
                
                showToast("Success! Custom itinerary generated.");

                // Smooth scroll to the result
                setTimeout(() => resultBox.scrollIntoView({behavior: 'smooth', block: 'center'}), 100);

            }, 2000); // 2 second fake generation time
        }

        // Close modal if clicking outside the white box
        window.onclick = function(event) {
            const modal = document.getElementById('tripModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>