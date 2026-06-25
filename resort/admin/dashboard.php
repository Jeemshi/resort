<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin(); // Protects this page[cite: 7]

$db = getDB();

// Fetch metrics summary from database[cite: 7]
$room_count = $db->query("SELECT COUNT(*) as total FROM rooms")->fetch_assoc()['total']; //[cite: 7]
$booking_count = $db->query("SELECT COUNT(*) as total FROM bookings")->fetch_assoc()['total']; //[cite: 7]
$guest_count = $db->query("SELECT COUNT(*) as total FROM guests WHERE status='active'")->fetch_assoc()['total']; //[cite: 7]

// Fetch the 5 most recent bookings[cite: 7]
$recent_bookings = $db->query("
    SELECT b.*, g.first_name, g.last_name, r.room_number 
    FROM bookings b
    JOIN guests g ON b.guest_id = g.id
    JOIN rooms r ON b.room_id = r.id
    ORDER BY b.created_at DESC LIMIT 5
"); //[cite: 7]

// NEW LAYOUT HOOKS[cite: 7]
$admin_page  = 'dashboard'; //[cite: 7]
$admin_title = 'Dashboard Overview'; //[cite: 7]
include __DIR__ . '/layout-top.php'; //[cite: 7]
?>

<!-- STATS METRICS GRID -->
<div class="stats-grid">
    <!-- Total Rooms -->
    <div class="stat-card green">
        <div class="stat-icon">
            <i class="fas fa-bed"></i>
        </div>
        <div>
            <div class="stat-label">Total Rooms</div>
            <div class="stat-value"><?= htmlspecialchars($room_count) ?></div>
            <div class="stat-sub">Configured properties</div>
        </div>
    </div>

    <!-- Total Bookings -->
    <div class="stat-card orange">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <div class="stat-label">Total Reservations</div>
            <div class="stat-value"><?= htmlspecialchars($booking_count) ?></div>
            <div class="stat-sub">Lifetime bookings placed</div>
        </div>
    </div>

    <!-- Active Guests -->
    <div class="stat-card dark">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <div class="stat-label">Active Guests</div>
            <div class="stat-value"><?= htmlspecialchars($guest_count) ?></div>
            <div class="stat-sub">Verified community accounts</div>
        </div>
    </div>

    <!-- Quick Actions Menu Wrapper -->
    <div class="stat-card red">
        <div class="stat-icon">
            <i class="fas fa-bolt"></i>
        </div>
        <div>
            <div class="stat-label">Quick Links</div>
            <div style="margin-top: 0.25rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="rooms.php" class="badge badge-info" style="text-transform: none;"><i class="fas fa-plus"></i> Room</a>
                <a href="bookings.php" class="badge badge-secondary" style="text-transform: none;"><i class="fas fa-search"></i> Find Stay</a>
            </div>
        </div>
    </div>
</div>

<!-- DATA CONTAINER: RECENT ACTIVITY LOGS -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-history" style="margin-right: 0.5rem; color: var(--text-mid);"></i> Recent Reservations</h3>
        <a href="bookings.php" class="btn btn-outline btn-sm">View All Bookings</a>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference Code</th>
                    <th>Guest Details</th>
                    <th>Room Assigned</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                    <?php while ($b = $recent_bookings->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--jungle-dark); font-family: var(--font-head); letter-spacing: 0.02em;">
                                    #<?= htmlspecialchars($b['id']) ?>
                                </strong>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-dark);">
                                    <?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary">Room <?= htmlspecialchars($b['room_number']) ?></span>
                            </td>
                            <td><?= date('M d, Y', strtotime($b['check_in'])) ?></td>
                            <td><?= date('M d, Y', strtotime($b['check_out'])) ?></td>
                            <td>
                                <?php 
                                $status = $b['status'] ?? 'pending';
                                $badgeClass = 'badge-warning';
                                if ($status === 'confirmed' || $status === 'paid') $badgeClass = 'badge-success';
                                if ($status === 'cancelled') $badgeClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-light);">
                            <i class="fas fa-folder-open" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                            No recent booking reservations logs available yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/layout-bottom.php'; ?>