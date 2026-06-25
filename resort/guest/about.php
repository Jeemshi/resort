<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'About Us';
$active_page = 'about';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="page-banner-content">
        <div class="eyebrow">Our Story</div>
        <h1>About Subic Resort</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Home</a> <i class="fas fa-chevron-right fa-xs"></i> About</div>
    </div>
</div>

<!-- OUR STORY -->
<section class="section">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5rem;align-items:center">
            <div>
                <div class="eyebrow">Est. 2018</div>
                <h2 class="section-title" style="margin:.5rem 0 1.25rem">We Started With a Treehouse and a Dream</h2>
                <p style="font-size:1rem;color:var(--text-mid);line-height:1.8;margin-bottom:1.25rem">
                    Subic Resort began as a single hand-built treehouse on a family plot of highland forest in Benguet. The founder, Mang Ricardo — a former forest ranger and hobbyist carpenter — wanted one thing: a place where visitors could sleep inside the forest, not outside it.
                </p>
                <p style="font-size:1rem;color:var(--text-mid);line-height:1.8;margin-bottom:1.25rem">
                    Word spread. Then more cottages followed, then the riverside stays, and eventually the Summit Suites that now attract guests from across Southeast Asia and beyond. Through it all, we kept our founding rule: the forest always wins. Every structure exists around nature, not instead of it.
                </p>
                <p style="font-size:1rem;color:var(--text-mid);line-height:1.8;margin-bottom:2rem">
                    Today Subic Resort employs over 40 people — all from the Benguet community — and is recognized by the Department of Tourism as a model for sustainable highland eco-tourism.
                </p>
                <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-dark btn-lg"><i class="fas fa-bed"></i> See Our Rooms</a>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <?php
                $colors = ['var(--jungle-dark)','var(--jungle-mid)','var(--canopy)','var(--ochre)'];
                $icons  = ['fa-tree','fa-water','fa-mountain','fa-campground'];
                $labels = ['Highland Forest','Mountain River','Summit Views','Community Owned'];
                
                // Map matching background images to specific tile indexes
                $bg_images = [
                    0 => 'https://i.pinimg.com/736x/2e/47/9e/2e479e6c821c5d15be00a45f7483caa0.jpg', // Highland Forest
                    3 => 'https://i.pinimg.com/736x/74/ff/30/74ff30eab62ffd6a09658304215a3dc7.jpg'  // Community Owned
                ];

                for ($i=0;$i<4;$i++): 
                    // Build background property string incorporating image if available
                    $tile_bg = isset($bg_images[$i]) 
                        ? "linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url('".$bg_images[$i]."') center/cover no-repeat" 
                        : $colors[$i];
                ?>
                <div style="aspect-ratio:1;background:<?=$tile_bg?>;border-radius:var(--radius-lg);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.75rem;color:white">
                    <i class="fas <?=$icons[$i]?>" style="font-size:2.5rem;opacity:.9"></i>
                    <div style="font-family:var(--font-head);font-size:.8rem;letter-spacing:.1em;text-transform:uppercase;opacity:.8"><?=$labels[$i]?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<!-- NUMBERS -->
<section class="section bg-mist">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow">By the Numbers</div>
            <h2 class="section-title">Six Years in the Forest</h2>
        </div>
        <div class="grid-4">
            <?php
            $stats = [
                ['5,000+','Happy Guests','fa-users','var(--canopy)'],
                ['9','Unique Rooms','fa-door-open','var(--ochre)'],
                ['40+','Local Staff','fa-hands-helping','var(--jungle)'],
                ['4.9','Average Rating','fa-star','var(--ember)'],
            ];
            foreach ($stats as $s): ?>
            <div style="text-align:center;padding:2rem;background:white;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)">
                <div style="width:60px;height:60px;background:<?=$s[3]?>;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;font-size:1.4rem;color:white;opacity:.9">
                    <i class="fas <?=$s[2]?>"></i>
                </div>
                <div style="font-family:var(--font-display);font-size:2.5rem;font-weight:900;color:var(--jungle-dark);line-height:1"><?=$s[0]?></div>
                <div style="font-size:.85rem;color:var(--text-light);margin-top:.5rem;font-family:var(--font-head);letter-spacing:.08em;text-transform:uppercase"><?=$s[1]?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TEAM -->
<section class="section">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow">Our People</div>
            <h2 class="section-title">The Subic Resort Family</h2>
            <p class="section-desc">Every person at Subic Resort calls the Benguet highlands home. Our guides grew up on these trails. Our chefs forage these forests. Our staff knows every guest by name.</p>
        </div>
        <div class="grid-4">
            <?php
            $team = [
                ['Ricardo Bakilan','Founder & Head Ranger','fa-mountain','He built the first treehouse by hand in 2018 and still leads the sunrise trek every Saturday.'],
                ['Ligaya Flores','Executive Chef','fa-utensils','Trained in Manila, returned to Benguet to cook using the ingredients she grew up with.'],
                ['Kuya Romy Aban','Senior Trail Guide','fa-hiking','25 years on these trails. Knows every endemic bird call and has never lost a guest.'],
                ['Ana Torres','Guest Relations','fa-heart','The reason guests come back. She remembers every guest\'s name, coffee order, and favorite trail.'],
            ];
            foreach ($team as $tm): ?>
            <div style="text-align:center;padding:2rem 1.5rem;background:white;border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--jungle),var(--jungle-mid));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2rem;color:white">
                    <i class="fas <?=$tm[2]?>"></i>
                </div>
                <h4 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--jungle-dark);margin-bottom:.3rem"><?=$tm[0]?></h4>
                <div style="font-family:var(--font-head);font-size:.72rem;letter-spacing:.1em;color:var(--ochre);text-transform:uppercase;font-weight:600;margin-bottom:.75rem"><?=$tm[1]?></div>
                <p style="font-size:.85rem;color:var(--text-mid);line-height:1.6"><?=$tm[3]?></p>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
</section>

<!-- VALUES -->
<section class="section bg-jungle">
    <div class="container">
        <div class="section-header center">
            <div class="eyebrow" style="color:var(--ochre)">What We Stand For</div>
            <h2 class="section-title" style="color:white">Our Commitments</h2>
        </div>
        <div class="grid-3">
            <?php
            $values = [
                ['fa-leaf','Conservation First','We give 5% of every booking to the Benguet Reforestation Fund and are on track to be carbon-neutral by 2026.'],
                ['fa-users','Community Economy','Every peso earned at Subic Resort stays local. We source food, materials, and labor from within 20km of the resort.'],
                ['fa-shield-alt','Safety Without Compromise','All adventure activities follow Philippine DOT safety standards. Our guides are certified first responders.'],
            ];
            foreach ($values as $v): ?>
            <div style="padding:2.5rem 2rem;border:1px solid rgba(255,255,255,.1);border-radius:var(--radius-lg);text-align:center">
                <div style="width:60px;height:60px;background:rgba(200,135,58,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.4rem;color:var(--ochre-light)">
                    <i class="fas <?=$v[0]?>"></i>
                </div>
                <h4 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:white;margin-bottom:.75rem"><?=$v[1]?></h4>
                <p style="font-size:.9rem;color:rgba(255,255,255,.55);line-height:1.7"><?=$v[2]?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section style="background:var(--stone);padding:5rem 0;text-align:center">
    <div class="container">
        <div class="eyebrow" style="margin-bottom:.5rem">Come See For Yourself</div>
        <h2 class="section-title" style="margin-bottom:1.25rem">Experience the Highland Difference</h2>
        <p style="font-size:1rem;color:var(--text-mid);max-width:480px;margin:0 auto 2rem;line-height:1.7">Stories can only go so far. Come spend a night in the canopy — or the river bank — and see what Subic Resort is really about.</p>
        <a href="<?= SITE_URL ?>/guest/rooms.php" class="btn btn-primary btn-lg"><i class="fas fa-bed"></i> Browse Rooms</a>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>