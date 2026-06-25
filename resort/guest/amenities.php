<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Amenities';
$active_page = 'amenities';
include __DIR__ . '/../includes/header.php';
$db = getDB();

// Check if the guest has an active booking to show appropriate CTA
$guest_has_booking = false;
if (isGuestLoggedIn()) {
    $guest_id = (int)$_SESSION['guest_id'];
    $result = $db->query("
        SELECT COUNT(*) AS cnt FROM bookings
        WHERE guest_id = $guest_id
          AND status IN ('pending', 'confirmed', 'checked_in')
    ");
    $row = $result->fetch_assoc();
    $guest_has_booking = ((int)($row['cnt'] ?? 0)) > 0;
}

$amenities = $db->query("SELECT * FROM amenities ORDER BY category, name");
$by_cat = [];
while ($a = $amenities->fetch_assoc()) {
    $by_cat[$a['category']][] = $a;
}
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">What's Included</div>
        <h1>Resort Amenities</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> Amenities</div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5rem;align-items:center;margin-bottom:5rem">
            <div>
                <div class="eyebrow">The Full Picture</div>
                <h2 class="section-title" style="margin:.5rem 0 1.25rem">Everything Your<br>Wild Stay Needs</h2>
                <p style="font-size:1rem;color:var(--text-mid);line-height:1.8;margin-bottom:1rem">
                    Every room at Subic Resort comes stocked with what you actually need — no fluff, nothing missing. From the high-thread-count linens made in Pampanga to the hand-carved bamboo furniture from our local artisans, comfort is never an afterthought.
                </p>
                <p style="font-size:1rem;color:var(--text-mid);line-height:1.8">
                    We also maintain shared resort facilities that would make any city hotel envious — from the summit infinity pool to the forest-edge yoga platform and our legendary communal fire pit.
                </p>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <?php
                $highlights = [
                    ['fa-swimming-pool','Summit Infinity Pool','var(--jungle-dark)'],
                    ['fa-utensils','Canopy Restaurant','var(--ochre)'],
                    ['fa-spa','Forest Spa & Wellness','var(--jungle-mid)'],
                    ['fa-fire','Communal Fire Pit','var(--ember)'],
                ];
                foreach ($highlights as $h): ?>
                <div style="background:<?=$h[2]?>;border-radius:var(--radius-lg);padding:1.5rem;color:white;text-align:center">
                    <i class="fas <?=$h[0]?>" style="font-size:2rem;margin-bottom:.75rem;opacity:.9"></i>
                    <div style="font-family:var(--font-head);font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;font-weight:600"><?=$h[1]?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-header center">
            <div class="eyebrow">Resort-Wide</div>
            <h2 class="section-title">Shared Facilities</h2>
        </div>
        <div class="grid-3" style="margin-bottom:5rem">
            <?php
            $facilities = [
                ['fa-swimming-pool','Summit Infinity Pool','Open 6AM–9PM. Heated during cooler months. Perched at the resort\'s highest point with unobstructed valley views.', 'https://i.pinimg.com/1200x/1f/2b/19/1f2b19b296f5cf2d53f9f2faae94c318.jpg'],
                ['fa-utensils','Canopy Restaurant','Open for breakfast, lunch, and dinner. Our executive chef cooks what the forest provides — a different menu, daily.', 'https://i.pinimg.com/1200x/54/25/a6/5425a6001412a5b053ba2eaa3332418d.jpg'],
                ['fa-spa','Forest Spa & Wellness Center','Full treatment menu: massage, botanical facials, herbal steam. Book at the front desk or add to your reservation.', 'https://i.pinimg.com/1200x/99/b8/b6/99b8b620897ffe9b7e9ad214626910f8.jpg'],
                ['fa-fire','Communal Fire Pit','The social heart of Subic Resort. Nightly bonfire from 7PM. Stories, s\'mores, and cold local brew included.', 'https://i.pinimg.com/1200x/de/28/59/de285978632d6812e4f5ca9ee8ce74d3.jpg'],
                ['fa-mountain','Sunrise Yoga Deck','600m² open-air platform at the treeline. Morning sessions at 5:30AM and 7AM, led by certified instructors.', 'https://i.pinimg.com/736x/2e/2c/c4/2e2cc4b12b1208ce47a6e105ab30d86f.jpg'],
                ['fa-car','Complimentary Parking','Secure gated parking for all registered guests. Vintage jeepney shuttle runs every 30 minutes around the grounds.', 'https://i.pinimg.com/1200x/b3/0e/35/b30e35f02c69363b22626a18d9453e07.jpg'],
                ['fa-wifi','Resort-Wide Wi-Fi','High-speed fiber connectivity throughout the property. Stream, work, or video call without interruption.', 'https://i.pinimg.com/736x/6f/9e/20/6f9e20bccb32f07cd502ab1d181ef6a2.jpg'],
                ['fa-tshirt','Laundry Services','Same-day laundry service for all guests. Drop off at the front desk by 9AM for return by 6PM.', 'https://i.pinimg.com/736x/5f/45/81/5f4581ff7438cdff472db6544b0f4d72.jpg'],
                ['fa-first-aid','24/7 Medical Support','Resident first-aider and emergency protocols for all adventure activities. Nearest hospital is 20 minutes away.', 'https://i.pinimg.com/1200x/a1/5d/19/a15d19782a2c7741baa50299f01cf653.jpg'],
            ];
            foreach ($facilities as $f): ?>
            <div class="card" style="padding:1.75rem">
                <div style="width:100%; height:200px; margin-bottom:1rem; border-radius:var(--radius); overflow:hidden;">
                    <img src="<?=$f[3]?>" alt="<?=$f[1]?>" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div style="width:52px;height:52px;background:var(--mist);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:var(--canopy);margin-bottom:1rem">
                    <i class="fas <?=$f[0]?>"></i>
                </div>
                <h4 style="font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--jungle-dark);margin-bottom:.5rem"><?=$f[1]?></h4>
                <p style="font-size:.87rem;color:var(--text-mid);line-height:1.6"><?=$f[2]?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($by_cat)): ?>
        <div class="section-header center">
            <div class="eyebrow">In Every Room</div>
            <h2 class="section-title">In-Room Amenities by Category</h2>
        </div>
        <div style="display:flex;flex-direction:column;gap:3rem">
            <?php 
            // Mapping for dynamic in-room database entries to match user images
            $amenity_images = [
                'Gear Locker' => 'https://i.pinimg.com/736x/e3/59/83/e359836ebbb62f87d1d5cc9b15fcf295.jpg',
                'Rain Shower' => 'https://i.pinimg.com/736x/99/34/b2/9934b2273018458279945981d3e5f545.jpg',
                'Soaking Tub' => 'https://i.pinimg.com/1200x/53/d4/a5/53d4a52cf35d07226dd7929f790bf1f6.jpg',
                'Air Conditioning' => 'https://i.pinimg.com/736x/33/a9/af/33a9af251d5cfc7299b0a9908515b1ed.jpg',
                'Free Wi-Fi' => 'https://i.pinimg.com/1200x/89/14/58/89145898e7aa096fbab910518e874701.jpg',
                'Coffee Station' => 'https://i.pinimg.com/736x/d2/c0/64/d2c0642a6e0531939d80f07bafc05365.jpg',
                'Minibar' => 'https://i.pinimg.com/1200x/98/0c/f4/980cf4eefa37a4338a97b159e30dca1d.jpg',
                'Smart TV' => 'https://i.pinimg.com/1200x/5b/cb/59/5bcb598d79ffb948c919c851a26722a4.jpg',
                'Fire Pit' => 'https://i.pinimg.com/736x/d3/40/8c/d3408c3c124465127dc7d1eb7f99b8a7.jpg',
                'Hammock' => 'https://i.pinimg.com/736x/35/c8/de/35c8dedf2d41e2fa2845f40c4defa535.jpg',
                'Private Deck' => 'https://i.pinimg.com/736x/05/c9/68/05c968bac428531b27aa7fcd38509172.jpg',
                'Plunge Pool' => 'https://i.pinimg.com/736x/71/21/95/712195cae6ca1938c75fdaf1d131e4f8.jpg',
                'Telescope' => 'https://i.pinimg.com/1200x/9c/88/bd/9c88bdb4070f0ad6ddfc36e0f483ff44.jpg',
                'Forest View' => 'https://i.pinimg.com/736x/c3/6d/0d/c36d0db883e85326db76e9449a3f9957.jpg',
                'River View' => 'https://i.pinimg.com/736x/0e/e2/72/0ee27270eec337ec37c0719d388fa5fd.jpg'
            ];

            foreach ($by_cat as $cat => $amenity_list): ?>
            <div>
                <h3 style="font-family:var(--font-head);font-size:.85rem;letter-spacing:.15em;text-transform:uppercase;color:var(--ochre);font-weight:700;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid var(--stone)">
                    <?= htmlspecialchars($cat) ?>
                </h3>
                <div class="grid-4">
                    <?php foreach ($amenity_list as $a): ?>
                    <div style="display:flex;flex-direction:column;gap:.75rem;padding:1rem;background:var(--mist);border-radius:var(--radius)">
                        <?php if (isset($amenity_images[$a['name']])): ?>
                        <div style="width:100%; height:130px; border-radius:var(--radius-sm); overflow:hidden;">
                            <img src="<?= $amenity_images[$a['name']] ?>" alt="<?= htmlspecialchars($a['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                        <?php endif; ?>
                        
                        <div style="display:flex;align-items:flex-start;gap:.75rem;">
                            <div style="width:36px;height:36px;background:var(--white);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:.9rem;color:var(--canopy);flex-shrink:0">
                                <i class="fas <?= htmlspecialchars($a['icon'] ?? 'fa-check') ?>"></i>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:.88rem;color:var(--jungle-dark)"><?= htmlspecialchars($a['name']) ?></div>
                                <?php if ($a['description']): ?>
                                <div style="font-size:.78rem;color:var(--text-light);margin-top:.2rem"><?= htmlspecialchars($a['description']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section style="background:var(--jungle-dark);padding:5rem 0;text-align:center">
    <div class="container">
        <div class="eyebrow" style="color:var(--ochre)">Bring Nothing But Curiosity</div>
        <h2 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;color:white;margin:.75rem 0 1.25rem">Everything Else Is Here Waiting</h2>
        <?php if ($guest_has_booking): ?>
        <p style="color:rgba(255,255,255,.7);font-size:1rem;margin-bottom:2rem">You have an active booking — you can add amenity requests right now.</p>
        <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap">
            <a href="<?= SITE_URL ?>/guest/my_bookings.php" class="btn btn-primary btn-lg"><i class="fas fa-concierge-bell"></i> Add Amenities to My Booking</a>
            <a href="<?= SITE_URL ?>/guest/services.php" class="btn btn-secondary btn-lg">Explore Services</a>
        </div>
        <?php elseif (isGuestLoggedIn()): ?>
        <p style="color:rgba(255,255,255,.7);font-size:1rem;margin-bottom:2rem">Amenity add-ons are available once you have an active room booking.</p>
        <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap">
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary btn-lg"><i class="fas fa-bed"></i> Book a Room</a>
            <a href="<?= SITE_URL ?>/guest/services.php" class="btn btn-secondary btn-lg">Explore Services</a>
        </div>
        <?php else: ?>
        <p style="color:rgba(255,255,255,.7);font-size:1rem;margin-bottom:2rem">Book a room first, then you'll be able to add amenity requests to your stay.</p>
        <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap">
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary btn-lg"><i class="fas fa-bed"></i> Book a Room</a>
            <a href="<?= SITE_URL ?>/guest/login.php" class="btn btn-secondary btn-lg"><i class="fas fa-sign-in-alt"></i> Log In</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>