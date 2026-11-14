<?php
require __DIR__ . '/includes/bootstrap.php';
track_visit('accueil');

$name    = s('site_name');
$siteUrl = rtrim(s('site_url'), '/');
$mailto  = mailto();
$photos  = photos();
$reviews = reviews();
$heroWebp = variant_src(hero_filename(), 'large');
$heroSmall = variant_src(hero_filename(), 'thumb'); // téléphones : ~800 px au lieu de 1920 px
$ogImage = $siteUrl . '/' . hero_image();
$showReviews = $reviews || s('google_rating') !== '' || s('salles1001_rating') !== '';
$reviewsLimit = max(1, (int)s('reviews_visible') ?: 6);
$mapSrc = s('map_query') !== '' ? 'https://www.google.com/maps?q=' . rawurlencode(s('map_query')) . '&output=embed&hl=fr' : '';
$blocks = [];
$v2 = home_v2();
$preview = isset($_GET['apercu']) && s('home_design') !== 'nouveau'; // aperçu avant activation
$sections = home_sections($v2);
$faq = $v2 && !empty($sections['faq']) ? items('faq') : [];
// Liens du menu dans le même ordre que les sections (le plan reste une page à part, toujours accessible)
$navItems = [];
foreach ($sections as $k => $visible) {
    if (($visible || $k === 'plan') && ($k !== 'avis' || $showReviews) && (HOME_SECTIONS[$k]['mobile'] ?? null) !== null) {
        $navItems[$k] = HOME_SECTIONS[$k];
    }
}
$jsonLd  = [
    '@context' => 'https://schema.org',
    '@type' => 'EventVenue',
    '@id' => $siteUrl . '/#venue',
    'url' => $siteUrl . '/',
    'name' => $name,
    'image' => array_values(array_unique(array_merge([$ogImage], array_map(fn($p) => $siteUrl . '/' . photo_src($p['filename']), array_slice($photos, 0, 5))))),
    'logo' => $siteUrl . '/icon-rn6.png',
    'priceRange' => '€€',
    'hasMap' => s('maps_url'),
    'sameAs' => array_values(array_filter([s('facebook_url'), s('salles1001_url'), s('google_reviews_url')])),
    'potentialAction' => ['@type' => 'ReserveAction', 'target' => $siteUrl . '/' . u('reservation.php')],
    'description' => s('og_description'),
    'email' => 'mailto:' . s('contact_email'),
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => '71 C route d’Auxerre',
        'postalCode' => '89380',
        'addressLocality' => 'Appoigny',
        'addressRegion' => 'Bourgogne-Franche-Comté',
        'addressCountry' => 'FR',
    ],
    'maximumAttendeeCapacity' => (int)s('capacity'),
    'amenityFeature' => array_map(fn($n) => ['@type' => 'LocationFeatureSpecification', 'name' => $n, 'value' => true], ['Parking', 'Accès PMR', 'Wi-Fi', 'Climatisation']),
];
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(s('meta_title')) ?></title>
  <meta name="description" content="<?= e(s('meta_description')) ?>">
  <meta name="robots" content="<?= isset($_GET['apercu']) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' ?>">
  <meta name="author" content="<?= e($name) ?>">
  <meta name="theme-color" content="#071c35">
  <link rel="canonical" href="<?= e($siteUrl) ?>/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:site_name" content="<?= e($name) ?>">
  <meta property="og:url" content="<?= e($siteUrl) ?>/">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:alt" content="<?= e($name) ?>, salle de réception à Appoigny">
  <meta property="og:title" content="<?= e(s('og_title')) ?>">
  <meta property="og:description" content="<?= e(s('og_description')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:title" content="<?= e(s('og_title')) ?>">
  <meta name="twitter:description" content="<?= e(s('og_description')) ?>">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="icon-rn6.png" type="image/png"><link rel="apple-touch-icon" href="apple-touch-icon.png">
<?php if ($heroWebp && $heroSmall): ?>
  <link rel="preload" as="image" href="<?= e($heroSmall) ?>" type="image/webp" media="(max-width: 800px)">
  <link rel="preload" as="image" href="<?= e($heroWebp) ?>" type="image/webp" media="(min-width: 801px)">
<?php elseif ($heroWebp): ?>
  <link rel="preload" as="image" href="<?= e($heroWebp) ?>" type="image/webp">
<?php else: ?>
  <link rel="preload" as="image" href="<?= e(hero_image()) ?>">
<?php endif; ?>
  <link rel="preload" href="assets/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php if ($faq): ?>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q['title'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => str_replace('**', '', $q['text'])]], $faq)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<?php if ($v2): ?>
  <link rel="preload" href="assets/fonts/fraunces.woff2" as="font" type="font/woff2" crossorigin>
<?php endif; ?>
  <style>
    @font-face{font-family:"Plus Jakarta Sans";src:url("assets/fonts/plus-jakarta-sans.woff2") format("woff2");font-weight:200 800;font-style:normal;font-display:swap}
    :root{
      --navy:#071c35;
      --navy-2:#0b2b4f;
      --gold:#c79a38;
      --gold-2:#f2d47b;
      --cream:#fbf8f1;
      --paper:#fbf8f1;
      --soft:#f4f6f8;
      --soft-blue:#eef3f7;
      --white:#ffffff;
      --ink:#152131;
      --muted:#617083;
      --line:rgba(7,28,53,.10);
      --shadow:0 18px 55px rgba(7,28,53,.12);
      --radius:22px;
    }
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{
      margin:0;
      font-family:"Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color:var(--ink);
      background:var(--cream);
      line-height:1.6;
    }
    a{color:inherit}
    .wrap{width:min(1160px, calc(100% - 36px)); margin:auto}

    header{
      position:sticky;
      top:0;
      z-index:50;
      background:rgba(5,18,31,.88);
      border-bottom:1px solid rgba(255,255,255,.10);
      backdrop-filter:blur(14px);
      box-shadow:0 8px 28px rgba(0,0,0,.12);
    }
    .nav{display:flex;align-items:center;justify-content:space-between;gap:20px;min-height:76px}
    .brand{display:flex;align-items:center;gap:12px;text-decoration:none;font-weight:700;color:white;font-size:1.06rem;letter-spacing:.005em}
    .brand-mark{width:48px;height:48px;border:2px solid #273CF5;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:.9rem;background:#fff;box-shadow:0 5px 18px rgba(0,0,0,.16);font-weight:900;letter-spacing:-.04em}.brand-mark .rn{color:#273CF5}.brand-mark .six{color:#B50E0E}
    .brand span small{display:block;font-size:.66rem;letter-spacing:.2em;color:var(--gold-2);text-transform:uppercase;font-weight:700;margin-top:2px}

    .facebook-top{display:inline-flex;align-items:center;gap:7px;background:#1877F2;color:#fff!important;padding:9px 12px;border-radius:10px;text-decoration:none!important;font-weight:900!important;box-shadow:0 6px 18px rgba(24,119,242,.3)}
    .facebook-top .fb-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fff;color:#1877F2;font-family:Arial,sans-serif;font-size:18px;font-weight:900;line-height:1}
    .facebook-top:hover{background:#1265d4!important;color:#fff!important;transform:translateY(-1px)}
    .navlinks{display:flex;align-items:center;gap:20px}
    .navlinks a{text-decoration:none;font-weight:600;font-size:.95rem;color:rgba(255,255,255,.92);position:relative;transition:.2s ease;letter-spacing:.005em}
    .navlinks a:hover{color:#fff2bd}
    .navlinks a:not(.cta-small):not(.facebook-top)::after{content:"";position:absolute;left:0;right:0;bottom:-6px;height:2px;border-radius:2px;background:var(--gold-2);transform:scaleX(0);transition:transform .2s ease}
    .navlinks a:not(.cta-small):not(.facebook-top):hover::after{transform:scaleX(1)}
    .navlinks .cta-small{font-weight:700}
    .navlinks .cta-small{background:linear-gradient(135deg,#f3d77d,#d6a23a);color:#132238;padding:11px 16px;border-radius:10px;border:1px solid #f6df99;box-shadow:0 7px 20px rgba(199,154,56,.25)}

    .hero{
      --hero-image:url('<?= e(hero_image()) ?>');
      position:relative;
      overflow:visible;
      min-height:690px;
      padding:104px 0 118px;
      display:flex;
      align-items:center;
      color:var(--navy);
      background-image:
        linear-gradient(90deg, rgba(255,255,255,.96) 0%, rgba(255,255,255,.88) 34%, rgba(255,255,255,.56) 62%, rgba(255,255,255,.14) 100%),
        linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.03)),
        var(--hero-image);
      background-size:cover;
      background-position:center center;
    }
    .hero:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.10));pointer-events:none}
    .hero-grid{display:block;position:relative;z-index:2}
    .hero-copy{max-width:760px}
    .eyebrow{display:inline-flex;align-items:center;gap:11px;font-size:.8rem;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:var(--navy);margin-bottom:18px}
    .eyebrow:before{content:"";width:38px;height:2px;background:var(--gold)}
    h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(3.6rem,7vw,6.8rem);line-height:.92;letter-spacing:-.045em;margin:0 0 22px;text-shadow:0 2px 14px rgba(255,255,255,.24)}
    h1 .h1-blue{color:#273CF5}
    h1 .h1-red{color:#B50E0E}
    h1 .hero-tagline{display:block;color:#26384d;font-style:normal;font-weight:600;font-size:.38em;letter-spacing:-.02em;margin-top:16px;text-shadow:0 1px 10px rgba(255,255,255,.65)}
    .lead{font-size:clamp(1.05rem,1.8vw,1.25rem);max-width:700px;color:#27394d;margin:0 0 30px;text-shadow:0 1px 10px rgba(255,255,255,.75)}
    .actions{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:0}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:14px 20px;border-radius:10px;text-decoration:none;font-weight:900;transition:.2s ease;border:1px solid transparent;box-shadow:0 7px 22px rgba(7,28,53,.12)}
    .btn-primary{background:linear-gradient(135deg,#f0cf72,#d8a83f);color:#152131;box-shadow:0 12px 30px rgba(0,0,0,.22)}
    .btn-primary:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 15px 34px rgba(126,84,7,.28)}
    .btn-gold{background:linear-gradient(135deg,#efd075,#c9952c);color:#11233a;border-color:#e7c360}
    .btn-gold:hover{transform:translateY(-2px);filter:brightness(1.05)}
    main a:not(.btn):not(.brand){text-decoration-thickness:2px;text-underline-offset:3px}
    .hero .btn-secondary{background:rgba(255,255,255,.94);border-color:#f0d17b;color:var(--navy);backdrop-filter:blur(6px);box-shadow:0 12px 30px rgba(32,19,2,.18)}
    .hero .btn-secondary:hover{border-color:#d5a53e;transform:translateY(-2px);background:#fff7df}
    .quickfacts{display:none}
    .dot{width:7px;height:7px;border-radius:50%;background:var(--gold);display:inline-block}

    .hero-facts-wrap{position:relative;z-index:5;margin-top:-60px}
    .hero-facts{background:rgba(255,255,255,.97);border:1px solid rgba(7,28,53,.08);border-radius:20px;box-shadow:0 20px 55px rgba(7,28,53,.14);display:grid;grid-template-columns:repeat(4,1fr);overflow:hidden}
    .hero-fact{padding:22px 24px;display:flex;gap:14px;align-items:center;min-height:96px}
    .hero-fact + .hero-fact{border-left:1px solid var(--line)}
    .hero-fact-icon{flex:0 0 46px;width:46px;height:46px;border-radius:50%;background:#f8f1e3;color:#8f6818;display:grid;place-items:center;font-size:1.25rem}
    .hero-fact strong{display:block;color:var(--navy);font-size:1rem}
    .hero-fact span{display:block;color:var(--muted);font-size:.92rem;line-height:1.35;margin-top:2px}

    section{padding:72px 0}
    .section-title{max-width:740px;margin-bottom:34px}
    .section-title p{margin:8px 0 0;color:var(--muted);font-size:1.04rem}
    /* Petit carrousel d'aperçu à droite du titre « La salle » */
    .salle-head{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,470px);gap:44px;align-items:center;margin-bottom:38px}
    .salle-head .section-title{margin-bottom:0}
    .salle-head.no-carousel{display:block}
    .mini-carousel{position:relative;border-radius:22px;overflow:hidden;aspect-ratio:4/3;background:#e9edf2;box-shadow:0 22px 55px rgba(7,28,53,.18);border:1px solid rgba(255,255,255,.6)}
    .mc-track{display:flex;height:100%;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;scrollbar-width:none;outline:none}
    .mc-track::-webkit-scrollbar{display:none}
    .mc-slide{flex:0 0 100%;height:100%;scroll-snap-align:start;border:0;padding:0;margin:0;background:none;cursor:zoom-in;display:block}
    .mc-slide picture,.mc-slide img{display:block;width:100%;height:100%}
    .mc-slide img{object-fit:cover;transition:transform .6s ease}
    .mini-carousel:hover .mc-slide img{transform:scale(1.03)}
    .mc-nav{position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;border-radius:50%;border:0;background:rgba(255,255,255,.88);color:var(--navy);font-size:26px;line-height:1;cursor:pointer;display:grid;place-items:center;box-shadow:0 6px 18px rgba(0,0,0,.18);opacity:0;transition:opacity .2s ease}
    .mc-prev{left:12px}.mc-next{right:12px}
    .mini-carousel:hover .mc-nav,.mc-nav:focus-visible{opacity:1}
    .mc-dots{position:absolute;left:0;right:0;bottom:12px;display:flex;justify-content:center;gap:6px;pointer-events:none}
    .mc-dots span{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.55);transition:.25s}
    .mc-dots span.on{background:#fff;width:20px;border-radius:4px}
    .mc-all{position:absolute;right:12px;top:12px;background:rgba(7,28,53,.72);color:#fff!important;text-decoration:none!important;font-size:.8rem;font-weight:700;padding:6px 11px;border-radius:999px;backdrop-filter:blur(4px)}
    @media (max-width:900px){.salle-head{grid-template-columns:1fr;gap:22px}.mini-carousel{max-width:560px}.mc-nav{opacity:1;width:36px;height:36px;font-size:22px}}
    h2{font-family:Georgia,"Times New Roman",serif;font-size:clamp(2rem,4vw,3.25rem);line-height:1.08;color:var(--navy);margin:0}

    .cards{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .card{background:white;border:1px solid var(--line);border-radius:var(--radius);padding:25px;box-shadow:0 7px 28px rgba(7,28,53,.055)}
    .icon{width:46px;height:46px;border-radius:14px;background:#f7efd9;color:#9c741d;display:grid;place-items:center;font-size:1.35rem;margin-bottom:17px}
    .card h3{margin:0 0 8px;color:var(--navy);font-size:1.1rem}
    .card p{margin:0;color:var(--muted)}
    .card strong{color:var(--ink)}

    .specs{background:linear-gradient(180deg,#f3f6f8 0%,#edf2f5 100%);color:var(--ink);position:relative;overflow:hidden;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
    .specs:before{content:"";position:absolute;width:420px;height:420px;border-radius:50%;border:1px solid rgba(199,154,56,.12);left:-200px;top:-180px;box-shadow:0 0 0 70px rgba(199,154,56,.018)}
    .specs h2{color:var(--navy)}
    .specs .section-title p{color:var(--muted)}
    .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;position:relative}
    .stat{padding:23px;border-radius:18px;background:rgba(255,255,255,.72);border:1px solid rgba(7,28,53,.08);box-shadow:0 5px 18px rgba(7,28,53,.035)}
    .stat b{display:block;color:#9a741f;font-size:1.42rem;margin-bottom:4px}
    .stat span{color:#536276;font-size:.93rem}

    .details-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
    .details-panel{background:white;border:1px solid var(--line);border-radius:var(--radius);padding:28px}
    .details-panel h3{font-family:Georgia,serif;color:var(--navy);font-size:1.55rem;margin:0 0 14px}
    .checklist{list-style:none;padding:0;margin:0;display:grid;gap:12px}
    .checklist li{display:flex;gap:11px;align-items:flex-start;color:#425166}
    .check{flex:0 0 24px;width:24px;height:24px;border-radius:50%;background:#f8efd8;color:#8a6417;display:grid;place-items:center;font-weight:900;font-size:.8rem;margin-top:1px}

    .pricing-section{background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
    .pricing-note{display:inline-flex;align-items:center;gap:8px;background:#fff8e8;border:1px solid rgba(199,154,56,.28);color:#70551d;padding:10px 14px;border-radius:999px;font-weight:800;font-size:.9rem;margin-top:12px}
    .price-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-top:26px}
    .day-price{background:var(--soft);border:1px solid var(--line);border-radius:16px;padding:16px 10px;text-align:center}
    .day-price strong{display:block;color:var(--navy);font-size:.86rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
    .day-price span{font-size:1.24rem;font-weight:900;color:#9a741f}
    .pricing-panels{display:grid;grid-template-columns:1fr;gap:22px;margin-top:22px}
    .pricing-card{background:var(--soft);border:1px solid var(--line);border-radius:var(--radius);padding:26px}
    .pricing-card h3{font-family:Georgia,serif;color:var(--navy);font-size:1.45rem;margin:0 0 16px}
    .price-list{list-style:none;padding:0;margin:0;display:grid;gap:0}
    .price-list li{display:flex;justify-content:space-between;gap:20px;padding:13px 0;border-bottom:1px solid var(--line);color:#425166}
    .price-list li:last-child{border-bottom:0}
    .price-list strong{color:var(--navy);text-align:right;white-space:nowrap}
    .pricing-foot{margin-top:20px;color:var(--muted);font-size:.92rem}
    .pricing-info{margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .pricing-info-item{background:#fff8e8;border:1px solid rgba(199,154,56,.28);border-radius:14px;padding:14px 16px;color:#5f4b22}
    .pricing-info-item strong{color:var(--navy)}
    .pricing-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}

    .photos-section{background:#fff}
    .gallery{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
    .gallery-item{position:relative;margin:0;aspect-ratio:4/3;cursor:pointer;border-radius:18px;overflow:hidden;background:linear-gradient(135deg,#f7f7f7,#edf0f3);border:1px solid var(--line);box-shadow:0 5px 18px rgba(7,28,53,.04)}
    .gallery-item img{position:relative;z-index:2;width:100%;height:100%;object-fit:cover;display:block;transition:transform .25s ease}
    .gallery-item:hover img{transform:scale(1.025)}
    .gallery-empty{position:absolute;z-index:1;inset:0;display:grid;place-items:center;text-align:center;padding:14px;color:#8792a0;font-weight:750;font-size:.9rem}
    .gallery-empty span{display:block;font-size:1.8rem;line-height:1;margin-bottom:7px;opacity:.55}
    .gallery-help{margin-top:18px;color:var(--muted);font-size:.92rem}
    .lightbox{position:fixed;inset:0;z-index:9999;background:rgba(3,12,24,.94);display:none;align-items:center;justify-content:center;padding:24px}
    .lightbox.open{display:flex}
    .lightbox-img{max-width:min(92vw,1500px);max-height:88vh;object-fit:contain;border-radius:10px;box-shadow:0 20px 70px rgba(0,0,0,.45)}
    .lightbox-close,.lightbox-prev,.lightbox-next{position:absolute;border:0;background:rgba(255,255,255,.12);color:#fff;cursor:pointer;display:grid;place-items:center;backdrop-filter:blur(5px)}
    .lightbox-close{top:20px;right:24px;width:46px;height:46px;border-radius:50%;font-size:28px}
    .lightbox-prev,.lightbox-next{top:50%;transform:translateY(-50%);width:54px;height:70px;border-radius:12px;font-size:38px}
    .lightbox-prev{left:22px}.lightbox-next{right:22px}
    .lightbox-counter{position:absolute;bottom:18px;left:50%;transform:translateX(-50%);color:#fff;background:rgba(0,0,0,.35);padding:7px 12px;border-radius:999px;font-size:.9rem}

    .contact{padding-top:18px;padding-bottom:82px}
    .contact-box{background:linear-gradient(135deg,var(--navy),#0b315a);border-radius:30px;padding:46px;display:grid;grid-template-columns:1.3fr .7fr;gap:30px;align-items:center;color:white;box-shadow:var(--shadow)}
    .contact-box h2{color:white;margin-bottom:13px}
    .contact-box p{margin:0;color:#d7e1ec;max-width:700px}
    .email-card{background:white;color:var(--navy);padding:22px;border-radius:20px;text-align:center}
    .email-card small{display:block;text-transform:uppercase;letter-spacing:.12em;color:#7c8998;font-weight:900;margin-bottom:6px}
    .email-card a{font-weight:900;text-decoration:none;word-break:break-word;font-size:1.05rem}
    .email-card button{margin-top:13px;border:0;background:#f4ead2;color:var(--navy);font-weight:800;padding:9px 13px;border-radius:999px;cursor:pointer}

    footer{border-top:1px solid var(--line);padding:24px 0 38px;color:#5d6979;font-size:.9rem;background:#fff}
    .foot{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}

    @media (max-width:900px){
      .navlinks a:not(.cta-small):not(.facebook-top){display:none}
      .hero{min-height:620px;padding:82px 0 112px;background-position:62% center}
      .contact-box{grid-template-columns:1fr}
      .cards{grid-template-columns:1fr 1fr}
      .gallery{grid-template-columns:repeat(2,1fr)}
      .stat-grid{grid-template-columns:1fr 1fr}
      .hero-facts{grid-template-columns:1fr 1fr}
      .price-grid{grid-template-columns:repeat(4,1fr)}
      .pricing-panels{grid-template-columns:1fr}
      .pricing-info{grid-template-columns:1fr}
      .hero-fact:nth-child(3){border-left:0;border-top:1px solid var(--line)}
      .hero-fact:nth-child(4){border-top:1px solid var(--line)}
    }
    @media (max-width:620px){
      .wrap{width:min(100% - 24px,1160px)}
      .nav{min-height:66px}
      .brand span{font-size:.9rem}
      .hero{min-height:590px;padding:70px 0 96px;background-position:66% center}
      h1{font-size:4.35rem;line-height:.88;letter-spacing:-.055em;font-weight:950;text-shadow:0 2px 18px rgba(255,255,255,.9)}
      h1 .h1-blue,h1 .h1-red{font-size:1em;line-height:.9;display:inline;font-weight:950}
      h1 .h1-blue{color:#273CF5;text-shadow:0 2px 0 rgba(255,255,255,.75),0 5px 20px rgba(39,60,245,.18)}
      h1 .h1-red{color:#B50E0E;text-shadow:0 2px 0 rgba(255,255,255,.75),0 5px 20px rgba(181,14,14,.18)}
      h1 .hero-tagline{font-size:.28em;line-height:1.15;margin-top:18px;letter-spacing:-.015em;font-weight:700}
      .hero-facts-wrap{margin-top:-44px}
      .hero-facts{grid-template-columns:1fr}
      .hero-fact + .hero-fact{border-left:0;border-top:1px solid var(--line)}
      .cards,.details-grid,.stat-grid,.gallery{grid-template-columns:1fr}
      .price-grid{grid-template-columns:1fr 1fr}
      .price-list li{display:block}
      .price-list strong{display:block;text-align:left;margin-top:4px;white-space:normal}
      section{padding:54px 0}
      .contact-box{padding:29px 22px}
    }
  
    .plan-cta-section{padding:22px 0 54px;background:var(--paper)}
    .plan-cta-box{display:flex;align-items:center;justify-content:space-between;gap:32px;padding:32px 36px;border:1px solid var(--line);border-radius:22px;background:#fff;box-shadow:0 12px 35px rgba(12,29,51,.06)}
    .plan-cta-box h2{margin:6px 0 8px;font-family:Georgia,serif;font-size:clamp(26px,3vw,38px);color:var(--navy)}
    .plan-cta-box p{margin:0;max-width:720px;color:var(--muted);line-height:1.65}
    .plan-cta-box .btn{flex:0 0 auto;white-space:nowrap}
    @media(max-width:760px){.plan-cta-box{align-items:flex-start;flex-direction:column;padding:26px 22px}.plan-cta-box .btn{width:100%;text-align:center}}


    /* Optimisations de rendu, notamment pour Safari/iPhone */
    #salle, #equipements, #mobilier, .plan-cta-section, #tarifs, #photos, #acces, .contact{
      content-visibility:auto;
      contain-intrinsic-size:1px 900px;
    }
    .gallery-item img[data-src]{opacity:0}
    .gallery-item img.is-loaded{opacity:1;transition:opacity .22s ease, transform .25s ease}
    @media (max-width:620px){
      header{backdrop-filter:none;-webkit-backdrop-filter:none;background:rgba(5,18,31,.96)}
      .hero .btn-secondary{backdrop-filter:none;-webkit-backdrop-filter:none}
      .lightbox-close,.lightbox-prev,.lightbox-next{backdrop-filter:none;-webkit-backdrop-filter:none}
    }
  
    /* Header responsive */
    @media (max-width:1100px){
      .nav{gap:12px}
      .navlinks{gap:12px}
      .navlinks a{font-size:.88rem}
      .navlinks .cta-small{padding:10px 12px}
    }
    @media (max-width:900px){
      .nav{min-height:68px}
      .brand{flex:0 0 auto}
      .navlinks{margin-left:auto;gap:9px}
      .navlinks a:not(.cta-small){display:none}
      .navlinks .cta-small{display:inline-flex;font-size:.86rem;padding:10px 13px;white-space:nowrap}
    }
    @media (max-width:620px){
      .nav{min-height:64px;gap:8px}
      .brand{gap:8px;min-width:0}
      .brand-mark{width:42px;height:42px;flex:0 0 42px}
      .brand > span{font-size:.82rem;line-height:1.15;white-space:nowrap}
      .brand span small{font-size:.55rem;letter-spacing:.10em;margin-top:3px}
      .navlinks{gap:6px;min-width:0}
      .navlinks .cta-small{font-size:.78rem;padding:9px 10px}
    }
    @media (max-width:430px){
      .wrap{width:min(100% - 16px,1160px)}
      .brand > span{display:none}
      .navlinks .cta-small{font-size:.76rem;padding:9px 9px}
    }


    /* ===== Ajouts : WebP, menu mobile, avis, carte, animations ===== */
<?php if ($heroWebp): ?>
    @supports (background-image: image-set(url("x.webp") type("image/webp"))){
      .hero{--hero-image:image-set(url('<?= e($heroWebp) ?>') type("image/webp"), url('<?= e(hero_image()) ?>') type("image/jpeg"))}
    }
<?php if ($heroSmall): ?>
    @media (max-width: 800px){@supports (background-image: image-set(url("x.webp") type("image/webp"))){
      .hero{--hero-image:image-set(url('<?= e($heroSmall) ?>') type("image/webp"), url('<?= e(hero_image()) ?>') type("image/jpeg"))}
    }}
<?php endif; ?>
<?php endif; ?>
    .gallery-item picture{display:block;width:100%;height:100%}

    /* Menu mobile */
    .menu-toggle{display:none;flex:0 0 44px;width:44px;height:44px;border-radius:10px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.06);cursor:pointer;flex-direction:column;align-items:center;justify-content:center;gap:5px;padding:0}
    .menu-toggle span{display:block;width:20px;height:2px;border-radius:2px;background:#fff;transition:transform .25s ease, opacity .2s ease}
    .menu-toggle[aria-expanded="true"] span:nth-child(1){transform:translateY(7px) rotate(45deg)}
    .menu-toggle[aria-expanded="true"] span:nth-child(2){opacity:0}
    .menu-toggle[aria-expanded="true"] span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
    .mobile-menu{display:none}
    @media (max-width:900px){
      .menu-toggle{display:inline-flex}
      .mobile-menu{display:block;position:absolute;left:0;right:0;top:100%;background:#06172a;border-top:1px solid rgba(255,255,255,.08);box-shadow:0 22px 40px rgba(0,0,0,.25);max-height:calc(100vh - 64px);overflow:auto;transform-origin:top;transition:opacity .22s ease, transform .22s ease}
      .mobile-menu[hidden]{display:block;opacity:0;transform:translateY(-8px);pointer-events:none;visibility:hidden}
      .mobile-menu ul{list-style:none;margin:0;padding:8px 0}
      .mobile-menu li a{display:flex;align-items:center;justify-content:space-between;padding:15px 22px;color:#fff;text-decoration:none;font-weight:800;font-size:1.05rem;border-bottom:1px solid rgba(255,255,255,.06)}
      .mobile-menu li a:after{content:"›";color:var(--gold-2);font-size:1.3rem}
      .mobile-menu li a:active{background:rgba(255,255,255,.05)}
      .mobile-menu .mm-actions{display:grid;gap:10px;padding:16px 18px 22px}
      .mobile-menu .mm-actions .btn{width:100%}
    }
    @media (max-width:620px){ .navlinks .cta-contact{display:none} }

    /* Avis */
    .reviews-section{background:var(--soft-blue);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
    .review-badges{display:flex;flex-wrap:wrap;gap:14px;margin:-8px 0 30px}
    .review-badge{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:14px 20px 14px 16px;text-decoration:none;color:var(--ink);box-shadow:0 7px 24px rgba(7,28,53,.06);transition:transform .2s ease, box-shadow .2s ease}
    a.review-badge:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(7,28,53,.10)}
    .review-badge .src-logo{flex:0 0 44px;width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:#fff;border:1px solid var(--line)}
    .review-badge .src-logo.s1001{background:var(--navy);color:var(--gold-2);font-weight:900;font-size:.72rem;letter-spacing:-.02em;border:0}
    .review-badge .score{font-size:1.9rem;font-weight:900;color:var(--navy);line-height:1}
    .review-badge small{display:block;color:var(--muted);font-size:.84rem;margin-top:3px}
    .star-meter{position:relative;display:inline-block;color:#d6dbe1;letter-spacing:2px;font-size:1rem;line-height:1}
    .star-meter:before{content:"★★★★★";position:absolute;left:0;top:0;width:var(--pct,100%);overflow:hidden;color:#f2b01e;white-space:nowrap}
    .reviews{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .review{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:24px;display:flex;flex-direction:column;gap:14px;box-shadow:0 7px 28px rgba(7,28,53,.05)}
    .review.is-more{display:none}
    .reviews.show-all .review.is-more{display:flex}
    .review-top{display:flex;justify-content:space-between;align-items:center;gap:10px}
    .stars{color:#f2b01e;letter-spacing:2px;font-size:1.05rem}
    .stars-off{color:#d6dbe1}
    .review-src{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;font-weight:800;color:var(--muted);background:var(--soft);border-radius:999px;padding:4px 10px}
    .review blockquote{margin:0;color:#34465b;position:relative;display:-webkit-box;-webkit-line-clamp:7;-webkit-box-orient:vertical;overflow:hidden}
    .review.open blockquote{display:block}
    .review .more{align-self:flex-start;border:0;background:none;color:#8f6818;font-weight:800;cursor:pointer;padding:0;font:inherit;font-size:.9rem}
    .review-author{display:flex;align-items:center;gap:12px;margin-top:auto;padding-top:14px;border-top:1px solid var(--line)}
    .review-author .avatar{color:#fff;font-size:1rem;flex:0 0 40px;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-weight:900;color:#fff;background:linear-gradient(135deg,var(--navy-2),#273CF5)}
    .review-author strong{display:block;color:var(--navy);line-height:1.2}
    .review-author span{font-size:.85rem;color:var(--muted)}
    .reviews-more{text-align:center;margin-top:26px}
    .reviews-more button{cursor:pointer;font:inherit}
    @media (max-width:900px){.reviews{grid-template-columns:1fr 1fr}}
    @media (max-width:620px){.reviews{grid-template-columns:1fr}.review-badge{flex:1 1 100%}}

    /* Carte */
    .map-box{position:relative;margin-top:22px;height:400px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--line);box-shadow:0 7px 28px rgba(7,28,53,.06);
      background:
        radial-gradient(circle at 62% 46%, rgba(199,154,56,.20) 0 70px, transparent 71px),
        linear-gradient(115deg, transparent 47%, rgba(255,255,255,.95) 47% 50%, transparent 50%),
        linear-gradient(25deg, transparent 60%, rgba(255,255,255,.85) 60% 62%, transparent 62%),
        repeating-linear-gradient(0deg, rgba(7,28,53,.05) 0 1px, transparent 1px 46px),
        repeating-linear-gradient(90deg, rgba(7,28,53,.05) 0 1px, transparent 1px 46px),
        #e9eef2}
    .map-box iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
    @media (max-width:620px){.map-box{height:320px}}

    /* Animations douces */
    @media (prefers-reduced-motion: no-preference){
      .hero-copy{animation:heroIn .9s cubic-bezier(.2,.7,.2,1) both}
      @keyframes heroIn{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
      .js-anim .reveal{opacity:0;transform:translateY(24px);transition:opacity .7s ease, transform .7s cubic-bezier(.2,.7,.2,1);transition-delay:var(--d,0ms)}
      .js-anim .reveal.in{opacity:1;transform:none}
    }
    .count{font-variant-numeric:tabular-nums}
<?php if ($v2): ?>
    /* ===== Nouvelle page d'accueil ===== */
    @font-face{font-family:"Fraunces";src:url("assets/fonts/fraunces.woff2") format("woff2");font-weight:500 700;font-style:normal;font-display:swap}
    .v2 h1,.v2 h2,.v2 .details-panel h3,.v2 .pricing-card h3,.v2 .plan-cta-box h2,.v2 .faq-q,.v2 .ev-card h3,.v2 .eqt-text h3,.v2 .band p,.v2 .kf b{font-family:"Fraunces",Georgia,"Times New Roman",serif;font-optical-sizing:auto}
    .v2 h1{letter-spacing:-.03em;font-weight:700}
    .v2 h2{letter-spacing:-.02em;font-weight:600}
    .v2 #salle{background:var(--cream)}
    .v2 #evenements{background:#fff}
    .v2 #equipements{background:var(--cream);border:0}
    .v2 #equipements:before{display:none}
    .v2 .plan-cta-section{background:var(--cream)}
    .v2 #tarifs{background:#fff}
    .v2 #photos{background:var(--cream)}
    .v2 #faq{background:linear-gradient(180deg,#fdf6e3,#fbf1d6);border-top:1px solid rgba(199,154,56,.18);border-bottom:1px solid rgba(199,154,56,.18)}
    .v2 #acces{background:#fff}
    .v2 #avis{background:var(--soft-blue)}
    .v2 #evenements,.v2 #faq,.v2 .band,.v2 .keyfigs{content-visibility:auto;contain-intrinsic-size:1px 700px}

    /* Bandeaux photo pleine largeur */
    .band{position:relative;min-height:clamp(320px,46vw,520px);display:flex;align-items:flex-end;padding:0;color:#fff;background:var(--navy) center/cover no-repeat;background-image:var(--bg);overflow:hidden}
    .band:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(5,18,31,0) 30%,rgba(5,18,31,.78) 100%)}
    @supports (background-attachment:fixed){@media (min-width:1000px) and (prefers-reduced-motion:no-preference){.band{background-attachment:fixed}}}
    .band .wrap{position:relative;padding:0 0 52px}
    .band p{margin:0;max-width:760px;font-size:clamp(1.7rem,3.6vw,3rem);line-height:1.12;font-weight:600;letter-spacing:-.02em;text-shadow:0 2px 24px rgba(0,0,0,.35)}
    .band p:before{content:"";display:block;width:54px;height:3px;background:var(--gold-2);margin-bottom:18px;border-radius:2px}

    /* Chiffres clés */
    .keyfigs{background:radial-gradient(circle at 15% 0%,#123b66 0,transparent 55%),linear-gradient(135deg,var(--navy),#0b2b4f);color:#fff;padding:64px 0}
    .kf-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
    .kf{text-align:center;padding:14px 8px;position:relative}
    .kf + .kf:before{content:"";position:absolute;left:-5px;top:18%;bottom:18%;width:1px;background:rgba(255,255,255,.14)}
    .kf b{display:block;font-size:clamp(2.6rem,5.4vw,4.2rem);line-height:1;color:var(--gold-2);font-weight:600;letter-spacing:-.03em}
    .kf span{display:block;margin-top:10px;color:#cfdae6;font-size:.98rem}

    /* Cartes « Pour quel événement ? » */
    .ev-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
    .ev-card{position:relative;display:flex;flex-direction:column;justify-content:flex-end;min-height:400px;border-radius:22px;overflow:hidden;color:#fff;text-decoration:none!important;background:var(--navy);box-shadow:0 14px 40px rgba(7,28,53,.16);isolation:isolate}
    .ev-card picture,.ev-card img{position:absolute;inset:0;width:100%;height:100%;z-index:-2}
    .ev-card img{object-fit:cover;transition:transform .6s ease}
    .ev-card:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(5,18,31,.05) 25%,rgba(5,18,31,.86) 100%)}
    .ev-card:hover img{transform:scale(1.06)}
    .ev-body{padding:22px}
    .ev-icon{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.16);backdrop-filter:blur(4px);font-size:1.3rem;margin-bottom:12px}
    .ev-card h3{margin:0 0 6px;font-size:1.5rem;font-weight:600;line-height:1.15}
    .ev-card p{margin:0 0 14px;color:#dde6ef;font-size:.95rem;line-height:1.45}
    .ev-cta{display:inline-flex;align-items:center;gap:6px;font-weight:800;color:var(--gold-2);font-size:.95rem}
    .ev-card:hover .ev-cta{gap:10px}

    /* Équipements en onglets */
    .eqt-tabs{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;padding:2px 2px 14px;margin-bottom:4px}
    .eqt-tabs::-webkit-scrollbar{display:none}
    .eqt-tabs button{flex:0 0 auto;display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:#fff;color:var(--navy);font:inherit;font-weight:800;font-size:.98rem;padding:11px 18px;border-radius:999px;cursor:pointer;transition:.2s}
    .eqt-tabs button:hover{border-color:var(--gold)}
    .eqt-tabs button[aria-selected="true"]{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 8px 22px rgba(7,28,53,.18)}
    .eqt-panel{display:grid;grid-template-columns:minmax(0,7fr) minmax(0,5fr);gap:28px;align-items:start;background:#fff;border:1px solid var(--line);border-radius:24px;padding:18px;box-shadow:0 10px 34px rgba(7,28,53,.07)}
    .eqt-panel.no-pic{grid-template-columns:1fr}
    .eqt-panel[hidden]{display:none}
    .eqt-main{display:block;width:100%;aspect-ratio:4/3;border:0;padding:0;border-radius:16px;overflow:hidden;cursor:zoom-in;background:#e9edf2}
    .eqt-main picture,.eqt-main img{display:block;width:100%;height:100%}
    .eqt-main img{object-fit:cover}
    .eqt-thumbs{display:flex;gap:8px;margin-top:10px;overflow-x:auto;scrollbar-width:none}
    .eqt-thumb{flex:0 0 76px;height:58px;border:2px solid transparent;border-radius:10px;overflow:hidden;padding:0;cursor:pointer;background:#e9edf2;opacity:.75}
    .eqt-thumb.on{border-color:var(--gold);opacity:1}
    .eqt-thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .eqt-text{padding:8px 8px 8px 0}
    .eqt-text h3{margin:0 0 6px;font-size:1.6rem;color:var(--navy);font-weight:600}
    .eqt-intro{margin:0 0 16px;color:var(--muted)}
    @media (max-width:900px){.eqt-panel{grid-template-columns:1fr;padding:12px}.eqt-text{padding:4px 6px 8px}}
    @media (max-width:620px){.eqt-tabs{display:grid;grid-template-columns:1fr 1fr;overflow:visible;padding-bottom:12px}.eqt-tabs button{justify-content:center;padding:11px 8px;font-size:.9rem}}

    /* Équipements + mobilier regroupés */
    .eq-grid{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:34px;align-items:stretch}
    .eq-photo{border-radius:24px;overflow:hidden;min-height:420px;box-shadow:var(--shadow);position:relative}
    .eq-photo picture,.eq-photo img{position:absolute;inset:0;width:100%;height:100%}
    .eq-photo img{object-fit:cover}
    .eq-side{display:grid;gap:16px;align-content:start}
    .eq-side .stat-grid{grid-template-columns:1fr 1fr}
    .eq-side .stat{background:#fff}
    .eq-lists{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .eq-lists .details-panel{padding:22px}
    .eq-lists .details-panel h3{font-size:1.25rem}

    /* Prochaines dates libres */
    .next-dates{margin-top:22px;padding:22px 24px;border-radius:20px;background:linear-gradient(135deg,var(--navy),#0b315a);color:#fff;display:grid;grid-template-columns:auto 1fr auto;gap:18px 24px;align-items:center}
    .next-dates h3{margin:0;font-size:1.05rem;font-weight:800;letter-spacing:.01em;display:flex;align-items:center;gap:10px}
    .next-dates h3:before{content:"";width:9px;height:9px;border-radius:50%;background:#3ddc84;box-shadow:0 0 0 4px rgba(61,220,132,.2)}
    .nd-list{display:flex;flex-wrap:wrap;gap:10px}
    .nd{display:inline-flex;flex-direction:column;padding:9px 14px;border-radius:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);text-decoration:none!important;color:#fff;line-height:1.25;transition:.2s}
    .nd:hover{background:var(--gold-2);color:var(--navy);border-color:var(--gold-2);transform:translateY(-2px)}
    .nd strong{font-size:.95rem}
    .nd small{font-size:.8rem;opacity:.8}
    .nd-all{color:var(--gold-2)!important;font-weight:800;white-space:nowrap;text-decoration:none!important}

    /* Galerie en mosaïque + onglets */
    .g-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:-10px 0 22px}
    .g-tab{border:1px solid var(--line);background:#fff;color:var(--navy);font:inherit;font-weight:700;font-size:.9rem;padding:8px 16px;border-radius:999px;cursor:pointer}
    .g-tab[aria-pressed="true"]{background:var(--navy);color:#fff;border-color:var(--navy)}
    .v2 .gallery{grid-template-columns:repeat(4,1fr);grid-auto-rows:210px;gap:12px}
    .v2 .gallery-item{aspect-ratio:auto;height:100%;border-radius:16px}
    .v2 .gallery:not(.filtered) .gallery-item:first-child{grid-column:span 2;grid-row:span 2}
    .v2 .gallery:not(.show-all):not(.filtered) .gallery-item.more{display:none}
    .v2 .gallery.filtered .gallery-item.out{display:none}
    .g-more{text-align:center;margin-top:24px}
    .g-more button{cursor:pointer;font:inherit;font-weight:900}

    /* Avis : bande qui défile */
    .v2 .reviews-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:24px}
    .v2 .reviews-head .section-title{margin-bottom:0}
    .v2 .review-badges{margin:0}
    .v2 .reviews{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:16px;padding:4px 2px 16px;scrollbar-width:none;-ms-overflow-style:none}
    .v2 .reviews::-webkit-scrollbar{display:none}
    .v2 .review{flex:0 0 min(380px,86%);scroll-snap-align:start}
    .v2 .review.is-more{display:flex}
    .v2 .reviews-more{display:none}
    .rv-nav{display:flex;gap:8px;justify-content:flex-end;margin-top:6px}
    .rv-nav button{width:42px;height:42px;border-radius:50%;border:1px solid var(--line);background:#fff;color:var(--navy);font-size:1.3rem;cursor:pointer}

    /* FAQ */
    .faq-list{max-width:860px;margin:0 auto;display:grid;gap:12px}
    .v2 #faq .section-title{margin-left:auto;margin-right:auto;text-align:center}
    .faq-item{background:#fff;border:1px solid rgba(199,154,56,.22);border-radius:16px;box-shadow:0 6px 20px rgba(120,84,10,.05)}
    .faq-q{list-style:none;cursor:pointer;padding:18px 54px 18px 22px;font-size:1.15rem;font-weight:600;color:var(--navy);position:relative}
    .faq-q::-webkit-details-marker{display:none}
    .faq-q:after{content:"+";position:absolute;right:20px;top:50%;transform:translateY(-50%);width:28px;height:28px;border-radius:50%;background:#f8efd8;color:#8a6417;display:grid;place-items:center;font-family:"Plus Jakarta Sans",sans-serif;font-weight:800;transition:transform .2s}
    .faq-item[open] .faq-q:after{content:"−"}
    .faq-a{padding:0 22px 18px;color:#425166;margin:0}

    /* Accès : adresse + carte côte à côte */
    .v2 .acces-grid{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:22px;align-items:stretch}
    .v2 .acces-grid .map-box{margin-top:0;height:auto;min-height:360px}

    /* Contact : formulaire + téléphone */
    .v2 .contact-box{grid-template-columns:minmax(0,5fr) minmax(0,6fr);align-items:start}
    .c-ways{display:grid;gap:10px;margin-top:22px}
    .c-way{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);color:#fff!important;text-decoration:none!important;font-weight:700;word-break:break-word}
    .c-way:hover{background:rgba(255,255,255,.14)}
    .c-way i{font-style:normal;flex:0 0 36px;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--gold-2);color:var(--navy)}
    .c-way.wa i{background:#25d366;color:#fff}
    .c-way small{display:block;font-weight:500;color:#b9c7d6;font-size:.8rem}
    .c-form{background:#fff;color:var(--ink);border-radius:22px;padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .c-form h3{grid-column:1/-1;margin:0;color:var(--navy);font-size:1.25rem}
    .c-form p.c-intro{grid-column:1/-1;margin:-6px 0 4px;color:var(--muted);font-size:.92rem}
    .c-form label{display:grid;gap:5px;font-size:.85rem;font-weight:700;color:var(--navy)}
    .c-form label.full{grid-column:1/-1}
    .c-form input,.c-form textarea{font:inherit;font-weight:400;font-size:16px;padding:11px 12px;border:1px solid #d5dce4;border-radius:10px;background:#fbfcfd;color:var(--ink);width:100%}
    .c-form input:focus,.c-form textarea:focus{outline:2px solid rgba(199,154,56,.45);border-color:var(--gold)}
    .c-form textarea{min-height:110px;resize:vertical}
    .c-form .btn{grid-column:1/-1;border:0;cursor:pointer;font:inherit;font-weight:900}
    .c-form .hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
    .c-msg{grid-column:1/-1;margin:0;padding:12px 14px;border-radius:10px;font-weight:700}
    .c-msg.ok{background:#e7f6ec;color:#14532d}
    .c-msg.err{background:#fdecea;color:#8a1c12}

    /* Barre « Réserver » fixe sur mobile */
    .mbar{display:none}
    @media (max-width:900px){
      .mbar{display:flex;gap:8px;position:fixed;left:10px;right:10px;bottom:calc(10px + env(safe-area-inset-bottom));z-index:60;padding:8px;border-radius:16px;background:rgba(5,18,31,.94);box-shadow:0 12px 30px rgba(0,0,0,.3);transform:translateY(140%);transition:transform .3s ease}
      .mbar.on{transform:none}
      .mbar a{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:12px 10px;border-radius:11px;font-weight:900;text-decoration:none;font-size:.95rem}
      .mbar .m-book{background:linear-gradient(135deg,#f0cf72,#d8a83f);color:#152131}
      .mbar .m-alt{background:rgba(255,255,255,.1);color:#fff;flex:0 0 auto;padding:12px 16px}
      .v2 footer{padding-bottom:100px}
    }

    /* Aperçu avant activation */
    .preview-pill{position:fixed;left:12px;bottom:12px;z-index:70;background:#fff7df;color:#5f4b22;border:1px solid #f0d17b;border-radius:12px;padding:8px 12px;font-size:.82rem;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.15);max-width:calc(100% - 24px)}
    .preview-pill a{color:#1727b8}
    @media (max-width:900px){.preview-pill{bottom:auto;top:76px}}

    @media (max-width:1000px){
      .ev-grid{grid-template-columns:1fr 1fr}
      .ev-card{min-height:340px}
      .eq-grid,.v2 .acces-grid{grid-template-columns:1fr}
      .eq-photo{min-height:300px}
      .next-dates{grid-template-columns:1fr}
      .v2 .contact-box{grid-template-columns:1fr}
    }
    @media (max-width:620px){
      .kf-grid{grid-template-columns:1fr 1fr;gap:18px 6px}
      .kf:nth-child(3):before{display:none}
      .ev-grid{grid-template-columns:1fr}
      .ev-card{min-height:300px}
      .eq-lists,.eq-side .stat-grid{grid-template-columns:1fr}
      .v2 .gallery{grid-template-columns:1fr 1fr;grid-auto-rows:150px}
      .c-form{grid-template-columns:1fr;padding:20px}
      .band .wrap{padding-bottom:36px}
      .band:before{background:linear-gradient(180deg,rgba(5,18,31,.2) 0%,rgba(5,18,31,.85) 100%)}
      .nd-list{display:grid;grid-template-columns:1fr 1fr}
      .nd{padding:9px 11px}
      .rv-nav{display:none}
    }
<?php endif; ?>
  </style>
</head>
<body<?= $v2 ? ' class="v2"' : '' ?>>
  <header>
    <div class="wrap nav">
      <a class="brand" href="#accueil" aria-label="Accueil <?= e($name) ?>">
        <div class="brand-mark" aria-hidden="true"><span class="rn">RN</span><span class="six">6</span></div>
        <span><?= e($name) ?><small><?= e(s('site_location')) ?></small></span>
      </a>
      <nav class="navlinks" aria-label="Navigation principale">
<?php foreach ($navItems as $k => $it): if ($it['nav'] === null) continue; ?>
        <a href="<?= e(u($it['href'])) ?>"<?= $k === 'plan' ? ' data-track="plan"' : '' ?>><?= e($it['nav']) ?></a>
<?php endforeach; ?>
        <a class="cta-small" href="<?= e(u('reservation.php')) ?>" data-track="reserver" style="background:linear-gradient(135deg,#273CF5,#1727b8);color:#fff;border-color:#5262ff;box-shadow:0 7px 20px rgba(39,60,245,.28)">Réserver en ligne</a>
        <a class="cta-small cta-contact" href="<?= e($mailto) ?>" data-track="email">Nous contacter</a>
      </nav>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Ouvrir le menu"><span></span><span></span><span></span></button>
    </div>
    <nav class="mobile-menu" id="mobile-menu" aria-label="Menu mobile" hidden>
      <ul>
<?php foreach ($navItems as $k => $it): ?>
        <li><a href="<?= e(u($it['href'])) ?>"<?= $k === 'plan' ? ' data-track="plan"' : '' ?>><?= e($it['mobile']) ?></a></li>
<?php endforeach; ?>
      </ul>
      <div class="mm-actions">
        <a class="btn btn-primary" href="<?= e(u('reservation.php')) ?>" data-track="reserver">📅 Réserver en ligne</a>
        <a class="btn btn-secondary" style="background:#fff;color:var(--navy)" href="<?= e($mailto) ?>" data-track="email">✉ Nous contacter</a>
      </div>
    </nav>
  </header>

  <main>
    <section class="hero" id="accueil">
      <div class="wrap hero-grid">
        <div class="hero-copy">
          <div class="eyebrow"><?= e(s('hero_eyebrow')) ?></div>
          <h1><span class="h1-blue"><?= e(s('hero_title_1')) ?></span> <span class="h1-red"><?= e(s('hero_title_2')) ?></span><span class="hero-tagline"><?= e(s('hero_tagline')) ?></span></h1>
          <p class="lead"><?= rich(s('hero_lead')) ?></p>
          <div class="actions">
            <a class="btn btn-primary" href="<?= e(u('reservation.php')) ?>" data-track="reserver">📅 Réserver en ligne</a>
            <a class="btn btn-primary" href="<?= e($mailto) ?>" data-track="email">✉ Demander une réservation</a>
          </div>
        </div>
      </div>
    </section>

    <div class="hero-facts-wrap">
      <div class="wrap">
        <div class="hero-facts">
<?php foreach (items('hero_facts') as $it): ?>
          <div class="hero-fact"><div class="hero-fact-icon"><?= e($it['icon']) ?></div><div><strong><?= e($it['title']) ?></strong><span><?= e($it['text']) ?></span></div></div>
<?php endforeach; ?>
        </div>
      </div>
    </div>

<?php ob_start(); ?>
    <section id="salle">
      <div class="wrap">
        <div class="salle-head<?= $photos ? '' : ' no-carousel' ?>">
        <div class="section-title">
          <h2><?= e(s('salle_title')) ?></h2>
          <p><?= rich(s('salle_subtitle')) ?></p>
        </div>
<?php if ($photos): $mini = array_slice($photos, 0, 8); ?>
        <div class="mini-carousel" role="region" aria-roledescription="carrousel" aria-label="Aperçu de la salle en photos">
          <div class="mc-track" tabindex="0">
<?php foreach ($mini as $i => $ph): $thumb = variant_src($ph['filename'], 'thumb'); ?>
            <button type="button" class="mc-slide" data-photo="<?= $i ?>" aria-label="Agrandir la photo <?= $i + 1 ?> sur <?= count($mini) ?><?= $ph['alt'] !== '' ? ' : ' . e($ph['alt']) : '' ?>">
              <picture><?php if ($thumb): ?><source type="image/webp" srcset="<?= e($thumb) ?>"><?php endif; ?><img src="<?= e(photo_src($ph['filename'])) ?>" alt="<?= e($ph['alt']) ?>" width="<?= (int)$ph['width'] ?>" height="<?= (int)$ph['height'] ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async"></picture>
            </button>
<?php endforeach; ?>
          </div>
          <button type="button" class="mc-nav mc-prev" aria-label="Photo précédente">‹</button>
          <button type="button" class="mc-nav mc-next" aria-label="Photo suivante">›</button>
          <div class="mc-dots" aria-hidden="true"><?php foreach ($mini as $i => $ph): ?><span<?= $i === 0 ? ' class="on"' : '' ?>></span><?php endforeach; ?></div>
          <a class="mc-all" href="#photos">Toutes les photos →</a>
        </div>
<?php endif; ?>
        </div>
        <div class="cards">
<?php foreach (items('salle_cards') as $it): ?>
          <article class="card"><div class="icon"><?= e($it['icon']) ?></div><h3><?= e($it['title']) ?></h3><p><?= rich($it['text']) ?></p></article>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php $blocks['salle'] = ob_get_clean(); ?>

<?php ob_start(); ?>
    <section class="specs" id="equipements">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('equip_title')) ?></h2>
          <p><?= rich(s('equip_subtitle')) ?></p>
        </div>
        <div class="stat-grid">
<?php foreach (items('equipements') as $it): ?>
          <div class="stat"><b><?= e($it['title']) ?></b><span><?= rich($it['text']) ?></span></div>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php $blocks['equipements'] = ob_get_clean(); ?>

<?php ob_start(); ?>
    <section id="mobilier">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('mobilier_title')) ?></h2>
          <p><?= rich(s('mobilier_subtitle')) ?></p>
        </div>
        <div class="details-grid">
<?php foreach (['mobilier' => 'mobilier_panel1', 'vaisselle' => 'mobilier_panel2'] as $grp => $titleKey): ?>
          <div class="details-panel">
            <h3><?= e(s($titleKey)) ?></h3>
            <ul class="checklist">
<?php foreach (items($grp) as $it): ?>
              <li><span class="check">✓</span><span><?= rich($it['text']) ?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php $blocks['mobilier'] = ob_get_clean(); ?>
<?php if ($v2): ?>
<?php ob_start();
// Onglets d'équipements : photos choisies par numéro + photos rangées dans l'espace correspondant
$eqTabs = [];
foreach (['salle' => ['🪑', 'salle'], 'cuisine' => ['🍽️', 'cuisine'], 'bar' => ['🍺', 'bar'], 'audio' => ['🎬', 'audiovisuel']] as $k => [$icon, $cat]) {
    $list = items('eq_' . $k);
    if (!$list) continue;
    $pics = [];
    foreach (array_filter(array_map('intval', explode(',', s('eqtab_' . $k . '_photos')))) as $n) {
        if (($ph = home_photo($n)) && !isset($pics[$ph['id']])) $pics[$ph['id']] = $ph;
    }
    foreach ($photos as $ph) {
        if (($ph['category'] ?? '') === $cat && $k !== 'salle' && !isset($pics[$ph['id']])) $pics[$ph['id']] = $ph;
    }
    $eqTabs[$k] = ['icon' => $icon, 'title' => s('eqtab_' . $k . '_title'), 'intro' => s('eqtab_' . $k . '_intro'), 'list' => $list, 'pics' => array_slice(array_values($pics), 0, 6)];
}
$photoIndex = array_flip(array_column($photos, 'id')); // pour ouvrir la visionneuse de la galerie
?>
    <section id="equipements">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('mobilier_title')) ?></h2>
          <p><?= rich(s('equip_subtitle')) ?></p>
        </div>
<?php if ($eqTabs): ?>
        <div class="eqt" data-eqt>
          <div class="eqt-tabs" role="tablist" aria-label="Équipements">
<?php $first = true; foreach ($eqTabs as $k => $tab): ?>
            <button type="button" role="tab" id="eqt-t-<?= $k ?>" aria-controls="eqt-p-<?= $k ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"<?= $first ? '' : ' tabindex="-1"' ?>><span aria-hidden="true"><?= $tab['icon'] ?></span> <?= e($tab['title']) ?></button>
<?php $first = false; endforeach; ?>
          </div>
<?php $first = true; foreach ($eqTabs as $k => $tab): ?>
          <div class="eqt-panel<?= $tab['pics'] ? '' : ' no-pic' ?>" role="tabpanel" id="eqt-p-<?= $k ?>" aria-labelledby="eqt-t-<?= $k ?>"<?= $first ? '' : ' hidden' ?>>
<?php if ($tab['pics']): ?>
            <div class="eqt-media">
              <button type="button" class="eqt-main"<?= isset($photoIndex[$tab['pics'][0]['id']]) ? ' data-photo="' . $photoIndex[$tab['pics'][0]['id']] . '"' : '' ?> aria-label="Agrandir la photo"><?= home_picture($tab['pics'][0], 'large', $first ? 'decoding="async"' : 'loading="lazy" decoding="async"') ?></button>
<?php if (count($tab['pics']) > 1): ?>
              <div class="eqt-thumbs">
<?php foreach ($tab['pics'] as $n => $ph): ?>
                <button type="button" class="eqt-thumb<?= $n === 0 ? ' on' : '' ?>" data-full="<?= e(home_photo_url($ph)) ?>" data-alt="<?= e($ph['alt']) ?>"<?= isset($photoIndex[$ph['id']]) ? ' data-photo="' . $photoIndex[$ph['id']] . '"' : '' ?> aria-label="Photo <?= $n + 1 ?>"><?= home_picture($ph, 'thumb') ?></button>
<?php endforeach; ?>
              </div>
<?php endif; ?>
            </div>
<?php endif; ?>
            <div class="eqt-text">
              <h3><span aria-hidden="true"><?= $tab['icon'] ?></span> <?= e($tab['title']) ?></h3>
<?php if ($tab['intro'] !== ''): ?>              <p class="eqt-intro"><?= rich($tab['intro']) ?></p>
<?php endif; ?>
              <ul class="checklist">
<?php foreach ($tab['list'] as $it): ?>
                <li><span class="check">✓</span><span><?= rich($it['text']) ?></span></li>
<?php endforeach; ?>
              </ul>
            </div>
          </div>
<?php $first = false; endforeach; ?>
        </div>
<?php endif; ?>
      </div>
    </section>
<?php $blocks['equipements'] = ob_get_clean(); ?>
<?php foreach ([1, 2] as $n): $bp = home_photo((int)s('band' . $n . '_photo')); if ($bp): ob_start(); ?>
    <section class="band" style="--bg:url('<?= e(home_photo_url($bp)) ?>')" aria-label="<?= e($bp['alt'] ?: s('site_name')) ?>">
      <div class="wrap"><p><?= e(s('band' . $n . '_text')) ?></p></div>
    </section>
<?php $blocks['bandeau' . $n] = ob_get_clean(); endif; endforeach; ?>
<?php $kf = items('chiffres'); if ($kf): ob_start(); ?>
    <section class="keyfigs" aria-label="Chiffres clés">
      <div class="wrap kf-grid">
<?php foreach ($kf as $it): ?>
        <div class="kf"><b><?= e($it['title']) ?></b><span><?= e($it['text']) ?></span></div>
<?php endforeach; ?>
      </div>
    </section>
<?php $blocks['chiffres'] = ob_get_clean(); endif; ?>
<?php $evs = items('evenements_cartes'); if ($evs): ob_start(); ?>
    <section id="evenements">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('events_title')) ?></h2>
          <p><?= rich(s('events_subtitle')) ?></p>
        </div>
        <div class="ev-grid">
<?php foreach ($evs as $it): $ep = home_photo((int)$it['value']); ?>
          <a class="ev-card" href="<?= e(u('reservation.php')) ?>" data-track="reserver">
<?php if ($ep): ?>            <?= home_picture($ep, 'thumb') ?>
<?php endif; ?>
            <div class="ev-body">
<?php if ($it['icon'] !== ''): ?>              <span class="ev-icon" aria-hidden="true"><?= e($it['icon']) ?></span>
<?php endif; ?>
              <h3><?= e($it['title']) ?></h3>
              <p><?= rich($it['text']) ?></p>
              <span class="ev-cta">Voir les disponibilités <span aria-hidden="true">→</span></span>
            </div>
          </a>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php $blocks['evenements'] = ob_get_clean(); endif; ?>
<?php if ($faq): ob_start(); ?>
    <section id="faq">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('faq_title')) ?></h2>
          <p><?= rich(s('faq_subtitle')) ?></p>
        </div>
        <div class="faq-list">
<?php foreach ($faq as $i => $q): ?>
          <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
            <summary class="faq-q"><?= e($q['title']) ?></summary>
            <p class="faq-a"><?= rich($q['text']) ?></p>
          </details>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php $blocks['faq'] = ob_get_clean(); endif; ?>
<?php endif; ?>

<?php ob_start(); ?>
    <section class="plan-cta-section" aria-labelledby="plan-cta-title">
      <div class="wrap">
        <div class="plan-cta-box">
          <div>
            <div class="eyebrow"><?= e(s('plan_cta_eyebrow')) ?></div>
            <h2 id="plan-cta-title"><?= e(s('plan_cta_title')) ?></h2>
            <p><?= rich(s('plan_cta_text')) ?></p>
          </div>
          <a class="btn btn-gold" href="<?= e(u('plan.php')) ?>" data-track="plan">Voir le plan de la salle →</a>
        </div>
      </div>
    </section>
<?php $blocks['plan'] = ob_get_clean(); ?>

<?php ob_start(); ?>
    <section class="pricing-section" id="tarifs">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('tarifs_title')) ?></h2>
          <p><?= rich(s('tarifs_subtitle')) ?></p>
<?php if (s('tarifs_badge') !== ''): ?>
          <div class="pricing-note"><?= e(s('tarifs_badge')) ?></div>
<?php endif; ?>
        </div>

        <div class="price-grid" aria-label="Tarifs de location 24 h par jour de début">
<?php foreach (['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'] as $i => $jour): ?>
          <div class="day-price"><strong><?= $jour ?></strong><span><?= money(ttc(bk_day_price($i + 1))) ?></span></div>
<?php endforeach; ?>
        </div>
        <p class="pricing-foot" style="margin-top:14px">Prix de la <strong>journée (24 h)</strong>, de <?= e(bk_day_slot(bk_now())[0]->format('H\hi')) ?> au lendemain <?= e(bk_day_slot(bk_now())[1]->format('H\hi')) ?>, selon le jour d’arrivée<?= bk_max_days() > 1 ? ' — possible aussi sur ' . (bk_max_days() >= 3 ? '48 h ou 72 h' : '48 h') : '' ?>. <strong>Location à l’heure</strong> de <?= e(str_replace(':', 'h', s('bk_hour_open'))) ?> à <?= e(str_replace(':', 'h', s('bk_hour_close'))) ?> : <?= money(bk_hour_price_ttc(bk_num('bk_min_hours', 2))) ?> les <?= e(fr_hours(bk_num('bk_min_hours', 2))) ?>, tarif dégressif au-delà. <a href="<?= e(u('reservation.php')) ?>" data-track="reserver">Voir les disponibilités et réserver</a>.</p>
<?php if ($v2 && ($next = home_next_free(4))): ?>
        <div class="next-dates">
          <h3><?= e(s('dates_title')) ?></h3>
          <div class="nd-list">
<?php foreach ($next as $nd): ?>
            <a class="nd" href="<?= e(u('reservation.php') . '?date=' . $nd['date']->format('Y-m-d')) ?>" data-track="date"><strong><?= e(ucfirst(fr_date_long($nd['date']))) ?></strong><small><?= $nd['special'] ? '★ ' . e($nd['special']) . ' · ' : '' ?><?= money($nd['price']) ?> la journée</small></a>
<?php endforeach; ?>
          </div>
          <a class="nd-all" href="<?= e(u('reservation.php')) ?>" data-track="reserver">Tout le calendrier →</a>
        </div>
<?php endif; ?>
<?php $specials = specials_upcoming(4); if ($specials): ?>
        <p class="pricing-foot" style="margin-top:6px">★ <strong>Tarifs spéciaux</strong> : <?= implode(' · ', array_map(fn($sp) => e($sp['label']) . ' (' . e(bk_dt($sp['date_from'])->format('d/m')) . ($sp['date_to'] !== $sp['date_from'] ? '–' . e(bk_dt($sp['date_to'])->format('d/m')) : '') . ') ' . e(special_label($sp)), $specials)) ?>.</p>
<?php endif; ?>

<?php $infos = items('tarif_infos'); if ($infos): ?>
        <div class="pricing-info" aria-label="Informations tarifaires complémentaires">
<?php foreach ($infos as $it): ?>
          <div class="pricing-info-item"><strong><?= e($it['title']) ?></strong> <?= rich($it['text']) ?></div>
<?php endforeach; ?>
        </div>
<?php endif; ?>

<?php $bookOpts = bk_options(); $options = items('tarif_options'); if ($bookOpts || $options): ?>
        <div class="pricing-panels">
          <div class="pricing-card">
            <h3><?= e(s('tarifs_options_title')) ?></h3>
            <ul class="price-list">
<?php foreach ($bookOpts as $o): ?>
              <li><span><?= e($o['label']) ?></span><strong><?= money(ttc((float)$o['price'])) ?><?= $o['unit'] === 'personne' ? ' / personne' : '' ?></strong></li>
<?php endforeach; ?>
<?php foreach ($options as $it): ?>
              <li><span><?= rich($it['text']) ?></span><strong><?= e($it['value']) ?></strong></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>

        <div class="pricing-actions">
          <a class="btn btn-gold" href="<?= e(u('reservation.php')) ?>" data-track="reserver">📅 Réserver en ligne →</a>
          <a class="btn btn-gold" href="#contact">Prendre rendez-vous pour visiter la salle →</a>
          <a class="btn btn-primary" href="<?= e($mailto) ?>" data-track="email">✉ Demander une réservation</a>
        </div>

        <p class="pricing-foot"><?= rich(s('tarifs_foot')) ?> <a href="<?= e($mailto) ?>" data-track="email"><strong><?= e(s('contact_email')) ?></strong></a>.</p>
      </div>
    </section>
<?php $blocks['tarifs'] = ob_get_clean(); ?>

<?php ob_start(); ?>
    <section id="acces">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('acces_title')) ?></h2>
          <p><?= rich(s('acces_subtitle')) ?></p>
        </div>
        <div class="<?= $v2 ? 'acces-grid' : 'details-grid' ?>">
          <div class="details-panel">
            <h3><?= e(s('acces_panel1')) ?></h3>
            <ul class="checklist">
<?php foreach (items('acces') as $it): ?>
              <li><span class="check">✓</span><span><?= rich($it['text']) ?></span></li>
<?php endforeach; ?>
            </ul>
<?php if (s('maps_url') !== ''): ?>
            <div style="margin-top:20px"><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= e(s('maps_url')) ?>" data-track="maps">Itinéraire dans Google Maps ↗</a></div>
<?php endif; ?>
          </div>
<?php if (!$v2): ?>
          <div class="details-panel">
            <h3><?= e(s('acces_panel2')) ?></h3>
            <ul class="checklist">
<?php foreach (items('evenements') as $it): ?>
              <li><span class="check">✓</span><span><?= rich($it['text']) ?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>
<?php if ($mapSrc): ?>
        <div class="map-box" id="map">
          <iframe src="<?= e($mapSrc) ?>" title="Carte Google Maps : <?= e(s('map_query')) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
<?php endif; ?>
<?php if ($v2): ?>
        </div>
<?php endif; ?>
      </div>
    </section>
<?php $blocks['acces'] = ob_get_clean(); ?>

<?php ob_start(); ?>
    <section class="photos-section" id="photos">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('photos_title')) ?></h2>
          <p><?= rich(s('photos_subtitle')) ?></p>
        </div>
<?php $cats = $v2 ? array_values(array_unique(array_filter(array_column($photos, 'category'), fn($c) => isset(PHOTO_CATEGORIES[$c])))) : []; ?>
<?php if (count($cats) > 1): ?>
        <div class="g-tabs" role="group" aria-label="Filtrer les photos">
          <button type="button" class="g-tab" data-cat="" aria-pressed="true">Toutes</button>
<?php foreach (array_keys(PHOTO_CATEGORIES) as $c): if (in_array($c, $cats, true)): ?>
          <button type="button" class="g-tab" data-cat="<?= e($c) ?>" aria-pressed="false"><?= e(PHOTO_CATEGORIES[$c]) ?></button>
<?php endif; endforeach; ?>
        </div>
<?php endif; ?>
        <div class="gallery" id="gallery" aria-label="Galerie photos de <?= e($name) ?>">
<?php foreach ($photos as $i => $ph): ?>
<?php $thumb = variant_src($ph['filename'], $v2 && $i === 0 ? 'large' : 'thumb'); $large = variant_src($ph['filename'], 'large'); ?>
          <figure class="gallery-item<?= $v2 && $i >= 5 ? ' more' : '' ?>" data-cat="<?= e($ph['category'] ?? '') ?>" data-track="photo"<?= $large ? ' data-full="' . e($large) . '"' : '' ?>>
            <picture>
<?php if ($thumb): ?>
              <source type="image/webp" data-srcset="<?= e($thumb) ?>">
<?php endif; ?>
              <img alt="<?= e($ph['alt']) ?>" data-src="<?= e(photo_src($ph['filename'])) ?>" width="<?= (int)$ph['width'] ?>" height="<?= (int)$ph['height'] ?>" loading="lazy" decoding="async" fetchpriority="low">
            </picture>
            <div class="gallery-empty"><div><span>📷</span>Photo <?= $i + 1 ?></div></div>
          </figure>
<?php endforeach; ?>
        </div>
<?php if ($v2 && count($photos) > 5): ?>
        <div class="g-more"><button type="button" class="btn btn-gold" id="g-more">Voir les <?= count($photos) ?> photos</button></div>
<?php endif; ?>
      </div>
    </section>
<?php $blocks['photos'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php if ($showReviews): ?>
    <section class="reviews-section" id="avis">
      <div class="wrap">
<?php if ($v2): ?><div class="reviews-head"><?php endif; ?>
        <div class="section-title">
          <h2><?= e(s('reviews_title')) ?></h2>
          <p><?= rich(s('reviews_subtitle')) ?></p>
        </div>
        <div class="review-badges">
<?php if (s('google_rating') !== ''): $g = (float)str_replace(',', '.', s('google_rating')); $tag = s('google_reviews_url') !== '' ? 'a' : 'div'; ?>
          <<?= $tag ?> class="review-badge"<?= $tag === 'a' ? ' href="' . e(s('google_reviews_url')) . '" target="_blank" rel="noopener" data-track="avis"' : '' ?>>
            <span class="src-logo" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg></span>
            <span><span class="score"><?= e(rating_label(s('google_rating'))) ?></span> <span class="star-meter" style="--pct:<?= round(min(5, $g) * 20) ?>%" aria-label="<?= e(rating_label(s('google_rating'))) ?> sur 5">★★★★★</span>
              <small><?= s('google_count') !== '' ? (int)s('google_count') . ' avis ' : 'Avis ' ?>Google<?= $tag === 'a' ? ' · Voir les avis ↗' : '' ?></small></span>
          </<?= $tag ?>>
<?php endif; ?>
<?php if (s('salles1001_rating') !== ''): $r1 = (float)str_replace(',', '.', s('salles1001_rating')); $tag = s('salles1001_url') !== '' ? 'a' : 'div'; ?>
          <<?= $tag ?> class="review-badge"<?= $tag === 'a' ? ' href="' . e(s('salles1001_url')) . '" target="_blank" rel="noopener" data-track="avis"' : '' ?>>
            <span class="src-logo s1001" aria-hidden="true">1001</span>
            <span><span class="score"><?= e(rating_label(s('salles1001_rating'))) ?></span> <span class="star-meter" style="--pct:<?= round(min(5, $r1) * 20) ?>%" aria-label="<?= e(rating_label(s('salles1001_rating'))) ?> sur 5">★★★★★</span>
              <small><?= s('salles1001_count') !== '' ? (int)s('salles1001_count') . ' avis ' : 'Avis ' ?>1001Salles<?= $tag === 'a' ? ' · Voir les avis ↗' : '' ?></small></span>
          </<?= $tag ?>>
<?php endif; ?>
        </div>
<?php if ($v2): ?></div><?php endif; ?>
<?php if ($reviews): ?>
        <div class="reviews" id="reviews-list">
<?php foreach ($reviews as $i => $rv): ?>
          <article class="review<?= $i >= $reviewsLimit ? ' is-more' : '' ?>">
            <div class="review-top">
              <?= stars((int)$rv['rating']) ?>
              <span class="review-src"><?= e(['google' => 'Avis Google', '1001' => 'Avis 1001Salles'][$rv['source']] ?? 'Avis client') ?></span>
            </div>
            <blockquote><?= nl2br(e($rv['text']), false) ?></blockquote>
<?php if (mb_strlen($rv['text']) > 330): ?>
            <button class="more" type="button">Lire la suite</button>
<?php endif; ?>
            <div class="review-author">
              <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($rv['author'], 0, 1))) ?></span>
              <div><strong><?= e($rv['author']) ?></strong><span><?= e(implode(' · ', array_filter([$rv['event'], $rv['review_date']]))) ?></span></div>
            </div>
          </article>
<?php endforeach; ?>
        </div>
<?php if ($v2 && count($reviews) > 1): ?>
        <div class="rv-nav"><button type="button" data-dir="-1" aria-label="Avis précédents">‹</button><button type="button" data-dir="1" aria-label="Avis suivants">›</button></div>
<?php endif; ?>
<?php if (count($reviews) > $reviewsLimit): ?>
        <div class="reviews-more"><button class="btn btn-gold" type="button" id="reviews-more"><?= count($reviews) - $reviewsLimit === 1 ? 'Voir l’autre avis' : 'Voir les ' . (count($reviews) - $reviewsLimit) . ' autres avis' ?></button></div>
<?php endif; ?>
<?php endif; ?>
      </div>
    </section>
<?php endif; ?>
<?php $blocks['avis'] = ob_get_clean(); ?>

    <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Galerie photo agrandie">
      <button class="lightbox-close" type="button" aria-label="Fermer">×</button>
      <button class="lightbox-prev" type="button" aria-label="Photo précédente">‹</button>
      <img class="lightbox-img" src="" alt="">
      <button class="lightbox-next" type="button" aria-label="Photo suivante">›</button>
      <div class="lightbox-counter"></div>
    </div>

<?php ob_start(); ?>
    <section class="contact" id="contact">
      <div class="wrap">
        <div class="contact-box">
          <div>
            <div class="eyebrow" style="color:var(--gold-2)"><?= e(s('contact_eyebrow')) ?></div>
            <h2><?= e(s('contact_title')) ?></h2>
            <p><?= rich(s('contact_text')) ?></p>
          </div>
          <div class="email-card">
            <small>Contact location</small>
            <a href="<?= e($mailto) ?>" data-track="email"><?= e(s('contact_email')) ?></a>
            <button type="button" id="copyMail" data-track="copy">Copier l’adresse e-mail</button>
          </div>
        </div>
      </div>
    </section>
<?php $blocks['contact'] = ob_get_clean(); ?>
<?php if ($v2): ob_start(); $tel = trim(s('contact_phone')); $cst = (string)($_GET['contact'] ?? ''); ?>
    <section class="contact" id="contact">
      <div class="wrap">
        <div class="contact-box">
          <div>
            <div class="eyebrow" style="color:var(--gold-2)"><?= e(s('contact_eyebrow')) ?></div>
            <h2><?= e(s('contact_title')) ?></h2>
            <p><?= rich(s('contact_text')) ?></p>
            <div class="c-ways">
<?php if ($tel !== ''): ?>
              <a class="c-way" href="tel:<?= e(home_phone_digits($tel)) ?>" data-track="tel"><i aria-hidden="true">📞</i><span><?= e($tel) ?><small>Appelez-nous</small></span></a>
<?php if (s('contact_whatsapp') === '1'): ?>
              <a class="c-way wa" href="https://wa.me/<?= e(home_phone_digits($tel, true)) ?>" target="_blank" rel="noopener" data-track="whatsapp"><i aria-hidden="true">✆</i><span>WhatsApp<small>Écrivez-nous en direct</small></span></a>
<?php endif; ?>
<?php endif; ?>
              <a class="c-way" href="<?= e($mailto) ?>" data-track="email"><i aria-hidden="true">✉</i><span><?= e(s('contact_email')) ?><small>Par e-mail</small></span></a>
            </div>
          </div>
          <form class="c-form" id="c-form" method="post" action="contact.php">
            <h3>Écrivez-nous</h3>
            <p class="c-intro"><?= e(s('contact_form_intro')) ?></p>
<?php if ($cst === 'ok'): ?>
            <p class="c-msg ok" role="status">Merci, votre message est bien parti : nous vous répondons rapidement.</p>
<?php elseif ($cst === 'err'): ?>
            <p class="c-msg err" role="alert">Le message n’a pas pu partir : vérifiez votre nom, votre e-mail et votre message, ou écrivez-nous à <?= e(s('contact_email')) ?>.</p>
<?php endif; ?>
            <label>Nom<input name="name" autocomplete="name" required maxlength="80"></label>
            <label>E-mail<input name="email" type="email" autocomplete="email" required maxlength="160"></label>
            <label>Téléphone <span style="font-weight:400;color:var(--muted)">(facultatif)</span><input name="phone" type="tel" autocomplete="tel" maxlength="30"></label>
            <label>Date souhaitée<input name="date" maxlength="40" placeholder="ex. samedi 14 juin"></label>
            <label class="full">Votre message<textarea name="message" required maxlength="3000" placeholder="Type d’événement, nombre d’invités, questions…"></textarea></label>
            <div class="hp" aria-hidden="true"><label>Site web<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <input type="hidden" name="t" value="<?= time() ?>">
            <button class="btn btn-primary" type="submit">✉ Envoyer le message</button>
          </form>
        </div>
      </div>
    </section>
<?php $blocks['contact'] = ob_get_clean(); endif; ?>
<?php foreach ($sections as $key => $visible) { if ($visible && isset($blocks[$key])) { echo $blocks[$key]; } } ?>
  </main>
<?php if ($v2): ?>
  <nav class="mbar" aria-label="Réserver">
    <a class="m-book" href="<?= e(u('reservation.php')) ?>" data-track="reserver">📅 Réserver en ligne</a>
    <a class="m-alt" href="<?= trim(s('contact_phone')) !== '' ? 'tel:' . e(home_phone_digits(s('contact_phone'))) : '#contact' ?>" data-track="<?= trim(s('contact_phone')) !== '' ? 'tel' : 'email' ?>" aria-label="<?= trim(s('contact_phone')) !== '' ? 'Appeler' : 'Nous écrire' ?>"><?= trim(s('contact_phone')) !== '' ? '📞' : '✉' ?></a>
  </nav>
<?php endif; ?>
<?php if ($preview): ?>
  <div class="preview-pill">👁 Aperçu de la nouvelle page d’accueil — les visiteurs voient encore l’ancienne. <a href="./">Voir la version actuelle</a></div>
<?php endif; ?>

  <footer>
    <div class="wrap foot">
      <span>© <?= date('Y') ?> <?= e(s('footer_text')) ?><br><small><a href="<?= e(u('mentions-legales.php')) ?>" style="color:inherit">Mentions légales</a> · <a href="<?= e(u('confidentialite.php')) ?>" style="color:inherit">Confidentialité</a> · <a href="<?= e(u('conditions.php')) ?>" style="color:inherit">Conditions de location</a> · Site web réalisé par <a href="https://www.linkedin.com/in/fendyb/" target="_blank" rel="noopener" style="color:inherit">FBA</a></small></span>
      <span><?= e(s('footer_tagline')) ?><br><small><?php if (s('salles1001_url') !== ''): ?><a href="<?= e(s('salles1001_url')) ?>" target="_blank" rel="noopener" data-track="1001" style="color:inherit;text-decoration:none">1001Salles</a> · <?php endif; ?><?php if (s('facebook_url') !== ''): ?><a href="<?= e(s('facebook_url')) ?>" target="_blank" rel="noopener" data-track="facebook" style="display:inline-flex;align-items:center;gap:7px;margin-left:6px;padding:7px 11px;border-radius:9px;background:#1464d8;color:#fff;text-decoration:none;font-weight:800;box-shadow:0 3px 10px rgba(0,0,0,.15)"><span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fff;color:#1877F2;font-family:Arial,sans-serif;font-size:18px;font-weight:900;line-height:1">f</span> Facebook</a><?php endif; ?></small></span>
    </div>
  </footer>

  <script>
    const CONTACT_EMAIL = <?= json_encode(s('contact_email'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const MAILTO = <?= json_encode($mailto, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const btn = document.getElementById('copyMail');
    if (btn) btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(CONTACT_EMAIL);
        const old = btn.textContent;
        btn.textContent = 'Adresse copiée ✓';
        setTimeout(() => btn.textContent = old, 1800);
      } catch (e) {
        window.location.href = MAILTO;
      }
    });

    // Galerie optimisée : les photos ne sont chargées que lorsqu'elles approchent de l'écran.
    const galleryItems = [...document.querySelectorAll('.gallery-item')];
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = lightbox.querySelector('.lightbox-img');
    const counter = lightbox.querySelector('.lightbox-counter');
    let currentPhoto = 0;

    function loadGalleryImage(img){
      if (!img || img.dataset.loaded === '1' || !img.dataset.src) return;
      img.dataset.loaded = '1';
      const source = img.parentNode.querySelector('source[data-srcset]');
      if (source) source.srcset = source.dataset.srcset; // vignette WebP légère
      img.src = img.dataset.src;                          // repli JPEG (anciens navigateurs)
    }

    // Grand format : WebP 1920 px si le navigateur le gère, sinon la photo d'origine
    function fullSrc(item){
      const img = item.querySelector('img');
      const webpOk = (img.currentSrc || '').includes('.webp') || window.__webp === true;
      return (webpOk && item.dataset.full) ? item.dataset.full : (img.dataset.src || img.src);
    }
    (function(){ const t = new Image(); t.onload = () => { window.__webp = t.width > 0; };
      t.src = 'data:image/webp;base64,UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA=='; })();
    function preload(index){
      const item = galleryItems[(index + galleryItems.length) % galleryItems.length];
      if (item) { const i = new Image(); i.src = fullSrc(item); }
    }

    galleryItems.forEach((item, index) => {
      const img = item.querySelector('img');
      const placeholder = item.querySelector('.gallery-empty');
      img.addEventListener('load', () => {
        img.classList.add('is-loaded');
        if (placeholder) placeholder.style.display = 'none';
      }, {once:true});
      img.addEventListener('error', () => { img.style.display = 'none'; }, {once:true});
      item.addEventListener('click', () => openPhoto(index));
    });

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          loadGalleryImage(entry.target.querySelector('img[data-src]'));
          observer.unobserve(entry.target);
        });
      }, {rootMargin:'350px 0px', threshold:0.01});
      galleryItems.forEach(item => observer.observe(item));
    } else {
      galleryItems.forEach(item => loadGalleryImage(item.querySelector('img[data-src]')));
    }

    function openPhoto(index){
      if (!galleryItems.length) return;
      currentPhoto = (index + galleryItems.length) % galleryItems.length;
      const img = galleryItems[currentPhoto].querySelector('img');
      loadGalleryImage(img);
      const src = fullSrc(galleryItems[currentPhoto]);
      if (!src) return;
      lightboxImg.src = src;
      preload(currentPhoto + 1); preload(currentPhoto - 1);
      lightboxImg.alt = img.alt || 'Photo de La Salle RN6';
      counter.textContent = `${currentPhoto + 1} / ${galleryItems.length}`;
      lightbox.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    window.rn6OpenPhoto = openPhoto;

    function closeLightbox(){
      lightbox.classList.remove('open');
      lightboxImg.removeAttribute('src');
      document.body.style.overflow = '';
    }
    lightbox.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
    lightbox.querySelector('.lightbox-prev').addEventListener('click', () => openPhoto(currentPhoto - 1));
    lightbox.querySelector('.lightbox-next').addEventListener('click', () => openPhoto(currentPhoto + 1));
    lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLightbox(); });
    document.addEventListener('keydown', e => {
      if (!lightbox.classList.contains('open')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') openPhoto(currentPhoto - 1);
      if (e.key === 'ArrowRight') openPhoto(currentPhoto + 1);
    });
    // Balayage sur mobile dans la visionneuse
    let touchX = null;
    lightbox.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, {passive:true});
    lightbox.addEventListener('touchend', e => {
      if (touchX === null) return;
      const dx = e.changedTouches[0].clientX - touchX; touchX = null;
      if (Math.abs(dx) > 50) openPhoto(currentPhoto + (dx < 0 ? 1 : -1));
    });

    // ===== Menu mobile =====
    const menuBtn = document.querySelector('.menu-toggle');
    const menu = document.getElementById('mobile-menu');
    function setMenu(open){
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
      menu.hidden = !open;
    }
    menuBtn.addEventListener('click', () => setMenu(menu.hidden));
    menu.addEventListener('click', e => { if (e.target.closest('a')) setMenu(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !menu.hidden) { setMenu(false); menuBtn.focus(); } });
    document.addEventListener('click', e => { if (!menu.hidden && !e.target.closest('header')) setMenu(false); });
    window.addEventListener('resize', () => { if (window.innerWidth > 900 && !menu.hidden) setMenu(false); });

    // ===== Avis : « Lire la suite » et « Voir plus » =====
    document.querySelectorAll('.review .more').forEach(b => b.addEventListener('click', () => {
      const r = b.closest('.review'); r.classList.toggle('open');
      b.textContent = r.classList.contains('open') ? 'Réduire' : 'Lire la suite';
    }));
    const moreBtn = document.getElementById('reviews-more');
    if (moreBtn) moreBtn.addEventListener('click', () => {
      document.getElementById('reviews-list').classList.add('show-all');
      moreBtn.parentNode.remove();
    });

    // ===== Animations : apparition au défilement + chiffres qui comptent =====
    (function(){
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce || !('IntersectionObserver' in window)) return;
      document.documentElement.classList.add('js-anim');

      const targets = document.querySelectorAll('main .section-title, .hero-fact, .card, .stat, .details-panel, .plan-cta-box, .day-price, .pricing-info-item, .pricing-card, .gallery-item, .review-badge, .review, .map-box, .contact-box, .ev-card, .kf, .faq-item, .next-dates, .eq-photo, .band p');
      const groups = new Map();
      targets.forEach(el => {
        el.classList.add('reveal');
        const n = groups.get(el.parentNode) || 0;      // léger décalage entre éléments voisins
        el.style.setProperty('--d', Math.min(n, 6) * 70 + 'ms');
        groups.set(el.parentNode, n + 1);
      });

      // Chiffres clés : « 120 personnes », « 125 m² », « 11,77 m² »…
      const counters = [];
      document.querySelectorAll('.hero-fact strong, .card p strong, .stat b, .kf b').forEach(el => {
        if (el.children.length) return;
        const m = el.textContent.match(/\d+(?:[,.]\d+)?/);
        if (!m) return;
        const decimals = (m[0].split(/[,.]/)[1] || '').length;
        const value = parseFloat(m[0].replace(',', '.'));
        if (value < 10 && !decimals) return;          // « 5 min » reste tel quel
        counters.push(el);
        el.classList.add('count');
        el._count = { before: el.textContent.slice(0, m.index), after: el.textContent.slice(m.index + m[0].length), value, decimals, sep: m[0].includes(',') ? ',' : '.' };
        el.textContent = el._count.before + (0).toFixed(decimals).replace('.', el._count.sep) + el._count.after;
      });
      function runCount(el){
        const c = el._count; if (!c || c.done) return; c.done = true;
        const t0 = performance.now(), dur = 1300;
        (function step(now){
          const p = Math.min(1, (now - t0) / dur), eased = 1 - Math.pow(1 - p, 3);
          el.textContent = c.before + (c.value * eased).toFixed(c.decimals).replace('.', c.sep) + c.after;
          if (p < 1) requestAnimationFrame(step);
        })(t0);
      }

      const io = new IntersectionObserver(entries => {
        entries.forEach(en => {
          if (!en.isIntersecting) return;
          en.target.classList.add('in');
          en.target.querySelectorAll('.count').forEach(runCount);
          if (en.target._count) runCount(en.target);
          io.unobserve(en.target);
        });
      }, {rootMargin:'0px 0px -8% 0px', threshold:0.08});
      targets.forEach(el => io.observe(el));
      counters.forEach(el => { if (!el.closest('.reveal')) io.observe(el); });
    })();

    // Petit carrousel « La salle » : défilement automatique (si visible, sans survol), glisser au doigt, clic = grand format
    (function(){
      const box = document.querySelector('.mini-carousel');
      if (!box) return;
      const track = box.querySelector('.mc-track');
      const slides = [...box.querySelectorAll('.mc-slide')];
      const dots = [...box.querySelectorAll('.mc-dots span')];
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      let paused = false, visible = false, timer = null;
      const current = () => Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
      const go = i => { const n = (i + slides.length) % slides.length; track.scrollTo({ left: n * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' }); };
      track.addEventListener('scroll', () => { const c = current(); dots.forEach((d, k) => d.classList.toggle('on', k === c)); }, { passive: true });
      box.querySelector('.mc-prev').addEventListener('click', () => go(current() - 1));
      box.querySelector('.mc-next').addEventListener('click', () => go(current() + 1));
      track.addEventListener('keydown', e => { if (e.key === 'ArrowRight') { e.preventDefault(); go(current() + 1); } if (e.key === 'ArrowLeft') { e.preventDefault(); go(current() - 1); } });
      slides.forEach(s => s.addEventListener('click', () => {
        if (typeof window.rn6OpenPhoto === 'function' && document.querySelector('.gallery-item')) window.rn6OpenPhoto(+s.dataset.photo);
        else location.hash = 'photos';
      }));
      ['mouseenter', 'focusin', 'touchstart'].forEach(ev => box.addEventListener(ev, () => { paused = true; }, { passive: true }));
      ['mouseleave', 'focusout'].forEach(ev => box.addEventListener(ev, () => { paused = false; }));
      if (!reduce && slides.length > 1) {
        if ('IntersectionObserver' in window) new IntersectionObserver(en => { visible = en[0].isIntersecting; }, { threshold: .4 }).observe(box); else visible = true;
        timer = setInterval(() => { if (visible && !paused && !document.hidden) go(current() + 1); }, 4500);
      }
    })();
<?php if ($v2): ?>
    // ===== Nouvelle page d'accueil =====
    (function(){
      // Galerie : « Voir toutes les photos » et onglets par espace
      const gal = document.getElementById('gallery');
      const more = document.getElementById('g-more');
      if (gal && more) more.addEventListener('click', () => { gal.classList.add('show-all'); more.parentNode.remove(); });
      document.querySelectorAll('.g-tab').forEach(b => b.addEventListener('click', () => {
        const c = b.dataset.cat;
        document.querySelectorAll('.g-tab').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
        gal.classList.toggle('filtered', c !== '');
        gal.querySelectorAll('.gallery-item').forEach(it => it.classList.toggle('out', c !== '' && it.dataset.cat !== c));
        if (c !== '' && more && more.isConnected) more.parentNode.hidden = true; else if (more && more.isConnected) more.parentNode.hidden = false;
      }));
      // Équipements : onglets (clavier compris), vignettes, agrandissement
      document.querySelectorAll('[data-eqt]').forEach(box => {
        const tabs = [...box.querySelectorAll('[role=tab]')];
        const show = tb => {
          tabs.forEach(x => { const on = x === tb; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1; document.getElementById(x.getAttribute('aria-controls')).hidden = !on; });
          tb.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        };
        tabs.forEach((tb, i) => {
          tb.addEventListener('click', () => show(tb));
          tb.addEventListener('keydown', e => { const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (d) { e.preventDefault(); const n = tabs[(i + d + tabs.length) % tabs.length]; show(n); n.focus(); } });
        });
        box.querySelectorAll('.eqt-thumb').forEach(th => th.addEventListener('click', () => {
          const media = th.closest('.eqt-media'), main = media.querySelector('.eqt-main'), img = main.querySelector('img');
          media.querySelectorAll('.eqt-thumb').forEach(x => x.classList.toggle('on', x === th));
          main.querySelectorAll('source').forEach(s => s.remove());
          img.src = th.dataset.full; img.alt = th.dataset.alt || '';
          if (th.dataset.photo) main.dataset.photo = th.dataset.photo; else delete main.dataset.photo;
        }));
        box.querySelectorAll('.eqt-main').forEach(m => m.addEventListener('click', () => {
          if (m.dataset.photo && typeof window.rn6OpenPhoto === 'function' && document.querySelector('.gallery-item')) window.rn6OpenPhoto(+m.dataset.photo);
        }));
      });
      // Avis : flèches
      const list = document.getElementById('reviews-list');
      document.querySelectorAll('.rv-nav button').forEach(b => b.addEventListener('click', () => {
        if (list) list.scrollBy({ left: +b.dataset.dir * Math.min(list.clientWidth, 400), behavior: 'smooth' });
      }));
      // Barre « Réserver » sur mobile : après le bandeau d'accueil, cachée sur le formulaire de contact
      const bar = document.querySelector('.mbar');
      if (bar) {
        const contact = document.getElementById('contact');
        const upd = () => {
          const past = window.scrollY > 520;
          const r = contact ? contact.getBoundingClientRect() : null;
          const onContact = r && r.top < window.innerHeight && r.bottom > 0;
          bar.classList.toggle('on', past && !onContact);
        };
        window.addEventListener('scroll', upd, { passive: true }); upd();
      }
      // Formulaire de contact : envoi sans quitter la page
      const f = document.getElementById('c-form');
      if (f && window.fetch && window.FormData) f.addEventListener('submit', async e => {
        e.preventDefault();
        const b = f.querySelector('button[type=submit]'), label = b.innerHTML;
        f.querySelectorAll('.c-msg').forEach(m => m.remove());
        const say = (cls, txt) => { const p = document.createElement('p'); p.className = 'c-msg ' + cls; p.setAttribute('role', cls === 'ok' ? 'status' : 'alert'); p.textContent = txt; b.before(p); };
        b.disabled = true; b.textContent = 'Envoi…';
        try {
          const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } });
          const j = await r.json();
          if (j.ok) {
            say('ok', 'Merci, votre message est bien parti : nous vous répondons rapidement.');
            f.reset();
            if (navigator.sendBeacon) { const d = new FormData(); d.append('k', 'formulaire'); d.append('p', 'accueil'); navigator.sendBeacon('track.php', d); }
          } else say('err', j.error || 'Envoi impossible.');
        } catch (err) { say('err', 'Connexion impossible : réessayez, ou écrivez-nous par e-mail.'); }
        b.disabled = false; b.innerHTML = label;
      });
    })();
<?php endif; ?>
  </script>
  <?= tracking_script('accueil') ?>
</body>
</html>
