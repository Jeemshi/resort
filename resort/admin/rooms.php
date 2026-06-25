<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'rooms';
$admin_title = 'Rooms';
$db = getDB();

$msg = '';
$err = '';
$action = $_GET['action'] ?? '';

// Delete room
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($db->query("DELETE FROM rooms WHERE id=$id")) $msg = 'Room deleted.';
    else $err = 'Cannot delete room — it may have existing bookings.';
}

// Toggle status
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $cur = $db->query("SELECT status FROM rooms WHERE id=$id")->fetch_assoc()['status'];
    $new = ($cur === 'available') ? 'maintenance' : 'available';
    $db->query("UPDATE rooms SET status='$new' WHERE id=$id");
    $msg = "Room status set to $new.";
}

// Add / Edit room
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fa = $_POST['form_action'] ?? '';
    if ($fa === 'save_room') {
        $rid      = (int)($_POST['room_id'] ?? 0);
        $rno      = sanitize($_POST['room_number']??'');
        $cat      = (int)$_POST['category_id'];
        $name     = sanitize($_POST['name']??'');
        $desc     = sanitize($_POST['description']??'');
        $price    = (float)$_POST['price_per_night'];
        $occ      = (int)$_POST['max_occupancy'];
        $floor    = (int)$_POST['floor'];
        $size     = (float)($_POST['size_sqm']??0);
        $view     = sanitize($_POST['view_type']??'');
        $featured = isset($_POST['featured']) ? 1 : 0;

        // 0. Validate room number (no duplicates, no special-chars-only, required)
        $rno_clean = preg_replace('/[^A-Za-z0-9\-]/', '', $rno);
        if (empty($rno) || strlen($rno_clean) < 2) {
            $err = 'Room number must contain at least 2 alphanumeric characters (e.g. JCV-03).';
        } elseif (empty($name)) {
            $err = 'Room name is required.';
        } elseif ($price <= 0) {
            $err = 'Price per night must be greater than 0.';
        } else {
            $chk_dup = $db->prepare("SELECT id FROM rooms WHERE room_number=? AND id!=?");
            $chk_dup->bind_param('si', $rno, $rid);
            $chk_dup->execute();
            if ($chk_dup->get_result()->num_rows > 0) {
                $err = "Room number \"$rno\" already exists. Please use a unique room number.";
            }
        }

        if (!$err) {
        // 1. Save or Update Room Record First
        if ($rid) {
            $db->query("UPDATE rooms SET room_number='$rno',category_id=$cat,name='$name',description='$desc',price_per_night=$price,max_occupancy=$occ,floor=$floor,size_sqm=$size,view_type='$view',featured=$featured WHERE id=$rid");
            $target_room_id = $rid;
            $msg = 'Room updated.';
        } else {
            $db->query("INSERT INTO rooms (room_number,category_id,name,description,price_per_night,max_occupancy,floor,size_sqm,view_type,featured) VALUES('$rno',$cat,'$name','$desc',$price,$occ,$floor,$size,'$view',$featured)");
            $target_room_id = $db->insert_id; // Capture the new auto-incremented room ID
            $msg = 'Room added.';
        }

        // 2. Save amenity checkboxes for this room
        if ($target_room_id) {
            $db->query("DELETE FROM room_amenities WHERE room_id=$target_room_id");
            $amenity_ids = isset($_POST['amenities']) ? (array)$_POST['amenities'] : [];
            foreach ($amenity_ids as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) $db->query("INSERT IGNORE INTO room_amenities (room_id, amenity_id) VALUES ($target_room_id, $aid)");
            }
        }

        // 3. Handle File Upload into the room_images Table
        if ($target_room_id && isset($_FILES['room_image']) && $_FILES['room_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['room_image']['tmp_name'];
            $orig_name = $_FILES['room_image']['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            
            // File Validation
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed)) {
                // Generate a completely clean, unique name to prevent collisions
                $new_filename = 'room_' . $target_room_id . '_' . time() . '.' . $ext;
                
                // Set path to point to your central root '/uploads/' directory
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    // Check if this room already has an image. If not, make this one the primary thumbnail
                    $check = $db->query("SELECT id FROM room_images WHERE room_id = $target_room_id LIMIT 1");
                    $is_primary = ($check->num_rows === 0) ? 1 : 0;

                    // Insert the image record securely using a prepared statement
                    $stmt = $db->prepare("INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $target_room_id, $new_filename, $is_primary);
                    $stmt->execute();
                }
            } else {
                $err = 'Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.';
            }
        }
        } // end if(!$err) validation
    }
}

// Fetch Categories Map
$categories = $db->query("SELECT * FROM room_categories ORDER BY name");
$cat_map = [];
while ($c = $categories->fetch_assoc()) $cat_map[$c['id']] = $c;
$categories->data_seek(0);

$search   = sanitize($_GET['search'] ?? '');
$cat_f    = (int)($_GET['category'] ?? 0);
$status_f = sanitize($_GET['status'] ?? '');

$where = '1=1';
if ($search)   $where .= " AND (r.name LIKE '%$search%' OR r.room_number LIKE '%$search%')";
if ($cat_f)    $where .= " AND r.category_id=$cat_f";
if ($status_f) $where .= " AND r.status='$status_f'";

// Modified Query: Sub-select queries the room_images table to pull the primary image filename
$rooms = $db->query("
    SELECT r.*, rc.name cat_name,
           (SELECT COUNT(*) FROM bookings b WHERE b.room_id=r.id) booking_count,
           (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=1 LIMIT 1) as primary_image
    FROM rooms r JOIN room_categories rc ON r.category_id=rc.id
    WHERE $where ORDER BY r.room_number ASC
");

// Edit target
$edit_room = null;
$edit_room_amenities = [];
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_room = $db->query("SELECT * FROM rooms WHERE id=$eid")->fetch_assoc();
    $ea_res = $db->query("SELECT amenity_id FROM room_amenities WHERE room_id=$eid");
    while ($ea = $ea_res->fetch_assoc()) $edit_room_amenities[] = $ea['amenity_id'];
}
// All amenities for checkbox list
$all_amenities = [];
$am_res = $db->query("SELECT * FROM amenities ORDER BY category, name");
while ($am = $am_res->fetch_assoc()) $all_amenities[] = $am;

include __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
<?php
$room_stats = [
    ['available','fa-check-circle','green'],['occupied','fa-user','orange'],
    ['maintenance','fa-tools','red'],['reserved','fa-lock','dark'],
];
foreach ($room_stats as [$st,$ico,$col]):
    $cnt = $db->query("SELECT COUNT(*) c FROM rooms WHERE status='$st'")->fetch_assoc()['c'];
?>
<div class="stat-card <?=$col?>"><div class="stat-icon"><i class="fas <?=$ico?>"></i></div>
<div><div class="stat-label"><?= ucfirst($st) ?></div><div class="stat-value"><?=$cnt?></div></div></div>
<?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr <?= $edit_room ? '380px' : '' ?>;gap:1.5rem;align-items:start">

<div class="admin-card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;flex:1">
            <input type="text" name="search" placeholder="Search room…" value="<?= htmlspecialchars($search) ?>">
            <select name="category">
                <option value="0">All Types</option>
                <?php foreach ($cat_map as $c): ?>
                <option value="<?=$c['id']?>" <?=$cat_f==$c['id']?'selected':''?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['available','occupied','maintenance','reserved'] as $s): ?>
                <option value="<?=$s?>" <?=$status_f===$s?'selected':''?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="rooms.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
        <button onclick="document.getElementById('roomFormPanel').style.display='block'; document.getElementById('editRoomId').value=''; document.getElementById('roomFormTitle').textContent='Add Room'; document.getElementById('roomForm').reset();" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Room
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Image</th><th>Room #</th><th>Name</th><th>Category</th><th>Rate/Night</th><th>Occupancy</th><th>View</th><th>Featured</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($rooms && $rooms->num_rows > 0):
                while ($r = $rooms->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php if (!empty($r['primary_image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($r['primary_image']) ?>" alt="Thumbnail" style="width:50px;height:38px;object-fit:cover;border-radius:4px;border:1px solid #ddd;display:block;">
                    <?php else: ?>
                        <div style="width:50px;height:38px;background:#f2f2f2;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.75rem;"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700;color:var(--canopy)"><?= htmlspecialchars($r['room_number']) ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($r['name']) ?></td>
                <td style="font-size:.83rem;color:var(--text-mid)"><?= htmlspecialchars($r['cat_name']) ?></td>
                <td style="font-weight:700"><?= formatCurrency($r['price_per_night']) ?></td>
                <td style="text-align:center"><?= $r['max_occupancy'] ?> <i class="fas fa-user fa-xs" style="color:var(--text-light)"></i></td>
                <td style="font-size:.82rem"><?= htmlspecialchars($r['view_type']??'—') ?></td>
                <td style="text-align:center"><?= $r['featured'] ? '<i class="fas fa-star" style="color:var(--ochre)"></i>' : '—' ?></td>
                <td style="text-align:center;font-weight:600"><?= $r['booking_count'] ?></td>
                <td><span class="badge <?= $r['status']==='available'?'badge-success':($r['status']==='occupied'?'badge-warning':($r['status']==='maintenance'?'badge-danger':'badge-info')) ?>"><?= ucfirst($r['status']) ?></span></td>
                <td>
                    <div class="action-btns">
                        <button onclick="editRoom(<?= htmlspecialchars(json_encode($r)) ?>)" class="btn btn-outline btn-sm" title="Edit"><i class="fas fa-edit"></i></button>
                        <a href="?action=toggle_status&id=<?=$r['id']?>" class="btn btn-dark btn-sm" title="Toggle Status"><i class="fas fa-wrench"></i></a>
                        <a href="?action=delete&id=<?=$r['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete this room permanently?" title="Delete"><i class="fas fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="11"><div class="empty-state"><i class="fas fa-door-open"></i> No rooms found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="roomFormPanel" style="display:<?= $edit_room ? 'block' : 'none' ?>">
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title" id="roomFormTitle"><?= $edit_room ? 'Edit Room' : 'Add Room' ?></span>
            <button onclick="document.getElementById('roomFormPanel').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:1rem"><i class="fas fa-times"></i></button>
        </div>
        
        <form method="POST" id="roomForm" enctype="multipart/form-data" style="padding:1.25rem">
            <input type="hidden" name="form_action" value="save_room">
            <input type="hidden" name="room_id" id="editRoomId" value="<?= $edit_room['id'] ?? '' ?>">
            <div class="form-group"><label class="form-label">Room Number *</label><input type="text" name="room_number" id="f_rno" class="form-control" required value="<?= htmlspecialchars($edit_room['room_number']??'') ?>" placeholder="e.g. JCV-03"></div>
            <div class="form-group"><label class="form-label">Category *</label>
                <select name="category_id" id="f_cat" class="form-control" required>
                    <?php $categories->data_seek(0); while ($c=$categories->fetch_assoc()): ?>
                    <option value="<?=$c['id']?>" <?=($edit_room['category_id']??'')==$c['id']?'selected':''?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Room Name *</label><input type="text" name="name" id="f_name" class="form-control" required value="<?= htmlspecialchars($edit_room['name']??'') ?>" placeholder="e.g. Mossy Ridge Villa"></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="f_desc" class="form-control" rows="3"><?= htmlspecialchars($edit_room['description']??'') ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Price/Night (₱) *</label><input type="number" name="price_per_night" id="f_price" class="form-control" required step="0.01" value="<?= $edit_room['price_per_night']??'' ?>"></div>
                <div class="form-group"><label class="form-label">Max Occupancy</label><input type="number" name="max_occupancy" id="f_occ" class="form-control" value="<?= $edit_room['max_occupancy']??2 ?>" min="1" max="20"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Floor</label><input type="number" name="floor" id="f_floor" class="form-control" value="<?= $edit_room['floor']??1 ?>" min="1"></div>
                <div class="form-group"><label class="form-label">Size (m²)</label><input type="number" name="size_sqm" id="f_size" class="form-control" step="0.01" value="<?= $edit_room['size_sqm']??'' ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">View Type</label><input type="text" name="view_type" id="f_view" class="form-control" value="<?= htmlspecialchars($edit_room['view_type']??'') ?>" placeholder="e.g. Forest Canopy"></div>
            
            <div class="form-group">
                <label class="form-label">Room Gallery Image</label>
                <input type="file" name="room_image" id="f_img" class="form-control" accept="image/*">
                <small style="color:var(--text-light); display:block; margin-top:4px;">Supports JPG, JPEG, PNG or WEBP formats</small>
            </div>

            <!-- Amenities Checkboxes -->
            <div class="form-group">
                <label class="form-label">Room Amenities</label>
                <div id="amenityCheckboxes" style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem .75rem;padding:.75rem;background:#f8f9fa;border-radius:8px;border:1px solid #e5e7eb;max-height:220px;overflow-y:auto">
                <?php
                $cat_group = '';
                foreach ($all_amenities as $am):
                    if ($am['category'] !== $cat_group):
                        $cat_group = $am['category'];
                ?>
                    <div style="grid-column:1/-1;font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--text-light);margin-top:.4rem;letter-spacing:.05em"><?= htmlspecialchars($cat_group) ?></div>
                <?php endif; ?>
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;cursor:pointer;color:#333;padding:.2rem 0">
                        <input type="checkbox" name="amenities[]" value="<?= $am['id'] ?>" style="width:auto;flex-shrink:0"
                               class="amenity-cb" data-amenity-id="<?= $am['id'] ?>"
                               <?= in_array($am['id'], $edit_room_amenities) ? 'checked' : '' ?>>
                        <i class="fas <?= htmlspecialchars($am['icon']??'fa-star') ?> fa-xs" style="color:var(--jungle);width:12px"></i>
                        <?= htmlspecialchars($am['name']) ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem">
                <input type="checkbox" name="featured" id="f_feat" style="width:auto" <?= ($edit_room['featured']??0)?'checked':'' ?>>
                <label for="f_feat" style="font-size:.88rem;cursor:pointer;color:var(--text-mid)">Mark as Featured Room</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Save Room</button>
        </form>
    </div>
</div>
</div>

<script>
function editRoom(r) {
    document.getElementById('roomFormPanel').style.display = 'block';
    document.getElementById('roomFormTitle').textContent = 'Edit Room';
    document.getElementById('editRoomId').value = r.id;
    document.getElementById('f_rno').value   = r.room_number;
    document.getElementById('f_cat').value   = r.category_id;
    document.getElementById('f_name').value  = r.name;
    document.getElementById('f_desc').value  = r.description;
    document.getElementById('f_price').value = r.price_per_night;
    document.getElementById('f_occ').value   = r.max_occupancy;
    document.getElementById('f_floor').value = r.floor;
    document.getElementById('f_size').value  = r.size_sqm;
    document.getElementById('f_view').value  = r.view_type;
    document.getElementById('f_feat').checked= r.featured == 1;
    document.getElementById('f_img').value = '';

    // Load amenities via AJAX
    fetch('ajax_room_amenities.php?room_id=' + r.id)
        .then(res => res.json())
        .then(ids => {
            document.querySelectorAll('.amenity-cb').forEach(cb => {
                cb.checked = ids.includes(parseInt(cb.value));
            });
        });

    document.getElementById('roomFormPanel').scrollIntoView({behavior:'smooth'});
}
</script>

<?php include __DIR__ . '/layout-bottom.php'; ?>