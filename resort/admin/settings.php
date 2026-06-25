<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
$admin_page  = 'settings';
$admin_title = 'Settings';
$db = getDB();

$msg = '';
$err = '';
$tab = $_GET['tab'] ?? 'general';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'save_settings') {
    $keys = ['resort_name','resort_email','resort_phone','resort_address','check_in_time','check_out_time','tax_rate','max_advance_booking_days','cancellation_hours'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $v = sanitize($_POST[$k]);
            $existing = $db->query("SELECT id FROM settings WHERE setting_key='$k'");
            if ($existing && $existing->num_rows > 0) $db->query("UPDATE settings SET setting_value='$v' WHERE setting_key='$k'");
            else $db->query("INSERT INTO settings (setting_key,setting_value) VALUES('$k','$v')");
        }
    }
    $msg = 'Settings saved successfully.';
}

// Save admin profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'save_profile') {
    $name  = sanitize($_POST['name']??'');
    $email = sanitize($_POST['email']??'');
    $id    = (int)$_SESSION['admin_id'];
    $db->query("UPDATE admins SET name='$name', email='$email' WHERE id=$id");
    if (!empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $db->query("UPDATE admins SET password='$hash' WHERE id=$id");
    }
    $_SESSION['admin_name'] = $name;
    $msg = 'Profile updated.';
}

// Contact status update
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $db->query("UPDATE contacts SET status='read' WHERE id=".(int)$_GET['mark_read']);
    $msg = 'Message marked as read.';
    $tab = 'contacts';
}

// Delete contact
if (isset($_GET['del_contact']) && is_numeric($_GET['del_contact'])) {
    $db->query("DELETE FROM contacts WHERE id=".(int)$_GET['del_contact']);
    $msg = 'Message deleted.';
    $tab = 'contacts';
}

// Fetch settings
$settings_q = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($s = $settings_q->fetch_assoc()) $settings[$s['setting_key']] = $s['setting_value'];

// Fetch admin data
$admin_data = $db->query("SELECT * FROM admins WHERE id=".(int)$_SESSION['admin_id'])->fetch_assoc();

// Contacts
$contacts_q = $db->query("SELECT * FROM contacts ORDER BY status ASC, created_at DESC");

// Admin list
$admins_list = $db->query("SELECT * FROM admins ORDER BY role, name");

include __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:.25rem;margin-bottom:1.5rem;border-bottom:2px solid var(--stone);padding-bottom:0;overflow-x:auto">
    <?php
    $tabs = [
        'general'  => ['fa-cog',       'General'],
        'booking'  => ['fa-calendar',  'Booking'],
        'billing'  => ['fa-coins',     'Billing'],
        'contacts' => ['fa-envelope',  'Messages'],
        'admins'   => ['fa-user-shield','Admin Users'],
        'profile'  => ['fa-user',      'My Profile'],
    ];
    foreach ($tabs as $tk => [$ti, $tl]):
        $unread = '';
        if ($tk === 'contacts') {
            $uc = $db->query("SELECT COUNT(*) c FROM contacts WHERE status='unread'")->fetch_assoc()['c'];
            if ($uc > 0) $unread = "<span style='background:var(--ember);color:white;border-radius:10px;font-size:.65rem;padding:.1rem .4rem;margin-left:.3rem'>$uc</span>";
        }
    ?>
    <a href="?tab=<?=$tk?>" style="display:flex;align-items:center;gap:.4rem;padding:.7rem 1.25rem;font-family:var(--font-head);font-size:.8rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:<?=$tab===$tk?'var(--jungle-dark)':'var(--text-light)'?>;border-bottom:2px solid <?=$tab===$tk?'var(--ochre)':'transparent'?>;margin-bottom:-2px;white-space:nowrap;text-decoration:none;transition:color .2s">
        <i class="fas <?=$ti?>"></i> <?=$tl?><?=$unread?>
    </a>
    <?php endforeach; ?>
</div>

<!-- GENERAL TAB -->
<?php if ($tab === 'general'): ?>
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-cog" style="color:var(--canopy);margin-right:.4rem"></i> General Settings</span></div>
    <form method="POST" style="padding:1.75rem;max-width:700px">
        <input type="hidden" name="form_action" value="save_settings">
        <div class="form-group"><label class="form-label">Resort Name</label><input type="text" name="resort_name" class="form-control" value="<?= htmlspecialchars($settings['resort_name']??'') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="resort_email" class="form-control" value="<?= htmlspecialchars($settings['resort_email']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Phone Number</label><input type="text" name="resort_phone" class="form-control" value="<?= htmlspecialchars($settings['resort_phone']??'') ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Full Address</label><textarea name="resort_address" class="form-control" rows="2"><?= htmlspecialchars($settings['resort_address']??'') ?></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </form>
</div>

<!-- BOOKING TAB -->
<?php elseif ($tab === 'booking'): ?>
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-calendar" style="color:var(--canopy);margin-right:.4rem"></i> Booking Settings</span></div>
    <form method="POST" style="padding:1.75rem;max-width:600px">
        <input type="hidden" name="form_action" value="save_settings">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Check-in Time</label><input type="time" name="check_in_time" class="form-control" value="<?= htmlspecialchars($settings['check_in_time']??'14:00') ?>"></div>
            <div class="form-group"><label class="form-label">Check-out Time</label><input type="time" name="check_out_time" class="form-control" value="<?= htmlspecialchars($settings['check_out_time']??'12:00') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Max Advance Booking Days</label><input type="number" name="max_advance_booking_days" class="form-control" value="<?= htmlspecialchars($settings['max_advance_booking_days']??'365') ?>"></div>
            <div class="form-group"><label class="form-label">Free Cancellation Window (hrs)</label><input type="number" name="cancellation_hours" class="form-control" value="<?= htmlspecialchars($settings['cancellation_hours']??'48') ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </form>
</div>

<!-- BILLING TAB -->
<?php elseif ($tab === 'billing'): ?>
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-coins" style="color:var(--canopy);margin-right:.4rem"></i> Billing Settings</span></div>
    <form method="POST" style="padding:1.75rem;max-width:500px">
        <input type="hidden" name="form_action" value="save_settings">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Tax Rate (%)</label><input type="number" name="tax_rate" class="form-control" step="0.01" value="<?= htmlspecialchars($settings['tax_rate']??'12') ?>"></div>
            <div class="form-group"><label class="form-label">Currency</label><input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($settings['currency']??'PHP') ?>" readonly></div>
        </div>
        <div style="background:var(--mist);border-radius:var(--radius);padding:1rem;font-size:.87rem;color:var(--text-mid);margin-bottom:1.25rem">
            <i class="fas fa-info-circle" style="color:var(--canopy)"></i> The tax rate applies to all room bookings. Service add-ons are priced inclusive of tax.
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </form>
</div>

<!-- CONTACTS TAB -->
<?php elseif ($tab === 'contacts'): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-envelope" style="color:var(--canopy);margin-right:.4rem"></i> Guest Messages</span>
        <span style="font-size:.82rem;color:var(--text-light)"><?= $db->query("SELECT COUNT(*) c FROM contacts WHERE status='unread'")->fetch_assoc()['c'] ?> unread</span>
    </div>
    <?php if ($contacts_q && $contacts_q->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while ($c = $contacts_q->fetch_assoc()): ?>
            <tr style="<?= $c['status']==='unread'?'background:#FFFBF5':'' ?>">
                <td style="font-size:.8rem;color:var(--text-light);white-space:nowrap"><?= date('M j, Y H:i', strtotime($c['created_at'])) ?></td>
                <td style="font-weight:<?=$c['status']==='unread'?'700':'500'?>"><?= htmlspecialchars($c['name']) ?></td>
                <td style="font-size:.85rem;color:var(--text-mid)"><?= htmlspecialchars($c['email']) ?></td>
                <td style="font-size:.85rem"><?= htmlspecialchars($c['subject']??'—') ?></td>
                <td style="font-size:.82rem;color:var(--text-mid);max-width:250px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['message']) ?></div></td>
                <td>
                    <span class="badge <?=$c['status']==='unread'?'badge-warning':($c['status']==='replied'?'badge-success':'badge-secondary')?>"><?= ucfirst($c['status']) ?></span>
                </td>
                <td>
                    <div class="action-btns">
                        <?php if ($c['status'] !== 'read'): ?>
                        <a href="?tab=contacts&mark_read=<?=$c['id']?>" class="btn btn-outline btn-sm" title="Mark Read"><i class="fas fa-check"></i></a>
                        <?php endif; ?>
                        <a href="mailto:<?= htmlspecialchars($c['email']) ?>?subject=Re: <?= htmlspecialchars($c['subject']??'Your enquiry') ?>" class="btn btn-dark btn-sm" title="Reply"><i class="fas fa-reply"></i></a>
                        <a href="?tab=contacts&del_contact=<?=$c['id']?>" class="btn btn-danger btn-sm" data-confirm="Delete this message?" title="Delete"><i class="fas fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="fas fa-envelope-open"></i> No messages yet</div>
    <?php endif; ?>
</div>

<!-- ADMINS TAB -->
<?php elseif ($tab === 'admins'): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-user-shield" style="color:var(--canopy);margin-right:.4rem"></i> Admin Users</span>
        <button onclick="document.getElementById('addAdminModal').classList.add('active')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Admin</button>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while ($a = $admins_list->fetch_assoc()): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:.6rem">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--ochre);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.8rem;flex-shrink:0"><?= strtoupper(substr($a['name'],0,1)) ?></div>
                        <span style="font-weight:600"><?= htmlspecialchars($a['name']) ?></span>
                    </div>
                </td>
                <td style="font-size:.87rem;color:var(--text-mid)"><?= htmlspecialchars($a['email']) ?></td>
                <td><span class="badge <?=$a['role']==='super_admin'?'badge-danger':($a['role']==='manager'?'badge-warning':'badge-info')?>"><?= ucfirst(str_replace('_',' ',$a['role'])) ?></span></td>
                <td style="font-size:.82rem;color:var(--text-light)"><?= $a['last_login'] ? date('M j, Y H:i', strtotime($a['last_login'])) : 'Never' ?></td>
                <td><span class="badge <?=$a['status']==='active'?'badge-success':'badge-danger'?>"><?= ucfirst($a['status']) ?></span></td>
                <td>
                    <?php if ($a['id'] != $_SESSION['admin_id']): ?>
                    <div class="action-btns">
                        <a href="?tab=admins&toggle_admin=<?=$a['id']?>" class="btn btn-outline btn-sm"><i class="fas fa-toggle-<?=$a['status']==='active'?'on':'off'?>"></i></a>
                        <a href="?tab=admins&del_admin=<?=$a['id']?>" class="btn btn-danger btn-sm" data-confirm="Remove this admin user?"><i class="fas fa-trash"></i></a>
                    </div>
                    <?php else: ?>
                    <span style="font-size:.78rem;color:var(--text-light)">Current user</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PROFILE TAB -->
<?php elseif ($tab === 'profile'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-user" style="color:var(--canopy);margin-right:.4rem"></i> My Profile</span></div>
        <form method="POST" style="padding:1.75rem">
            <input type="hidden" name="form_action" value="save_profile">
            <div style="text-align:center;margin-bottom:1.75rem">
                <div style="width:72px;height:72px;border-radius:50%;background:var(--ochre);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:2rem;font-weight:700;color:white;margin:0 auto .75rem"><?= strtoupper(substr($admin_data['name']??'A',0,1)) ?></div>
                <div style="font-family:var(--font-head);font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--ochre)"><?= ucfirst(str_replace('_',' ',$admin_data['role']??'')) ?></div>
            </div>
            <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin_data['name']??'') ?>" required></div>
            <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin_data['email']??'') ?>" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Update Profile</button>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title"><i class="fas fa-lock" style="color:var(--ember);margin-right:.4rem"></i> Change Password</span></div>
        <form method="POST" style="padding:1.75rem">
            <input type="hidden" name="form_action" value="save_profile">
            <input type="hidden" name="name" value="<?= htmlspecialchars($admin_data['name']??'') ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($admin_data['email']??'') ?>">
            <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current" minlength="8"></div>
            <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password"></div>
            <div style="background:var(--mist);border-radius:var(--radius);padding:1rem;font-size:.83rem;color:var(--text-mid);margin-bottom:1.25rem">
                <i class="fas fa-shield-alt" style="color:var(--canopy)"></i> Use at least 8 characters with a mix of letters and numbers.
            </div>
            <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center"><i class="fas fa-key"></i> Change Password</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Add Admin Modal -->
<div class="modal-overlay" id="addAdminModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Admin User</span>
            <button class="modal-close" onclick="document.getElementById('addAdminModal').classList.remove('active')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="?tab=admins">
            <input type="hidden" name="form_action" value="add_admin">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="admin_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="admin_email" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Role</label>
                    <select name="admin_role" class="form-control">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Initial Password *</label><input type="text" name="admin_password" class="form-control" value="wildnest2025" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addAdminModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Admin</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/layout-bottom.php'; ?>
