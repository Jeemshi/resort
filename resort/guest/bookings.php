<?php
require_once __DIR__ . '/../includes/config.php';

if (!isGuestLoggedIn()) {
    $current_url = $_SERVER['REQUEST_URI'];
    redirect(SITE_URL . '/guest/login.php?redirect=' . urlencode($current_url));
}

$db = getDB();
$error = '';
$success_booking = null;
$guest_id = (int)$_SESSION['guest_id'];

$room_id      = (int)($_GET['room_id'] ?? 0);
$check_in     = sanitize($_GET['check_in'] ?? '');
$check_out    = sanitize($_GET['check_out'] ?? '');
$guests_count = (int)($_GET['guests'] ?? 1);

if ($room_id <= 0) { redirect(SITE_URL . '/guest/rooms.php'); }
if (empty($check_in))  $check_in  = date('Y-m-d', strtotime('+1 day'));
if (empty($check_out)) $check_out = date('Y-m-d', strtotime('+3 days'));

$stmt = $db->prepare("SELECT r.*, rc.name as category_name FROM rooms r JOIN room_categories rc ON r.category_id = rc.id WHERE r.id = ?");
$stmt->bind_param('i', $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    $error = "Room not found.";
} else {
    $date1 = new DateTime($check_in);
    $date2 = new DateTime($check_out);
    $nights = max(1, $date1->diff($date2)->days);
    $room_rate    = (float)$room['price_per_night'];
    $subtotal     = $room_rate * $nights;
    $tax_amount   = $subtotal * 0.12;
    $total_amount = $subtotal + $tax_amount;
    $downpayment_value = $total_amount * 0.30;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_booking'])) {
        $special_requests = sanitize($_POST['special_requests'] ?? '');
        $payment_method   = sanitize($_POST['payment_method'] ?? 'cash');
        $payment_tier     = sanitize($_POST['payment_option'] ?? 'full');
        $cart_json        = $_POST['cart_data'] ?? '[]';
        $amenity_json     = $_POST['amenity_data'] ?? '[]';

        // --- Double-booking check: check active bookings for this room on overlapping dates ---
        $chk_overlap = $db->prepare("
            SELECT id FROM bookings 
            WHERE room_id = ? 
              AND status NOT IN ('cancelled','checked_out')
              AND check_in < ? AND check_out > ?
            LIMIT 1
        ");
        $chk_overlap->bind_param('iss', $room_id, $check_out, $check_in);
        $chk_overlap->execute();
        $overlap = $chk_overlap->get_result()->fetch_assoc();

        // Also check room status directly
        $chk_status = $db->query("SELECT status FROM rooms WHERE id = $room_id")->fetch_assoc();

        if ($overlap || ($chk_status['status'] ?? '') === 'occupied') {
            $error = "Sorry, this room is already booked for the selected dates. Please choose different dates or another room.";
        } else {
            // Validate payment-method-specific fields
            $online_payment = in_array($payment_method, ['gcash', 'credit_card', 'maya', 'paypal']);
            $payment_extra = [];

            if ($payment_method === 'gcash') {
                $gcash_ref = trim($_POST['gcash_ref'] ?? '');
                if (empty($gcash_ref)) { $error = "Please enter your GCash reference number."; }
                $payment_extra['reference'] = $gcash_ref;
            } elseif ($payment_method === 'credit_card') {
                $cc_number = trim($_POST['cc_number'] ?? '');
                $cc_name   = trim($_POST['cc_name'] ?? '');
                $cc_expiry = trim($_POST['cc_expiry'] ?? '');
                $cc_cvv    = trim($_POST['cc_cvv'] ?? '');
                if (empty($cc_number) || strlen(preg_replace('/\D/','',$cc_number)) < 13) { $error = "Please enter a valid credit card number."; }
                elseif (empty($cc_name))   { $error = "Please enter the cardholder name."; }
                elseif (empty($cc_expiry)) { $error = "Please enter the card expiry date."; }
                elseif (empty($cc_cvv))    { $error = "Please enter the CVV."; }
                $payment_extra['cc_last4'] = substr(preg_replace('/\D/','',$cc_number), -4);
            } elseif ($payment_method === 'maya') {
                $maya_ref = trim($_POST['maya_ref'] ?? '');
                if (empty($maya_ref)) { $error = "Please enter your Maya reference number."; }
                $payment_extra['reference'] = $maya_ref;
            }

            // For online payments, require ID and receipt uploads
            $id_upload_path      = '';
            $receipt_upload_path = '';

            if (empty($error) && $online_payment) {
                $upload_dir = __DIR__ . '/../uploads/payment_docs/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                // ID upload
                if (empty($_FILES['id_upload']['name'])) {
                    $error = "Please upload a valid government ID for verification.";
                } elseif ($_FILES['id_upload']['error'] !== UPLOAD_ERR_OK) {
                    $error = "ID upload failed. Please try again.";
                } else {
                    $id_ext = strtolower(pathinfo($_FILES['id_upload']['name'], PATHINFO_EXTENSION));
                    if (!in_array($id_ext, ['jpg','jpeg','png','pdf'])) {
                        $error = "ID must be JPG, PNG, or PDF.";
                    } else {
                        $id_filename = 'id_' . $guest_id . '_' . time() . '.' . $id_ext;
                        if (!move_uploaded_file($_FILES['id_upload']['tmp_name'], $upload_dir . $id_filename)) {
                            $error = "Failed to save ID file. Please try again.";
                        } else {
                            $id_upload_path = 'payment_docs/' . $id_filename;
                        }
                    }
                }

                // Receipt upload
                if (empty($error)) {
                    if (empty($_FILES['receipt_upload']['name'])) {
                        $error = "Please upload your payment receipt.";
                    } elseif ($_FILES['receipt_upload']['error'] !== UPLOAD_ERR_OK) {
                        $error = "Receipt upload failed. Please try again.";
                    } else {
                        $rec_ext = strtolower(pathinfo($_FILES['receipt_upload']['name'], PATHINFO_EXTENSION));
                        if (!in_array($rec_ext, ['jpg','jpeg','png','pdf'])) {
                            $error = "Receipt must be JPG, PNG, or PDF.";
                        } else {
                            $rec_filename = 'receipt_' . $guest_id . '_' . time() . '.' . $rec_ext;
                            if (!move_uploaded_file($_FILES['receipt_upload']['tmp_name'], $upload_dir . $rec_filename)) {
                                $error = "Failed to save receipt file. Please try again.";
                            } else {
                                $receipt_upload_path = 'payment_docs/' . $rec_filename;
                            }
                        }
                    }
                }
            }

            if (empty($error)) {
                $db->begin_transaction();
                try {
                    $booking_ref = 'WN-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

                    $cart_services = json_decode($cart_json, true) ?: [];
                    $services_subtotal = 0;
                    foreach ($cart_services as $cs) {
                        $services_subtotal += (float)$cs['price'] * (int)$cs['qty'];
                    }
                    // Amenities are complimentary room features — no extra charge
                    $amenity_extras   = json_decode($amenity_json, true) ?: [];
                    $amenity_subtotal = 0;

                    $combined_subtotal = $subtotal + $services_subtotal + $amenity_subtotal;
                    $combined_tax      = $combined_subtotal * 0.12;
                    $combined_total    = $combined_subtotal + $combined_tax;
                    $dp_val            = $combined_total * 0.30;

                    // Build payment notes (reference numbers, card last 4, uploads)
                    $pay_notes = json_encode(array_merge($payment_extra, [
                        'id_doc'      => $id_upload_path,
                        'receipt_doc' => $receipt_upload_path,
                    ]));

                    // Status is always 'pending' — admin must confirm
                    $stmt_book = $db->prepare("
                        INSERT INTO bookings (
                            booking_ref, guest_id, room_id, check_in, check_out, nights,
                            adults, children, room_rate, subtotal, tax_amount, total_amount,
                            special_requests, payment_option, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt_book->bind_param(
                        'siissiiddddss',
                        $booking_ref, $guest_id, $room_id, $check_in, $check_out,
                        $nights, $guests_count, $room_rate,
                        $combined_subtotal, $combined_tax, $combined_total,
                        $special_requests, $payment_tier
                    );
                    $stmt_book->execute();
                    $booking_id = $db->insert_id;

                    foreach ($cart_services as $cs) {
                        $svc_id    = (int)$cs['id'];
                        $svc_qty   = (int)$cs['qty'];
                        $svc_price = (float)$cs['price'];
                        $svc_total = $svc_price * $svc_qty;
                        $db->query("INSERT INTO booking_services (booking_id,service_id,quantity,unit_price,total_price) VALUES($booking_id,$svc_id,$svc_qty,$svc_price,$svc_total)");
                    }

                    // Save amenity add-on selections to booking_addon_services
                    foreach ($amenity_extras as $amenity_id) {
                        $amenity_id = (int)$amenity_id;
                        if ($amenity_id <= 0) continue;
                        $amenity_row = $db->query("SELECT * FROM amenities WHERE id=$amenity_id")->fetch_assoc();
                        if (!$amenity_row) continue;
                        // Find or create a zero-price service record for this amenity
                        $safe_name = $db->real_escape_string($amenity_row['name']);
                        $safe_icon = $db->real_escape_string($amenity_row['icon'] ?? 'fa-concierge-bell');
                        $safe_cat  = $db->real_escape_string($amenity_row['category'] ?? 'Amenities');
                        $svc_check = $db->query("SELECT id FROM services WHERE name='$safe_name' AND price=0 LIMIT 1")->fetch_assoc();
                        if ($svc_check) {
                            $svc_id = (int)$svc_check['id'];
                        } else {
                            $db->query("INSERT INTO services (name, description, price, category, icon, available) VALUES ('$safe_name', 'Guest amenity add-on', 0, '$safe_cat', '$safe_icon', 0)");
                            $svc_id = $db->insert_id;
                        }
                        $db->query("INSERT INTO booking_addon_services (booking_id, service_id, quantity, unit_price, total_price, notes) VALUES ($booking_id, $svc_id, 1, 0, 0, 'Guest amenity request at booking')");
                    }

                    $amount_paid    = ($payment_tier === 'downpayment') ? $dp_val : 0.00;
                    $balance        = $combined_total - $amount_paid;
                    $payment_status = ($payment_tier === 'downpayment') ? 'partial' : 'unpaid';
                    $invoice_num    = 'INV-' . time() . '-' . $booking_id;

                    $stmt_bill = $db->prepare("
                        INSERT INTO billing (booking_id, invoice_number, amount_paid, balance, payment_method, payment_status, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt_bill->bind_param('isddsss', $booking_id, $invoice_num, $amount_paid, $balance, $payment_method, $payment_status, $pay_notes);
                    $stmt_bill->execute();

                    $db->commit();

                    $success_booking = [
                        'ref'      => $booking_ref,
                        'room'     => $room['name'],
                        'dates'    => date('M d, Y', strtotime($check_in)) . ' → ' . date('M d, Y', strtotime($check_out)),
                        'nights'   => $nights,
                        'total'    => $combined_total,
                        'paid'     => $amount_paid,
                        'balance'  => $balance,
                        'method'   => strtoupper($payment_method),
                        'status'   => $payment_status,
                        'services' => count($cart_services),
                        'vat'      => $combined_tax,
                        'online'   => $online_payment,
                    ];
                } catch (Exception $e) {
                    $db->rollback();
                    $error = "Booking failed: " . $e->getMessage();
                }
            }
        }
    }
}

$page_title = 'Complete Your Booking';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Checkout</div>
        <h1>Complete Your Booking</h1>
    </div>
</div>

<section class="section" style="background:#f8f9fa;padding:4rem 0">
    <div class="container" style="max-width:1100px">

        <?php if ($error): ?>
        <div style="background:#fce8e6;color:#a83232;padding:1.25rem;border-radius:6px;margin-bottom:2rem;border-left:5px solid #a83232;font-weight:500">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success_booking): ?>
        <div style="background:white;border:1px solid #fde8cd;border-radius:12px;padding:3rem;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.06);margin:0 auto;max-width:700px;border-top:6px solid #c8873a">
            <div style="width:80px;height:80px;background:#fef7e0;color:#c8873a;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <h2 style="font-family:var(--font-display);color:#162e22;margin-bottom:.5rem;font-size:2rem">Booking Submitted!</h2>
            <p style="color:#666;margin-bottom:.5rem">Your reservation is <strong style="color:#b06000">pending review</strong>.</p>
            <p style="color:#888;font-size:.9rem;margin-bottom:2rem">Our staff will verify your details and confirm your booking within 24 hours. You will be notified once confirmed.</p>
            <?php if ($success_booking['online']): ?>
            <div style="background:#fef7e0;border:1px solid #f5d89c;border-radius:8px;padding:1rem;margin-bottom:1.5rem;text-align:left;font-size:.85rem;color:#7a5000">
                <i class="fas fa-info-circle"></i> <strong>ID & Receipt Uploaded:</strong> Our team will verify your documents before confirming the booking.
            </div>
            <?php endif; ?>
            <div style="max-width:520px;margin:0 auto 2rem;background:#f8f9fa;border:1px solid #e1e4e8;border-radius:8px;padding:1.5rem;text-align:left">
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">Booking Ref:</span><strong style="font-family:monospace;color:#162e22"><?= $success_booking['ref'] ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">Room:</span><span style="font-weight:600"><?= htmlspecialchars($success_booking['room']) ?></span></div>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">Dates:</span><span><?= $success_booking['dates'] ?> (<?= $success_booking['nights'] ?> nights)</span></div>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">Booking Status:</span><span style="color:#b06000;font-weight:700"><i class="fas fa-hourglass-half"></i> Pending Confirmation</span></div>
                <?php if ($success_booking['services'] > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">Services Added:</span><span style="color:#2d5a27;font-weight:600"><?= $success_booking['services'] ?> service<?=$success_booking['services']>1?'s':''?></span></div>
                <?php endif; ?>
                <hr style="border:0;border-top:1px dashed #e1e4e8;margin:.5rem 0">
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">VAT (12%):</span><span>₱<?= number_format($success_booking['vat'], 2) ?></span></div>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:1rem;font-weight:700;color:#162e22"><span>Total Amount:</span><span>₱<?= number_format($success_booking['total'], 2) ?></span></div>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem"><span style="color:#666">Payment via:</span><span><?= $success_booking['method'] ?></span></div>
                <?php if ($success_booking['balance'] > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem;color:#b06000"><span>Balance Due On-site:</span><strong>₱<?= number_format($success_booking['balance'], 2) ?></strong></div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                <a href="my_bookings.php" class="btn btn-primary"><i class="fas fa-bookmark"></i> View My Bookings</a>
                <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline">Back to Home</a>
            </div>
        </div>

        <?php else: ?>

        <div style="margin-bottom:1.5rem">
            <a href="<?= SITE_URL ?>/guest/rooms.php" style="color:var(--text-mid);text-decoration:none;font-size:.9rem"><i class="fas fa-arrow-left"></i> Back to Rooms</a>
        </div>

        <!-- Cart Summary Banner -->
        <div id="cartBanner" style="display:none;background:var(--jungle-dark);color:white;border-radius:var(--radius-lg);padding:1.25rem 1.75rem;margin-bottom:2rem;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:.75rem">
                <i class="fas fa-shopping-cart" style="color:var(--ochre-light,#f5c483);font-size:1.25rem"></i>
                <div>
                    <div style="font-weight:700;font-size:.95rem" id="cartBannerTitle">Services Added</div>
                    <div style="font-size:.8rem;color:rgba(255,255,255,.6)" id="cartBannerSub"></div>
                </div>
            </div>
            <a href="<?= SITE_URL ?>/guest/cart.php" style="color:white;background:rgba(255,255,255,0.15);padding:.5rem 1rem;border-radius:6px;font-size:.85rem;text-decoration:none;font-weight:600">Edit Cart →</a>
        </div>

        <!-- Pending Notice -->
        <div style="background:#fef7e0;border:1px solid #f5d89c;border-radius:8px;padding:1rem 1.5rem;margin-bottom:2rem;display:flex;align-items:center;gap:.75rem">
            <i class="fas fa-info-circle" style="color:#c8873a;font-size:1.25rem"></i>
            <div>
                <strong style="color:#7a5000">Your reservation will be pending admin confirmation.</strong>
                <div style="font-size:.85rem;color:#8a6010;margin-top:.2rem">After submitting, our staff will review and confirm your booking within 24 hours.</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:2.5rem;align-items:start">
            <!-- Form -->
            <div>
                <div style="background:white;border:1px solid #e1e4e8;border-radius:10px;padding:2rem;box-shadow:0 2px 12px rgba(0,0,0,.04)">
                    <form method="POST" enctype="multipart/form-data" id="bookingForm">
                        <input type="hidden" name="cart_data" id="cartDataInput" value="[]">
                        <input type="hidden" name="amenity_data" id="amenityDataInput" value="[]">

                        <h2 style="font-family:var(--font-display);color:#162e22;margin:0 0 1.5rem;font-size:1.3rem;border-bottom:1px solid #eee;padding-bottom:.75rem">
                            <i class="fas fa-wallet" style="color:#c8873a"></i> 1. Payment Method
                        </h2>

                        <!-- Payment Methods (no Bank Wire) -->
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.75rem;margin-bottom:1.5rem">
                            <?php
                            $methods = [
                                ['cash',        'fa-money-bill-wave', '#2d5a27', 'Cash'],
                                ['gcash',       'fa-mobile-alt',      '#1a73e8', 'GCash'],
                                ['credit_card', 'fa-credit-card',     '#a83232', 'Credit Card'],
                                ['maya',        'fa-wallet',          '#6d28d9', 'Maya'],
                            ];
                            foreach ($methods as $i => $m): ?>
                            <label class="pay-method-label" style="border:2px solid <?=$i===0?'var(--jungle-dark)':'#e1e4e8'?>;border-radius:8px;padding:1rem;text-align:center;cursor:pointer;display:block;transition:all .2s">
                                <input type="radio" name="payment_method" value="<?=$m[0]?>" <?=$i===0?'checked':''?> style="margin-bottom:.5rem;transform:scale(1.1)" onchange="switchPaymentFields(this.value)"><br>
                                <i class="fas <?=$m[1]?>" style="color:<?=$m[2]?>;font-size:1.25rem;margin-bottom:.25rem;display:block"></i>
                                <span style="font-size:.85rem;font-weight:600;color:#333"><?=$m[3]?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Dynamic payment fields -->
                        <div id="paymentFields" style="margin-bottom:1.5rem">

                            <!-- CASH: no extra fields -->
                            <div id="fields_cash" class="pay-fields" style="background:#f0faf4;border:1px solid #c3e6cb;border-radius:8px;padding:1.25rem">
                                <div style="display:flex;align-items:center;gap:.75rem;color:#2d5a27">
                                    <i class="fas fa-money-bill-wave fa-lg"></i>
                                    <div>
                                        <div style="font-weight:700">Pay at the Resort</div>
                                        <div style="font-size:.85rem;color:#4a7c59;margin-top:.2rem">Bring the exact amount (or more) when you check in. Our staff will assist you.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- GCASH: reference number only -->
                            <div id="fields_gcash" class="pay-fields" style="display:none">
                                <div style="background:#e8f0fe;border:1px solid #b8d0fc;border-radius:8px;padding:1rem;margin-bottom:1rem;font-size:.85rem;color:#1a4f9c">
                                    <i class="fas fa-info-circle"></i> Send payment to GCash number: <strong>09XX-XXX-XXXX</strong> (Subic Resort). Enter your reference number below and upload your receipt and ID in the section underneath.
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">GCash Reference Number *</label>
                                    <input type="text" name="gcash_ref" placeholder="e.g. 1234567890" style="width:100%;padding:.7rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.9rem">
                                </div>
                            </div>

                            <!-- CREDIT CARD: card details only -->
                            <div id="fields_credit_card" class="pay-fields" style="display:none">
                                <div style="background:#fce8e6;border:1px solid #f5b8b8;border-radius:8px;padding:1rem;margin-bottom:1rem;font-size:.85rem;color:#a83232">
                                    <i class="fas fa-lock"></i> Your card information is submitted securely for manual verification by our staff.
                                </div>
                                <div style="display:grid;gap:1rem">
                                    <div>
                                        <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">Card Number *</label>
                                        <input type="text" name="cc_number" placeholder="1234 5678 9012 3456" maxlength="19" style="width:100%;padding:.7rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.9rem;letter-spacing:.05em" oninput="formatCardNumber(this)">
                                    </div>
                                    <div>
                                        <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">Cardholder Name *</label>
                                        <input type="text" name="cc_name" placeholder="As it appears on the card" style="width:100%;padding:.7rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.9rem">
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                                        <div>
                                            <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">Expiry Date *</label>
                                            <input type="text" name="cc_expiry" placeholder="MM/YY" maxlength="5" style="width:100%;padding:.7rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.9rem" oninput="formatExpiry(this)">
                                        </div>
                                        <div>
                                            <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">CVV *</label>
                                            <input type="text" name="cc_cvv" placeholder="3-4 digits" maxlength="4" style="width:100%;padding:.7rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.9rem">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MAYA: reference number only -->
                            <div id="fields_maya" class="pay-fields" style="display:none">
                                <div style="background:#f3e8ff;border:1px solid #d8b4fe;border-radius:8px;padding:1rem;margin-bottom:1rem;font-size:.85rem;color:#6d28d9">
                                    <i class="fas fa-info-circle"></i> Send payment to Maya number: <strong>09XX-XXX-XXXX</strong> (Subic Resort). Enter your reference number below and upload your receipt and ID in the section underneath.
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">Maya Reference Number *</label>
                                    <input type="text" name="maya_ref" placeholder="e.g. 1234567890" style="width:100%;padding:.7rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.9rem">
                                </div>
                            </div>
                        </div>

                        <!-- SINGLE shared file upload block — shown only for online payments -->
                        <div id="onlineDocsSection" style="display:none;background:#fffdf5;border:1px solid #f0d98c;border-radius:8px;padding:1.25rem;margin-bottom:2rem">
                            <div style="font-weight:700;color:#7a5000;font-size:.92rem;margin-bottom:1rem">
                                <i class="fas fa-paperclip"></i> Required Documents for Online Payment
                            </div>
                            <div style="display:grid;gap:1rem">
                                <div>
                                    <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">
                                        Government-Issued ID <span style="color:#a83232">*</span>
                                    </label>
                                    <input type="file" name="id_upload" id="id_upload" accept=".jpg,.jpeg,.png,.pdf"
                                           style="width:100%;padding:.6rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.85rem;background:white">
                                    <div style="font-size:.75rem;color:#888;margin-top:.3rem">
                                        Passport, Driver's License, SSS, PhilHealth, UMID, etc. — JPG, PNG, or PDF
                                    </div>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:.4rem">
                                        Payment Receipt / Screenshot <span style="color:#a83232">*</span>
                                    </label>
                                    <input type="file" name="receipt_upload" id="receipt_upload" accept=".jpg,.jpeg,.png,.pdf"
                                           style="width:100%;padding:.6rem;border:1px solid #ccd0d5;border-radius:6px;font-size:.85rem;background:white">
                                    <div style="font-size:.75rem;color:#888;margin-top:.3rem">
                                        Screenshot of your GCash/Maya transfer or credit card authorization — JPG, PNG, or PDF
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h2 style="font-family:var(--font-display);color:#162e22;margin:0 0 1rem;font-size:1.3rem;border-bottom:1px solid #eee;padding-bottom:.75rem">
                            <i class="fas fa-layer-group" style="color:#c8873a"></i> 2. Payment Plan
                        </h2>
                        <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:2rem">
                            <label id="dpLabel" style="display:block;background:#fafbfc;border:2px solid var(--jungle-dark);border-radius:8px;padding:1.25rem;cursor:pointer;position:relative">
                                <input type="radio" name="payment_option" value="downpayment" checked style="position:absolute;top:1.5rem;right:1.5rem;transform:scale(1.2)">
                                <div style="font-weight:700;color:#162e22;font-size:1.05rem;margin-bottom:.25rem">30% Downpayment</div>
                                <p style="font-size:.85rem;color:#666;margin:0;max-width:85%">Reserve now with <strong id="dpAmountDisplay">₱0.00</strong>. Pay the balance on arrival.</p>
                            </label>
                            <label id="fullLabel" style="display:block;background:white;border:2px solid #e1e4e8;border-radius:8px;padding:1.25rem;cursor:pointer;position:relative">
                                <input type="radio" name="payment_option" value="full" style="position:absolute;top:1.5rem;right:1.5rem;transform:scale(1.2)">
                                <div style="font-weight:700;color:#333;font-size:1.05rem;margin-bottom:.25rem">Full Payment</div>
                                <p style="font-size:.85rem;color:#666;margin:0;max-width:85%">Pay the complete amount of <strong id="fullAmountDisplay">₱0.00</strong> now.</p>
                            </label>
                        </div>

                        <div style="margin-top:1.5rem">
                            <label style="display:block;font-weight:600;color:#333;margin-bottom:.5rem;font-size:.9rem">Special Requests (Optional)</label>
                            <textarea name="special_requests" rows="3" placeholder="Dietary requirements, room setup preferences, arrival time..." style="width:100%;padding:.75rem;border:1px solid #ccd0d5;border-radius:6px;font-family:inherit;font-size:.9rem;resize:vertical"></textarea>
                        </div>

                        <div style="border-top:1px solid #eee;padding-top:1.5rem;margin-top:1.5rem;display:flex;justify-content:flex-end">
                            <button type="submit" name="finalize_booking" class="btn btn-primary" style="padding:.9rem 2.5rem;font-size:1rem">
                                <i class="fas fa-paper-plane"></i> Submit Booking Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Summary -->
            <div>
                <div style="background:white;border:1px solid #e1e4e8;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04);position:sticky;top:20px">
                    <div style="background:#162e22;color:white;padding:1.25rem 1.5rem">
                        <span style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.5)">Room</span>
                        <h3 style="font-family:var(--font-display);margin:.2rem 0 0;font-size:1.2rem;color:#f5c483">Room <?= htmlspecialchars($room['room_number']) ?></h3>
                    </div>
                    <div style="padding:1.5rem">
                        <div style="font-weight:700;color:#333;margin-bottom:.2rem"><?= htmlspecialchars($room['name']) ?></div>
                        <div style="font-size:.8rem;color:#777;text-transform:uppercase;font-weight:600;margin-bottom:1.25rem"><?= htmlspecialchars($room['category_name']) ?></div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.8rem;background:#fafbfc;padding:.75rem;border-radius:4px;border:1px solid #eee;margin-bottom:1.25rem">
                            <div><strong style="color:#555">Check-In:</strong><br><?= date('M d, Y', strtotime($check_in)) ?></div>
                            <div><strong style="color:#555">Check-Out:</strong><br><?= date('M d, Y', strtotime($check_out)) ?></div>
                        </div>

                        <div style="border-top:1px dashed #e1e4e8;padding-top:1rem;display:flex;flex-direction:column;gap:.5rem;font-size:.88rem;color:#555">
                            <div style="display:flex;justify-content:space-between"><span>Room (<?=$nights?> night<?=$nights>1?'s':''?>)</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
                            <div id="servicesRow" style="display:none;justify-content:space-between"><span>Services</span><span id="servicesCostDisplay">₱0.00</span></div>
                            <div style="display:flex;justify-content:space-between;color:#b06000"><span>VAT (12%)</span><span id="taxDisplay">₱<?= number_format($tax_amount, 2) ?></span></div>
                            <div id="summaryTotal" style="display:flex;justify-content:space-between;font-size:1.1rem;color:#162e22;font-weight:bold;border-top:1px solid #eee;padding-top:.85rem;margin-top:.25rem">
                                <span>Total</span><span>₱<?= number_format($total_amount, 2) ?></span>
                            </div>
                        </div>

                        <div id="miniCartList" style="margin-top:.75rem;display:none">
                            <div style="font-size:.75rem;color:#777;text-transform:uppercase;font-weight:700;letter-spacing:.05em;margin-bottom:.5rem">Added Services:</div>
                            <div id="miniCartItems" style="display:flex;flex-direction:column;gap:.35rem"></div>
                        </div>

                        <!-- Booking status note -->
                        <div style="margin-top:1rem;background:#fef7e0;border-radius:6px;padding:.75rem;font-size:.8rem;color:#7a5000;border:1px solid #f5d89c">
                            <i class="fas fa-hourglass-half"></i> Booking will be <strong>Pending</strong> until confirmed by our staff.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
var BASE_SUBTOTAL = <?= $subtotal ?>;
var NIGHTS = <?= $nights ?>;

function getCart() {
    try { return JSON.parse(localStorage.getItem('resort_service_cart') || '[]'); } catch(e) { return []; }
}
function getAmenities() {
    try { return JSON.parse(localStorage.getItem('resort_amenity_cart') || '[]'); } catch(e) { return []; }
}
var amenityPrices = {}; // amenities are complimentary — no extra charge

function fmt(n) { return '₱' + parseFloat(n).toFixed(2).replace(/\d(?=(\d{3})+\.)/g,'$&,'); }

function updateSummary() {
    var cart = getCart();
    var amenities = getAmenities();
    var svcTotal = cart.reduce(function(s,i){ return s + i.price*i.qty; }, 0);
    var amenTotal = 0; // amenities are complimentary
    var combined_sub = BASE_SUBTOTAL + svcTotal + amenTotal;
    var tax = combined_sub * 0.12;
    var total = combined_sub + tax;
    var dp = total * 0.30;

    document.getElementById('taxDisplay').textContent = fmt(tax);
    document.getElementById('summaryTotal').querySelector('span:last-child').textContent = fmt(total);
    document.getElementById('dpAmountDisplay').textContent = fmt(dp);
    document.getElementById('fullAmountDisplay').textContent = fmt(total);

    var svcRow = document.getElementById('servicesRow');
    if (svcTotal + amenTotal > 0) {
        svcRow.style.display = 'flex';
        document.getElementById('servicesCostDisplay').textContent = fmt(svcTotal + amenTotal);
    } else { svcRow.style.display = 'none'; }

    var banner = document.getElementById('cartBanner');
    if (cart.length > 0 || amenities.length > 0) {
        banner.style.display = 'flex';
        var totalItems = cart.reduce(function(s,i){ return s+i.qty; }, 0);
        document.getElementById('cartBannerTitle').textContent = totalItems + ' service' + (totalItems>1?'s':'') + ' added' + (amenities.length>0?' + '+amenities.length+' amenity req.':'');
        document.getElementById('cartBannerSub').textContent = 'Extra: ' + fmt(svcTotal + amenTotal);
    } else { banner.style.display = 'none'; }

    var miniList = document.getElementById('miniCartList');
    var miniItems = document.getElementById('miniCartItems');
    if (cart.length > 0) {
        miniList.style.display = 'block';
        miniItems.innerHTML = cart.map(function(i){
            return '<div style="display:flex;justify-content:space-between;font-size:.82rem;color:#555"><span><i class="fas '+i.icon+' fa-xs"></i> '+i.name+' ×'+i.qty+'</span><span>'+fmt(i.price*i.qty)+'</span></div>';
        }).join('');
    } else { miniList.style.display = 'none'; }

    document.getElementById('cartDataInput').value = JSON.stringify(cart);
    document.getElementById('amenityDataInput').value = JSON.stringify(amenities);
}

// Switch payment method fields
var ONLINE_METHODS = ['gcash', 'credit_card', 'maya'];

function switchPaymentFields(method) {
    // Show the right method-specific panel
    document.querySelectorAll('.pay-fields').forEach(function(el){ el.style.display = 'none'; });
    var target = document.getElementById('fields_' + method);
    if (target) target.style.display = 'block';

    // Show/hide the single shared file upload block
    var docsSection = document.getElementById('onlineDocsSection');
    if (docsSection) {
        docsSection.style.display = ONLINE_METHODS.indexOf(method) !== -1 ? 'block' : 'none';
    }

    // Clear the file inputs when switching methods so stale data doesn't linger
    var idInput = document.getElementById('id_upload');
    var recInput = document.getElementById('receipt_upload');
    if (idInput) idInput.value = '';
    if (recInput) recInput.value = '';

    // Highlight selected method card
    document.querySelectorAll('.pay-method-label').forEach(function(el){
        el.style.borderColor = '#e1e4e8';
    });
    var checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (checkedRadio) checkedRadio.closest('label').style.borderColor = 'var(--jungle-dark)';
}

// Payment option highlight
document.querySelectorAll('input[name="payment_option"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('input[name="payment_option"]').forEach(function(r) {
            r.closest('label').style.borderColor = '#e1e4e8';
        });
        this.closest('label').style.borderColor = 'var(--jungle-dark)';
    });
});

// Credit card number formatter
function formatCardNumber(input) {
    var v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.replace(/(.{4})/g,'$1 ').trim();
}
function formatExpiry(input) {
    var v = input.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2);
    input.value = v;
}

document.addEventListener('DOMContentLoaded', function() {
    updateSummary();
    switchPaymentFields('cash');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>