<?php
require_once __DIR__ . '/../includes/config.php';

if (!isGuestLoggedIn()) {
    redirect(SITE_URL . '/guest/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$db = getDB();
$error = '';
$success = '';
$guest_id = (int)$_SESSION['guest_id'];

// Handle cancellation / refund request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_cancel'])) {
    $cancel_id     = (int)$_POST['cancel_booking_id'];
    $cancel_reason = trim($_POST['cancel_reason'] ?? '');
    $other_reason  = trim($_POST['cancel_reason_other'] ?? '');

    if ($cancel_reason === 'other') {
        $reason = empty($other_reason) ? '' : 'Other: ' . $other_reason;
    } else {
        $reason = $cancel_reason;
    }

    if (empty($reason)) {
        $error = "Please select a reason for your cancellation request.";
    } else {
        $stmt = $db->prepare("SELECT b.status, b.room_id, bil.amount_paid, bil.payment_status FROM bookings b LEFT JOIN billing bil ON b.id = bil.booking_id WHERE b.id = ? AND b.guest_id = ?");
        $stmt->bind_param('ii', $cancel_id, $guest_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if ($booking && in_array($booking['status'], ['pending', 'confirmed'])) {
            $stmt_up = $db->prepare("UPDATE bookings SET status='pending_cancellation', cancel_reason=? WHERE id=?");
            $stmt_up->bind_param('si', $reason, $cancel_id);
            $stmt_up->execute()
                ? $success = "Your cancellation & refund request has been submitted. Our team will review it within 24 hours."
                : $error = "Something went wrong. Please try again.";
        } else {
            $error = "This booking cannot be cancelled (status: " . strtoupper($booking['status'] ?? 'unknown') . ").";
        }
    }
}

// Fetch all bookings
$history_sql = "
    SELECT b.*, r.name as room_name, r.room_number, r.view_type,
           bil.invoice_number, bil.amount_paid, bil.balance, bil.payment_method, bil.payment_status, bil.notes as billing_notes
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN billing bil ON b.id = bil.booking_id
    WHERE b.guest_id = $guest_id
    ORDER BY b.created_at DESC
";
$tracking_ledger = $db->query($history_sql);

$page_title = 'My Bookings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Reservation Center</div>
        <h1>My Bookings</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> My Bookings</div>
    </div>
</div>

<section class="section" style="background:#f8f9fa;padding:3rem 0">
    <div class="container" style="max-width:1000px">

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

        <!-- Stats Row -->
        <?php
        $all = $db->query("SELECT status, COUNT(*) c FROM bookings WHERE guest_id=$guest_id GROUP BY status");
        $counts = ['pending'=>0,'confirmed'=>0,'checked_in'=>0,'cancelled'=>0,'pending_cancellation'=>0];
        while ($r = $all->fetch_assoc()) $counts[$r['status']] = ($counts[$r['status']] ?? 0) + $r['c'];
        $total_bookings = array_sum($counts);
        ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem">
            <div style="background:white;border-radius:10px;padding:1.25rem;text-align:center;border:1px solid #e1e4e8;box-shadow:0 2px 8px rgba(0,0,0,.03)">
                <div style="font-size:1.75rem;font-weight:800;color:var(--jungle-dark);font-family:var(--font-display)"><?= $total_bookings ?></div>
                <div style="font-size:.78rem;color:#777;text-transform:uppercase;font-weight:600;margin-top:.2rem">Total</div>
            </div>
            <div style="background:white;border-radius:10px;padding:1.25rem;text-align:center;border:1px solid #fde8cd;box-shadow:0 2px 8px rgba(0,0,0,.03)">
                <div style="font-size:1.75rem;font-weight:800;color:#b06000;font-family:var(--font-display)"><?= $counts['pending'] ?></div>
                <div style="font-size:.78rem;color:#777;text-transform:uppercase;font-weight:600;margin-top:.2rem">Pending</div>
            </div>
            <div style="background:white;border-radius:10px;padding:1.25rem;text-align:center;border:1px solid #c3e6cb;box-shadow:0 2px 8px rgba(0,0,0,.03)">
                <div style="font-size:1.75rem;font-weight:800;color:#137333;font-family:var(--font-display)"><?= $counts['confirmed'] ?></div>
                <div style="font-size:.78rem;color:#777;text-transform:uppercase;font-weight:600;margin-top:.2rem">Confirmed</div>
            </div>
            <div style="background:white;border-radius:10px;padding:1.25rem;text-align:center;border:1px solid #fce8e6;box-shadow:0 2px 8px rgba(0,0,0,.03)">
                <div style="font-size:1.75rem;font-weight:800;color:#a83232;font-family:var(--font-display)"><?= $counts['cancelled'] ?></div>
                <div style="font-size:.78rem;color:#777;text-transform:uppercase;font-weight:600;margin-top:.2rem">Cancelled</div>
            </div>
        </div>

        <?php if (!$tracking_ledger || $tracking_ledger->num_rows === 0): ?>
        <div style="background:white;border-radius:10px;border:1px solid #e1e4e8;padding:5rem 2rem;text-align:center">
            <i class="fas fa-calendar-times" style="font-size:4rem;color:#ddd;margin-bottom:1.5rem;display:block"></i>
            <h3 style="font-family:var(--font-display);font-size:1.4rem;color:var(--jungle-dark);margin-bottom:.75rem">No Bookings Yet</h3>
            <p style="color:#888;margin-bottom:1.5rem">You haven't made any reservations. Start by browsing our rooms!</p>
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary"><i class="fas fa-bed"></i> Browse Rooms</a>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:1.5rem">
            <?php while ($b = $tracking_ledger->fetch_assoc()):
                $status = $b['status'];

                $svc_result = $db->query("SELECT bs.quantity, bs.unit_price, bs.total_price, s.name, s.icon FROM booking_services bs JOIN services s ON bs.service_id=s.id WHERE bs.booking_id=".$b['id']);
                $booking_services = [];
                while ($sv = $svc_result->fetch_assoc()) $booking_services[] = $sv;

                $status_styles = [
                    'pending'              => ['bg'=>'#fef7e0','color'=>'#b06000','icon'=>'fa-hourglass-half','label'=>'Pending Confirmation'],
                    'confirmed'            => ['bg'=>'#e6f4ea','color'=>'#137333','icon'=>'fa-check-circle','label'=>'Confirmed'],
                    'checked_in'           => ['bg'=>'#e8f0fe','color'=>'#1a73e8','icon'=>'fa-bed','label'=>'Checked In'],
                    'checked_out'          => ['bg'=>'#f1f3f4','color'=>'#5f6368','icon'=>'fa-sign-out-alt','label'=>'Checked Out'],
                    'cancelled'            => ['bg'=>'#fce8e6','color'=>'#a83232','icon'=>'fa-times-circle','label'=>'Cancelled'],
                    'pending_cancellation' => ['bg'=>'#fff3cd','color'=>'#856404','icon'=>'fa-hourglass-half','label'=>'Awaiting Cancellation'],
                ];
                $ss = $status_styles[$status] ?? ['bg'=>'#f1f3f4','color'=>'#5f6368','icon'=>'fa-circle','label'=>ucfirst($status)];

                $pay_styles = [
                    'unpaid'   => ['bg'=>'#fce8e6','color'=>'#a83232','label'=>'Unpaid'],
                    'partial'  => ['bg'=>'#fef7e0','color'=>'#b06000','label'=>'Partial'],
                    'paid'     => ['bg'=>'#e8f0fe','color'=>'#1a73e8','label'=>'Paid'],
                    'refunded' => ['bg'=>'#e6f4ea','color'=>'#137333','label'=>'Refunded'],
                ];
                $ps = $pay_styles[$b['payment_status'] ?? 'unpaid'] ?? ['bg'=>'#f1f3f4','color'=>'#777','label'=>ucfirst($b['payment_status'])];
            ?>
            <div style="background:white;border:1px solid #e1e4e8;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.03)">
                <!-- Card Header -->
                <div style="background:#fafbfc;border-bottom:1px solid #eee;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
                    <div>
                        <span style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:700">Booking Reference</span>
                        <div style="font-family:monospace;font-size:1.05rem;font-weight:bold;color:#162e22"><?= $b['booking_ref'] ?></div>
                    </div>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <span style="padding:.3rem .8rem;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;background:<?=$ss['bg']?>;color:<?=$ss['color']?>">
                            <i class="fas <?=$ss['icon']?> fa-xs"></i> <?=$ss['label']?>
                        </span>
                        <span style="padding:.3rem .8rem;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;background:<?=$ps['bg']?>;color:<?=$ps['color']?>">
                            <?=$ps['label']?>
                        </span>
                    </div>
                </div>

                <!-- Card Body -->
                <div style="padding:1.5rem;display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;align-items:start">
                    <!-- Room Info -->
                    <div>
                        <h3 style="margin:0 0 .3rem;font-family:var(--font-display);font-size:1.15rem;color:#162e22"><?= htmlspecialchars($b['room_name']) ?></h3>
                        <div style="font-size:.85rem;color:#555;margin-bottom:.25rem"><i class="fas fa-door-open fa-xs" style="color:var(--jungle)"></i> Room <?= htmlspecialchars($b['room_number']) ?></div>
                        <div style="font-size:.83rem;color:#777"><i class="far fa-calendar-alt fa-xs"></i> <?= date('M d, Y', strtotime($b['check_in'])) ?> → <?= date('M d, Y', strtotime($b['check_out'])) ?></div>
                        <div style="font-size:.8rem;color:#999;margin-top:.15rem"><?= $b['nights'] ?> night<?=$b['nights']>1?'s':''?> · <?= $b['adults'] ?> guest<?=$b['adults']>1?'s':''?></div>

                        <?php if (!empty($booking_services)): ?>
                        <div style="margin-top:.75rem;padding:.6rem;background:#f8f9fa;border-radius:6px">
                            <div style="font-size:.72rem;color:#777;font-weight:700;text-transform:uppercase;margin-bottom:.35rem">Added Services</div>
                            <?php foreach ($booking_services as $sv): ?>
                            <div style="font-size:.8rem;color:#444;display:flex;align-items:center;gap:.4rem;margin-bottom:.2rem">
                                <i class="fas <?= htmlspecialchars($sv['icon']??'fa-star') ?> fa-xs" style="color:var(--jungle);width:14px"></i>
                                <?= htmlspecialchars($sv['name']) ?> ×<?= $sv['quantity'] ?>
                                <span style="color:#888;margin-left:auto">₱<?= number_format($sv['total_price'],2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Financial Info -->
                    <div>
                        <div style="font-size:.75rem;color:#999;font-weight:700;text-transform:uppercase;margin-bottom:.5rem">Payment Details</div>
                        <div style="font-size:.88rem;color:#444;margin-bottom:.2rem">Total: <strong style="color:#162e22">₱<?= number_format($b['total_amount'],2) ?></strong></div>
                        <div style="font-size:.85rem;color:#137333">Paid: ₱<?= number_format($b['amount_paid'],2) ?> via <?= strtoupper($b['payment_method'] ?? '—') ?></div>
                        <?php if ($b['balance'] > 0 && !in_array($status, ['cancelled'])): ?>
                        <div style="font-size:.82rem;color:#b06000;font-weight:600;margin-top:.2rem">Balance: ₱<?= number_format($b['balance'],2) ?></div>
                        <?php endif; ?>
                        <?php if ($b['payment_status'] === 'refunded'): ?>
                        <div style="font-size:.82rem;color:#137333;font-weight:600;margin-top:.35rem;background:#e6f4ea;padding:.35rem .6rem;border-radius:4px"><i class="fas fa-undo-alt fa-xs"></i> Amount Refunded</div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div style="text-align:right">
                        <?php if ($status === 'cancelled'): ?>
                        <div style="text-align:left;background:#fafbfc;border:1px solid #eee;padding:.75rem;border-radius:6px">
                            <div style="font-size:.72rem;font-weight:700;color:#a83232;margin-bottom:.3rem"><i class="fas fa-times-circle"></i> Cancelled</div>
                            <div style="font-size:.78rem;color:#666;line-height:1.4"><?= htmlspecialchars($b['billing_notes'] ?? 'Reservation has been cancelled.') ?></div>
                        </div>

                        <?php elseif ($status === 'pending_cancellation'): ?>
                        <div style="text-align:left;background:#fffcf6;border:1px solid #f5e4cd;padding:.75rem;border-radius:6px">
                            <div style="font-size:.75rem;color:#b06000;font-weight:700;margin-bottom:.3rem"><i class="fas fa-hourglass-half"></i> Review In Progress</div>
                            <div style="font-size:.78rem;color:#666;line-height:1.4">Your cancellation & refund request is being reviewed. Expected: within 24-48 hours.</div>
                            <?php if ($b['cancel_reason']): ?>
                            <div style="font-size:.75rem;color:#888;margin-top:.35rem;font-style:italic">Reason: "<?= htmlspecialchars($b['cancel_reason']) ?>"</div>
                            <?php endif; ?>
                        </div>

                        <?php elseif (in_array($status, ['pending', 'confirmed'])): ?>
                        <!-- Add Services Button -->
                        <div style="margin-bottom:.75rem">
                            <a href="<?= SITE_URL ?>/guest/add_services.php?booking_id=<?= $b['id'] ?>"
                               class="btn btn-primary" style="width:100%;justify-content:center;font-size:.82rem;padding:.55rem">
                                <i class="fas fa-concierge-bell"></i> Add Services / Amenities
                            </a>
                        </div>
                        <!-- Cancellation form with radio button reasons -->
                        <div style="background:#fafbfc;border:1px solid #e1e4e8;padding:1rem;border-radius:8px;text-align:left">
                            <div style="font-size:.78rem;font-weight:700;color:#a83232;margin-bottom:.5rem"><i class="fas fa-undo-alt"></i> Request Cancellation & Refund</div>
                            <div style="font-size:.72rem;color:#888;margin-bottom:.75rem;line-height:1.4">
                                Refund policy: Full refund if cancelled 48+ hrs before check-in. 50% within 24-48 hrs. No refund within 24 hrs.
                            </div>
                            <form method="POST" onsubmit="return validateCancelForm(this)">
                                <input type="hidden" name="cancel_booking_id" value="<?= $b['id'] ?>">
                                <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem">
                                    <?php
                                    $reasons = [
                                        'change_of_plans'    => 'Change of plans',
                                        'emergency'          => 'Personal / family emergency',
                                        'found_better_deal'  => 'Found a better deal elsewhere',
                                        'travel_restriction' => 'Travel restriction or health concern',
                                        'work_commitment'    => 'Work or schedule conflict',
                                        'incorrect_booking'  => 'Booked incorrect dates/room',
                                        'other'              => 'Other (please specify)',
                                    ];
                                    foreach ($reasons as $val => $label): ?>
                                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;cursor:pointer;color:#333;padding:.3rem .4rem;border-radius:4px;transition:background .15s" onmouseover="this.style.background='#f0f4f0'" onmouseout="this.style.background='transparent'">
                                        <input type="radio" name="cancel_reason" value="<?= $val ?>" style="margin:0;flex-shrink:0" onchange="toggleOtherField(this,'other_<?= $b['id'] ?>')">
                                        <?= htmlspecialchars($label) ?>
                                    </label>
                                    <?php endforeach; ?>
                                    <div id="other_<?= $b['id'] ?>" style="display:none;margin-top:.25rem">
                                        <textarea name="cancel_reason_other" placeholder="Please describe your reason..." rows="2" style="width:100%;font-size:.78rem;padding:.4rem;border:1px solid #ccc;border-radius:4px;resize:none;font-family:inherit"></textarea>
                                    </div>
                                </div>
                                <button type="submit" name="action_request_cancel" style="width:100%;background:#a83232;color:white;border:none;padding:.55rem;font-size:.78rem;font-weight:700;border-radius:4px;cursor:pointer;font-family:inherit">
                                    <i class="fas fa-undo-alt"></i> Submit Cancellation Request
                                </button>
                            </form>
                        </div>

                        <?php elseif ($status === 'checked_in'): ?>
                        <div style="margin-bottom:.75rem">
                            <a href="<?= SITE_URL ?>/guest/add_services.php?booking_id=<?= $b['id'] ?>"
                               class="btn btn-primary" style="width:100%;justify-content:center;font-size:.82rem;padding:.55rem">
                                <i class="fas fa-concierge-bell"></i> Add Services / Amenities
                            </a>
                        </div>
                        <div style="font-size:.78rem;color:#0065a4;text-align:left;background:#e8f0fe;padding:.75rem;border-radius:6px">
                            <i class="fas fa-bed"></i> <strong>Currently Checked In</strong><br>
                            <span style="font-size:.72rem;color:#555">Enjoying your stay? Add more services anytime!</span>
                        </div>

                        <?php else: ?>
                        <div style="font-size:.78rem;color:#888;text-align:left;border-left:3px solid #ccc;padding-left:.6rem">
                            <i class="fas fa-lock" style="color:#bbb"></i> <strong>Stay Completed</strong><br>
                            <span style="font-size:.72rem">Thank you for staying with us!</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($b['invoice_number']): ?>
                        <div style="margin-top:.75rem;text-align:right">
                            <span style="font-size:.75rem;color:#999">Invoice: <strong style="font-family:monospace"><?= $b['invoice_number'] ?></strong></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:2rem">
            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary"><i class="fas fa-plus"></i> Book Another Room</a>
        </div>
    </div>
</section>

<!-- Refund Policy Info -->
<section style="background:var(--jungle-dark);padding:3rem 0">
    <div class="container" style="max-width:800px;text-align:center">
        <div class="eyebrow" style="color:var(--ochre-light,#f5c483)">Our Policy</div>
        <h3 style="font-family:var(--font-display);color:white;font-size:1.5rem;margin:.5rem 0 1rem">Cancellation & Refund Policy</h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;text-align:left">
            <div style="background:rgba(255,255,255,.07);border-radius:var(--radius);padding:1.25rem">
                <div style="color:var(--ochre-light,#f5c483);font-weight:700;font-size:.9rem;margin-bottom:.5rem"><i class="fas fa-clock"></i> 48+ Hours Before</div>
                <div style="color:rgba(255,255,255,.75);font-size:.85rem;line-height:1.6">Full refund of all payments made, processed within 3-5 business days.</div>
            </div>
            <div style="background:rgba(255,255,255,.07);border-radius:var(--radius);padding:1.25rem">
                <div style="color:var(--ochre-light,#f5c483);font-weight:700;font-size:.9rem;margin-bottom:.5rem"><i class="fas fa-clock"></i> 24-48 Hours Before</div>
                <div style="color:rgba(255,255,255,.75);font-size:.85rem;line-height:1.6">50% refund of the total paid amount, processed within 5-7 business days.</div>
            </div>
            <div style="background:rgba(255,255,255,.07);border-radius:var(--radius);padding:1.25rem">
                <div style="color:#f87171;font-weight:700;font-size:.9rem;margin-bottom:.5rem"><i class="fas fa-ban"></i> Under 24 Hours</div>
                <div style="color:rgba(255,255,255,.75);font-size:.85rem;line-height:1.6">No refund available. Room charges apply in full.</div>
            </div>
        </div>
    </div>
</section>

<script>
function toggleOtherField(radio, fieldId) {
    var el = document.getElementById(fieldId);
    if (el) el.style.display = (radio.value === 'other') ? 'block' : 'none';
}

function validateCancelForm(form) {
    var chosen = form.querySelector('input[name="cancel_reason"]:checked');
    if (!chosen) {
        alert('Please select a reason for cancellation.');
        return false;
    }
    if (chosen.value === 'other') {
        var other = form.querySelector('textarea[name="cancel_reason_other"]').value.trim();
        if (!other) {
            alert('Please describe your reason for cancellation.');
            return false;
        }
    }
    return true;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
