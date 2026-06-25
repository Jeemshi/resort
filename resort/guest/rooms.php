<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Rooms & Villas';
$active_page = 'rooms';
include __DIR__ . '/../includes/header.php';

$db = getDB();

// Filters
$category_filter = (int)($_GET['category'] ?? 0);
$check_in  = sanitize($_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day')));
$check_out = sanitize($_GET['check_out'] ?? date('Y-m-d', strtotime('+3 days')));
$guests    = (int)($_GET['guests'] ?? 1);

// Build query
$where = "r.status = 'available'";
$params = [];
$types  = '';

if ($category_filter > 0) {
    $where .= " AND r.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}
if ($guests > 1) {
    $where .= " AND r.max_occupancy >= ?";
    $params[] = $guests;
    $types .= 'i';
}

$sql = "
    SELECT r.*, rc.name as category_name,
           (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=1 LIMIT 1) as primary_image
    FROM rooms r
    JOIN room_categories rc ON r.category_id = rc.id
    WHERE $where
    ORDER BY r.featured DESC, r.price_per_night ASC
";

if ($types) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rooms_result = $stmt->get_result();
} else {
    $rooms_result = $db->query($sql);
}

// Categories for filter
$cats = $db->query("SELECT * FROM room_categories ORDER BY base_price ASC");
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Accommodations</div>
        <h1>Rooms & Villas</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> Rooms</div>
    </div>
</div>

<div style="background:var(--mist);padding:2rem 0;position:sticky;top:0;z-index:50;box-shadow:var(--shadow-sm)">
    <div class="container">
        <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
            <div class="booking-field" style="flex:1;min-width:150px">
                <label>Check In</label>
                <input type="date" name="check_in" value="<?= $check_in ?>" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="booking-field" style="flex:1;min-width:150px">
                <label>Check Out</label>
                <input type="date" name="check_out" value="<?= $check_out ?>">
            </div>
            <div class="booking-field" style="flex:1;min-width:120px">
                <label>Guests</label>
                <select name="guests">
                    <?php for ($i=1;$i<=8;$i++): ?>
                    <option value="<?=$i?>" <?=$i==$guests?'selected':''?>><?=$i?> Guest<?=$i>1?'s':''?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="booking-field" style="flex:1;min-width:160px">
                <label>Room Type</label>
                <select name="category">
                    <option value="0">All Types</option>
                    <?php while ($cat = $cats->fetch_assoc()): ?>
                    <option value="<?=$cat['id']?>" <?=$category_filter==$cat['id']?'selected':''?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-outline">Clear</a>
        </form>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
            <div>
                <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--jungle-dark)">
                    <?= $rooms_result ? $rooms_result->num_rows : 0 ?> Room<?= ($rooms_result && $rooms_result->num_rows!=1)?'s':'' ?> Available
                </h2>
                <p style="font-size:.85rem;color:var(--text-light)">
                    <?= formatDate($check_in) ?> → <?= formatDate($check_out) ?> · <?= $guests ?> guest<?=$guests>1?'s':''?>
                </p>
            </div>
        </div>

        <?php if (!$rooms_result || $rooms_result->num_rows === 0): ?>
        <div style="text-align:center;padding:5rem 2rem;background:var(--stone);border-radius:var(--radius-lg)">
            <i class="fas fa-bed" style="font-size:4rem;color:var(--stone-dark);margin-bottom:1rem;display:block"></i>
            <h3 style="font-family:var(--font-display);font-size:1.5rem;color:var(--jungle-dark);margin-bottom:.5rem">No Rooms Found</h3>
            <p style="color:var(--text-light)">Try different dates or filters.</p>
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary" style="margin-top:1.25rem">View All Rooms</a>
        </div>
        <?php else: ?>

        <div style="display:flex;flex-direction:column;gap:2rem">
            <?php while ($room = $rooms_result->fetch_assoc()):
                // Calculate nights
                $nights = max(1, (int)((strtotime($check_out) - strtotime($check_in)) / 86400));
                $total  = $room['price_per_night'] * $nights;
            ?>
            <div class="card" style="display:flex;flex-direction:row;overflow:hidden;min-height:280px">
                
                <div style="width:340px;flex-shrink:0;position:relative;background:linear-gradient(135deg,var(--jungle-dark),var(--jungle-mid));display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <?php if ($room['primary_image']): ?>
                        <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($room['primary_image']) ?>" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
                    <?php elseif (stripos($room['name'], 'Pathfinder Bungalow') !== false): ?>
                        <img src="https://www.rjdexplorer.com/wp-content/uploads/2025/11/batch_IMG_8965-1024x704.jpg" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php elseif (stripos($room['name'], 'Bamboo Creek Cottage') !== false): ?>
                        <img src="https://a0.muscache.com/im/pictures/hosting/Hosting-731392589633894464/original/b452ba4e-3de1-4c38-a3ca-2e77302b3ee6.jpeg" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php elseif (stripos($room['name'], 'Mossy Ridge Villa') !== false): ?>
                        <img src="https://pix8.agoda.net/hotelImages/59228966/0/40f837c9c61ada1aa428fbbb469a5076.jpg?va=1&ce=0&s=1024x" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php elseif (stripos($room['name'], 'Horizon Suite') !== false): ?>
                        <img src="https://gallery.streamlinevrs.com/units-gallery/00/0D/E4/image_167597382.jpeg" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php elseif ($room['category_id'] == 1): ?>
                        <img src="https://pix10.agoda.net/hotelImages/6180845/-1/ba980f3789236a5b9c73f42451dab346.jpg?ce=0&s=1024x768" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                        <img src="https://static.tripzilla.ph/media/119928/conversions/eco-saddle-w1024.webp" alt="<?= htmlspecialchars($room['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php endif; ?>
                    
                    <?php if ($room['featured']): ?>
                    <div style="position:absolute;top:1rem;left:1rem;background:var(--ochre);color:white;padding:.25rem .7rem;border-radius:20px;font-size:.72rem;font-weight:700;font-family:var(--font-head);letter-spacing:.08em;text-transform:uppercase;z-index:2;">
                        <i class="fas fa-star fa-xs"></i> Featured
                    </div>
                    <?php endif; ?>
                </div>

                <div style="flex:1;padding:2rem;display:flex;flex-direction:column;justify-content:space-between">
                    <div>
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.5rem;gap:1rem;flex-wrap:wrap">
                            <div>
                                <div class="card-tag"><?= htmlspecialchars($room['category_name']) ?></div>
                                <h3 style="font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--jungle-dark);line-height:1.2;margin:.3rem 0"><?= htmlspecialchars($room['name']) ?></h3>
                                <div style="font-size:.85rem;color:var(--text-light)"><i class="fas fa-door-open fa-xs" style="margin-right:.3rem"></i>Room <?= htmlspecialchars($room['room_number']) ?></div>
                            </div>
                            <div style="text-align:right;flex-shrink:0">
                                <div class="price-tag"><?= formatCurrency($room['price_per_night']) ?> <span>/ night</span></div>
                                <?php if ($nights > 1): ?>
                                <div style="font-size:.82rem;color:var(--ochre);font-weight:600;margin-top:.2rem"><?= formatCurrency($total) ?> total (<?=$nights?> nights)</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p style="font-size:.9rem;color:var(--text-mid);line-height:1.6;margin-bottom:1rem;max-width:600px"><?= htmlspecialchars(substr($room['description'],0,150)) ?>…</p>
                        <div class="amenity-list">
                            <span class="amenity-badge"><i class="fas fa-users"></i> Max <?=$room['max_occupancy']?> guests</span>
                            <?php if ($room['size_sqm']): ?><span class="amenity-badge"><i class="fas fa-expand-arrows-alt"></i> <?=$room['size_sqm']?> m²</span><?php endif; ?>
                            <span class="amenity-badge"><i class="fas fa-eye"></i> <?= htmlspecialchars($room['view_type']) ?></span>
                            <span class="amenity-badge"><i class="fas fa-layer-group"></i> Floor <?=$room['floor']?></span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--stone);flex-wrap:wrap;gap:.75rem">
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <span class="badge <?= $room['status']==='available'?'badge-success':'badge-warning' ?>">
                                <i class="fas <?=$room['status']==='available'?'fa-check':'fa-clock'?> fa-xs"></i>
                                <?= ucfirst($room['status']) ?>
                            </span>
                        </div>
                        <div style="display:flex;gap:.75rem">
                            <a href="<?= SITE_URL ?>/guest/room-detail.php?id=<?=$room['id']?>&check_in=<?=$check_in?>&check_out=<?=$check_out?>&guests=<?=$guests?>" class="btn btn-outline btn-sm">View Details</a>
                            <?php if ($room['status']==='available'): ?>
                            <a href="<?= SITE_URL ?>/guest/bookings.php?room_id=<?=$room['id']?>&check_in=<?=$check_in?>&check_out=<?=$check_out?>&guests=<?=$guests?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-calendar-plus"></i> Book Now
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>