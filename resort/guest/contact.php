<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Contact';
$active_page = 'contact';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['name'] ?? '');
    $email   = sanitize($_POST['email'] ?? '');
    $phone   = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($name)||empty($email)||empty($message)) {
        $error = 'Please fill in all required fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO contacts (name,email,phone,subject,message) VALUES(?,?,?,?,?)");
        $stmt->bind_param('sssss', $name, $email, $phone, $subject, $message);
        if ($stmt->execute()) {
            $success = 'Your message has been sent! We\'ll get back to you within 24 hours.';
        } else {
            $error = 'Failed to send your message. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Get in Touch</div>
        <h1>Contact Subic Resort</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> Contact</div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:4rem;align-items:start">
            <!-- Contact Info -->
            <div>
                <div class="eyebrow" style="margin-bottom:.5rem">Find Us</div>
                <h2 style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--jungle-dark);margin-bottom:1.5rem;line-height:1.2">We're Hard to Find,<br>Easy to Love</h2>
                <p style="font-size:.95rem;color:var(--text-mid);line-height:1.7;margin-bottom:2rem">Subic Resort is tucked into the highland forest above Baguio City. Once you see the carved wooden sign and smell the pine, you'll know you're close.</p>

                <?php
                $contacts = [
                    ['fa-map-marker-alt','Address','Sitio Kagubatan, Barangay Bundok<br>Baguio City, Benguet 2600'],
                    ['fa-phone','Phone','+63 912 345 6789<br>+63 74 123 4567'],
                    ['fa-envelope','Email','hello@HSubicResort.com<br>bookings@SubicResort.com'],
                    ['fa-clock','Office Hours','Mon–Sun: 7:00 AM – 8:00 PM<br>Emergency line available 24/7'],
                    ['fa-car','How to Get Here','2.5 hours from Manila by air + 45 min drive from NAIA. We offer resort pickup from Baguio town center.'],
                ];
                foreach ($contacts as $c): ?>
                <div style="display:flex;gap:1rem;margin-bottom:1.5rem;align-items:flex-start">
                    <div style="width:44px;height:44px;background:var(--mist);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--canopy);font-size:1rem;flex-shrink:0">
                        <i class="fas <?=$c[0]?>"></i>
                    </div>
                    <div>
                        <div style="font-family:var(--font-head);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--ochre);font-weight:700;margin-bottom:.3rem"><?=$c[1]?></div>
                        <div style="font-size:.9rem;color:var(--text-mid);line-height:1.6"><?=$c[2]?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Socials -->
                <div style="margin-top:2rem;padding-top:2rem;border-top:1px solid var(--stone)">
                    <div style="font-family:var(--font-head);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-light);font-weight:600;margin-bottom:1rem">Follow the Wild Life</div>
                    <div style="display:flex;gap:.75rem">
                        <a href="#" style="width:44px;height:44px;background:var(--jungle-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.9rem;transition:var(--transition)" onmouseover="this.style.background='var(--ochre)'" onmouseout="this.style.background='var(--jungle-dark)'"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" style="width:44px;height:44px;background:var(--jungle-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.9rem;transition:var(--transition)" onmouseover="this.style.background='var(--ochre)'" onmouseout="this.style.background='var(--jungle-dark)'"><i class="fab fa-instagram"></i></a>
                        <a href="#" style="width:44px;height:44px;background:var(--jungle-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.9rem;transition:var(--transition)" onmouseover="this.style.background='var(--ochre)'" onmouseout="this.style.background='var(--jungle-dark)'"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div style="background:white;border-radius:var(--radius-xl);padding:3rem;box-shadow:var(--shadow-lg)">
                <div class="eyebrow" style="margin-bottom:.5rem">Message Us</div>
                <h3 style="font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--jungle-dark);margin-bottom:.5rem">Send a Message</h3>
                <p style="font-size:.9rem;color:var(--text-light);margin-bottom:2rem">We respond to all inquiries within 24 hours. For urgent booking matters, please call us directly.</p>

                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="Juan dela Cruz" value="<?= htmlspecialchars($_POST['name']??'') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" placeholder="juan@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+63 912 345 6789" value="<?= htmlspecialchars($_POST['phone']??'') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <select name="subject" class="form-control">
                                <option value="">Select a topic</option>
                                <option value="Room Inquiry">Room Inquiry</option>
                                <option value="Booking Question">Booking Question</option>
                                <option value="Service Add-On">Service Add-On</option>
                                <option value="Event / Group Booking">Event / Group Booking</option>
                                <option value="Feedback">Feedback</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message *</label>
                        <textarea name="message" class="form-control" placeholder="Tell us about your planned visit, questions, or anything else..." rows="6" required><?= htmlspecialchars($_POST['message']??'') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- MAP PLACEHOLDER -->
<div style="background:linear-gradient(135deg,var(--jungle-dark),var(--jungle-mid));height:300px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden">
    <div style="text-align:center;color:white;position:relative;z-index:1">
        <i class="fas fa-map-marked-alt" style="font-size:3rem;color:var(--ochre-light);margin-bottom:1rem;display:block"></i>
        <h3 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700">Sitio Kagubatan, Baguio City</h3>
        <p style="color:rgba(255,255,255,.6);margin:.5rem 0 1.25rem">Benguet Province, Philippines · 1,200m ASL</p>
        <a href="https://maps.google.com" target="_blank" class="btn btn-primary"><i class="fas fa-directions"></i> Get Directions</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
