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
$sections = home_sections();
// Liens du menu dans le même ordre que les sections (le plan reste une page à part, toujours accessible)
$navItems = [];
foreach ($sections as $k => $visible) {
    if (($visible || $k === 'plan') && ($k !== 'avis' || $showReviews)) {
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
  <meta name="robots" content="index, follow, max-image-preview:large">
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
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
  <style>
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
      font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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
    .brand{display:flex;align-items:center;gap:12px;text-decoration:none;font-weight:800;color:white}
    .brand-mark{width:48px;height:48px;border:2px solid #273CF5;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:.9rem;background:#fff;box-shadow:0 5px 18px rgba(0,0,0,.16);font-weight:900;letter-spacing:-.04em}.brand-mark .rn{color:#273CF5}.brand-mark .six{color:#B50E0E}
    .brand span small{display:block;font-size:.68rem;letter-spacing:.16em;color:var(--gold-2);text-transform:uppercase;font-weight:800}

    .facebook-top{display:inline-flex;align-items:center;gap:7px;background:#1877F2;color:#fff!important;padding:9px 12px;border-radius:10px;text-decoration:none!important;font-weight:900!important;box-shadow:0 6px 18px rgba(24,119,242,.3)}
    .facebook-top .fb-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fff;color:#1877F2;font-family:Arial,sans-serif;font-size:18px;font-weight:900;line-height:1}
    .facebook-top:hover{background:#1265d4!important;color:#fff!important;transform:translateY(-1px)}
    .navlinks{display:flex;align-items:center;gap:20px}
    .navlinks a{text-decoration:none;font-weight:800;font-size:.94rem;color:#fff;position:relative;transition:.2s ease}
    .navlinks a:hover{color:#fff2bd;text-shadow:0 0 14px rgba(242,212,123,.35)}
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
    .gallery-item{position:relative;aspect-ratio:4/3;cursor:pointer;border-radius:18px;overflow:hidden;background:linear-gradient(135deg,#f7f7f7,#edf0f3);border:1px solid var(--line);box-shadow:0 5px 18px rgba(7,28,53,.04)}
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
  </style>
</head>
<body>
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
        <div class="section-title">
          <h2><?= e(s('salle_title')) ?></h2>
          <p><?= rich(s('salle_subtitle')) ?></p>
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
        <p class="pricing-foot" style="margin-top:14px">Prix de la <strong>journée (24 h)</strong>, de <?= e(str_replace(':', 'h', s('bk_day_start'))) ?> à <?= e(str_replace(':', 'h', s('bk_day_start'))) ?>, selon le jour d’arrivée<?= bk_max_days() > 1 ? ' — possible aussi sur ' . (bk_max_days() >= 3 ? '48 h ou 72 h' : '48 h') : '' ?>. <strong>Location à l’heure</strong> de <?= e(str_replace(':', 'h', s('bk_hour_open'))) ?> à <?= e(str_replace(':', 'h', s('bk_hour_close'))) ?> : <?= money(bk_hour_price_ttc(bk_num('bk_min_hours', 2))) ?> les <?= e(fr_hours(bk_num('bk_min_hours', 2))) ?>, tarif dégressif au-delà. <a href="<?= e(u('reservation.php')) ?>" data-track="reserver">Voir les disponibilités et réserver</a>.</p>
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
        <div class="details-grid">
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
          <div class="details-panel">
            <h3><?= e(s('acces_panel2')) ?></h3>
            <ul class="checklist">
<?php foreach (items('evenements') as $it): ?>
              <li><span class="check">✓</span><span><?= rich($it['text']) ?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php if ($mapSrc): ?>
        <div class="map-box" id="map">
          <iframe src="<?= e($mapSrc) ?>" title="Carte Google Maps : <?= e(s('map_query')) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
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
        <div class="gallery" aria-label="Galerie photos de <?= e($name) ?>">
<?php foreach ($photos as $i => $ph): ?>
<?php $thumb = variant_src($ph['filename'], 'thumb'); $large = variant_src($ph['filename'], 'large'); ?>
          <figure class="gallery-item" data-track="photo"<?= $large ? ' data-full="' . e($large) . '"' : '' ?>>
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
      </div>
    </section>
<?php $blocks['photos'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php if ($showReviews): ?>
    <section class="reviews-section" id="avis">
      <div class="wrap">
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
<?php foreach ($sections as $key => $visible) { if ($visible && isset($blocks[$key])) { echo $blocks[$key]; } } ?>
  </main>

  <footer>
    <div class="wrap foot">
      <span>© <?= date('Y') ?> <?= e(s('footer_text')) ?><br><small><a href="<?= e(u('mentions-legales.php')) ?>" style="color:inherit">Mentions légales</a> · <a href="<?= e(u('confidentialite.php')) ?>" style="color:inherit">Confidentialité</a> · <a href="<?= e(u('conditions.php')) ?>" style="color:inherit">Conditions de location</a></small></span>
      <span><?= e(s('footer_tagline')) ?><br><small><?php if (s('salles1001_url') !== ''): ?><a href="<?= e(s('salles1001_url')) ?>" target="_blank" rel="noopener" data-track="1001" style="color:inherit;text-decoration:none">1001Salles</a> · <?php endif; ?><?php if (s('facebook_url') !== ''): ?><a href="<?= e(s('facebook_url')) ?>" target="_blank" rel="noopener" data-track="facebook" style="display:inline-flex;align-items:center;gap:7px;margin-left:6px;padding:7px 11px;border-radius:9px;background:#1464d8;color:#fff;text-decoration:none;font-weight:800;box-shadow:0 3px 10px rgba(0,0,0,.15)"><span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fff;color:#1877F2;font-family:Arial,sans-serif;font-size:18px;font-weight:900;line-height:1">f</span> Facebook</a><?php endif; ?></small></span>
    </div>
  </footer>

  <script>
    const CONTACT_EMAIL = <?= json_encode(s('contact_email'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const MAILTO = <?= json_encode($mailto, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const btn = document.getElementById('copyMail');
    btn.addEventListener('click', async () => {
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

      const targets = document.querySelectorAll('main .section-title, .hero-fact, .card, .stat, .details-panel, .plan-cta-box, .day-price, .pricing-info-item, .pricing-card, .gallery-item, .review-badge, .review, .map-box, .contact-box');
      const groups = new Map();
      targets.forEach(el => {
        el.classList.add('reveal');
        const n = groups.get(el.parentNode) || 0;      // léger décalage entre éléments voisins
        el.style.setProperty('--d', Math.min(n, 6) * 70 + 'ms');
        groups.set(el.parentNode, n + 1);
      });

      // Chiffres clés : « 120 personnes », « 125 m² », « 11,77 m² »…
      const counters = [];
      document.querySelectorAll('.hero-fact strong, .card p strong, .stat b').forEach(el => {
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
  </script>
  <?= tracking_script('accueil') ?>
</body>
</html>
