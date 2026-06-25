<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'guests';
$admin_title = 'Guests';
$db = getDB();

$msg = '';
$err = '';

// Handle actions
$action = $_GET['action'] ?? '';

// Toggle status
if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $cur = $db->query("SELECT status FROM guests WHERE id=$id")->fetch_assoc()['status'] ?? '';
    $new = ($cur === 'active') ? 'inactive' : 'active';
    $db->query("UPDATE guests SET status='$new' WHERE id=$id");
    $msg = "Guest status updated to $new.";
}

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->query("UPDATE guests SET status='banned' WHERE id=$id");
    $msg = "Guest has been banned.";
}

// Add guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'add_guest') {
    $fn    = sanitize($_POST['first_name']??'');
    $ln    = sanitize($_POST['last_name']??'');
    $em    = sanitize($_POST['email']??'');
    $ph    = sanitize($_POST['phone']??'');
    $pass  = password_hash($_POST['password']??'guest1234', PASSWORD_DEFAULT);
    $check = $db->prepare("SELECT id FROM guests WHERE email=?");
    $check->bind_param('s',$em); $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $err = 'Email already in use.';
    } else {
        $stmt = $db->prepare("INSERT INTO guests (first_name,last_name,email,phone,password) VALUES(?,?,?,?,?)");
        $stmt->bind_param('sssss',$fn,$ln,$em,$ph,$pass);
        $stmt->execute() ? $msg = 'Guest added successfully.' : $err = 'Failed to add guest.';
    }
}

// Filters & pagination
$search = sanitize($_GET['search'] ?? '');
$status_f = sanitize($_GET['status'] ?? '');
$per_page = 15;
$page_num = max(1,(int)($_GET['page']??1));
$offset = ($page_num-1)*$per_page;

$where = '1=1';
if ($search) $where .= " AND (g.first_name LIKE '%$search%' OR g.last_name LIKE '%$search%' OR g.email LIKE '%$search%' OR g.phone LIKE '%$search%')";
if ($status_f) $where .= " AND g.status='$status_f'";

$total = $db->query("SELECT COUNT(*) c FROM guests g WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total/$per_page);

$guests = $db->query("
    SELECT g.*,
        (SELECT COUNT(*) FROM bookings WHERE guest_id=g.id) booking_count,
        (SELECT IFNULL(SUM(b2.total_amount),0) FROM bookings b2 WHERE b2.guest_id=g.id AND b2.status NOT IN('cancelled')) total_spent
    FROM guests g WHERE $where ORDER BY g.created_at DESC LIMIT $per_page OFFSET $offset
");

include __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <?php
    $active_c   = $db->query("SELECT COUNT(*) c FROM guests WHERE status='active'")->fetch_assoc()['c'];
    $inactive_c = $db->query("SELECT COUNT(*) c FROM guests WHERE status='inactive'")->fetch_assoc()['c'];
    $banned_c   = $db->query("SELECT COUNT(*) c FROM guests WHERE status='banned'")->fetch_assoc()['c'];
    $mini = [['Active Guests',$active_c,'fa-user-check','green'],['Inactive',$inactive_c,'fa-user-clock','orange'],['Banned',$banned_c,'fa-user-slash','red']];
    foreach ($mini as $m): ?>
    <div class="stat-card <?= $m[3] ?>">
        <div class="stat-icon"><i class="fas <?= $m[2] ?>"></i></div>
        <div><div class="stat-label"><?= $m[0] ?></div><div class="stat-value"><?= $m[1] ?></div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;flex:1">
            <input type="text" name="search" placeholder="Search name, email, phone…" value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="active" <?=$status_f==='active'?'selected':''?>>Active</option>
                <option value="inactive" <?=$status_f==='inactive'?'selected':''?>>Inactive</option>
                <option value="banned" <?=$status_f==='banned'?'selected':''?>>Banned</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
            <a href="<?= SITE_URL ?>/admin/guests.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
        <button onclick="document.getElementById('addGuestModal').classList.add('active')" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add Guest
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Total Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($guests && $guests->num_rows > 0):
                    $i = $offset+1;
                    while ($g = $guests->fetch_assoc()): ?>
                <tr>
                    <td style="color:var(--text-light);font-size:.8rem"><?= $i++ ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--jungle-dark);display:flex;align-items:center;justify-content:center;color:var(--ochre-light);font-weight:700;font-size:.8rem;flex-shrink:0">
                                <?= strtoupper(substr($g['first_name'],0,1)) ?>
                            </div>
                            <span style="font-weight:600"><?= htmlspecialchars($g['first_name'].' '.$g['last_name']) ?></span>
                        </div>
                    </td>
                    <td style="font-size:.87rem;color:var(--text-mid)"><?= htmlspecialchars($g['email']) ?></td>
                    <td style="font-size:.87rem"><?= htmlspecialchars($g['phone'] ?: '—') ?></td>
                    <td style="text-align:center;font-weight:600"><?= $g['booking_count'] ?></td>
                    <td style="font-weight:600;color:var(--jungle-mid)"><?= formatCurrency($g['total_spent']) ?></td>
                    <td style="font-size:.82rem;color:var(--text-light)"><?= date('M j, Y', strtotime($g['created_at'])) ?></td>
                    <td><span class="badge <?= $g['status']==='active'?'badge-success':($g['status']==='banned'?'badge-danger':'badge-warning') ?>"><?= ucfirst($g['status']) ?></span></td>
                    <td>
                        <div class="action-btns">
                            <a href="?action=toggle&id=<?= $g['id'] ?>&<?= http_build_query(array_filter(['search'=>$search,'status'=>$status_f,'page'=>$page_num])) ?>" class="btn btn-outline btn-sm" title="Toggle Status">
                                <i class="fas fa-toggle-<?= $g['status']==='active'?'on':'off' ?>"></i>
                            </a>
                            <a href="<?= SITE_URL ?>/admin/bookings.php?guest_id=<?= $g['id'] ?>" class="btn btn-dark btn-sm" title="View Bookings">
                                <i class="fas fa-calendar"></i>
                            </a>
                            <a href="?action=delete&id=<?= $g['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Ban this guest? They will not be able to login." title="Ban">
                                <i class="fas fa-ban"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-users"></i> No guests found</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($p=1;$p<=$pages;$p++): ?>
        <a href="?page=<?=$p?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_f) ?>" class="page-btn <?=$p==$page_num?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Guest Modal -->
<div class="modal-overlay" id="addGuestModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New Guest</span>
            <button class="modal-close" onclick="document.getElementById('addGuestModal').classList.remove('active')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_action" value="add_guest">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                </div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                <div class="form-group"><label class="form-label">Initial Password</label><input type="text" name="password" class="form-control" value="wildnest2025" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addGuestModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Guest</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/layout-bottom.php'; ?>
