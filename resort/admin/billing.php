<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'billing';
$admin_title = 'Billing';
$db = getDB();

$msg = '';
$err = '';

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'record_payment') {
    $bill_id = (int)$_POST['bill_id'];
    $amount  = (float)$_POST['amount_paid'];
    $method  = sanitize($_POST['payment_method']);
    $notes   = sanitize($_POST['notes']??'');

    $bill = $db->query("SELECT * FROM billing WHERE id=$bill_id")->fetch_assoc();
    if ($bill) {
        $new_paid = $bill['amount_paid'] + $amount;
        $booking  = $db->query("SELECT total_amount FROM bookings WHERE id={$bill['booking_id']}")->fetch_assoc();
        $total    = $booking['total_amount'];
        $balance  = max(0, $total - $new_paid);
        $pstatus  = ($balance <= 0) ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
        $pdate    = ($pstatus === 'paid') ? "NOW()" : "payment_date";

        $db->query("UPDATE billing SET amount_paid=$new_paid, balance=$balance, payment_status='$pstatus', payment_method='$method', payment_date=NOW(), notes='$notes' WHERE id=$bill_id");
        $msg = "Payment of " . formatCurrency($amount) . " recorded. Status: $pstatus.";
    }
}

// Filters
$search    = sanitize($_GET['search'] ?? '');
$pstatus_f = sanitize($_GET['pstatus'] ?? '');
$bid_f     = (int)($_GET['booking_id'] ?? 0);
$per_page  = 15;
$page_num  = max(1,(int)($_GET['page']??1));
$offset    = ($page_num-1)*$per_page;

$where = '1=1';
if ($search)    $where .= " AND (bl.invoice_number LIKE '%$search%' OR b.booking_ref LIKE '%$search%' OR CONCAT(g.first_name,' ',g.last_name) LIKE '%$search%')";
if ($pstatus_f) $where .= " AND bl.payment_status='$pstatus_f'";
if ($bid_f)     $where .= " AND bl.booking_id=$bid_f";

$total_count = $db->query("SELECT COUNT(*) c FROM billing bl JOIN bookings b ON bl.booking_id=b.id JOIN guests g ON b.guest_id=g.id WHERE $where")->fetch_assoc()['c'];
$pages = ceil($total_count/$per_page);

$bills = $db->query("
    SELECT bl.*, b.booking_ref, b.total_amount, b.check_in, b.check_out, b.status booking_status,
           CONCAT(g.first_name,' ',g.last_name) guest_name, r.name room_name,
           IFNULL((SELECT SUM(total_price) FROM booking_addon_services bas WHERE bas.booking_id=b.id AND bas.status!='cancelled'),0) as addon_total
    FROM billing bl
    JOIN bookings b ON bl.booking_id=b.id
    JOIN guests g ON b.guest_id=g.id
    JOIN rooms r ON b.room_id=r.id
    WHERE $where ORDER BY bl.created_at DESC LIMIT $per_page OFFSET $offset
");

// Summary stats
$rev_total   = $db->query("SELECT IFNULL(SUM(amount_paid),0) v FROM billing WHERE payment_status IN('paid','partial')")->fetch_assoc()['v'];
$rev_month   = $db->query("SELECT IFNULL(SUM(amount_paid),0) v FROM billing WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())")->fetch_assoc()['v'];
$outstanding = $db->query("SELECT IFNULL(SUM(balance),0) v FROM billing WHERE payment_status IN('unpaid','partial')")->fetch_assoc()['v'];
$paid_count  = $db->query("SELECT COUNT(*) c FROM billing WHERE payment_status='paid'")->fetch_assoc()['c'];

include __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Revenue Stats -->
<div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card green"><div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div><div class="stat-label">Total Revenue</div><div class="stat-value" style="font-size:1.3rem"><?= formatCurrency($rev_total) ?></div></div></div>
    <div class="stat-card orange"><div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div><div class="stat-label">This Month</div><div class="stat-value" style="font-size:1.3rem"><?= formatCurrency($rev_month) ?></div></div></div>
    <div class="stat-card red"><div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:1.3rem"><?= formatCurrency($outstanding) ?></div></div></div>
    <div class="stat-card dark"><div class="stat-icon"><i class="fas fa-check-double"></i></div>
        <div><div class="stat-label">Fully Paid</div><div class="stat-value"><?= $paid_count ?></div><div class="stat-sub">invoices</div></div></div>
</div>

<div class="admin-card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;flex:1">
            <input type="text" name="search" placeholder="Invoice, booking ref, guest…" value="<?= htmlspecialchars($search) ?>">
            <select name="pstatus">
                <option value="">All Payment Status</option>
                <option value="unpaid"  <?=$pstatus_f==='unpaid'  ?'selected':''?>>Unpaid</option>
                <option value="partial" <?=$pstatus_f==='partial' ?'selected':''?>>Partial</option>
                <option value="paid"    <?=$pstatus_f==='paid'    ?'selected':''?>>Paid</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="<?= SITE_URL ?>/admin/billing.php" class="btn btn-outline btn-sm">Clear</a>
        </form>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr><th>Invoice</th><th>Booking Ref</th><th>Guest</th><th>Room</th><th>Check In</th><th>Total</th><th>Paid</th><th>Balance</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($bills && $bills->num_rows > 0):
                    while ($bl = $bills->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700;font-size:.82rem;color:var(--canopy)"><?= htmlspecialchars($bl['invoice_number']) ?></td>
                    <td style="font-size:.83rem"><a href="<?= SITE_URL ?>/admin/bookings.php?view=<?=$bl['booking_id']?>" style="color:var(--jungle-mid)"><?= htmlspecialchars($bl['booking_ref']) ?></a></td>
                    <td style="font-weight:600;font-size:.87rem"><?= htmlspecialchars($bl['guest_name']) ?></td>
                    <td style="font-size:.83rem;color:var(--text-mid)"><?= htmlspecialchars($bl['room_name']) ?></td>
                    <td style="font-size:.83rem"><?= formatDate($bl['check_in']) ?></td>
                    <td style="font-weight:700"><?= formatCurrency($bl['total_amount']) ?></td>
                    <td style="color:var(--canopy);font-weight:600"><?= formatCurrency($bl['amount_paid']) ?></td>
                    <td style="color:<?= $bl['balance']>0?'var(--ember)':'var(--canopy)' ?>;font-weight:600"><?= formatCurrency($bl['balance']??0) ?></td>
                    <td style="font-size:.82rem;text-transform:capitalize"><?= str_replace('_',' ',$bl['payment_method']??'—') ?></td>
                    <td><span class="badge <?= getStatusBadge($bl['payment_status']) ?>"><?= ucfirst($bl['payment_status']) ?></span></td>
                    <td>
                        <?php if ($bl['payment_status'] !== 'paid'): ?>
                        <button onclick="openPayModal(<?=$bl['id']?>, '<?= htmlspecialchars($bl['invoice_number']) ?>', <?=$bl['balance']??0?>, '<?= htmlspecialchars($bl['guest_name']) ?>')" class="btn btn-primary btn-sm">
                            <i class="fas fa-credit-card"></i> Pay
                        </button>
                        <?php else: ?>
                        <span style="font-size:.8rem;color:var(--canopy)"><i class="fas fa-check-circle"></i> Cleared</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="11"><div class="empty-state"><i class="fas fa-file-invoice-dollar"></i> No billing records found</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($p=1;$p<=$pages;$p++): ?>
        <a href="?page=<?=$p?>&search=<?= urlencode($search) ?>&pstatus=<?= urlencode($pstatus_f) ?>" class="page-btn <?=$p==$page_num?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Record Payment</span>
            <button class="modal-close" onclick="document.getElementById('payModal').classList.remove('active')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="form_action" value="record_payment">
            <input type="hidden" name="bill_id" id="payBillId">
            <div class="modal-body">
                <div id="payInfo" style="background:var(--mist);padding:1rem;border-radius:var(--radius);margin-bottom:1.25rem;font-size:.88rem;color:var(--jungle-dark)"></div>
                <div class="form-group">
                    <label class="form-label">Amount Being Paid (₱) *</label>
                    <input type="number" name="amount_paid" id="payAmount" class="form-control" step="0.01" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="gcash">GCash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes / Reference</label>
                    <input type="text" name="notes" class="form-control" placeholder="Transaction ref, notes…">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('payModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayModal(billId, invoice, balance, guestName) {
    document.getElementById('payBillId').value   = billId;
    document.getElementById('payAmount').value   = balance.toFixed(2);
    document.getElementById('payAmount').max     = balance;
    document.getElementById('payInfo').innerHTML = `<strong>${guestName}</strong> · Invoice: ${invoice} · <span style="color:var(--ember)">Balance: ₱${parseFloat(balance).toLocaleString('en',{minimumFractionDigits:2})}</span>`;
    document.getElementById('payModal').classList.add('active');
}
</script>

<?php include __DIR__ . '/layout-bottom.php'; ?>
