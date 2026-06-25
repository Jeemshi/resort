<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Services';
$active_page = 'services';
include __DIR__ . '/../includes/header.php';
$db = getDB();
$services = $db->query("SELECT * FROM services WHERE available=1 ORDER BY category, price ASC");
$by_cat = [];
while ($s = $services->fetch_assoc()) $by_cat[$s['category']][] = $s;
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">What We Offer</div>
        <h1>Adventures & Services</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> Services</div>
    </div>
</div>

<!-- Cart Notification Toast -->
<div id="cartToast" style="position:fixed;bottom:2rem;right:2rem;background:var(--jungle-dark);color:white;padding:1rem 1.5rem;border-radius:var(--radius-lg);z-index:9999;display:none;box-shadow:0 10px 40px rgba(0,0,0,.3);align-items:center;gap:.75rem;max-width:340px">
    <i class="fas fa-check-circle" style="color:#7BC0A3;font-size:1.25rem;flex-shrink:0"></i>
    <div>
        <div id="cartToastMsg" style="font-weight:600;font-size:.9rem"></div>
        <a href="<?= SITE_URL ?>/guest/rooms.php" style="font-size:.8rem;color:rgba(255,255,255,.7)">Go to Rooms to book with this service →</a>
    </div>
    <button onclick="document.getElementById('cartToast').style.display='none'" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:.25rem;margin-left:auto;flex-shrink:0"><i class="fas fa-times"></i></button>
</div>

<!-- Floating Cart Button -->
<div id="cartBubble" style="position:fixed;bottom:2rem;left:2rem;z-index:999;display:none">
    <a href="<?= SITE_URL ?>/guest/cart.php" style="display:flex;align-items:center;gap:.6rem;background:var(--jungle-dark);color:white;padding:.9rem 1.4rem;border-radius:50px;text-decoration:none;font-family:var(--font-head);font-size:.85rem;font-weight:600;letter-spacing:.05em;box-shadow:0 8px 30px rgba(31,56,61,.35);transition:all .2s">
        <i class="fas fa-shopping-cart"></i>
        <span>View Cart</span>
        <span id="cartBubbleCount" style="background:var(--ochre);color:white;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700">0</span>
    </a>
</div>

<section class="section">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow">Beyond the Room</div>
            <h2 class="section-title">Curated for the Adventurous</h2>
            <p class="section-desc">Our guides, chefs, and wellness practitioners have spent their lives in these highlands. Everything we offer is designed by people who know this forest the way you know your neighborhood.</p>
        </div>

        <?php if (empty($by_cat)): ?>
        <div style="text-align:center;padding:4rem;color:var(--text-light)">No services currently available.</div>
        <?php else: ?>
        <?php foreach ($by_cat as $cat => $svcs): ?>
        <div style="margin-bottom:4rem">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:2rem;padding-bottom:1rem;border-bottom:2px solid var(--stone)">
                <div style="width:40px;height:40px;background:var(--jungle-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--ochre-light,#f5c483);font-size:1rem">
                    <?php
                    $cat_icons = ['Adventure'=>'fa-hiking','Wellness'=>'fa-spa','Dining'=>'fa-utensils','Transport'=>'fa-bus','Experience'=>'fa-fire','Water Sports'=>'fa-water','Land Activities'=>'fa-mountain'];
                    $ci = $cat_icons[$cat] ?? 'fa-star';
                    ?>
                    <i class="fas <?=$ci?>"></i>
                </div>
                <div>
                    <h3 style="font-family:var(--font-head);font-size:1rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--jungle-dark)"><?= htmlspecialchars($cat) ?></h3>
                    <div style="font-size:.8rem;color:var(--text-light)"><?= count($svcs) ?> experience<?=count($svcs)>1?'s':''?> available</div>
                </div>
            </div>
            <div class="grid-3">
                <?php foreach ($svcs as $s): ?>
                <div class="card" style="display:flex;flex-direction:column">
                    <?php
                    // Map of service images
                    $service_images = [
                        'Canopy Zip Line Tour' => 'https://i.pinimg.com/736x/1c/1c/85/1c1c859a2e82c37f8164f9e316e975d8.jpg',
                        'Wildlife Night Walk' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTVCfh2QbI_e2wd3IXxolYpG0ce9I9z-JXQzSoRKLVe_zlnxo9b1Xy73A4&s=10',
                        'River Kayaking' => 'https://kayakasiaphilippines.wordpress.com/wp-content/uploads/2019/01/ATTA-Cover-3.jpg',
                        'Guided Trek Package' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRvx_TGtP7BzOz3-KZY8BmgyZXld7B8GPLBIySre0-Bon8CgTXnar62RC4&s=10',
                        'Sunrise Breakfast Basket' => 'https://i.pinimg.com/736x/c3/8f/e8/c38fe881895f4814f782f9364d03774c.jpg',
                        "Jungle Chef's Table" => 'https://i.pinimg.com/webp85/736x/1e/0c/d5/1e0cd587fc1f4e2a45b69be6050bf89b.webp',
                        'Bonfire & Storytelling Night' => 'https://i.pinimg.com/736x/16/12/ae/1612aea17c2b3d5bff9e67072f8ee25e.jpg',
                        'Resort Jeepney Transfer' => 'https://i.pinimg.com/736x/9b/d6/fb/9bd6fb6d88cdec68e3d0565bb360b7cb.jpg',
                        'Sunrise Yoga Session' => 'https://i.pinimg.com/736x/de/59/ea/de59ea735371c0f37d95467c6a3fda5a.jpg',
                        'Forest Spa Treatment' => 'https://i.pinimg.com/736x/8e/a5/b1/8ea5b13249944f4c1ce8e0f18e8f73c1.jpg',
                        'Resort Jeepney Transfer ' => 'https://i.pinimg.com/736x/9b/d6/fb/9bd6fb6d88cdec68e3d0565bb360b7cb.jpg'
                    ];

                    $service_name = trim($s['name']);
                    if (isset($service_images[$service_name])) {
                        $card_bg = "linear-gradient(rgba(31,56,61,0.4), rgba(31,56,61,0.4)), url('" . $service_images[$service_name] . "') center/cover no-repeat";
                    } else {
                        $card_bg = "linear-gradient(135deg,var(--jungle-dark),var(--jungle))";
                    }
                    ?>
                    <div style="background:<?= $card_bg ?>;padding:2.5rem 2rem;text-align:center">
                        <div style="width:72px;height:72px;background:rgba(255,255,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:2rem;color:var(--ochre-light,#f5c483)">
                            <i class="fas <?= htmlspecialchars($s['icon']??'fa-star') ?>"></i>
                        </div>
                        <div style="margin-top:1rem;font-family:var(--font-head);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--ochre-light,#f5c483)"><?= htmlspecialchars($s['category']) ?></div>
                    </div>
                    <div class="card-body" style="flex:1">
                        <h4 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--jungle-dark);margin-bottom:.5rem"><?= htmlspecialchars($s['name']) ?></h4>
                        <p style="font-size:.87rem;color:var(--text-mid);line-height:1.6"><?= htmlspecialchars($s['description']) ?></p>
                    </div>
                    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem">
                        <div class="price-tag"><?= formatCurrency($s['price']) ?> <span>/ person</span></div>
                        <button 
                            onclick="addToCart(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['name'])) ?>', <?= $s['price'] ?>, '<?= htmlspecialchars($s['icon']??'fa-star') ?>')"
                            class="btn btn-dark btn-sm service-add-btn" 
                            data-id="<?= $s['id'] ?>"
                            style="flex-shrink:0">
                            <i class="fas fa-plus"></i> Add to Stay
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section bg-stone">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow">How It Works</div>
            <h2 class="section-title">Adding Services to Your Stay</h2>
        </div>
        <div class="grid-4">
            <?php
            $steps = [
                ['1','Add Services','Browse and click "Add to Stay" on any service you want.'],
                ['2','Review Cart','Check your cart summary — see all selected services & totals.'],
                ['3','Book Your Room','Choose your room and checkout with your services included.'],
                ['4','Experience Begins','Show up. We handle everything else.'],
            ];
            foreach ($steps as $step): ?>
            <div style="text-align:center;padding:2rem 1.25rem">
                <div style="width:56px;height:56px;background:var(--jungle-dark);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-family:var(--font-display);font-size:1.4rem;font-weight:900;color:var(--ochre-light,#f5c483)"><?=$step[0]?></div>
                <h4 style="font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--jungle-dark);margin-bottom:.5rem"><?=$step[1]?></h4>
                <p style="font-size:.85rem;color:var(--text-mid);line-height:1.6"><?=$step[2]?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
// Cart stored in localStorage
function getCart() {
    try { return JSON.parse(localStorage.getItem('resort_service_cart') || '[]'); } catch(e) { return []; }
}
function saveCart(cart) {
    localStorage.setItem('resort_service_cart', JSON.stringify(cart));
    updateCartBubble();
}
function updateCartBubble() {
    var cart = getCart();
    var total = cart.reduce(function(s,i){ return s + i.qty; }, 0);
    var bubble = document.getElementById('cartBubble');
    var count = document.getElementById('cartBubbleCount');
    if (total > 0) {
        bubble.style.display = 'block';
        count.textContent = total;
    } else {
        bubble.style.display = 'none';
    }
    // Update add buttons
    cart.forEach(function(item) {
        var btn = document.querySelector('.service-add-btn[data-id="'+item.id+'"]');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-check"></i> Added ('+item.qty+')';
            btn.style.background = '#2d5a27';
        }
    });
}
function addToCart(id, name, price, icon) {
    var cart = getCart();
    var existing = cart.find(function(i){ return i.id == id; });
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id: id, name: name, price: price, icon: icon, qty: 1 });
    }
    saveCart(cart);
    // Show toast
    document.getElementById('cartToastMsg').textContent = name + ' added to your stay!';
    var toast = document.getElementById('cartToast');
    toast.style.display = 'flex';
    setTimeout(function(){ toast.style.display = 'none'; }, 4000);
    // Update button
    var btn = document.querySelector('.service-add-btn[data-id="'+id+'"]');
    var qty = existing ? existing.qty : 1;
    if (btn) {
        btn.innerHTML = '<i class="fas fa-check"></i> Added ('+(existing?existing.qty:1)+')';
        btn.style.background = '#2d5a27';
    }
}
document.addEventListener('DOMContentLoaded', updateCartBubble);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>