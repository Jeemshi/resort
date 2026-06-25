<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'bookings';
$admin_title = 'Bookings';
$db = getDB();

$msg = '';
$err = '';

// =============================================
// HANDLE CANCELLATION APPROVAL / REFUND
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'process_cancellation') {
    $bid      = (int)$_POST['booking_id'];
    $decision = sanitize($_POST['decision'] ?? ''); // 'approve' or 'reject'
    $note     = sanitize($_POST['admin_note'] ?? '');
    $refund_pct = (float)($_POST['refund_percent'] ?? 100);

    if (!in_array($decision, ['approve', 'reject'])) {
        $err = "Invalid decision.";
    } else {
        $b_data = $db->query("SELECT b.*, bil.amount_paid, bil.id as bil_id FROM bookings b LEFT JOIN billing bil ON b.id=bil.booking_id WHERE b.id=$bid")->fetch_assoc();
        if (!$b_data) {
            $err = "Booking not found.";
        } elseif ($decision === 'approve') {
            $refund_amount = round((float)$b_data['amount_paid'] * ($refund_pct / 100), 2);
            $db->begin_transaction();
            try {
                // Update booking to cancelled
                $db->query("UPDATE bookings SET status='cancelled' WHERE id=$bid");
                // Update billing: mark refunded and note
                $refund_note = addslashes("Refund processed by admin: " . ($note ?: "Cancellation approved. {$refund_pct}% refund of ₱" . number_format($b_data['amount_paid'],2) . " = ₱" . number_format($refund_amount,2)));
                $db->query("UPDATE billing SET payment_status='refunded', notes='$refund_note' WHERE booking_id=$bid");
                // Free the room
                $db->query("UPDATE rooms SET status='available' WHERE id=" . (int)$b_data['room_id']);
                $db->commit();
                $msg = "Cancellation approved. Refund of ₱" . number_format($refund_amount, 2) . " (" . $refund_pct . "%) processed.";
            } catch (Exception $e) {
                $db->rollback();
                $err = "Failed: " . $e->getMessage();
            }
        } else {
            // Reject: put back to confirmed
            $reject_note = addslashes("Cancellation request rejected by admin: " . ($note ?: "No reason provided."));
            $db->query("UPDATE bookings SET status='confirmed', cancel_reason=NULL WHERE id=$bid");
            $db->query("UPDATE billing SET notes='$reject_note' WHERE booking_id=$bid");
            $msg = "Cancellation request rejected. Booking restored to Confirmed.";
        }
    }
}


// Handle status update
if (isset($_POST['update_status'])) {
    $bid    = (int)$_POST['booking_id'];
    $status = sanitize($_POST['status']);
    $allowed = ['pending','confirmed','checked_in','checked_out','cancelled'];
    if (in_array($status, $allowed)) {
        $room_q = $db->query("SELECT room_id FROM bookings WHERE id=$bid");
        $rid = $room_q->fetch_assoc()['room_id'];
        if ($status === 'checked_in')   $db->query("UPDATE rooms SET status='occupied' WHERE id=$rid");
        if (in_array($status,['checked_out','cancelled'])) $db->query("UPDATE rooms SET status='available' WHERE id=$rid");
        $msg = "Booking status updated to " . str_replace('_',' ', $status) . ".";
    }
}

// =============================================
// HANDLE ADMIN AMENITY ADD-ONS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'admin_add_amenities') {
    $bid      = (int)$_POST['booking_id'];
    $selected = array_map('intval', $_POST['amenity_ids'] ?? []);

    // Validate booking exists and is in an amenable state
    $bcheck = $db->query("SELECT b.id, b.total_amount, b.status, bil.id as bil_id, bil.amount_paid FROM bookings b LEFT JOIN billing bil ON b.id=bil.booking_id WHERE b.id=$bid")->fetch_assoc();
    if (!$bcheck) {
        $err = "Booking not found.";
    } elseif (!in_array($bcheck['status'], ['pending','confirmed','checked_in'])) {
        $err = "Amenities can only be added to pending, confirmed, or checked-in bookings.";
    } elseif (empty($selected)) {
        $err = "Please select at least one amenity.";
    } else {
        $added_lines = [];
        foreach ($selected as $amenity_id) {
            $amenity_row = $db->query("SELECT * FROM amenities WHERE id=$amenity_id")->fetch_assoc();
            if (!$amenity_row) continue;

            $safe_name = addslashes($amenity_row['name']);
            $added_lines[] = $amenity_row['name'];

            // Find or create a matching services row to satisfy the FK on booking_addon_services
            $svc_row = $db->query("SELECT id FROM services WHERE name='$safe_name' LIMIT 1")->fetch_assoc();
            if ($svc_row) {
                $svc_id = (int)$svc_row['id'];
            } else {
                $safe_icon = addslashes($amenity_row['icon'] ?? 'fa-concierge-bell');
                $safe_cat  = addslashes($amenity_row['category'] ?? 'Amenities');
                $db->query("INSERT INTO services (name, description, price, category, icon, available)
                            VALUES ('$safe_name', 'Walk-in amenity add-on', 0, '$safe_cat', '$safe_icon', 0)");
                $svc_id = (int)$db->insert_id;
            }

            $db->query("INSERT INTO booking_addon_services (booking_id, service_id, quantity, unit_price, total_price, notes)
                        VALUES ($bid, $svc_id, 1, 0, 0, 'Admin amenity add-on (walk-in)')");
        }
        $msg = "Amenities added: " . implode(', ', $added_lines) . ".";
        header("Location: " . SITE_URL . "/admin/bookings.php?view=$bid&amenity_added=1");
        exit;
    }
}

// Create booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'create_booking') {
    $room_id   = (int)$_POST['room_id'];
    $check_in  = sanitize($_POST['check_in']);
    $check_out = sanitize($_POST['check_out']);
    $adults    = (int)$_POST['adults'];
    $children  = (int)($_POST['children']??0);
    $special   = sanitize($_POST['special_requests']??'');
    
    // Default fallback status
    $booking_status = 'pending'; 

    // WALK-IN HANDLING LOGIC
    $is_walkin = isset($_POST['is_walkin']) && $_POST['is_walkin'] === '1';
    if ($is_walkin) {
        $w_first = sanitize($_POST['walkin_first_name'] ?? '');
        $w_last  = sanitize($_POST['walkin_last_name'] ?? '');
        $w_email = sanitize($_POST['walkin_email'] ?? '');
        $w_phone = sanitize($_POST['walkin_phone'] ?? '');
        $w_pass  = password_hash('wildnest2025', PASSWORD_DEFAULT); // Default system placeholder password

        if (empty($w_first) || empty($w_last)) {
            $err = "Walk-in guest first and last names are required.";
        } else {
            // Check if email already exists to prevent duplicate profiles
            $email_check = $db->query("SELECT id FROM guests WHERE email='$w_email' AND email != '' LIMIT 1");
            if ($email_check && $email_check->num_rows > 0) {
                $guest_id = (int)$email_check->fetch_assoc()['id'];
            } else {
                // Register the walk-in guest into the database directly
                $db->query("INSERT INTO guests (first_name, last_name, email, phone, password, status) VALUES ('$w_first', '$w_last', '$w_email', '$w_phone', '$w_pass', 'active')");
                $guest_id = (int)$db->insert_id;
            }
            
            // Check if administrative option was marked to auto check-in right away
            if (isset($_POST['walkin_checkin_now']) && $_POST['walkin_checkin_now'] === '1') {
                $booking_status = 'checked_in';
            }
        }
    } else {
        // Standard flow using the pre-existing selected guest dropdown
        $guest_id = (int)$_POST['guest_id'];
    }

    if (empty($err)) {
        $nights = max(1,(int)((strtotime($check_out)-strtotime($check_in))/86400));
        $rate   = (float)$db->query("SELECT price_per_night FROM rooms WHERE id=$room_id")->fetch_assoc()['price_per_night'];
        $subtot = $rate * $nights;
        $tax    = $subtot * 0.12;
        $total  = $subtot + $tax;
        $ref    = 'WN-' . strtoupper(substr(uniqid(), -6));

        $sql = "INSERT INTO bookings (booking_ref,guest_id,room_id,check_in,check_out,nights,adults,children,room_rate,subtotal,tax_amount,total_amount,special_requests,status)
                VALUES('$ref',$guest_id,$room_id,'$check_in','$check_out',$nights,$adults,$children,$rate,$subtot,$tax,$total,'".addslashes($special)."','$booking_status')";
        
        if ($db->query($sql)) {
            $bid = $db->insert_id;
            $inv = 'INV-' . strtoupper(substr(uniqid(),-6));
            $db->query("INSERT INTO billing (booking_id,invoice_number,balance,payment_status) VALUES($bid,'$inv',$total,'unpaid')");
            
            // If instant check-in option was selected, update room status explicitly to occupied
            if ($booking_status === 'checked_in') {
                $db->query("UPDATE rooms SET status='occupied' WHERE id=$room_id");
            }

            $msg = "Booking created successfully. Ref: $ref";
        } else {
            $err = "Failed to create booking: " . $db->error;
        }
    }
}

// Filters
$status_f   = sanitize($_GET['status']   ?? '');
$search     = sanitize($_GET['search']   ?? '');
$guest_id_f = (int)($_GET['guest_id']   ?? 0);
$date_from  = sanitize($_GET['date_from']?? '');
$date_to    = sanitize($_GET['date_to']  ?? '');
$per_page   = 15;
$page_num   = max(1,(int)($_GET['page']??1));
$offset     = ($page_num-1)*$per_page;

$where = '1=1';
if ($status_f)   $where .= " AND b.status='$status_f'";
if ($search)     $where .= " AND (b.booking_ref LIKE '%$search%' OR g.first_name LIKE '%$search%' OR g.last_name LIKE '%$search%' OR g.email LIKE '%$search%')";
if ($guest_id_f) $where .= " AND b.guest_id=$guest_id_f";
if ($date_from)  $where .= " AND b.check_in>='$date_from'";
if ($date_to)    $where .= " AND b.check_in<='$date_to'";

$total_count = $db->query("SELECT COUNT(*) c FROM bookings b JOIN guests g ON b.guest_id=g.id WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total_count/$per_page);

$bookings = $db->query("
    SELECT b.*, CONCAT(g.first_name,' ',g.last_name) guest_name, g.email guest_email, g.phone guest_phone,
           r.name room_name, r.room_number,
           bl.payment_status, bl.amount_paid, bl.invoice_number
    FROM bookings b
    JOIN guests g ON b.guest_id=g.id
    JOIN rooms r ON b.room_id=r.id
    LEFT JOIN billing bl ON bl.booking_id=b.id
    WHERE $where
    ORDER BY b.created_at DESC LIMIT $per_page OFFSET $offset
");

// For modals: list guests and rooms
$all_guests = $db->query("SELECT id, CONCAT(first_name,' ',last_name) name, email FROM guests WHERE status='active' ORDER BY first_name");
$all_rooms  = $db->query("SELECT id, room_number, name, price_per_night FROM rooms WHERE status='available' ORDER BY room_number");

// View single booking
$view_id = (int)($_GET['view'] ?? 0);
$view_booking = null;
if ($view_id) {
    $vq = $db->query("SELECT b.*, CONCAT(g.first_name,' ',g.last_name) guest_name, g.email guest_email, g.phone guest_phone, r.name room_name, r.room_number, rc.name cat_name, bl.invoice_number, bl.amount_paid, bl.balance, bl.payment_status, bl.payment_method FROM bookings b JOIN guests g ON b.guest_id=g.id JOIN rooms r ON b.room_id=r.id JOIN room_categories rc ON r.category_id=rc.id LEFT JOIN billing bl ON bl.booking_id=b.id WHERE b.id=$view_id");
    $view_booking = $vq ? $vq->fetch_assoc() : null;
}

include __DIR__ . '/layout-top.php';
?>

<!-- PENDING CANCELLATION REQUESTS -->
<?php
$pending_cancels = $db->query("
    SELECT b.*, g.first_name, g.last_name, g.email as guest_email,
           r.name as room_name, r.room_number,
           bil.amount_paid, bil.payment_method, bil.payment_status
    FROM bookings b
    JOIN guests g ON b.guest_id = g.id
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN billing bil ON b.id = bil.booking_id
    WHERE b.status = 'pending_cancellation'
    ORDER BY b.updated_at DESC
");
if ($pending_cancels && $pending_cancels->num_rows > 0):
?>
<div style="background:#fffcf6;border:2px solid #f5e4cd;border-radius:10px;padding:1.5rem;margin-bottom:2rem">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem">
        <div style="width:36px;height:36px;background:#b06000;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.9rem"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <h3 style="font-family:var(--font-head);font-size:1rem;font-weight:700;color:#b06000;letter-spacing:.05em;text-transform:uppercase;margin:0">Pending Cancellation & Refund Requests</h3>
            <div style="font-size:.8rem;color:#888"><?= $pending_cancels->num_rows ?> request(s) awaiting admin review</div>
        </div>
    </div>
    
    <?php while ($pc = $pending_cancels->fetch_assoc()): 
        $days_before = max(0, (int)((strtotime($pc['check_in']) - time()) / 86400));
        if ($days_before >= 2) { $suggested_pct = 100; $pct_label = 'Full Refund (100%)'; }
        elseif ($days_before >= 1) { $suggested_pct = 50; $pct_label = '50% Refund'; }
        else { $suggested_pct = 0; $pct_label = 'No Refund (Policy)'; }
    ?>
    <div style="background:white;border:1px solid #f5e4cd;border-radius:8px;padding:1.25rem;margin-bottom:1rem">
        <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:1.5rem;align-items:start">
            <div>
                <div style="font-family:monospace;font-weight:700;font-size:.95rem;color:#162e22;margin-bottom:.25rem"><?= $pc['booking_ref'] ?></div>
                <div style="font-size:.85rem;color:#444;font-weight:600"><?= htmlspecialchars($pc['first_name'].' '.$pc['last_name']) ?></div>
                <div style="font-size:.78rem;color:#888"><?= htmlspecialchars($pc['guest_email']) ?></div>
                <div style="font-size:.82rem;color:#555;margin-top:.35rem"><?= htmlspecialchars($pc['room_name']) ?> · <?= date('M d',strtotime($pc['check_in'])) ?> → <?= date('M d, Y',strtotime($pc['check_out'])) ?></div>
                <div style="font-size:.8rem;color:#b06000;margin-top:.2rem">Check-in in <?= $days_before ?> day<?=$days_before!==1?'s':''?> · Suggested: <?= $pct_label ?></div>
                <?php if ($pc['cancel_reason']): ?>
                <div style="margin-top:.5rem;font-size:.8rem;color:#666;background:#f8f9fa;padding:.4rem .6rem;border-radius:4px;border-left:3px solid #f5e4cd"><strong>Reason:</strong> <?= htmlspecialchars($pc['cancel_reason']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:.8rem;color:#777;margin-bottom:.25rem">Amount Paid: <strong style="color:#162e22">₱<?= number_format($pc['amount_paid'],2) ?></strong></div>
                <div style="font-size:.8rem;color:#777">Total: <strong>₱<?= number_format($pc['total_amount'],2) ?></strong></div>
                <div style="font-size:.8rem;color:#777;margin-top:.15rem">Via: <?= strtoupper($pc['payment_method']) ?></div>
            </div>
            <div style="min-width:280px">
                <form method="POST" style="display:flex;flex-direction:column;gap:.6rem">
                    <input type="hidden" name="form_action" value="process_cancellation">
                    <input type="hidden" name="booking_id" value="<?= $pc['id'] ?>">
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <label style="font-size:.78rem;color:#555;font-weight:600;flex-shrink:0">Refund %:</label>
                        <select name="refund_percent" style="flex:1;padding:.35rem;border:1px solid #ccc;border-radius:4px;font-size:.82rem">
                            <option value="100" <?=$suggested_pct===100?'selected':''?>>100% – Full Refund (₱<?= number_format($pc['amount_paid'],2) ?>)</option>
                            <option value="50" <?=$suggested_pct===50?'selected':''?>>50% – Half Refund (₱<?= number_format($pc['amount_paid']*0.5,2) ?>)</option>
                            <option value="0" <?=$suggested_pct===0?'selected':''?>>0% – No Refund</option>
                        </select>
                    </div>
                    <textarea name="admin_note" placeholder="Admin note (optional)..." style="padding:.4rem;border:1px solid #ccc;border-radius:4px;font-size:.78rem;height:50px;resize:none;font-family:inherit"></textarea>
                    <div style="display:flex;gap:.5rem">
                        <button type="submit" name="decision" value="approve" style="flex:1;background:#137333;color:white;border:none;padding:.5rem;border-radius:4px;cursor:pointer;font-size:.8rem;font-weight:700;font-family:inherit">
                            <i class="fas fa-check"></i> Approve & Refund
                        </button>
                        <button type="submit" name="decision" value="reject" style="flex:1;background:#a83232;color:white;border:none;padding:.5rem;border-radius:4px;cursor:pointer;font-size:.8rem;font-weight:700;font-family:inherit">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>



<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem">
<?php
$statuses = ['pending'=>['fa-clock','orange'],'confirmed'=>['fa-check','green'],'checked_in'=>['fa-sign-in-alt','green'],'checked_out'=>['fa-sign-out-alt','dark'],'cancelled'=>['fa-times','red']];
foreach ($statuses as $st => [$ico, $col]):
    $cnt = $db->query("SELECT COUNT(*) c FROM bookings WHERE status='$st'")->fetch_assoc()['c'];
?>
<a href="?status=<?=$st?>" style="text-decoration:none">
    <div class="stat-card <?=$col?>" style="<?=$status_f===$st?'border:2px solid var(--ochre)':''?>">
        <div class="stat-icon"><i class="fas <?=$ico?>"></i></div>
        <div><div class="stat-label"><?= ucfirst(str_replace('_',' ',$st)) ?></div><div class="stat-value" style="font-size:1.6rem"><?=$cnt?></div></div>
    </div>
</a>
<?php endforeach; ?>
</div>

<?php if ($view_booking): ?>
<div class="admin-card" style="margin-bottom:1.5rem">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-file-alt" style="color:var(--canopy);margin-right:.4rem"></i> Booking Detail — <?= htmlspecialchars($view_booking['booking_ref']) ?></span>
        <a href="<?= SITE_URL ?>/admin/bookings.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Close</a>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2rem;padding:1.5rem">
        <div>
            <h4 style="font-family:var(--font-head);font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-light);margin-bottom:1rem">Guest Info</h4>
            <p><strong><?= htmlspecialchars($view_booking['guest_name']) ?></strong></p>
            <p style="font-size:.87rem;color:var(--text-mid)"><?= htmlspecialchars($view_booking['guest_email']) ?></p>
            <p style="font-size:.87rem;color:var(--text-mid)"><?= htmlspecialchars($view_booking['guest_phone']??'—') ?></p>
        </div>
        <div>
            <h4 style="font-family:var(--font-head);font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-light);margin-bottom:1rem">Stay Info</h4>
            <p><strong><?= htmlspecialchars($view_booking['room_name']) ?></strong> (<?= htmlspecialchars($view_booking['room_number']) ?>)</p>
            <p style="font-size:.87rem;color:var(--text-mid)"><?= formatDate($view_booking['check_in']) ?> → <?= formatDate($view_booking['check_out']) ?></p>
            <p style="font-size:.87rem;color:var(--text-mid)"><?= $view_booking['nights'] ?> nights · <?= $view_booking['adults'] ?> adult<?=$view_booking['adults']>1?'s':''?><?=$view_booking['children']>0?' · '.$view_booking['children'].' child'.($view_booking['children']>1?'ren':''):''; ?></p>
            <?php if ($view_booking['special_requests']): ?>
            <p style="font-size:.82rem;color:var(--ochre);margin-top:.4rem"><i class="fas fa-comment-alt fa-xs"></i> <?= htmlspecialchars($view_booking['special_requests']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h4 style="font-family:var(--font-head);font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-light);margin-bottom:1rem">Billing</h4>
            <p style="font-size:.87rem;color:var(--text-mid)">Invoice: <?= htmlspecialchars($view_booking['invoice_number']??'—') ?></p>
            <p>Room Rate: <?= formatCurrency($view_booking['room_rate']) ?>/night</p>
            <p>Subtotal: <?= formatCurrency($view_booking['subtotal']) ?></p>
            <p>Tax (12%): <?= formatCurrency($view_booking['tax_amount']) ?></p>
            <p style="font-size:1.1rem;font-weight:700;color:var(--jungle-dark);margin-top:.4rem">Total: <?= formatCurrency($view_booking['total_amount']) ?></p>
            <p style="font-size:.87rem">Paid: <?= formatCurrency($view_booking['amount_paid']??0) ?> · Balance: <?= formatCurrency($view_booking['balance']??$view_booking['total_amount']) ?></p>
            <span class="badge <?= getStatusBadge($view_booking['payment_status']??'unpaid') ?>" style="margin-top:.4rem"><?= ucfirst($view_booking['payment_status']??'unpaid') ?></span>
        </div>
    </div>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--stone);display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <span>Status: <span class="badge <?= getStatusBadge($view_booking['status']) ?>"><?= ucfirst(str_replace('_',' ',$view_booking['status'])) ?></span></span>
        <form method="POST" style="display:flex;gap:.5rem;align-items:center;flex:1">
            <input type="hidden" name="booking_id" value="<?= $view_booking['id'] ?>">
            <select name="status" class="form-control" style="width:auto;padding:.4rem .75rem;font-size:.85rem">
                <?php foreach (['pending','confirmed','checked_in','checked_out','cancelled'] as $s): ?>
                <option value="<?=$s?>" <?=$s===$view_booking['status']?'selected':''?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update Status</button>
        </form>
        <a href="<?= SITE_URL ?>/admin/billing.php?booking_id=<?= $view_booking['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-file-invoice"></i> View Invoice</a>
    </div>
</div>

<?php
// Show amenity-added flash
if (isset($_GET['amenity_added'])): ?>
<div style="background:#e6f4ea;color:#137333;padding:1rem 1.5rem;border-radius:8px;border-left:5px solid #137333;margin-bottom:1rem;font-weight:500">
    <i class="fas fa-check-circle"></i> Amenities successfully added to this booking.
</div>
<?php endif;

// Only show panel for actionable statuses
if (in_array($view_booking['status'], ['pending','confirmed','checked_in'])):

// Fetch all amenities from DB grouped by category
$all_amenities_res = $db->query("SELECT * FROM amenities ORDER BY category, name");
$amenities_by_cat  = [];
while ($am = $all_amenities_res->fetch_assoc()) {
    $amenities_by_cat[$am['category']][] = $am;
}

// Fetch existing add-ons for this booking
$existing_addons = $db->query("
    SELECT ba.id, ba.quantity, ba.total_price, ba.added_at,
           COALESCE(s.name, 'Amenity') AS svc_name, COALESCE(s.icon, 'fa-check') AS svc_icon
    FROM booking_addon_services ba
    LEFT JOIN services s ON ba.service_id = s.id
    WHERE ba.booking_id = {$view_booking['id']}
    ORDER BY ba.added_at DESC
");
$addon_rows = [];
while ($ar = $existing_addons->fetch_assoc()) $addon_rows[] = $ar;
?>

<div class="admin-card" style="margin-bottom:1.5rem">
    <div class="admin-card-header">
        <span class="admin-card-title">
            <i class="fas fa-concierge-bell" style="color:var(--canopy);margin-right:.4rem"></i>
            Amenity Add-ons — <?= htmlspecialchars($view_booking['booking_ref']) ?>
        </span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 280px;gap:2rem;padding:1.5rem;align-items:start">

        <!-- Amenity Selection Form -->
        <div>
            <?php if (!empty($err) && strpos($err, 'Amenity') !== false): ?>
            <div style="background:#fce8e6;color:#a83232;padding:.9rem 1.1rem;border-radius:6px;border-left:4px solid #a83232;margin-bottom:1rem;font-size:.85rem">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($err) ?>
            </div>
            <?php endif; ?>
            <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:1.25rem">
                Select amenities to request for this guest's stay. These are informational add-ons — staff will be notified to prepare them.
            </p>
            <form method="POST" id="adminAmenityForm">
                <input type="hidden" name="form_action" value="admin_add_amenities">
                <input type="hidden" name="booking_id" value="<?= $view_booking['id'] ?>">

                <?php foreach ($amenities_by_cat as $cat => $items): ?>
                <div style="margin-bottom:1.25rem">
                    <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--text-light);margin-bottom:.6rem;padding-bottom:.35rem;border-bottom:1px solid var(--stone,#ddd)">
                        <?= htmlspecialchars($cat) ?>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem">
                        <?php foreach ($items as $a): ?>
                        <label style="display:flex;align-items:center;gap:.6rem;padding:.65rem .75rem;background:var(--mist,#f4f6f4);border:2px solid transparent;border-radius:7px;cursor:pointer;transition:all .15s"
                               id="albl_<?= $a['id'] ?>"
                               onmouseover="this.style.borderColor='var(--canopy)'"
                               onmouseout="syncBorder(<?= $a['id'] ?>)">
                            <input type="checkbox" name="amenity_ids[]" value="<?= $a['id'] ?>"
                                   id="ach_<?= $a['id'] ?>"
                                   style="width:15px;height:15px;accent-color:var(--canopy);flex-shrink:0"
                                   onchange="syncBorder(<?= $a['id'] ?>);updateBtn()">
                            <i class="fas <?= htmlspecialchars($a['icon'] ?? 'fa-check') ?>" style="color:var(--canopy);width:14px;text-align:center;flex-shrink:0;font-size:.85rem"></i>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:.83rem;font-weight:600;color:var(--jungle-dark)"><?= htmlspecialchars($a['name']) ?></div>
                                <?php if (!empty($a['description'])): ?>
                                <div style="font-size:.72rem;color:var(--text-light);margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($a['description']) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="display:flex;align-items:center;gap:1rem;padding-top:1rem;border-top:1px solid var(--stone,#ddd)">
                    <span id="selectedCount" style="font-size:.83rem;color:var(--text-mid)">None selected</span>
                    <button type="submit" class="btn btn-primary btn-sm" id="addAmenityBtn" disabled style="opacity:.5">
                        <i class="fas fa-plus-circle"></i> Add to Booking
                    </button>
                </div>
            </form>
        </div>

        <!-- Already Added -->
        <div>
            <div style="background:var(--mist,#f4f6f4);border-radius:8px;padding:1.25rem">
                <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--text-light);margin-bottom:.75rem">
                    Already Requested
                </div>
                <?php if (!empty($addon_rows)): ?>
                    <?php foreach ($addon_rows as $ar): ?>
                    <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;padding:.4rem 0;border-bottom:1px solid #e0e3e0">
                        <i class="fas <?= htmlspecialchars($ar['svc_icon']) ?>" style="color:var(--canopy);width:14px;text-align:center;flex-shrink:0"></i>
                        <span style="color:var(--jungle-dark);font-weight:500;flex:1"><?= htmlspecialchars($ar['svc_name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size:.82rem;color:var(--text-light);font-style:italic;margin:0">No amenities requested yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function syncBorder(id) {
    var cb  = document.getElementById('ach_' + id);
    var lbl = document.getElementById('albl_' + id);
    if (!cb || !lbl) return;
    lbl.style.borderColor = cb.checked ? 'var(--canopy)' : 'transparent';
    lbl.style.background  = cb.checked ? '#eaf3ea' : 'var(--mist,#f4f6f4)';
}
function updateBtn() {
    var checked = document.querySelectorAll('#adminAmenityForm input[name="amenity_ids[]"]:checked').length;
    var btn = document.getElementById('addAmenityBtn');
    var lbl = document.getElementById('selectedCount');
    btn.disabled  = checked === 0;
    btn.style.opacity = checked > 0 ? '1' : '.5';
    lbl.textContent = checked > 0 ? checked + ' amenit' + (checked > 1 ? 'ies' : 'y') + ' selected' : 'None selected';
}
</script>

<?php endif; ?>

<div class="admin-card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;flex:1">
            <input type="text" name="search" placeholder="Search ref, guest…" value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending','confirmed','checked_in','checked_out','cancelled'] as $s): ?>
                <option value="<?=$s?>" <?=$status_f===$s?'selected':''?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?= $date_from ?>" placeholder="From">
            <input type="date" name="date_to" value="<?= $date_to ?>" placeholder="To">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="<?= SITE_URL ?>/admin/bookings.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
        <button onclick="document.getElementById('newBookingModal').classList.add('active')" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> New Booking
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Ref</th><th>Guest</th><th>Room</th><th>Check In</th><th>Check Out</th><th>Nights</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($bookings && $bookings->num_rows > 0):
                    while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><a href="?view=<?= $b['id'] ?>" style="color:var(--canopy);font-weight:700;font-size:.82rem"><?= htmlspecialchars($b['booking_ref']) ?></a></td>
                    <td>
                        <div style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($b['guest_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-light)"><?= htmlspecialchars($b['guest_email']) ?></div>
                    </td>
                    <td style="font-size:.85rem">
                        <div><?= htmlspecialchars($b['room_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-light)"><?= htmlspecialchars($b['room_number']) ?></div>
                    </td>
                    <td style="font-size:.85rem"><?= formatDate($b['check_in']) ?></td>
                    <td style="font-size:.85rem"><?= formatDate($b['check_out']) ?></td>
                    <td style="text-align:center;font-weight:600"><?= $b['nights'] ?></td>
                    <td style="font-weight:700;color:var(--jungle-dark)"><?= formatCurrency($b['total_amount']) ?></td>
                    <td><span class="badge <?= getStatusBadge($b['payment_status']??'unpaid') ?>"><?= ucfirst($b['payment_status']??'unpaid') ?></span></td>
                    <td><span class="badge <?= getStatusBadge($b['status']) ?>"><?= ucfirst(str_replace('_',' ',$b['status'])) ?></span></td>
                    <td>
                        <div class="action-btns">
                            <a href="?view=<?= $b['id'] ?>" class="btn btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?= SITE_URL ?>/admin/billing.php?booking_id=<?= $b['id'] ?>" class="btn btn-dark btn-sm" title="Billing"><i class="fas fa-file-invoice"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="10"><div class="empty-state"><i class="fas fa-calendar-times"></i> No bookings found</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($p=1;$p<=$pages;$p++): ?>
        <a href="?page=<?=$p?>&status=<?= urlencode($status_f) ?>&search=<?= urlencode($search) ?>" class="page-btn <?=$p==$page_num?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="newBookingModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Create New Booking</span>
            <button class="modal-close" onclick="document.getElementById('newBookingModal').classList.remove('active')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_action" value="create_booking">
            <div class="modal-body">
                
                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                        <label class="form-label" style="margin:0;">Guest Profile *</label>
                        <button type="button" class="btn btn-outline btn-sm" id="walkInToggleBtn" onclick="toggleWalkInMode()" style="padding:0.2rem 0.5rem; font-size:0.75rem;">
                            <i class="fas fa-walking"></i> Switch to Walk-In
                        </button>
                    </div>
                    
                    <input type="hidden" name="is_walkin" id="is_walkin_flag" value="0">

                    <div id="standardGuestContainer">
                        <select name="guest_id" id="guestSelectField" class="form-control" required>
                            <option value="">— Select Guest —</option>
                            <?php if ($all_guests): $all_guests->data_seek(0); while ($ag = $all_guests->fetch_assoc()): ?>
                            <option value="<?=$ag['id']?>"><?= htmlspecialchars($ag['name']) ?> (<?= htmlspecialchars($ag['email']) ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>

                    <div id="walkInFieldsContainer" style="display:none; background:var(--mist); padding:1rem; border-radius:var(--radius); border:1px dashed var(--stone);">
                        <div class="form-row" style="margin-bottom:0.5rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:0.75rem;">First Name *</label>
                                <input type="text" name="walkin_first_name" id="w_fn" class="form-control" placeholder="John">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:0.75rem;">Last Name *</label>
                                <input type="text" name="walkin_last_name" id="w_ln" class="form-control" placeholder="Doe">
                            </div>
                        </div>
                        <div class="form-row" style="margin-bottom:0.5rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:0.75rem;">Email Address</label>
                                <input type="email" name="walkin_email" class="form-control" placeholder="john@example.com">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:0.75rem;">Phone Number</label>
                                <input type="tel" name="walkin_phone" class="form-control" placeholder="09123456789">
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.4rem; margin-top:0.6rem;">
                            <input type="checkbox" name="walkin_checkin_now" id="w_checkin" value="1" style="width:auto; cursor:pointer;">
                            <label for="w_checkin" style="font-size:0.8rem; cursor:pointer; color:var(--jungle-dark); font-weight:600;">
                                <i class="fas fa-sign-in-alt"></i> Check-in guest immediately right now
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Room *</label>
                    <select name="room_id" class="form-control" required id="roomSelect" onchange="updateRate(this)">
                        <option value="">— Select Room —</option>
                        <?php if ($all_rooms) while ($ar = $all_rooms->fetch_assoc()): ?>
                        <option value="<?=$ar['id']?>" data-rate="<?=$ar['price_per_night']?>"><?= htmlspecialchars($ar['room_number'].' – '.$ar['name']) ?> (<?= formatCurrency($ar['price_per_night']) ?>/night)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Check In *</label><input type="date" name="check_in" class="form-control" min="<?= date('Y-m-d') ?>" required id="ciDate" onchange="calcTotal()"></div>
                    <div class="form-group"><label class="form-label">Check Out *</label><input type="date" name="check_out" class="form-control" required id="coDate" onchange="calcTotal()"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Adults *</label><input type="number" name="adults" class="form-control" value="1" min="1" max="8" required></div>
                    <div class="form-group"><label class="form-label">Children</label><input type="number" name="children" class="form-control" value="0" min="0" max="6"></div>
                </div>
                <div class="form-group"><label class="form-label">Special Requests</label><textarea name="special_requests" class="form-control" rows="2" placeholder="Dietary needs, room preferences, celebrations…"></textarea></div>
                <div id="totalPreview" style="background:var(--mist);border-radius:var(--radius);padding:1rem;display:none;font-size:.88rem;color:var(--jungle-dark)"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('newBookingModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Create Booking</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentRate = 0;
function updateRate(sel) { currentRate = parseFloat(sel.options[sel.selectedIndex]?.dataset?.rate||0); calcTotal(); }
function calcTotal() {
    const ci = document.getElementById('ciDate').value;
    const co = document.getElementById('coDate').value;
    const p  = document.getElementById('totalPreview');
    if (!ci||!co||!currentRate) { p.style.display='none'; return; }
    const nights = Math.max(1, Math.round((new Date(co)-new Date(ci))/86400000));
    const sub = currentRate * nights;
    const tax = sub * 0.12;
    const tot = sub + tax;
    p.innerHTML = `<strong>${nights} night${nights>1?'s':''}</strong> &nbsp;·&nbsp; Subtotal: ₱${sub.toLocaleString('en',{minimumFractionDigits:2})} &nbsp;·&nbsp; Tax (12%): ₱${tax.toLocaleString('en',{minimumFractionDigits:2})} &nbsp;·&nbsp; <strong>Total: ₱${tot.toLocaleString('en',{minimumFractionDigits:2})}</strong>`;
    p.style.display='block';
}

// Javascript toggler between existing dropdown list or text entry forms
function toggleWalkInMode() {
    const flag = document.getElementById('is_walkin_flag');
    const toggleBtn = document.getElementById('walkInToggleBtn');
    const standardContainer = document.getElementById('standardGuestContainer');
    const walkInContainer = document.getElementById('walkInFieldsContainer');
    
    const guestSelect = document.getElementById('guestSelectField');
    const walkInFn = document.getElementById('w_fn');
    const walkInLn = document.getElementById('w_ln');

    if (flag.value === "0") {
        // Toggle to Walk-In Mode
        flag.value = "1";
        toggleBtn.innerHTML = '<i class="fas fa-users"></i> Switch to Select Guest';
        standardContainer.style.display = "none";
        walkInContainer.style.display = "block";
        
        // Update required attributes so empty selectors don't block submission
        guestSelect.required = false;
        guestSelect.value = ""; 
        walkInFn.required = true;
        walkInLn.required = true;
    } else {
        // Toggle back to Standard Select Mode
        flag.value = "0";
        toggleBtn.innerHTML = '<i class="fas fa-walking"></i> Switch to Walk-In';
        standardContainer.style.display = "block";
        walkInContainer.style.display = "none";
        
        guestSelect.required = true;
        walkInFn.required = false;
        walkInLn.required = false;
    }
}
</script>

<?php include __DIR__ . '/layout-bottom.php'; endif;?>