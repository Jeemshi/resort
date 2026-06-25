<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Room Details';
$active_page = 'rooms';
include __DIR__ . '/../includes/header.php';

$db = getDB();

$room_id   = (int)($_GET['id'] ?? 0);
$check_in  = sanitize($_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day')));
$check_out = sanitize($_GET['check_out'] ?? date('Y-m-d', strtotime('+3 days')));
$guests    = (int)($_GET['guests'] ?? 1);

if ($room_id <= 0) { redirect(SITE_URL . '/guest/rooms.php'); }

$stmt = $db->prepare("SELECT r.*, rc.name as category_name FROM rooms r JOIN room_categories rc ON r.category_id = rc.id WHERE r.id = ?");
$stmt->bind_param('i', $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
if (!$room) { redirect(SITE_URL . '/guest/rooms.php'); }

// Images
$imgs = $db->query("SELECT * FROM room_images WHERE room_id=$room_id ORDER BY is_primary DESC, sort_order ASC");
$images = [];
while ($img = $imgs->fetch_assoc()) $images[] = $img;

// Amenities
$amen = $db->query("SELECT a.* FROM amenities a JOIN room_amenities ra ON a.id=ra.amenity_id WHERE ra.room_id=$room_id");
$amenities = [];
while ($a = $amen->fetch_assoc()) $amenities[] = $a;

$nights = max(1, (int)((strtotime($check_out) - strtotime($check_in)) / 86400));
$subtotal = $room['price_per_night'] * $nights;
$tax = $subtotal * 0.12;
$total = $subtotal + $tax;

// Map Specific Room Names to their unique URLs
$room_name = $room['name'] ?? '';
$featured_image_url = '';

if (strpos($room_name, 'Whitewater Cottage') !== false) {
    $featured_image_url = "";
} elseif (strpos($room_name, 'Pinnacle Suite') !== false) {
    $featured_image_url = "https://haspcms-chroma-hospitality.s3.ap-southeast-1.amazonaws.com/01JYDVY7EX8A78F2220VXP0VCM.jpg";
} elseif (strpos($room_name, 'Pathfinder Bungalow') !== false) {
    $featured_image_url = "https://dynamic-media-cdn.tripadvisor.com/media/photo-o/0d/70/e5/51/deluxe-seaview-room--v11822846.jpg?w=900&h=500&s=1";
} elseif (strpos($room_name, 'Bamboo Creek Cottage') !== false) {
    $featured_image_url = "https://a0.muscache.com/im/pictures/hosting/Hosting-U3RheVN1cHBseUxpc3Rpbmc6NzMwNjIzMDMyMTIyMDQ0NDUx/original/0a652006-75b1-49cd-9c94-545ec932c3a0.jpeg";
} elseif (strpos($room_name, 'Mossy Ridge Villa') !== false) {
    $featured_image_url = "https://bizhero.ph/images/stores/1674723871-2023-01-26-391501.jpg";
} elseif (strpos($room_name, 'Horizon Suite') !== false) {
    $featured_image_url = "https://gallery.streamlinevrs.com/units-gallery/00/0D/E4/image_167597372.jpeg";
} elseif (!empty($images)) {
    // Dynamic Fallback: Use standard path from db if it doesn't match our 6 specific custom selections
    $featured_image_url = SITE_URL . '/uploads/' . htmlspecialchars($images[0]['image_path']);
}
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow"><?= htmlspecialchars($room['category_name']) ?></div>
        <h1><?= htmlspecialchars($room['name']) ?></h1>
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <i class="fas fa-chevron-right fa-xs"></i>
            <a href="<?= SITE_URL ?>/guest/rooms.php">Rooms</a>
            <i class="fas fa-chevron-right fa-xs"></i>
            <?= htmlspecialchars($room['name']) ?>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 360px;gap:3rem;align-items:start">
            <div>
                <div style="background:linear-gradient(135deg,var(--jungle-dark),var(--jungle));border-radius:var(--radius-lg);overflow:hidden;height:420px;margin-bottom:1rem;position:relative">
                    <?php if (!empty($featured_image_url)): ?>
                        <img src="<?= $featured_image_url ?>" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:100%">
                            <i class="fas fa-bed" style="font-size:6rem;color:rgba(255,255,255,.2)"></i>
                        </div>
                    <?php endif; ?>

                    <?php if ($room['featured']): ?>
                    <div style="position:absolute;top:1.5rem;left:1.5rem;background:var(--ochre);color:white;padding:.4rem 1rem;border-radius:20px;font-size:.8rem;font-weight:700;font-family:var(--font-head);letter-spacing:.08em;text-transform:uppercase">
                        <i class="fas fa-star fa-xs"></i> Featured
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:2rem;margin-bottom:1.5rem">
                    <h2 style="font-family:var(--font-display);font-size:1.5rem;color:var(--jungle-dark);margin-bottom:1rem">About this Room</h2>
                    <p style="color:var(--text-mid);line-height:1.8;font-size:.95rem"><?= htmlspecialchars($room['description']) ?></p>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-top:1.5rem">
                        <div style="background:var(--mist);border-radius:var(--radius);padding:1rem;text-align:center">
                            <i class="fas fa-users" style="color:var(--jungle);font-size:1.25rem;margin-bottom:.5rem;display:block"></i>
                            <div style="font-size:.8rem;color:var(--text-light);text-transform:uppercase;font-family:var(--font-head);letter-spacing:.08em">Max Guests</div>
                            <div style="font-weight:700;color:var(--jungle-dark)"><?= $room['max_occupancy'] ?></div>
                        </div>
                        <?php if ($room['size_sqm']): ?>
                        <div style="background:var(--mist);border-radius:var(--radius);padding:1rem;text-align:center">
                            <i class="fas fa-expand-arrows-alt" style="color:var(--jungle);font-size:1.25rem;margin-bottom:.5rem;display:block"></i>
                            <div style="font-size:.8rem;color:var(--text-light);text-transform:uppercase;font-family:var(--font-head);letter-spacing:.08em">Room Size</div>
                            <div style="font-weight:700;color:var(--jungle-dark)"><?= $room['size_sqm'] ?> m²</div>
                        </div>
                        <?php endif; ?>
                        <div style="background:var(--mist);border-radius:var(--radius);padding:1rem;text-align:center">
                            <i class="fas fa-eye" style="color:var(--jungle);font-size:1.25rem;margin-bottom:.5rem;display:block"></i>
                            <div style="font-size:.8rem;color:var(--text-light);text-transform:uppercase;font-family:var(--font-head);letter-spacing:.08em">View</div>
                            <div style="font-weight:700;color:var(--jungle-dark)"><?= htmlspecialchars($room['view_type']) ?></div>
                        </div>
                        <div style="background:var(--mist);border-radius:var(--radius);padding:1rem;text-align:center">
                            <i class="fas fa-layer-group" style="color:var(--jungle);font-size:1.25rem;margin-bottom:.5rem;display:block"></i>
                            <div style="font-size:.8rem;color:var(--text-light);text-transform:uppercase;font-family:var(--font-head);letter-spacing:.08em">Floor</div>
                            <div style="font-weight:700;color:var(--jungle-dark)"><?= $room['floor'] ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($amenities)): ?>
                <div class="card" style="padding:2rem">
                    <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--jungle-dark);margin-bottom:1.25rem">Room Amenities</h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem">
                        <?php foreach ($amenities as $a): ?>
                        <div style="display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;background:var(--mist);border-radius:var(--radius-sm)">
                            <i class="fas <?= htmlspecialchars($a['icon']??'fa-check') ?>" style="color:var(--jungle);width:18px;text-align:center"></i>
                            <span style="font-size:.85rem;color:var(--text-dark);font-weight:500"><?= htmlspecialchars($a['name']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div style="position:sticky;top:100px">
                <div class="card" style="overflow:hidden">
                    <div style="background:var(--jungle-dark);padding:1.5rem;color:white">
                        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.6);margin-bottom:.25rem">Room <?= htmlspecialchars($room['room_number']) ?></div>
                        <h3 style="font-family:var(--font-display);font-size:1.25rem;color:var(--ochre-light,#f5c483)"><?= htmlspecialchars($room['name']) ?></h3>
                        <div class="price-tag" style="color:white;margin-top:.75rem"><?= formatCurrency($room['price_per_night']) ?> <span style="color:rgba(255,255,255,.5)">/night</span></div>
                    </div>
                    <div style="padding:1.5rem">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem;font-size:.85rem">
                            <div>
                                <div style="color:var(--text-light);font-size:.75rem;margin-bottom:.2rem">Check In</div>
                                <div style="font-weight:600;color:var(--jungle-dark)"><?= date('M d, Y', strtotime($check_in)) ?></div>
                            </div>
                            <div>
                                <div style="color:var(--text-light);font-size:.75rem;margin-bottom:.2rem">Check Out</div>
                                <div style="font-weight:600;color:var(--jungle-dark)"><?= date('M d, Y', strtotime($check_out)) ?></div>
                            </div>
                        </div>
                        <div style="border-top:1px dashed var(--stone);padding-top:1rem;display:flex;flex-direction:column;gap:.5rem;font-size:.88rem">
                            <div style="display:flex;justify-content:space-between;color:var(--text-mid)">
                                <span><?= formatCurrency($room['price_per_night']) ?> × <?= $nights ?> night<?=$nights>1?'s':''?></span>
                                <span><?= formatCurrency($subtotal) ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;color:var(--text-mid)">
                                <span>VAT (12%)</span>
                                <span><?= formatCurrency($tax) ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.05rem;color:var(--jungle-dark);border-top:1px solid var(--stone);padding-top:.75rem;margin-top:.25rem">
                                <span>Total</span>
                                <span><?= formatCurrency($total) ?></span>
                            </div>
                        </div>
                        <?php if ($room['status'] === 'available'): ?>
                        <a href="<?= SITE_URL ?>/guest/bookings.php?room_id=<?=$room['id']?>&check_in=<?=$check_in?>&check_out=<?=$check_out?>&guests=<?=$guests?>" 
                           class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.25rem">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                        <?php else: ?>
                        <div style="text-align:center;padding:1rem;background:var(--stone);border-radius:var(--radius);margin-top:1rem;color:var(--text-mid);font-size:.88rem">
                            <i class="fas fa-clock"></i> Currently Unavailable
                        </div>
                        <?php endif; ?>
                        <a href="<?= SITE_URL ?>/guest/rooms.php?check_in=<?=$check_in?>&check_out=<?=$check_out?>&guests=<?=$guests?>" 
                           style="display:block;text-align:center;margin-top:.75rem;font-size:.85rem;color:var(--text-mid)">
                            ← Back to all rooms
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>