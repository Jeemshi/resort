<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'services';
$admin_title = 'Services';
$db = getDB();

$msg = '';
$err = '';

// Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM services WHERE id=$id") ? $msg='Service deleted.' : $err='Cannot delete service.';
}

// Toggle availability
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $cur = $db->query("SELECT available FROM services WHERE id=$id")->fetch_assoc()['available'];
    $new = $cur ? 0 : 1;
    $db->query("UPDATE services SET available=$new WHERE id=$id");
    $msg = 'Service availability updated.';
}

// Save service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'save_service') {
    $sid   = (int)($_POST['service_id']??0);
    $name  = sanitize($_POST['name']??'');
    $desc  = sanitize($_POST['description']??'');
    $price = (float)$_POST['price'];
    $cat   = sanitize($_POST['category']??'');
    $icon  = sanitize($_POST['icon']??'fa-star');
    $avail = isset($_POST['available']) ? 1 : 0;

    if ($sid) {
        $db->query("UPDATE services SET name='$name',description='$desc',price=$price,category='$cat',icon='$icon',available=$avail WHERE id=$sid");
        $msg = 'Service updated.';
    } else {
        $db->query("INSERT INTO services (name,description,price,category,icon,available) VALUES('$name','$desc',$price,'$cat','$icon',$avail)");
        $msg = 'Service added.';
    }
}

$search = sanitize($_GET['search'] ?? '');
$cat_f  = sanitize($_GET['category'] ?? '');

$where = '1=1';
if ($search) $where .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
if ($cat_f)  $where .= " AND category='$cat_f'";

$services = $db->query("SELECT * FROM services WHERE $where ORDER BY category, name");
$cats_q   = $db->query("SELECT DISTINCT category FROM services ORDER BY category");
$cats     = [];
while ($c = $cats_q->fetch_assoc()) $cats[] = $c['category'];

$edit_svc = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit_svc = $db->query("SELECT * FROM services WHERE id=$eid")->fetch_assoc();
}

// Totals
$total_svcs    = $db->query("SELECT COUNT(*) c FROM services")->fetch_assoc()['c'];
$active_svcs   = $db->query("SELECT COUNT(*) c FROM services WHERE available=1")->fetch_assoc()['c'];
$svc_revenue   = $db->query("SELECT IFNULL(SUM(bs.total_price),0) v FROM booking_services bs")->fetch_assoc()['v'];

include __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="stat-card green"><div class="stat-icon"><i class="fas fa-concierge-bell"></i></div><div><div class="stat-label">Total Services</div><div class="stat-value"><?=$total_svcs?></div></div></div>
    <div class="stat-card orange"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-label">Active</div><div class="stat-value"><?=$active_svcs?></div></div></div>
    <div class="stat-card dark"><div class="stat-icon"><i class="fas fa-coins"></i></div><div><div class="stat-label">Service Revenue</div><div class="stat-value" style="font-size:1.2rem"><?=formatCurrency($svc_revenue)?></div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr <?=$edit_svc?'380px':''?>;gap:1.5rem;align-items:start">

<div class="admin-card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;flex:1">
            <input type="text" name="search" placeholder="Search services…" value="<?= htmlspecialchars($search) ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($cats as $c): ?>
                <option value="<?=$c?>" <?=$cat_f===$c?'selected':''?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="<?= SITE_URL ?>/admin/services.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
        <button onclick="document.getElementById('svcPanel').style.display='block';document.getElementById('svcId').value='';document.getElementById('svcFormTitle').textContent='Add Service';document.getElementById('svcForm').reset();document.getElementById('svcAvail').checked=true" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Service
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Icon</th><th>Name</th><th>Category</th><th>Price</th><th>Times Booked</th><th>Revenue</th><th>Available</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($services && $services->num_rows > 0):
                while ($s = $services->fetch_assoc()):
                    $booked = $db->query("SELECT COUNT(*) c, IFNULL(SUM(total_price),0) r FROM booking_services WHERE service_id={$s['id']}")->fetch_assoc();
            ?>
            <tr>
                <td style="text-align:center">
                    <div style="width:36px;height:36px;background:var(--mist);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;color:var(--canopy)">
                        <i class="fas <?= htmlspecialchars($s['icon']??'fa-star') ?>"></i>
                    </div>
                </td>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                    <div style="font-size:.78rem;color:var(--text-light)"><?= htmlspecialchars(substr($s['description'],0,55)) ?>…</div>
                </td>
                <td><span class="badge badge-info"><?= htmlspecialchars($s['category']) ?></span></td>
                <td style="font-weight:700;color:var(--jungle-dark)"><?= formatCurrency($s['price']) ?></td>
                <td style="text-align:center;font-weight:600"><?= $booked['c'] ?></td>
                <td style="font-weight:600;color:var(--canopy)"><?= formatCurrency($booked['r']) ?></td>
                <td style="text-align:center">
                    <a href="?action=toggle&id=<?=$s['id']?>" title="Toggle">
                        <i class="fas fa-toggle-<?=$s['available']?'on':'off'?>" style="font-size:1.2rem;color:<?=$s['available']?'var(--canopy)':'var(--stone-dark)'?>"></i>
                    </a>
                </td>
                <td>
                    <div class="action-btns">
                        <button onclick="editSvc(<?= htmlspecialchars(json_encode($s)) ?>)" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></button>
                        <a href="?action=delete&id=<?=$s['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete this service?"><i class="fas fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-concierge-bell"></i> No services found</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Service Form Panel -->
<div id="svcPanel" style="display:<?=$edit_svc?'block':'none'?>">
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title" id="svcFormTitle"><?=$edit_svc?'Edit Service':'Add Service'?></span>
            <button onclick="document.getElementById('svcPanel').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--text-light)"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="svcForm" style="padding:1.25rem">
            <input type="hidden" name="form_action" value="save_service">
            <input type="hidden" name="service_id" id="svcId" value="<?=$edit_svc['id']??''?>">
            <div class="form-group"><label class="form-label">Service Name *</label><input type="text" name="name" id="f_sname" class="form-control" required value="<?= htmlspecialchars($edit_svc['name']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="f_sdesc" class="form-control" rows="3"><?= htmlspecialchars($edit_svc['description']??'') ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Price (₱) *</label><input type="number" name="price" id="f_sprice" class="form-control" step="0.01" required value="<?=$edit_svc['price']??''?>"></div>
                <div class="form-group"><label class="form-label">Category</label>
                    <input type="text" name="category" id="f_scat" class="form-control" list="catlist" value="<?= htmlspecialchars($edit_svc['category']??'') ?>" placeholder="Adventure, Wellness…">
                    <datalist id="catlist"><?php foreach ($cats as $c) echo "<option value='$c'>"; ?></datalist>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">FontAwesome Icon Class</label>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <input type="text" name="icon" id="f_sicon" class="form-control" value="<?= htmlspecialchars($edit_svc['icon']??'fa-star') ?>" placeholder="fa-hiking" oninput="document.getElementById('iconPreview').className='fas '+this.value">
                    <div style="width:40px;height:40px;background:var(--mist);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--canopy);flex-shrink:0">
                        <i class="fas <?= htmlspecialchars($edit_svc['icon']??'fa-star') ?>" id="iconPreview"></i>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem">
                <input type="checkbox" name="available" id="svcAvail" style="width:auto" <?=($edit_svc['available']??1)?'checked':''?>>
                <label for="svcAvail" style="font-size:.88rem;cursor:pointer;color:var(--text-mid)">Available for booking</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Save Service</button>
        </form>
    </div>
</div>
</div>

<script>
function editSvc(s) {
    document.getElementById('svcPanel').style.display = 'block';
    document.getElementById('svcFormTitle').textContent = 'Edit Service';
    document.getElementById('svcId').value      = s.id;
    document.getElementById('f_sname').value    = s.name;
    document.getElementById('f_sdesc').value    = s.description;
    document.getElementById('f_sprice').value   = s.price;
    document.getElementById('f_scat').value     = s.category;
    document.getElementById('f_sicon').value    = s.icon;
    document.getElementById('iconPreview').className = 'fas ' + s.icon;
    document.getElementById('svcAvail').checked = s.available == 1;
    document.getElementById('svcPanel').scrollIntoView({behavior:'smooth'});
}
</script>

<?php include __DIR__ . '/layout-bottom.php'; ?>
