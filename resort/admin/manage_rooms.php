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

        // Validate: room number must have at least 2 alphanumeric chars
        $rno_alpha = preg_replace('/[^A-Za-z0-9]/', '', $rno);
        if (empty($rno) || strlen($rno_alpha) < 2) {
            $err = 'Room number must contain at least 2 alphanumeric characters (e.g. JCV-03). No special-character-only values allowed.';
        } elseif (empty($name)) {
            $err = 'Room name is required.';
        } elseif ($price <= 0) {
            $err = 'Price per night must be greater than 0.';
        } elseif ($cat <= 0) {
            $err = 'Please select a valid room category.';
        } else {
            // Check duplicate room number (exclude current room on edit)
            $chk = $db->prepare("SELECT id FROM rooms WHERE room_number=? AND id!=?");
            $chk->bind_param('si', $rno, $rid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $err = "Room number "$rno" is already in use. Each room must have a unique number.";
            }
        }

        if (!$err) {
            if ($rid) {
                $db->query("UPDATE rooms SET room_number='$rno',category_id=$cat,name='$name',description='$desc',price_per_night=$price,max_occupancy=$occ,floor=$floor,size_sqm=$size,view_type='$view',featured=$featured WHERE id=$rid");
                $msg = 'Room updated.';
            } else {
                $db->query("INSERT INTO rooms (room_number,category_id,name,description,price_per_night,max_occupancy,floor,size_sqm,view_type,featured) VALUES('$rno',$cat,'$name','$desc',$price,$occ,$floor,$size,'$view',$featured)");
                $msg = 'Room added.';
            }
        }
    }
}

// Fetch
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

$rooms = $db->query("
    SELECT r.*, rc.name cat_name,
           (SELECT COUNT(*) FROM bookings b WHERE b.room_id=r.id) booking_count
    FROM rooms r JOIN room_categories rc ON r.category_id=rc.id
    WHERE $where ORDER BY r.room_number ASC
");

// Edit target
$edit_room = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_room = $db->query("SELECT * FROM rooms WHERE id=$eid")->fetch_assoc();
}

include __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Summary Cards -->
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

<!-- Rooms Table -->
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
            <a href="<?= SITE_URL ?>/admin/rooms.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
        <button onclick="document.getElementById('roomFormPanel').style.display='block'; document.getElementById('editRoomId').value=''; document.getElementById('roomFormTitle').textContent='Add Room'; document.getElementById('roomForm').reset();" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Room
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Room #</th><th>Name</th><th>Category</th><th>Rate/Night</th><th>Occupancy</th><th>View</th><th>Featured</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($rooms && $rooms->num_rows > 0):
                while ($r = $rooms->fetch_assoc()): ?>
            <tr>
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
            <tr><td colspan="10"><div class="empty-state"><i class="fas fa-door-open"></i> No rooms found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Room Form Panel -->
<div id="roomFormPanel" style="display:<?= $edit_room ? 'block' : 'none' ?>">
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title" id="roomFormTitle"><?= $edit_room ? 'Edit Room' : 'Add Room' ?></span>
            <button onclick="document.getElementById('roomFormPanel').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--text-light);font-size:1rem"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="roomForm" style="padding:1.25rem">
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
    document.getElementById('roomFormPanel').scrollIntoView({behavior:'smooth'});
}
</script>

<?php include __DIR__ . '/layout-bottom.php'; ?>