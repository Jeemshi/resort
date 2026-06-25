<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'reports';
$admin_title = 'Reports';
$db = getDB();

// Date range filter
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);
$type  = $_GET['type'] ?? 'overview';

$date_start = $month ? "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01" : "$year-01-01";
$date_end   = $month ? date('Y-m-t', strtotime($date_start)) : "$year-12-31";

// -- Overview KPIs --
$total_revenue     = $db->query("SELECT IFNULL(SUM(amount_paid),0) v FROM billing WHERE payment_date BETWEEN '$date_start' AND '$date_end'")->fetch_assoc()['v'];
$total_bookings    = $db->query("SELECT COUNT(*) c FROM bookings WHERE created_at BETWEEN '$date_start' AND '$date_end 23:59:59'")->fetch_assoc()['c'];
$cancelled         = $db->query("SELECT COUNT(*) c FROM bookings WHERE status='cancelled' AND created_at BETWEEN '$date_start' AND '$date_end 23:59:59'")->fetch_assoc()['c'];
$new_guests        = $db->query("SELECT COUNT(*) c FROM guests WHERE created_at BETWEEN '$date_start' AND '$date_end 23:59:59'")->fetch_assoc()['c'];
$avg_booking_value = $db->query("SELECT IFNULL(AVG(total_amount),0) v FROM bookings WHERE created_at BETWEEN '$date_start' AND '$date_end 23:59:59' AND status!='cancelled'")->fetch_assoc()['v'];

// Monthly revenue breakdown (for selected year)
$monthly_rev = $db->query("
    SELECT MONTH(payment_date) mo, MONTHNAME(payment_date) mon_name, SUM(amount_paid) rev, COUNT(*) cnt
    FROM billing WHERE YEAR(payment_date)=$year AND payment_status IN('paid','partial')
    GROUP BY MONTH(payment_date) ORDER BY MONTH(payment_date)
");
$monthly_data = [];
while ($r = $monthly_rev->fetch_assoc()) $monthly_data[$r['mo']] = $r;

// Top rooms by revenue
$top_rooms = $db->query("
    SELECT r.name, r.room_number, COUNT(b.id) bookings, SUM(b.total_amount) revenue, AVG(b.nights) avg_nights
    FROM rooms r JOIN bookings b ON b.room_id=r.id
    WHERE b.status!='cancelled' AND b.created_at BETWEEN '$date_start' AND '$date_end 23:59:59'
    GROUP BY r.id ORDER BY revenue DESC LIMIT 5
");

// Bookings by status
$by_status = $db->query("SELECT status, COUNT(*) c FROM bookings WHERE created_at BETWEEN '$date_start' AND '$date_end 23:59:59' GROUP BY status");
$status_data = [];
while ($r = $by_status->fetch_assoc()) $status_data[$r['status']] = $r['c'];

// Top services
$top_svcs = $db->query("
    SELECT s.name, SUM(bs.quantity) qty, SUM(bs.total_price) rev
    FROM booking_services bs JOIN services s ON bs.service_id=s.id
    JOIN bookings b ON bs.booking_id=b.id
    WHERE b.created_at BETWEEN '$date_start' AND '$date_end 23:59:59'
    GROUP BY s.id ORDER BY rev DESC LIMIT 5
");

// Guest origin (bookings per month)
$bookings_by_month = $db->query("
    SELECT MONTH(created_at) mo, MONTHNAME(created_at) name, COUNT(*) cnt
    FROM bookings WHERE YEAR(created_at)=$year GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)
");
$bm_data = [];
while ($r = $bookings_by_month->fetch_assoc()) $bm_data[$r['mo']] = $r;

$max_rev = !empty($monthly_data) ? max(array_column($monthly_data,'rev')) : 1;
$max_bm  = !empty($bm_data) ? max(array_column($bm_data,'cnt')) : 1;

include __DIR__ . '/layout-top.php';
?>

<!-- Filters -->
<div class="admin-card" style="margin-bottom:1.5rem">
    <div style="padding:1rem 1.5rem">
        <form method="GET" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
            <div class="booking-field">
                <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--text-light);display:block;margin-bottom:.3rem">Year</label>
                <select name="year" class="form-control" style="width:auto;padding:.4rem .75rem">
                    <?php for ($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                    <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="booking-field">
                <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--text-light);display:block;margin-bottom:.3rem">Month</label>
                <select name="month" class="form-control" style="width:auto;padding:.4rem .75rem">
                    <option value="0">All Months</option>
                    <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?=$m?>" <?=$m==$month?'selected':''?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:1.2rem"><i class="fas fa-filter"></i> Apply</button>
            <a href="<?= SITE_URL ?>/admin/reports.php" class="btn btn-outline btn-sm" style="margin-top:1.2rem">Reset</a>
            <span style="margin-top:1.2rem;font-size:.82rem;color:var(--text-light)">
                Showing: <strong><?= $month ? date('F Y', strtotime($date_start)) : "Full Year $year" ?></strong>
            </span>
        </form>
    </div>
</div>

<!-- KPI Summary -->
<div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div><div class="stat-label">Total Revenue</div><div class="stat-value" style="font-size:1.3rem"><?= formatCurrency($total_revenue) ?></div></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div><div class="stat-label">Total Bookings</div><div class="stat-value"><?= $total_bookings ?></div><div class="stat-sub"><?= $cancelled ?> cancelled</div></div>
    </div>
    <div class="stat-card dark">
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
        <div><div class="stat-label">New Guests</div><div class="stat-value"><?= $new_guests ?></div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-label">Avg Booking Value</div><div class="stat-value" style="font-size:1.2rem"><?= formatCurrency($avg_booking_value) ?></div></div>
    </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">

    <!-- Monthly Revenue Chart -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-chart-bar" style="color:var(--ochre);margin-right:.4rem"></i> Monthly Revenue — <?=$year?></span></div>
        <div style="padding:1.5rem">
            <div style="display:flex;align-items:flex-end;gap:8px;height:150px">
                <?php for ($m=1; $m<=12; $m++):
                    $rev = $monthly_data[$m]['rev'] ?? 0;
                    $h   = $max_rev > 0 ? max(4, round(($rev/$max_rev)*130)) : 4;
                    $mon = date('M', mktime(0,0,0,$m,1));
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="font-size:.55rem;color:var(--canopy);font-weight:700"><?= $rev > 0 ? '₱'.number_format($rev/1000,0).'k' : '' ?></div>
                    <div title="<?=$mon?>: <?=formatCurrency($rev)?>" style="width:100%;border-radius:3px 3px 0 0;background:<?=$m==$month?'var(--ochre)':'var(--canopy)'?>;height:<?=$h?>px;opacity:<?=$rev>0?'.9':'.2'?>;transition:.2s;cursor:pointer" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity='<?=$rev>0?'.9':'.2'?>'"></div>
                    <div style="font-size:.6rem;color:var(--text-light)"><?=$mon?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Bookings Volume Chart -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-chart-line" style="color:var(--canopy);margin-right:.4rem"></i> Bookings Volume — <?=$year?></span></div>
        <div style="padding:1.5rem">
            <div style="display:flex;align-items:flex-end;gap:8px;height:150px">
                <?php for ($m=1; $m<=12; $m++):
                    $cnt = $bm_data[$m]['cnt'] ?? 0;
                    $h   = $max_bm > 0 ? max(4, round(($cnt/$max_bm)*130)) : 4;
                    $mon = date('M', mktime(0,0,0,$m,1));
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="font-size:.6rem;color:var(--jungle-mid);font-weight:700"><?= $cnt > 0 ? $cnt : '' ?></div>
                    <div title="<?=$mon?>: <?=$cnt?> bookings" style="width:100%;border-radius:3px 3px 0 0;background:var(--jungle-mid);height:<?=$h?>px;opacity:<?=$cnt>0?'.85':'.15'?>;cursor:pointer" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity='<?=$cnt>0?'.85':'.15'?>'"></div>
                    <div style="font-size:.6rem;color:var(--text-light)"><?=$mon?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Tables Row -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem">

    <!-- Top Rooms -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-trophy" style="color:var(--ochre);margin-right:.4rem"></i> Top Rooms</span></div>
        <div style="padding:.5rem 0">
            <?php if ($top_rooms && $top_rooms->num_rows > 0):
                $rank = 1;
                while ($r = $top_rooms->fetch_assoc()): ?>
            <div style="padding:.85rem 1.25rem;border-bottom:1px solid #F0F2F5;display:flex;align-items:center;gap:.75rem">
                <div style="width:24px;height:24px;border-radius:50%;background:<?=$rank===1?'var(--ochre)':($rank===2?'var(--stone-dark)':'var(--mist)')  ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:<?=$rank<=2?'white':'var(--text-mid)'?>;flex-shrink:0"><?=$rank++?></div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:.85rem;color:var(--jungle-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['name']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-light)"><?= $r['bookings'] ?> bookings · <?= round($r['avg_nights'],1) ?> avg nights</div>
                </div>
                <div style="font-weight:700;font-size:.85rem;color:var(--canopy);white-space:nowrap"><?= formatCurrency($r['revenue']) ?></div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state" style="padding:2rem"><i class="fas fa-bed"></i> No data</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Status Breakdown -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-pie-chart" style="color:var(--jungle);margin-right:.4rem"></i> Booking Status</span></div>
        <div style="padding:1.25rem">
            <?php
            $st_colors = ['pending'=>'var(--ochre)','confirmed'=>'var(--canopy)','checked_in'=>'var(--jungle-mid)','checked_out'=>'var(--bark)','cancelled'=>'var(--ember)'];
            $st_total = array_sum($status_data);
            if ($st_total > 0):
                foreach ($st_colors as $st => $color):
                    $cnt = $status_data[$st] ?? 0;
                    $pct = $st_total > 0 ? round($cnt/$st_total*100) : 0;
                ?>
                <div style="margin-bottom:.75rem">
                    <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;font-size:.82rem">
                        <span style="color:var(--text-mid);text-transform:capitalize"><?= str_replace('_',' ',$st) ?></span>
                        <span style="font-weight:600;color:var(--jungle-dark)"><?=$cnt?> <span style="color:var(--text-light);font-weight:400">(<?=$pct?>%)</span></span>
                    </div>
                    <div style="background:var(--stone);border-radius:4px;height:8px;overflow:hidden">
                        <div style="width:<?=$pct?>%;height:100%;background:<?=$color?>;border-radius:4px;transition:width .4s"></div>
                    </div>
                </div>
                <?php endforeach;
            else: ?>
            <div class="empty-state" style="padding:1rem"><i class="fas fa-chart-pie"></i> No data</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Services -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-concierge-bell" style="color:var(--ember);margin-right:.4rem"></i> Top Services</span></div>
        <div style="padding:.5rem 0">
            <?php if ($top_svcs && $top_svcs->num_rows > 0):
                while ($s = $top_svcs->fetch_assoc()): ?>
            <div style="padding:.85rem 1.25rem;border-bottom:1px solid #F0F2F5;display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                <div style="min-width:0">
                    <div style="font-weight:600;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($s['name']) ?></div>
                    <div style="font-size:.75rem;color:var(--text-light)"><?= $s['qty'] ?> sold</div>
                </div>
                <div style="font-weight:700;font-size:.85rem;color:var(--canopy);white-space:nowrap"><?= formatCurrency($s['rev']) ?></div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state" style="padding:2rem"><i class="fas fa-concierge-bell"></i> No data</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/layout-bottom.php'; ?>
