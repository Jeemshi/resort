<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Your Cart';
$active_page = 'services';

// Check if the guest has an active booking (pending, confirmed, or checked_in)
// Amenity selection is only allowed when booking a room or after a booking exists
$can_add_amenities = false;
if (isGuestLoggedIn()) {
    $db = getDB();
    $guest_id = (int)$_SESSION['guest_id'];
    $result = $db->query("
        SELECT COUNT(*) AS cnt FROM bookings
        WHERE guest_id = $guest_id
          AND status IN ('pending', 'confirmed', 'checked_in')
    ");
    $row = $result->fetch_assoc();
    $can_add_amenities = ((int)($row['cnt'] ?? 0)) > 0;
}

// Load amenities from DB for rendering
$db = isset($db) ? $db : getDB();
$amenity_rows_res = $db->query("SELECT id, name, icon, category, description FROM amenities ORDER BY category, name");
$amenity_items_php = [];
while ($ar = $amenity_rows_res->fetch_assoc()) {
    $amenity_items_php[] = $ar;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Review Your Selection</div>
        <h1>Your Stay Cart</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> <a href="<?= SITE_URL ?>/guest/services.php">Services</a> <i class="fas fa-chevron-right fa-xs"></i> Cart</div>
    </div>
</div>

<section class="section">
    <div class="container" style="max-width:900px">
        <div id="cartEmpty" style="text-align:center;padding:5rem 2rem;display:none">
            <i class="fas fa-shopping-cart" style="font-size:4rem;color:var(--stone-dark);margin-bottom:1.5rem;display:block"></i>
            <h3 style="font-family:var(--font-display);font-size:1.5rem;color:var(--jungle-dark);margin-bottom:.75rem">Your Cart is Empty</h3>
            <p style="color:var(--text-mid);margin-bottom:1.5rem">Browse our services and add experiences to your stay.</p>
            <a href="<?= SITE_URL ?>/guest/services.php" class="btn btn-primary"><i class="fas fa-concierge-bell"></i> Browse Services</a>
        </div>

        <div id="cartContent" style="display:none">
            <div style="display:grid;grid-template-columns:1fr 320px;gap:2rem;align-items:start">
                <!-- Cart Items -->
                <div>
                    <div class="card" style="overflow:hidden">
                        <div style="background:var(--jungle-dark);color:white;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between">
                            <h3 style="font-family:var(--font-display);font-size:1.1rem;margin:0">Selected Services</h3>
                            <button onclick="clearCart()" style="background:none;border:1px solid rgba(255,255,255,.3);color:rgba(255,255,255,.7);padding:.3rem .75rem;border-radius:var(--radius-sm);cursor:pointer;font-size:.8rem">
                                <i class="fas fa-trash-alt"></i> Clear All
                            </button>
                        </div>
                        <div id="cartItemsList" style="padding:0"></div>
                    </div>

                    <!-- Amenities Checklist -->
                    <div class="card" style="padding:2rem;margin-top:1.5rem">
                        <h3 style="font-family:var(--font-display);font-size:1.15rem;color:var(--jungle-dark);margin-bottom:1rem">
                            <i class="fas fa-bed" style="color:var(--jungle)"></i> Room Amenities Add-ons
                        </h3>
                        <?php if ($can_add_amenities): ?>
                        <p style="font-size:.85rem;color:var(--text-mid);margin-bottom:1.25rem">Select any additional in-room items you'd like prepared before arrival. All amenity requests are complimentary.</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem" id="amenitiesList">
                            <!-- populated by JS -->
                        </div>
                        <?php elseif (!isGuestLoggedIn()): ?>
                        <div style="background:#fef7e0;border:1px solid #f0c040;border-radius:8px;padding:1.25rem 1.5rem;display:flex;align-items:flex-start;gap:1rem">
                            <i class="fas fa-lock" style="color:#b06000;font-size:1.2rem;margin-top:.1rem;flex-shrink:0"></i>
                            <div>
                                <div style="font-weight:700;color:#7a4a00;margin-bottom:.3rem">Login Required</div>
                                <p style="font-size:.85rem;color:#7a4a00;margin:0 0 .75rem">You need to be logged in and have a room booking to add amenities.</p>
                                <a href="<?= SITE_URL ?>/guest/login.php?redirect=<?= urlencode(SITE_URL . '/guest/cart.php') ?>" class="btn btn-primary" style="font-size:.85rem;padding:.5rem 1.1rem">
                                    <i class="fas fa-sign-in-alt"></i> Log In
                                </a>
                                <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-outline" style="font-size:.85rem;padding:.5rem 1.1rem;margin-left:.5rem">
                                    <i class="fas fa-bed"></i> Book a Room First
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="background:#fef7e0;border:1px solid #f0c040;border-radius:8px;padding:1.25rem 1.5rem;display:flex;align-items:flex-start;gap:1rem">
                            <i class="fas fa-info-circle" style="color:#b06000;font-size:1.2rem;margin-top:.1rem;flex-shrink:0"></i>
                            <div>
                                <div style="font-weight:700;color:#7a4a00;margin-bottom:.3rem">Booking Required to Add Amenities</div>
                                <p style="font-size:.85rem;color:#7a4a00;margin:0 0 .75rem">Amenity add-ons are only available when you have an active room booking (pending, confirmed, or checked-in).</p>
                                <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary" style="font-size:.85rem;padding:.5rem 1.1rem">
                                    <i class="fas fa-bed"></i> Book a Room
                                </a>
                                <a href="<?= SITE_URL ?>/guest/my_bookings.php" class="btn btn-outline" style="font-size:.85rem;padding:.5rem 1.1rem;margin-left:.5rem">
                                    <i class="fas fa-list"></i> My Bookings
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Summary -->
                <div style="position:sticky;top:100px">
                    <div class="card" style="overflow:hidden">
                        <div style="background:var(--jungle-dark);padding:1.25rem 1.5rem;color:white">
                            <h3 style="font-family:var(--font-display);font-size:1.1rem;color:var(--ochre-light,#f5c483);margin:0">Order Summary</h3>
                        </div>
                        <div style="padding:1.5rem">
                            <div id="summaryItems" style="display:flex;flex-direction:column;gap:.5rem;font-size:.88rem;color:var(--text-mid);margin-bottom:1rem"></div>
                            <div id="amenitySummary" style="display:flex;flex-direction:column;gap:.4rem;font-size:.85rem;color:var(--text-mid);margin-bottom:1rem;border-top:1px dashed var(--stone);padding-top:.75rem"></div>
                            <div style="border-top:1px solid var(--stone);padding-top:.75rem;display:flex;flex-direction:column;gap:.35rem;font-size:.9rem">
                                <div style="display:flex;justify-content:space-between;color:var(--text-mid)">
                                    <span>Services Subtotal</span>
                                    <span id="servicesSubtotal">&#8369;0.00</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;color:var(--text-mid)">
                                    <span>Amenities</span>
                                    <span id="amenitiesTotal">&#8212;</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;color:var(--text-mid)">
                                    <span>VAT (12%)</span>
                                    <span id="vatAmount">&#8369;0.00</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;color:var(--jungle-dark);border-top:1px solid var(--stone);padding-top:.75rem;margin-top:.25rem">
                                    <span>Services Total</span>
                                    <span id="grandTotal">&#8369;0.00</span>
                                </div>
                            </div>
                            <div style="margin-top:.5rem;font-size:.75rem;color:var(--text-light);background:var(--mist);padding:.6rem .8rem;border-radius:var(--radius-sm)">
                                <i class="fas fa-info-circle"></i> Room charges will be added at checkout.
                            </div>
                            <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.25rem">
                                <i class="fas fa-bed"></i> Choose a Room
                            </a>
                            <a href="<?= SITE_URL ?>/guest/services.php" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:.6rem">
                                <i class="fas fa-plus"></i> Add More Services
                            </a>
                        </div>
                    </div>

                    <!-- Items count -->
                    <div style="text-align:center;margin-top:.75rem;font-size:.82rem;color:var(--text-light)">
                        <span id="totalItemsCount">0</span> item(s) in cart
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
var amenityItems = <?= json_encode(array_map(function($a) {
    return ['id' => (string)$a['id'], 'name' => $a['name'], 'icon' => $a['icon'] ?? 'fa-check', 'cat' => $a['category'] ?? ''];
}, $amenity_items_php)) ?>;

function getCart() {
    try { return JSON.parse(localStorage.getItem('resort_service_cart') || '[]'); } catch(e) { return []; }
}
function saveCart(cart) { localStorage.setItem('resort_service_cart', JSON.stringify(cart)); }
function getAmenities() {
    try { return JSON.parse(localStorage.getItem('resort_amenity_cart') || '[]'); } catch(e) { return []; }
}
function saveAmenities(list) { localStorage.setItem('resort_amenity_cart', JSON.stringify(list)); }
function fmt(n) { return '\u20B1' + parseFloat(n).toFixed(2).replace(/\d(?=(\d{3})+\.)/g,'$&,'); }

function renderCart() {
    var cart = getCart();
    var amenities = getAmenities();

    <?php if (!$can_add_amenities): ?>
    // Not eligible — clear stale amenity data
    saveAmenities([]);
    amenities = [];
    <?php endif; ?>

    var isEmpty = cart.length === 0;
    document.getElementById('cartEmpty').style.display = isEmpty ? 'block' : 'none';
    document.getElementById('cartContent').style.display = isEmpty ? 'none' : 'grid';
    if (isEmpty) return;

    // Items list
    var listHtml = '';
    cart.forEach(function(item) {
        listHtml += '<div style="display:flex;align-items:center;gap:1rem;padding:1rem 1.5rem;border-bottom:1px solid var(--stone)">'
            + '<div style="width:44px;height:44px;background:var(--mist);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--jungle);font-size:1.1rem;flex-shrink:0"><i class="fas '+item.icon+'"></i></div>'
            + '<div style="flex:1"><div style="font-weight:600;color:var(--jungle-dark);font-size:.9rem">'+item.name+'</div>'
            + '<div style="font-size:.8rem;color:var(--text-light)">'+fmt(item.price)+' per person</div></div>'
            + '<div style="display:flex;align-items:center;gap:.5rem">'
            + '<button onclick="changeQty('+item.id+',-1)" style="width:28px;height:28px;border:1px solid var(--stone-dark);background:white;border-radius:4px;cursor:pointer;font-size:.9rem">&#8722;</button>'
            + '<span style="min-width:28px;text-align:center;font-weight:700;color:var(--jungle-dark)">'+item.qty+'</span>'
            + '<button onclick="changeQty('+item.id+',1)" style="width:28px;height:28px;border:1px solid var(--stone-dark);background:white;border-radius:4px;cursor:pointer;font-size:.9rem">+</button>'
            + '</div>'
            + '<div style="min-width:70px;text-align:right;font-weight:700;color:var(--jungle-dark)">'+fmt(item.price*item.qty)+'</div>'
            + '<button onclick="removeItem('+item.id+')" style="background:none;border:none;color:var(--text-light);cursor:pointer;padding:.25rem;margin-left:.25rem"><i class="fas fa-times"></i></button>'
            + '</div>';
    });
    document.getElementById('cartItemsList').innerHTML = listHtml;

    // Amenities checkboxes (only when eligible)
    <?php if ($can_add_amenities): ?>
    var amenHtml = '';
    amenityItems.forEach(function(a) {
        var checked = amenities.indexOf(a.id) >= 0;
        amenHtml += '<label style="display:flex;align-items:center;gap:.6rem;padding:.7rem .8rem;background:var(--mist);border-radius:var(--radius-sm);cursor:pointer;border:2px solid '+(checked?'var(--jungle)':'transparent')+';transition:all .2s">'
            + '<input type="checkbox" value="'+a.id+'" '+(checked?'checked':'')+' onchange="toggleAmenity(\''+a.id+'\')" style="width:16px;height:16px;accent-color:var(--jungle)">'
            + '<i class="fas '+a.icon+'" style="color:var(--jungle);width:16px;text-align:center"></i>'
            + '<span style="font-size:.85rem;color:var(--text-dark);font-weight:500;flex:1">'+a.name+'</span>'
            + '<span style="font-size:.75rem;color:var(--text-light)">Free</span>'
            + '</label>';
    });
    document.getElementById('amenitiesList').innerHTML = amenHtml;
    <?php endif; ?>

    // Summary
    var servicesSubtotal = cart.reduce(function(s,i){ return s + i.price*i.qty; }, 0);
    var subtotal = servicesSubtotal;
    var vat = subtotal * 0.12;
    var grand = subtotal + vat;
    var totalItems = cart.reduce(function(s,i){ return s+i.qty; },0);

    var summHtml = '';
    cart.forEach(function(item){
        summHtml += '<div style="display:flex;justify-content:space-between"><span>'+item.name+' x'+item.qty+'</span><span>'+fmt(item.price*item.qty)+'</span></div>';
    });
    document.getElementById('summaryItems').innerHTML = summHtml;

    var amenSummHtml = '';
    <?php if ($can_add_amenities): ?>
    amenityItems.filter(function(a){ return amenities.indexOf(a.id)>=0; }).forEach(function(a){
        amenSummHtml += '<div style="display:flex;justify-content:space-between"><span><i class="fas '+a.icon+' fa-xs"></i> '+a.name+'</span><span style="color:var(--text-light)">Free</span></div>';
    });
    <?php endif; ?>
    document.getElementById('amenitySummary').innerHTML = amenSummHtml || '';

    document.getElementById('servicesSubtotal').textContent = fmt(servicesSubtotal);
    document.getElementById('amenitiesTotal').textContent = amenities.length > 0 ? amenities.length + ' request' + (amenities.length > 1 ? 's' : '') : '\u2014';
    document.getElementById('vatAmount').textContent = fmt(vat);
    document.getElementById('grandTotal').textContent = fmt(grand);
    document.getElementById('totalItemsCount').textContent = totalItems + (<?= $can_add_amenities ? 'true' : 'false' ?> && amenities.length > 0 ? ' + '+amenities.length+' amenity req.' : '');
}

function changeQty(id, delta) {
    var cart = getCart();
    var item = cart.find(function(i){ return i.id == id; });
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) cart = cart.filter(function(i){ return i.id != id; });
    saveCart(cart);
    renderCart();
}

function removeItem(id) {
    var cart = getCart().filter(function(i){ return i.id != id; });
    saveCart(cart);
    renderCart();
}

function clearCart() {
    if (!confirm('Remove all services from your cart?')) return;
    saveCart([]);
    saveAmenities([]);
    renderCart();
}

function toggleAmenity(id) {
    var list = getAmenities();
    var idx = list.indexOf(id);
    if (idx >= 0) list.splice(idx, 1);
    else list.push(id);
    saveAmenities(list);
    renderCart();
}

document.addEventListener('DOMContentLoaded', renderCart);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>