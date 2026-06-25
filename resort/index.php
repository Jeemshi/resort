<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Home';
$active_page = 'home';
include __DIR__ . '/includes/header.php';

$db = getDB();

// Featured Rooms
$rooms_result = $db->query("
    SELECT r.*, rc.name as category_name,
           (SELECT image_path FROM room_images ri WHERE ri.room_id = r.id AND ri.is_primary=1 LIMIT 1) as primary_image
    FROM rooms r
    JOIN room_categories rc ON r.category_id = rc.id
    WHERE r.featured = 1 AND r.status = 'available'
    LIMIT 3
");

// Services preview
$services_result = $db->query("SELECT * FROM services WHERE available=1 LIMIT 4");
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-overlay"></div>
        <svg style="position:absolute;bottom:0;left:0;right:0;opacity:.15" viewBox="0 0 1440 320" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path fill="#3D7A5C" d="M0,224L60,208C120,192,240,160,360,165.3C480,171,600,213,720,234.7C840,256,960,256,1080,234.7C1200,213,1320,171,1380,149.3L1440,128L1440,320L1380,320C1320,320,1200,320,1080,320C960,320,840,320,720,320C600,320,480,320,360,320C240,320,120,320,60,320L0,320Z"></path>
            <path fill="#2C5F44" d="M0,288L80,277.3C160,267,320,245,480,240C640,235,800,245,960,240C1120,235,1280,213,1360,202.7L1440,192L1440,320L1360,320C1280,320,1120,320,960,320C800,320,640,320,480,320C320,320,160,320,80,320L0,320Z"></path>
        </svg>
    </div>
    <div class="hero-content">
        <div class="hero-kicker">
            <div class="hero-kicker-line"></div>
            <span class="eyebrow">Zambales, Philippines</span>
        </div>
        <h1 class="display-title hero-title">
            Where the Wild<br>
            <span>Becomes Home</span>
        </h1>
        <p class="hero-subtitle">
            Suspended above highland forest floors and perched over rushing mountain rivers — Subic Resort is the basecamp for your most unforgettable escape.
        </p>
        <div class="hero-actions">
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary btn-lg">
                <i class="fas fa-bed"></i> Explore Rooms
            </a>
            <a href="<?= SITE_URL ?>/guest/about.php" class="btn btn-secondary btn-lg">
                Our Story
            </a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="hero-stat-number">9</div>
                <div class="hero-stat-label">Unique Stays</div>
            </div>
            <div>
                <div class="hero-stat-number">10+</div>
                <div class="hero-stat-label">Adventures</div>
            </div>
            <div>
                <div class="hero-stat-number">4.9★</div>
                <div class="hero-stat-label">Guest Rating</div>
            </div>
            <div>
                <div class="hero-stat-number">1,200m</div>
                <div class="hero-stat-label">Above Sea Level</div>
            </div>
        </div>
    </div>
</section>

<!-- BOOKING BAR -->
<div style="background:var(--mist);padding:3rem 0">
    <div class="container">
        <div class="booking-bar">
            <form method="GET" action="<?= SITE_URL ?>/guest/rooms.php">
                <div class="booking-bar-inner">
                    <div class="booking-field">
                        <label><i class="fas fa-calendar-check"></i> Check In</label>
                        <input type="date" name="check_in" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                    <div class="booking-field">
                        <label><i class="fas fa-calendar-times"></i> Check Out</label>
                        <input type="date" name="check_out" min="<?= date('Y-m-d', strtotime('+2 days')) ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                    </div>
                    <div class="booking-field">
                        <label><i class="fas fa-users"></i> Guests</label>
                        <select name="guests">
                            <?php for ($i=1; $i<=8; $i++): ?>
                                <option value="<?=$i?>"><?=$i?> Guest<?=$i>1?'s':''?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="booking-field">
                        <label><i class="fas fa-door-open"></i> Room Type</label>
                        <select name="category">
                            <option value="">Any Type</option>
                            <option value="1">Jungle Canopy Villa</option>
                            <option value="2">Riverside Cottage</option>
                            <option value="3">Summit Suite</option>
                            <option value="4">Explorer Bungalow</option>
                            <option value="5">Family Lodge</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FEATURED ROOMS -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="eyebrow">Our Accommodations</div>
            <h2 class="section-title">Find Your Perfect Nest</h2>
            <p class="section-desc">From treetop villas draped in living ferns to riverside cottages with your feet over the water — each stay at Subic Resort is a room unlike any other.</p>
        </div>

        <?php if ($rooms_result && $rooms_result->num_rows > 0): ?>
        <div class="grid-3">
            <?php while ($room = $rooms_result->fetch_assoc()): ?>
            <div class="card">
                <div class="card-img-placeholder" style="position:relative;overflow:hidden;height:220px;background:linear-gradient(135deg,var(--jungle-dark),var(--jungle-mid))">
                    <?php
                    // Determine the best image for this room (clean, no duplicates)
                    $room_name = $room['name'] ?? '';
                    $img_src = '';
                    if (!empty($room['primary_image'])) {
                        $img_src = SITE_URL . '/uploads/' . htmlspecialchars($room['primary_image']);
                    } elseif (strpos($room_name, 'Pinnacle Suite') !== false) {
                        $img_src = "https://haspcms-chroma-hospitality.s3.ap-southeast-1.amazonaws.com/01JYDVY7EX8A78F2220VXP0VCM.jpg";
                    } elseif (strpos($room_name, 'Pathfinder Bungalow') !== false) {
                        $img_src = "https://dynamic-media-cdn.tripadvisor.com/media/photo-o/0d/70/e5/51/deluxe-seaview-room--v11822846.jpg?w=900&h=500&s=1";
                    } elseif (strpos($room_name, 'Bamboo Creek Cottage') !== false) {
                        $img_src = "https://a0.muscache.com/im/pictures/hosting/Hosting-U3RheVN1cHBseUxpc3Rpbmc6NzMwNjIzMDMyMTIyMDQ0NDUx/original/0a652006-75b1-49cd-9c94-545ec932c3a0.jpeg";
                    } elseif (strpos($room_name, 'Mossy Ridge Villa') !== false) {
                        $img_src = "https://bizhero.ph/images/stores/1674723871-2023-01-26-391501.jpg";
                    } elseif (strpos($room_name, 'Horizon Suite') !== false) {
                        $img_src = "https://gallery.streamlinevrs.com/units-gallery/00/0D/E4/image_167597372.jpeg";
                    } elseif ($room['category_id'] == 1) {
                        $img_src = "https://pix10.agoda.net/hotelImages/6180845/-1/ba980f3789236a5b9c73f42451dab346.jpg?ce=0&s=1024x768";
                    } else {
                        $img_src = "https://static.tripzilla.ph/media/119928/conversions/eco-saddle-w1024.webp";
                    }
                    ?>
                    <?php if ($img_src): ?>
                        <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:100%">
                            <i class="fas fa-<?= $room['category_id']==1?'tree':($room['category_id']==2?'water':($room['category_id']==3?'mountain':'compass')) ?>" style="font-size:4rem;color:rgba(255,255,255,.2)"></i>
                        </div>
                    <?php endif; ?>
                    <div style="position:absolute;top:1rem;left:1rem">
                        <span class="badge badge-success" style="background:rgba(0,0,0,.5);color:white;backdrop-filter:blur(4px)">
                            <?= htmlspecialchars($room['view_type']) ?>
                        </span>
                    </div>
                    <div style="position:absolute;top:1rem;right:1rem">
                        <span class="badge" style="background:var(--ochre);color:white">
                            <i class="fas fa-star"></i> 4.9
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-tag"><?= htmlspecialchars($room['category_name']) ?></div>
                    <h3 class="card-title"><?= htmlspecialchars($room['name']) ?></h3>
                    <p class="card-desc"><?= htmlspecialchars(substr($room['description'], 0, 110)) ?>…</p>
                    <div class="amenity-list">
                        <span class="amenity-badge"><i class="fas fa-users"></i> Up to <?= $room['max_occupancy'] ?></span>
                        <?php if ($room['size_sqm']): ?>
                        <span class="amenity-badge"><i class="fas fa-expand-arrows-alt"></i> <?= $room['size_sqm'] ?> m²</span>
                        <?php endif; ?>
                        <span class="amenity-badge"><i class="fas fa-layer-group"></i> Floor <?= $room['floor'] ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <div>
                        <div class="price-tag"><?= formatCurrency($room['price_per_night']) ?> <span>/ night</span></div>
                    </div>
                    <a href="<?= SITE_URL ?>/guest/room-detail.php?id=<?= $room['id'] ?>" class="btn btn-dark btn-sm">View Room</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:2.5rem">
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-outline btn-lg">
                <i class="fas fa-grid"></i> View All Rooms
            </a>
        </div>
    </div>
</section>

<section class="section bg-jungle">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow" style="color:var(--ochre)">The Subic Resort Difference</div>
            <h2 class="section-title" style="color:white">Built for the Wild at Heart</h2>
            <p class="section-desc">We didn't build a resort and put trees around it. We built around the trees, the river, and the ridge — and then made it comfortable.</p>
        </div>
        <div class="grid-4">
            <?php
            $features = [
                ['fa-tree','Canopy Architecture','Every structure is engineered around living trees, not despite them.'],
                ['fa-leaf','Zero-Waste Kitchen','Foraged ingredients, no single-use plastics, composted waste.'],
                ['fa-shield-alt','Safety-First Adventures','Certified guides, full safety gear, and risk-calibrated packages.'],
                ['fa-map-marked-alt','Deep Local Roots','Owned and staffed by the Benguet community we call home.'],
            ];
            foreach ($features as $f): ?>
            <div style="text-align:center;padding:2rem 1rem">
                <div style="width:64px;height:64px;border-radius:50%;background:rgba(200,135,58,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.6rem;color:var(--ochre-light)">
                    <i class="fas <?= $f[0] ?>"></i>
                </div>
                <h4 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:white;margin-bottom:.5rem"><?= $f[1] ?></h4>
                <p style="font-size:.88rem;color:rgba(255,255,255,.55);line-height:1.7"><?= $f[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SERVICES PREVIEW -->
<section class="section bg-stone">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow">Adventure & Wellness</div>
            <h2 class="section-title">Curated Experiences</h2>
            <p class="section-desc">Our menu of adventures, wellness treatments, and dining experiences are crafted by people who live these mountains.</p>
        </div>
        <?php if ($services_result && $services_result->num_rows > 0): ?>
        <div class="grid-4">
            <?php while ($svc = $services_result->fetch_assoc()): ?>
            <div class="card" style="text-align:center;padding:2rem">
                <div style="width:68px;height:68px;background:var(--mist);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.5rem;color:var(--canopy)">
                    <i class="fas <?= htmlspecialchars($svc['icon'] ?? 'fa-star') ?>"></i>
                </div>
                <div class="card-tag"><?= htmlspecialchars($svc['category']) ?></div>
                <h4 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--jungle-dark);margin:.4rem 0"><?= htmlspecialchars($svc['name']) ?></h4>
                <p style="font-size:.85rem;color:var(--text-mid);line-height:1.6;margin-bottom:1rem"><?= htmlspecialchars(substr($svc['description'],0,80)) ?>…</p>
                <div style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--ochre)"><?= formatCurrency($svc['price']) ?></div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        <div style="text-align:center;margin-top:2.5rem">
            <a href="<?= SITE_URL ?>/guest/services.php" class="btn btn-dark btn-lg">All Services</a>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="section">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow">Guest Stories</div>
            <h2 class="section-title">What the Wild Ones Say</h2>
        </div>
        <div class="grid-3">
            <?php
            $testimonials = [
                ['The Pinnacle Suite absolutely blew our minds. We watched clouds move through the valley from our plunge pool. No resort has ever made us feel this remote and this pampered at the same time.', 'Maria Santos', 'Cebu City', 5],
                ['The guided night walk was the highlight of our trip. Our guide Manong Romy knew every sound, every creature. We saw a flying lemur 10 feet away. Unbelievable.', 'Jake Reyes', 'Manila', 5],
                ['Whitewater Cottage is perfect. We ate breakfast with our feet hanging over the river. The staff remembered our names by day two. Subic Resort feels like a family home, not a hotel.', 'Ana Lim', 'Singapore', 5],
            ];
            foreach ($testimonials as $t): ?>
            <div class="card" style="padding:2rem">
                <div style="color:var(--ochre);margin-bottom:1rem;font-size:.9rem">
                    <?php for ($i=0;$i<$t[3];$i++) echo '<i class="fas fa-star"></i> '; ?>
                </div>
                <p style="font-family:var(--font-display);font-style:italic;font-size:1rem;color:var(--jungle-dark);line-height:1.7;margin-bottom:1.25rem">"<?= $t[0] ?>"</p>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div style="width:40px;height:40px;background:var(--jungle-mid);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1rem;font-weight:700;font-family:var(--font-display)">
                        <?= $t[1][0] ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:.9rem;color:var(--jungle-dark)"><?= $t[1] ?></div>
                        <div style="font-size:.78rem;color:var(--text-light)"><?= $t[2] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section style="background:var(--ochre);padding:5rem 0;text-align:center">
    <div class="container">
        <div class="eyebrow" style="color:rgba(255,255,255,.7)">Ready for Your Expedition?</div>
        <h2 style="font-family:var(--font-display);font-size:clamp(2rem,5vw,3.5rem);font-weight:900;color:white;margin:.75rem 0 1.25rem;line-height:1.1">The Forest Is Waiting</h2>
        <p style="font-size:1.1rem;color:rgba(255,255,255,.8);max-width:500px;margin:0 auto 2.5rem;line-height:1.7">Book your stay today and step into the highland wilderness. No two nights at Subic Resort are ever the same.</p>
        <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap">
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-dark btn-lg"><i class="fas fa-bed"></i> Browse Rooms</a>
            <a href="<?= SITE_URL ?>/guest/contact.php" class="btn btn-secondary btn-lg">Get in Touch</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
