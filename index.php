<?php
require __DIR__ . '/includes/bootstrap.php';
track_visit('accueil');

$name    = s('site_name');
$siteUrl = rtrim(s('site_url'), '/');
$mailto  = mailto();
$photos  = photos();
$jsonLd  = [
    '@context' => 'https://schema.org',
    '@type' => 'EventVenue',
    '@id' => $siteUrl . '/#venue',
    'url' => $siteUrl . '/',
    'name' => $name,
    'image' => $siteUrl . '/logo-rn6.png',
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
  <meta property="og:image" content="<?= e($siteUrl) ?>/logo-rn6.png">
  <meta property="og:image:alt" content="Logo de <?= e($name) ?>">
  <meta property="og:title" content="<?= e(s('og_title')) ?>">
  <meta property="og:description" content="<?= e(s('og_description')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= e($siteUrl) ?>/logo-rn6.png">
  <meta name="twitter:title" content="<?= e(s('og_title')) ?>">
  <meta name="twitter:description" content="<?= e(s('og_description')) ?>">
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="logo-rn6.png" type="image/png">
  <link rel="preload" as="image" href="<?= e(hero_image()) ?>">
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

    footer{border-top:1px solid var(--line);padding:24px 0 38px;color:#768394;font-size:.9rem;background:#fff}
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
        <a href="#salle">La salle</a>
        <a href="#equipements">Équipements</a>
        <a href="#mobilier">Mobilier</a>
        <a href="#tarifs">Tarifs</a>
        <a href="#acces">Accès</a>
        <a href="plan.php" data-track="plan">Plan</a>
        <a href="#photos">Photos</a>
        <a class="cta-small" href="reservation.php" data-track="reserver" style="background:linear-gradient(135deg,#273CF5,#1727b8);color:#fff;border-color:#5262ff;box-shadow:0 7px 20px rgba(39,60,245,.28)">Réserver en ligne</a>
        <a class="cta-small" href="<?= e($mailto) ?>" data-track="email">Nous contacter</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero" id="accueil">
      <div class="wrap hero-grid">
        <div class="hero-copy">
          <div class="eyebrow"><?= e(s('hero_eyebrow')) ?></div>
          <h1><span class="h1-blue"><?= e(s('hero_title_1')) ?></span> <span class="h1-red"><?= e(s('hero_title_2')) ?></span><span class="hero-tagline"><?= e(s('hero_tagline')) ?></span></h1>
          <p class="lead"><?= rich(s('hero_lead')) ?></p>
          <div class="actions">
            <a class="btn btn-primary" href="reservation.php" data-track="reserver">📅 Réserver en ligne</a>
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

    <section class="plan-cta-section" aria-labelledby="plan-cta-title">
      <div class="wrap">
        <div class="plan-cta-box">
          <div>
            <div class="eyebrow"><?= e(s('plan_cta_eyebrow')) ?></div>
            <h2 id="plan-cta-title"><?= e(s('plan_cta_title')) ?></h2>
            <p><?= rich(s('plan_cta_text')) ?></p>
          </div>
          <a class="btn btn-gold" href="plan.php" data-track="plan">Voir le plan de la salle →</a>
        </div>
      </div>
    </section>

    <section class="pricing-section" id="tarifs">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('tarifs_title')) ?></h2>
          <p><?= rich(s('tarifs_subtitle')) ?></p>
<?php if (s('tarifs_badge') !== ''): ?>
          <div class="pricing-note"><?= e(s('tarifs_badge')) ?></div>
<?php endif; ?>
        </div>

<?php $jours = items('tarif_jours'); if ($jours): ?>
        <div class="price-grid" aria-label="Tarifs de location par jour" style="grid-template-columns:repeat(<?= min(7, count($jours)) ?>,1fr)">
<?php foreach ($jours as $it): ?>
          <div class="day-price"><strong><?= e($it['title']) ?></strong><span><?= e($it['value']) ?></span></div>
<?php endforeach; ?>
        </div>
<?php endif; ?>

<?php $infos = items('tarif_infos'); if ($infos): ?>
        <div class="pricing-info" aria-label="Informations tarifaires complémentaires">
<?php foreach ($infos as $it): ?>
          <div class="pricing-info-item"><strong><?= e($it['title']) ?></strong> <?= rich($it['text']) ?></div>
<?php endforeach; ?>
        </div>
<?php endif; ?>

<?php $options = items('tarif_options'); if ($options): ?>
        <div class="pricing-panels">
          <div class="pricing-card">
            <h3><?= e(s('tarifs_options_title')) ?></h3>
            <ul class="price-list">
<?php foreach ($options as $it): ?>
              <li><span><?= rich($it['text']) ?></span><strong><?= e($it['value']) ?></strong></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>

        <div class="pricing-actions">
          <a class="btn btn-gold" href="reservation.php" data-track="reserver">📅 Réserver en ligne →</a>
          <a class="btn btn-gold" href="#contact">Prendre rendez-vous pour visiter la salle →</a>
          <a class="btn btn-primary" href="<?= e($mailto) ?>" data-track="email">✉ Demander une réservation</a>
        </div>

        <p class="pricing-foot"><?= rich(s('tarifs_foot')) ?> <a href="<?= e($mailto) ?>" data-track="email"><strong><?= e(s('contact_email')) ?></strong></a>.</p>
      </div>
    </section>

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
            <div style="margin-top:20px"><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= e(s('maps_url')) ?>" data-track="maps">Voir l’adresse sur la carte ↗</a></div>
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
      </div>
    </section>

    <section class="photos-section" id="photos">
      <div class="wrap">
        <div class="section-title">
          <h2><?= e(s('photos_title')) ?></h2>
          <p><?= rich(s('photos_subtitle')) ?></p>
        </div>
        <div class="gallery" aria-label="Galerie photos de <?= e($name) ?>">
<?php foreach ($photos as $i => $ph): ?>
          <figure class="gallery-item" data-track="photo">
            <img alt="<?= e($ph['alt']) ?>" data-src="<?= e(photo_src($ph['filename'])) ?>" width="<?= (int)$ph['width'] ?>" height="<?= (int)$ph['height'] ?>" loading="lazy" decoding="async" fetchpriority="low">
            <div class="gallery-empty"><div><span>📷</span>Photo <?= $i + 1 ?></div></div>
          </figure>
<?php endforeach; ?>
        </div>
      </div>
    </section>

    <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Galerie photo agrandie">
      <button class="lightbox-close" type="button" aria-label="Fermer">×</button>
      <button class="lightbox-prev" type="button" aria-label="Photo précédente">‹</button>
      <img class="lightbox-img" src="" alt="">
      <button class="lightbox-next" type="button" aria-label="Photo suivante">›</button>
      <div class="lightbox-counter"></div>
    </div>

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
  </main>

  <footer>
    <div class="wrap foot">
      <span>© <?= date('Y') ?> <?= e(s('footer_text')) ?></span>
      <span><?= e(s('footer_tagline')) ?><br><small><?php if (s('salles1001_url') !== ''): ?><a href="<?= e(s('salles1001_url')) ?>" target="_blank" rel="noopener" data-track="1001" style="color:inherit;text-decoration:none">1001Salles</a> · <?php endif; ?><?php if (s('facebook_url') !== ''): ?><a href="<?= e(s('facebook_url')) ?>" target="_blank" rel="noopener" data-track="facebook" aria-label="Suivez <?= e($name) ?> sur Facebook" style="display:inline-flex;align-items:center;gap:7px;margin-left:6px;padding:7px 11px;border-radius:9px;background:#1877F2;color:#fff;text-decoration:none;font-weight:800;box-shadow:0 3px 10px rgba(0,0,0,.15)"><span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#fff;color:#1877F2;font-family:Arial,sans-serif;font-size:18px;font-weight:900;line-height:1">f</span> Facebook</a><?php endif; ?></small></span>
    </div>
  </footer>

  <script>
    const CONTACT_EMAIL = <?= json_encode(s('contact_email')) ?>;
    const MAILTO = <?= json_encode($mailto) ?>;
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
      img.src = img.dataset.src;
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
      const src = img.dataset.src || img.src;
      if (!src) return;
      loadGalleryImage(img);
      lightboxImg.src = src;
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
  </script>
  <?= tracking_script('accueil') ?>
</body>
</html>
