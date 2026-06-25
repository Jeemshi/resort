<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin(); // Restrict page access to authenticated admins

$admin_page  = 'rooms'; // Sets the active tab highlighting in your layout sidebar
$admin_title = 'Room Status Override Manager';
$db = getDB();

$msg = '';
$err = '';

// Handle Manual Status Change Request
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = sanitize($_GET['action']);
    
    // Explicit status updates matching your database structure
    if ($action === 'set_available') {
        if ($db->query("UPDATE rooms SET status='available' WHERE id=$id")) {
            $msg = "Room set to Available successfully.";
        } else {
            $err = "Database error. Failed to alter status.";
        }
    } elseif ($action === 'set_occupied') {
        if ($db->query("UPDATE rooms SET status='occupied' WHERE id=$id")) {
            $msg = "Room explicitly flagged as Occupied.";
        } else {
            $err = "Database error. Failed to alter status.";
        }
    } elseif ($action === 'set_maintenance') {
        if ($db->query("UPDATE rooms SET status='maintenance' WHERE id=$id")) {
            $msg = "Room flagged for Cleaning / Maintenance.";
        } else {
            $err = "Database error. Failed to alter status.";
        }
    }
}

// Fetch Metrics Summaries for the Header Cards
$total_q = $db->query("SELECT COUNT(*) as total FROM rooms")->fetch_assoc();
$total_rooms = $total_q['total'] ?? 0;

$avail_q = $db->query("SELECT COUNT(*) as total FROM rooms WHERE status='available'")->fetch_assoc();
$available_rooms = $avail_q['total'] ?? 0;

$occ_q = $db->query("SELECT COUNT(*) as total FROM rooms WHERE status='occupied'")->fetch_assoc();
$occupied_rooms = $occ_q['total'] ?? 0;

$maint_q = $db->query("SELECT COUNT(*) as total FROM rooms WHERE status='maintenance'")->fetch_assoc();
$maintenance_rooms = $maint_q['total'] ?? 0;

// Fetch all rooms ordered by their floor and room identifier
$rooms_list = $db->query("SELECT * FROM rooms ORDER BY floor ASC, room_number ASC");

include __DIR__ . '/layout-top.php'; // Inject standard top header layout
?>

<?php if ($msg): ?>
    <div class="alert alert-success" style="background:#d4edda; color:#155724; padding:0.85rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius); border-left:5px solid var(--jungle);">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger" style="background:#f8d7da; color:#721c24; padding:0.85rem 1.25rem; margin-bottom:1.5rem; border-radius:var(--radius); border-left:5px solid var(--ember);">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
    <div class="stat-card" style="background:var(--surface); padding:1.25rem; border-radius:var(--radius); box-shadow:0 2px 8px rgba(0,0,0,0.04); border-left:4px solid var(--jungle);">
        <div style="font-size:0.78rem; color:var(--text-light); text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Available / Free</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--jungle); margin-top:0.25rem;"><?= $available_rooms ?> <span style="font-size:1rem; font-weight:400; color:var(--text-light);">/ <?= $total_rooms ?></span></div>
    </div>
    <div class="stat-card" style="background:var(--surface); padding:1.25rem; border-radius:var(--radius); box-shadow:0 2px 8px rgba(0,0,0,0.04); border-left:4px solid var(--ochre);">
        <div style="font-size:0.78rem; color:var(--text-light); text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Occupied Rooms</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--ochre); margin-top:0.25rem;"><?= $occupied_rooms ?></div>
    </div>
    <div class="stat-card" style="background:var(--surface); padding:1.25rem; border-radius:var(--radius); box-shadow:0 2px 8px rgba(0,0,0,0.04); border-left:4px solid var(--ember);">
        <div style="font-size:0.78rem; color:var(--text-light); text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Out of Order</div>
        <div style="font-size:1.8rem; font-weight:700; color:var(--ember); margin-top:0.25rem;"><?= $maintenance_rooms ?></div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header" style="padding:1.25rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
        <span class="admin-card-title" style="font-size:1.15rem; font-weight:600; color:var(--text-dark);">
            <i class="fas fa-door-open" style="color:var(--jungle); margin-right:0.5rem;"></i> Front-Desk Operational Override Grid
        </span>
    </div>
    
    <div style="padding:1.5rem; display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:1.25rem;">
        <?php if ($rooms_list && $rooms_list->num_rows > 0): ?>
            <?php while($room = $rooms_list->fetch_assoc()): 
                $status = $room['status'] ?? 'available';
                
                // Color mapping variables based on room state
                $border_color = 'var(--jungle)';
                $badge_style  = 'background:#e2f7ed; color:var(--jungle);';
                
                if ($status === 'occupied') {
                    $border_color = 'var(--ochre)';
                    $badge_style  = 'background:#fff3cd; color:#856404;';
                } elseif ($status === 'maintenance') {
                    $border_color = 'var(--ember)';
                    $badge_style  = 'background:#fce8e6; color:var(--ember);';
                }
            ?>
                <div class="room-status-item" style="background:#fff; border:1px solid #e1e4e8; border-top:5px solid <?= $border_color ?>; border-radius:var(--radius); padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 5px rgba(0,0,0,0.01);">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                            <strong style="font-size:1.2rem; color:var(--text-dark);">Room <?= htmlspecialchars($room['room_number']) ?></strong>
                            <span style="font-size:0.75rem; font-weight:700; padding:0.25rem 0.6rem; border-radius:50px; text-transform:uppercase; <?= $badge_style ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </div>
                        <div style="font-size:0.88rem; color:var(--text-mid); font-weight:500; margin-bottom:0.25rem;"><?= htmlspecialchars($room['name']) ?></div>
                        <div style="font-size:0.78rem; color:var(--text-light); margin-bottom:1.25rem;">
                            <i class="fas fa-layer-group"></i> Floor <?= htmlspecialchars($room['floor'] ?? '1') ?> &nbsp;·&nbsp; 
                            <i class="fas fa-users"></i> Max <?= htmlspecialchars($room['max_occupancy']) ?> Guests
                        </div>
                    </div>

                    <div style="border-top:1px dashed #eee; padding-top:0.85rem; display:flex; gap:0.4rem; justify-content:flex-end; align-items:center;">
                        <span style="font-size:0.72rem; color:var(--text-light); margin-right:auto; font-weight:600;">Change to:</span>
                        
                        <?php if ($status !== 'available'): ?>
                            <a href="?action=set_available&id=<?= $room['id'] ?>" class="btn" style="padding:0.3rem 0.5rem; font-size:0.72rem; background:none; border:1px solid var(--jungle); color:var(--jungle); border-radius:4px;" title="Set Free/Available">
                                <i class="fas fa-check"></i> Free
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($status !== 'occupied'): ?>
                            <a href="?action=set_occupied&id=<?= $room['id'] ?>" class="btn" style="padding:0.3rem 0.5rem; font-size:0.72rem; background:none; border:1px solid var(--ochre); color:#856404; border-radius:4px;" title="Set Occupied Manual Override">
                                <i class="fas fa-user-lock"></i> Occupy
                            </a>
                        <?php endif; ?>

                        <?php if ($status !== 'maintenance'): ?>
                            <a href="?action=set_maintenance&id=<?= $room['id'] ?>" class="btn" style="padding:0.3rem 0.5rem; font-size:0.72rem; background:none; border:1px solid var(--ember); color:var(--ember); border-radius:4px;" title="Set Out of Order / Cleaning">
                                <i class="fas fa-tools"></i> Clean
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column:1/-1; text-align:center; padding:4rem 2rem; color:var(--text-light);">
                <i class="fas fa-hotel" style="font-size:3rem; margin-bottom:1rem; display:block; color:#ccd1d9;"></i>
                No rooms found in the system database setup.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/layout-bottom.php'; // Closes application layout elements ?>