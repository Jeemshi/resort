<?php
require_once __DIR__ . '/../includes/config.php';

if (!isGuestLoggedIn()) {
    redirect(SITE_URL . '/guest/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$db = getDB();
$guest_id = (int)$_SESSION['guest_id'];
$booking_id = (int)($_GET['booking_id'] ?? 0);
$error = '';
$success = '';

// Verify this booking belongs to this guest and is in a state where add-ons make sense
$stmt = $db->prepare("
    SELECT b.*, r.name room_name, r.room_number,
           bil.invoice_number, bil.id as bill_id,
           bil.payment_status, bil.amount_paid
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN billing bil ON b.id = bil.booking_id
    WHERE b.id = ? AND b.guest_id = ?
");
$stmt->bind_param('ii', $booking_id, $guest_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    redirect(SITE_URL . '/guest/my_bookings.php');
}

// Only allow add-ons on pending, confirmed, or checked_in
$allowed_statuses = ['pending', 'confirmed', 'checked_in'];
if (!in_array($booking['status'], $allowed_statuses)) {
    redirect(SITE_URL . '/guest/my_bookings.php');
}

// Handle add-on submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_services'])) {
    $selected = $_POST['services'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    
    if (empty($selected)) {
        $error = 'Please select at least one service to add.';
    } else {
        $added_total = 0;
        foreach ($selected as $svc_id) {
            $svc_id = (int)$svc_id;
            $qty    = max(1, (int)($quantities[$svc_id] ?? 1));
            $svc    = $db->query("SELECT * FROM services WHERE id=$svc_id AND available=1")->fetch_assoc();
            if (!$svc) continue;
            
            $unit  = (float)$svc['price'];
            $total = $unit * $qty;
            $added_total += $total;

            $ins = $db->prepare("INSERT INTO booking_addon_services (booking_id, service_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('iiidd', $booking_id, $svc_id, $qty, $unit, $total);
            $ins->execute();
        }

        // Update billing total
        if ($added_total > 0 && $booking['bill_id']) {
            $new_total = (float)$booking['total_amount'] + $added_total;
            $new_balance = max(0, $new_total - (float)$booking['amount_paid']);
            $pstatus = ($new_balance <= 0) ? 'paid' : (($booking['amount_paid'] > 0) ? 'partial' : 'unpaid');
            $upd = $db->prepare("UPDATE bookings SET total_amount=? WHERE id=?");
            $upd->bind_param('di', $new_total, $booking_id);
            $upd->execute();
            $upd2 = $db->prepare("UPDATE billing SET balance=?, payment_status=?, addon_total=IFNULL(addon_total,0)+? WHERE id=?");
            $upd2->bind_param('dsdi', $new_balance, $pstatus, $added_total, $booking['bill_id']);
            $upd2->execute();
        }
        $success = 'Services added successfully! Your updated receipt is shown below.';
        // Refresh booking data
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
    }
}

// Fetch available services
$services_by_cat = [];
$svc_res = $db->query("SELECT * FROM services WHERE available=1 ORDER BY category, name");
while ($svc = $svc_res->fetch_assoc()) {
    $services_by_cat[$svc['category']][] = $svc;
}

// Fetch existing add-ons for this booking
$addons = $db->query("
    SELECT ba.*, s.name svc_name, s.icon, s.category
    FROM booking_addon_services ba
    JOIN services s ON ba.service_id = s.id
    WHERE ba.booking_id = $booking_id AND ba.status != 'cancelled'
    ORDER BY ba.added_at DESC
");
$addon_list = [];
$addon_total = 0;
while ($a = $addons->fetch_assoc()) {
    $addon_list[] = $a;
    $addon_total += $a['total_price'];
}

// Fetch original booking services
$orig_svcs = $db->query("
    SELECT bs.*, s.name svc_name, s.icon FROM booking_services bs
    JOIN services s ON bs.service_id = s.id
    WHERE bs.booking_id = $booking_id
");
$orig_list = [];
$orig_total = 0;
while ($os = $orig_svcs->fetch_assoc()) { $orig_list[] = $os; $orig_total += $os['total_price']; }

$page_title = 'Add Services';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Booking #<?= htmlspecialchars($booking['booking_ref']) ?></div>
        <h1>Add Services & Amenities</h1>
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>/index.php">Home</a>
            <i class="fas fa-chevron-right fa-xs"></i>
            <a href="<?= SITE_URL ?>/guest/my_bookings.php">My Bookings</a>
            <i class="fas fa-chevron-right fa-xs"></i> Add Services
        </div>
    </div>
</div>

<section class="section" style="background:#f8f9fa;padding:3rem 0">
<div class="container" style="max-width:1050px">

    <?php if ($error): ?>
    <div style="background:#fce8e6;color:#a83232;padding:1.25rem;border-radius:8px;margin-bottom:1.5rem;border-left:5px solid #a83232;font-weight:500">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:#e6f4ea;color:#137333;padding:1.25rem;border-radius:8px;margin-bottom:1.5rem;border-left:5px solid #137333;font-weight:500">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:1.75rem;align-items:start">

        <!-- Left: Service Selection -->
        <div>
            <div style="background:white;border-radius:10px;border:1px solid #e1e4e8;overflow:hidden;margin-bottom:1.5rem">
                <div style="background:#fafbfc;border-bottom:1px solid #eee;padding:1rem 1.5rem">
                    <h3 style="margin:0;font-family:var(--font-display);font-size:1.1rem;color:var(--jungle-dark)">
                        <i class="fas fa-concierge-bell" style="color:var(--ochre)"></i> Available Services
                    </h3>
                    <p style="margin:.25rem 0 0;font-size:.82rem;color:#777">Select services to add to booking <?= htmlspecialchars($booking['booking_ref']) ?></p>
                </div>
                <form method="POST" id="addServiceForm" style="padding:1.5rem">
                    <?php foreach ($services_by_cat as $cat => $svcs): ?>
                    <div style="margin-bottom:1.5rem">
                        <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--text-light);margin-bottom:.75rem;padding-bottom:.4rem;border-bottom:1px solid #f0f0f0">
                            <?= htmlspecialchars($cat) ?>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:.6rem">
                        <?php foreach ($svcs as $svc): ?>
                        <label style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer;transition:all .15s"
                               onmouseover="this.style.borderColor='var(--jungle)';this.style.background='#f0f7f3'"
                               onmouseout="this.style.borderColor='#e5e7eb';this.style.background='white'"
                               onclick="toggleSvc(event,<?= $svc['id'] ?>)">
                            <input type="checkbox" name="services[]" value="<?= $svc['id'] ?>" id="svc<?= $svc['id'] ?>"
                                   style="width:auto;flex-shrink:0" onchange="updateTotal()">
                            <div style="width:36px;height:36px;background:var(--jungle-dark);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="fas <?= htmlspecialchars($svc['icon']??'fa-star') ?>" style="color:#fff;font-size:.85rem"></i>
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:.9rem;color:#162e22"><?= htmlspecialchars($svc['name']) ?></div>
                                <div style="font-size:.75rem;color:#777;line-height:1.4;margin-top:.1rem"><?= htmlspecialchars(substr($svc['description'],0,80)) ?>…</div>
                            </div>
                            <div style="text-align:right;flex-shrink:0">
                                <div style="font-weight:700;color:var(--jungle-dark);font-size:.95rem">₱<?= number_format($svc['price'],2) ?></div>
                                <div style="margin-top:.35rem">
                                    <label style="font-size:.72rem;color:#888" onclick="event.stopPropagation()">Qty:
                                        <input type="number" name="quantities[<?= $svc['id'] ?>]" min="1" max="20" value="1"
                                               id="qty<?= $svc['id'] ?>" style="width:45px;padding:.15rem .3rem;font-size:.75rem;border:1px solid #ccc;border-radius:4px;text-align:center"
                                               onclick="event.stopPropagation()" onchange="updateTotal()">
                                    </label>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="border-top:2px solid #e5e7eb;padding-top:1rem;display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <span style="font-size:.8rem;color:#777">Services total:</span>
                            <span id="selectedTotal" style="font-weight:700;color:var(--jungle-dark);font-size:1.05rem;margin-left:.35rem">₱0.00</span>
                        </div>
                        <button type="submit" name="add_services" class="btn btn-primary" id="addBtn" disabled style="opacity:.5">
                            <i class="fas fa-plus-circle"></i> Add to Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Receipt Summary -->
        <div style="position:sticky;top:1rem">
            <div style="background:white;border-radius:10px;border:1px solid #e1e4e8;overflow:hidden">
                <div style="background:var(--jungle-dark);padding:1rem 1.5rem">
                    <h3 style="margin:0;font-family:var(--font-display);color:white;font-size:1rem"><i class="fas fa-receipt"></i> Updated Receipt</h3>
                    <div style="font-size:.78rem;color:rgba(255,255,255,.6);margin-top:.2rem">Ref: <?= htmlspecialchars($booking['booking_ref']) ?></div>
                </div>
                <div style="padding:1.25rem">
                    <!-- Stay Info -->
                    <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #f0f0f0">
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#999;margin-bottom:.5rem">Room</div>
                        <div style="font-weight:600;color:#162e22"><?= htmlspecialchars($booking['room_name']) ?></div>
                        <div style="font-size:.8rem;color:#777">Room <?= htmlspecialchars($booking['room_number']) ?></div>
                        <div style="display:flex;justify-content:space-between;margin-top:.5rem;font-size:.85rem">
                            <span><?= date('M d', strtotime($booking['check_in'])) ?> → <?= date('M d, Y', strtotime($booking['check_out'])) ?></span>
                            <span style="font-weight:700">₱<?= number_format($booking['room_rate'],2) ?>/night</span>
                        </div>
                        <div style="font-size:.78rem;color:#888"><?= $booking['nights'] ?> night<?= $booking['nights']>1?'s':'' ?></div>
                    </div>

                    <!-- Subtotal -->
                    <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #f0f0f0">
                        <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem">
                            <span>Room Subtotal</span>
                            <span>₱<?= number_format($booking['subtotal'],2) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem;color:#777">
                            <span>Tax (12%)</span>
                            <span>₱<?= number_format($booking['tax_amount'],2) ?></span>
                        </div>
                        <?php if (!empty($orig_list)): ?>
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:#999;margin:.5rem 0 .35rem">Original Services</div>
                        <?php foreach ($orig_list as $os): ?>
                        <div style="display:flex;justify-content:space-between;font-size:.8rem;color:#555;margin-bottom:.2rem">
                            <span><i class="fas <?= htmlspecialchars($os['icon']??'fa-star') ?> fa-xs" style="color:var(--jungle);width:12px"></i> <?= htmlspecialchars($os['svc_name']) ?> ×<?= $os['quantity'] ?></span>
                            <span>₱<?= number_format($os['total_price'],2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Add-ons already added -->
                    <?php if (!empty($addon_list)): ?>
                    <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid #f0f0f0">
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--jungle);margin-bottom:.35rem">Add-on Services</div>
                        <?php foreach ($addon_list as $a): ?>
                        <div style="display:flex;justify-content:space-between;font-size:.8rem;color:#555;margin-bottom:.2rem">
                            <span><i class="fas <?= htmlspecialchars($a['icon']??'fa-plus') ?> fa-xs" style="color:var(--ochre);width:12px"></i> <?= htmlspecialchars($a['svc_name']) ?> ×<?= $a['quantity'] ?></span>
                            <span>₱<?= number_format($a['total_price'],2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div style="display:flex;justify-content:space-between;font-size:.8rem;font-weight:600;color:var(--jungle);margin-top:.4rem">
                            <span>Add-ons Total</span>
                            <span>₱<?= number_format($addon_total,2) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Grand Total -->
                    <div style="background:#f8f9fa;border-radius:8px;padding:.75rem">
                        <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:800;color:var(--jungle-dark)">
                            <span>Grand Total</span>
                            <span>₱<?= number_format($booking['total_amount'],2) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.8rem;color:#137333;margin-top:.3rem">
                            <span>Paid</span>
                            <span>₱<?= number_format($booking['amount_paid'] ?? 0, 2) ?></span>
                        </div>
                        <?php $bal = max(0, $booking['total_amount'] - ($booking['amount_paid']??0)); ?>
                        <?php if ($bal > 0): ?>
                        <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#b06000;font-weight:600;margin-top:.2rem">
                            <span>Balance Due</span>
                            <span>₱<?= number_format($bal,2) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top:1rem">
                        <a href="<?= SITE_URL ?>/guest/my_bookings.php" class="btn btn-outline" style="width:100%;justify-content:center;font-size:.85rem">
                            <i class="fas fa-arrow-left"></i> Back to My Bookings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

<script>
var prices = <?= json_encode(array_column(array_merge(...array_values($services_by_cat)), null, 'id')) ?>;

function toggleSvc(e, id) {
    // prevent double-trigger from label child elements
}

function updateTotal() {
    var total = 0;
    var anyChecked = false;
    document.querySelectorAll('input[name="services[]"]').forEach(function(cb) {
        if (cb.checked) {
            anyChecked = true;
            var qty = parseInt(document.getElementById('qty' + cb.value).value) || 1;
            var price = parseFloat(prices[cb.value] ? prices[cb.value].price : 0);
            total += qty * price;
        }
    });
    document.getElementById('selectedTotal').textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var btn = document.getElementById('addBtn');
    btn.disabled = !anyChecked;
    btn.style.opacity = anyChecked ? '1' : '.5';
}

// Re-calc on quantity change
document.querySelectorAll('input[type=number]').forEach(function(el) {
    el.addEventListener('input', updateTotal);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>