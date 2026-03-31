<?php

$json_file = __DIR__ . '/stats.json';

if (!file_exists($json_file)) {
    die('<p style="color:red;font-family:monospace;padding:40px">
        ERROR: stats.json not found at ' . htmlspecialchars($json_file) . '
    </p>');
}

$raw  = file_get_contents($json_file);
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('<p style="color:red;font-family:monospace;padding:40px">
        ERROR: Invalid JSON — ' . json_last_error_msg() . '
    </p>');
}

$title = htmlspecialchars($data['dashboard_title'] ?? 'ClimAgro Dashboard');
$tagline = htmlspecialchars($data['tagline'] ?? '');
$updated = htmlspecialchars($data['last_updated'] ?? date('Y-m-d'));
$metrics = $data['metrics'] ?? [];

/* ── SVG icons keyed by id ──────────────────────────────── */
function get_icon(string $key): string {
    $icons = [
        'map'     => '<path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>',
        'leaf'    => '<path d="M5 3s2.5 0 5 3 5 3 5 3-5 1-7.5-1S5 3 5 3zM5 21V10m0 0s3-2 7-2 7 2 7 2"/>',
        'rain'    => '<path d="M3 12a9 9 0 1018 0 9 9 0 00-18 0M8 16v2M12 16v4M16 16v2"/>',
        'chart'   => '<path d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>',
        'alert'   => '<path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
        'station' => '<path d="M9.59 4.59A2 2 0 1111 8H2m10.59 11.41A2 2 0 1014 16H2m15.73-8.27A2.5 2.5 0 1119.5 12H2"/>',
    ];
    return $icons[$key] ?? $icons['chart'];
}

/* ── Format large numbers ───────────────────────────────── */
function fmt(float $v, string $unit): string {
    if ($v >= 1_000_000)      $n = number_format($v / 1_000_000, 2) . 'M';
    elseif ($v >= 1_000)      $n = number_format($v);
    elseif (floor($v) != $v)  $n = number_format($v, 1);
    else                      $n = number_format((int)$v);
    return $n . ($unit ? '<span class="unit">' . htmlspecialchars($unit) . '</span>' : '');
}

/* ── Sanitise hex colour ────────────────────────────────── */
function safe_color(string $c): string {
    return preg_match('/^#[0-9a-fA-F]{3,6}$/', $c) ? $c : '#2dd4bf';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title><?= $title ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>

<style>
/* ══ RESET ═══════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

/* ══ CLIMAGRO BRAND PALETTE ══════════════════════════════
   Extracted directly from climagroanalytics.com:
   ─ Deep forest green  #0a3d2e  (primary bg)
   ─ Rich green         #0f5c3a  (surface)
   ─ Teal accent        #2dd4bf  (primary accent)
   ─ Lime green         #4ade80  (secondary accent)
   ─ White              #f0fdf4  (text)
   ═══════════════════════════════════════════════════════ */
:root{
  --bg:          #071f17;
  --bg2:         #0a2e1e;
  --surface:     #0d3d28;
  --surface2:    #114d33;
  --border:      rgba(45,212,191,0.15);
  --border2:     rgba(45,212,191,0.3);
  --text:        #ecfdf5;
  --text2:       #a7f3d0;
  --text3:       #6ee7b7;
  --muted:       #4ade80;
  --accent:      #2dd4bf;   /* teal */
  --accent2:     #4ade80;   /* lime */
  --glow:        rgba(45,212,191,0.18);
  --glow2:       rgba(74,222,128,0.12);
  --radius:      20px;
  --radius-sm:   12px;
  --font:        'Outfit',sans-serif;
  --mono:        'JetBrains Mono',monospace;
}

html{font-size:16px;scroll-behavior:smooth}
body{
  font-family:var(--font);
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  overflow-x:hidden;
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
}

/* ══ ANIMATED BACKGROUND ══════════════════════════════════ */
body::before{
  content:'';
  position:fixed;inset:0;
  background:
    radial-gradient(ellipse 60% 50% at 20% 20%, rgba(45,212,191,0.08) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 80% 80%, rgba(74,222,128,0.07) 0%, transparent 60%),
    radial-gradient(ellipse 40% 40% at 50% 50%, rgba(15,92,58,0.3) 0%, transparent 70%);
  pointer-events:none;
  z-index:0;
  animation:bgPulse 8s ease-in-out infinite alternate;
}
@keyframes bgPulse{
  from{opacity:0.7}
  to{opacity:1}
}

/* Floating orbs */
.orb{
  position:fixed;border-radius:50%;
  filter:blur(80px);pointer-events:none;z-index:0;
  animation:float 12s ease-in-out infinite;
}
.orb-1{width:400px;height:400px;background:rgba(45,212,191,0.07);top:-100px;right:-100px;animation-delay:0s}
.orb-2{width:320px;height:320px;background:rgba(74,222,128,0.06);bottom:-80px;left:-80px;animation-delay:-4s}
.orb-3{width:200px;height:200px;background:rgba(45,212,191,0.05);top:50%;left:50%;transform:translate(-50%,-50%);animation-delay:-8s}
@keyframes float{
  0%,100%{transform:translateY(0) scale(1)}
  50%{transform:translateY(-30px) scale(1.05)}
}

/* ══ MOVING GRID LINES ════════════════════════════════════ */
.grid-overlay{
  position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:
    linear-gradient(rgba(45,212,191,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(45,212,191,0.04) 1px, transparent 1px);
  background-size:60px 60px;
  animation:gridMove 20s linear infinite;
}
@keyframes gridMove{
  from{background-position:0 0}
  to{background-position:60px 60px}
}

/* ══ LAYOUT WRAPPER ═══════════════════════════════════════ */
.wrap{position:relative;z-index:1;max-width:1240px;margin:0 auto;padding:0 28px 60px}

/* ══ HEADER ═══════════════════════════════════════════════ */
header{
  padding:48px 0 40px;
  display:flex;align-items:flex-end;justify-content:space-between;
  flex-wrap:wrap;gap:20px;
  border-bottom:1px solid var(--border);
  position:relative;
}
header::after{
  content:'';position:absolute;bottom:-1px;left:0;
  width:280px;height:2px;
  background:linear-gradient(90deg,var(--accent),var(--accent2),transparent);
  border-radius:2px;
}

.brand{display:flex;align-items:center;gap:16px}
.brand-icon{
  width:52px;height:52px;border-radius:14px;
  background:linear-gradient(135deg,var(--surface2),var(--surface));
  border:1px solid var(--border2);
  display:flex;align-items:center;justify-content:center;font-size:26px;
  box-shadow:0 0 20px var(--glow);
  animation:iconGlow 3s ease-in-out infinite alternate;
}
@keyframes iconGlow{
  from{box-shadow:0 0 20px var(--glow)}
  to{box-shadow:0 0 36px rgba(45,212,191,0.35),0 0 60px rgba(45,212,191,0.1)}
}
.brand-text h1{
  font-size:clamp(1.5rem,3vw,2.1rem);font-weight:800;
  background:linear-gradient(135deg,#fff 30%,var(--accent));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  letter-spacing:-0.03em;line-height:1;
}
.brand-text p{font-size:0.75rem;color:var(--text3);margin-top:5px;font-family:var(--mono);letter-spacing:0.04em}

.header-right{text-align:right}
.live-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(45,212,191,0.1);border:1px solid var(--border2);
  color:var(--accent);font-family:var(--mono);font-size:0.72rem;
  padding:6px 14px;border-radius:100px;letter-spacing:0.05em;
  box-shadow:0 0 12px rgba(45,212,191,0.15);
}
.live-dot{
  width:7px;height:7px;border-radius:50%;background:var(--accent);
  animation:livePulse 1.8s ease-in-out infinite;
}
@keyframes livePulse{
  0%,100%{opacity:1;transform:scale(1)}
  50%{opacity:0.3;transform:scale(0.65)}
}
.updated{font-size:0.7rem;color:var(--text3);font-family:var(--mono);margin-top:8px}

/* ══ SECTION LABEL ════════════════════════════════════════ */
.section-label{
  display:flex;align-items:center;gap:12px;
  font-family:var(--mono);font-size:0.68rem;text-transform:uppercase;
  letter-spacing:0.12em;color:var(--text3);
  margin:36px 0 20px;
}
.section-label::after{content:'';flex:1;height:1px;background:var(--border)}

/* ══ METRICS GRID ═════════════════════════════════════════ */
.grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
  gap:20px;
}

/* ══ METRIC CARD ══════════════════════════════════════════ */
.card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:26px 26px 22px;
  position:relative;overflow:hidden;
  cursor:default;
  transition:transform 0.28s ease, border-color 0.28s ease, box-shadow 0.28s ease;
  animation:cardIn 0.5s ease both;
}
.card:hover{
  transform:translateY(-5px);
  border-color:var(--border2);
  box-shadow:0 20px 48px rgba(0,0,0,0.35), 0 0 0 1px rgba(45,212,191,0.1), inset 0 1px 0 rgba(255,255,255,0.05);
}

/* Stagger */
<?php for($i=0;$i<12;$i++): ?>
.card:nth-child(<?= $i+1 ?>){animation-delay:<?= $i*0.07 ?>s}
<?php endfor; ?>

@keyframes cardIn{
  from{opacity:0;transform:translateY(20px)}
  to{opacity:1;transform:translateY(0)}
}

/* Coloured top bar */
.card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--c),transparent);
  opacity:0.9;
}

/* Corner glow */
.card::after{
  content:'';position:absolute;
  top:-40px;right:-40px;
  width:160px;height:160px;border-radius:50%;
  background:radial-gradient(circle,var(--c) 0%,transparent 65%);
  opacity:0.07;pointer-events:none;
  transition:opacity 0.3s;
}
.card:hover::after{opacity:0.13}

/* Shine sweep on hover */
.card-shine{
  position:absolute;inset:0;
  background:linear-gradient(105deg,transparent 30%,rgba(255,255,255,0.04) 50%,transparent 70%);
  transform:translateX(-100%);
  transition:transform 0s;
  pointer-events:none;
}
.card:hover .card-shine{
  transform:translateX(100%);
  transition:transform 0.55s ease;
}

.card-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}
.card-label{
  font-size:0.72rem;font-weight:600;color:var(--text3);
  text-transform:uppercase;letter-spacing:0.08em;line-height:1.4;max-width:65%;
}
.card-icon-wrap{
  width:42px;height:42px;border-radius:11px;
  background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.07);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.card-icon-wrap svg{width:20px;height:20px;stroke:var(--c);stroke-width:1.8;fill:none;stroke-linecap:round;stroke-linejoin:round}

.card-value{
  font-family:var(--font);
  font-size:clamp(2rem,4vw,2.8rem);
  font-weight:800;letter-spacing:-0.04em;
  color:var(--text);line-height:1;
  margin-bottom:10px;
}
.unit{font-size:1rem;font-weight:600;color:var(--text3);margin-left:3px;vertical-align:middle}

.card-desc{font-size:0.76rem;color:var(--text3);line-height:1.6;margin-bottom:16px}

.card-trend{
  display:inline-flex;align-items:center;gap:5px;
  font-family:var(--mono);font-size:0.7rem;font-weight:500;
  padding:4px 11px;border-radius:8px;
}
.trend-up{background:rgba(74,222,128,0.1);color:#4ade80;border:1px solid rgba(74,222,128,0.2)}
.trend-dn{background:rgba(251,146,60,0.1);color:#fb923c;border:1px solid rgba(251,146,60,0.2)}

/* Animated counter underline */
.card-value-wrap{position:relative;display:inline-block}
.card-value-wrap::after{
  content:'';position:absolute;bottom:-4px;left:0;
  height:2px;width:0%;
  background:linear-gradient(90deg,var(--c),transparent);
  border-radius:2px;
  transition:width 0.6s ease 0.2s;
}
.card:hover .card-value-wrap::after{width:100%}

/* ══ PARTICLE DOTS (CSS only) ════════════════════════════ */
.particles{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}
.particle{
  position:absolute;border-radius:50%;
  background:var(--accent);opacity:0;
  animation:particleFloat 15s ease-in-out infinite;
}
<?php
$ps = [[2,10,5,0],[1.5,30,8,2],[1,55,3,5],[2.5,70,6,1],[1,85,4,3],[1.5,20,7,7],[2,40,5,4],[1,60,3,9],[1.5,75,6,6],[2,95,4,8]];
foreach($ps as $i=>[$s,$l,$d,$dl]):
?>
.particle:nth-child(<?= $i+1 ?>){
  width:<?= $s ?>px;height:<?= $s ?>px;
  left:<?= $l ?>%;bottom:-<?= $d ?>px;
  animation-duration:<?= 12+$i*1.5 ?>s;
  animation-delay:-<?= $dl ?>s;
}
<?php endforeach; ?>
@keyframes particleFloat{
  0%{transform:translateY(0) rotate(0);opacity:0}
  10%{opacity:0.5}
  90%{opacity:0.3}
  100%{transform:translateY(-100vh) rotate(360deg);opacity:0}
}

/* ══ SITE FOOTER ══════════════════════════════════════════ */
.site-footer{
  margin-top:72px;
  background:#0a3326;
  border-top:1px solid rgba(45,212,191,0.18);
  position:relative;z-index:1;
  /* pull full width past .wrap padding */
  margin-left:-28px;margin-right:-28px;
  padding:52px 28px 0;
}
.site-footer::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),transparent);
}

/* 3-column grid */
.footer-grid{
  max-width:1240px;margin:0 auto;
  display:grid;grid-template-columns:1fr 1fr 1fr;
  gap:48px;padding-bottom:40px;
}
@media(max-width:768px){
  .footer-grid{grid-template-columns:1fr;gap:32px}
  .site-footer{margin-left:-16px;margin-right:-16px;padding:40px 16px 0}
}

.footer-col h4{
  font-size:0.95rem;font-weight:700;color:var(--text);
  margin-bottom:18px;letter-spacing:-0.01em;
}

/* Quick Links */
.footer-links{list-style:none;display:flex;flex-direction:column;gap:10px}
.footer-links a{
  color:var(--text2);font-size:0.88rem;text-decoration:none;
  transition:color 0.18s,padding-left 0.18s;display:inline-block;
}
.footer-links a:hover{color:var(--accent);padding-left:6px}

/* Contact items */
.contact-list{display:flex;flex-direction:column;gap:13px}
.contact-item{display:flex;align-items:flex-start;gap:11px;color:var(--text2);font-size:0.88rem;line-height:1.5}
.contact-item svg{
  width:17px;height:17px;stroke:var(--accent);stroke-width:1.8;
  fill:none;stroke-linecap:round;stroke-linejoin:round;
  flex-shrink:0;margin-top:2px;
}

/* Subscribe */
.sub-form{
  display:flex;align-items:center;
  background:rgba(255,255,255,0.07);
  border:1px solid rgba(45,212,191,0.25);
  border-radius:100px;overflow:hidden;
  transition:border-color 0.2s,box-shadow 0.2s;
}
.sub-form:focus-within{
  border-color:rgba(45,212,191,0.55);
  box-shadow:0 0 0 3px rgba(45,212,191,0.1);
}
.sub-form input{
  flex:1;background:transparent;border:none;outline:none;
  padding:12px 18px;font-size:0.85rem;
  color:var(--text);font-family:var(--font);
}
.sub-form input::placeholder{color:rgba(255,255,255,0.35)}
.sub-btn{
  width:44px;height:44px;border-radius:50%;margin:4px;
  background:var(--accent2);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;transition:background 0.18s,transform 0.18s;
}
.sub-btn:hover{background:var(--accent);transform:scale(1.08)}
.sub-btn svg{width:16px;height:16px;stroke:#0a3326;stroke-width:2.2;fill:none;stroke-linecap:round;stroke-linejoin:round}

/* Bottom bar */
.footer-bottom{
  max-width:1240px;margin:0 auto;
  border-top:1px solid rgba(45,212,191,0.1);
  padding:18px 0 22px;
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:14px;
}
.footer-copy{font-size:0.78rem;color:var(--text3);font-family:var(--mono);line-height:1.6}

/* Social icons */
.social-row{display:flex;align-items:center;gap:18px}
.social-link{
  color:var(--text3);transition:color 0.18s,transform 0.18s;display:inline-flex;
}
.social-link:hover{color:var(--accent);transform:translateY(-2px)}
.social-link svg{width:18px;height:18px;stroke:currentColor;stroke-width:1.8;fill:none;stroke-linecap:round;stroke-linejoin:round}

/* Scroll-to-top */
.scroll-top{
  width:40px;height:40px;border-radius:8px;
  background:var(--accent2);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background 0.18s,transform 0.18s;
  box-shadow:0 4px 14px rgba(74,222,128,0.3);
}
.scroll-top:hover{background:var(--accent);transform:translateY(-3px)}
.scroll-top svg{width:16px;height:16px;stroke:#0a3326;stroke-width:2.5;fill:none;stroke-linecap:round;stroke-linejoin:round}

/* ══ RESPONSIVE ═══════════════════════════════════════════ */
@media(max-width:640px){
  .wrap{padding:0 16px 40px}
  header{padding:32px 0 28px}
  .grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- Background layers -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
<div class="grid-overlay"></div>
<div class="particles">
  <?php for($i=0;$i<10;$i++): ?><div class="particle"></div><?php endfor; ?>
</div>

<div class="wrap">

  <!-- ── HEADER ──────────────────────────────────────────── -->
  <header>
    <div class="brand">
      <div class="brand-icon">🌱</div>
      <div class="brand-text">
        <h1><?= $title ?></h1>
        <?php if($tagline): ?>
          <p><?= $tagline ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="header-right">
      <div class="live-badge">
        <div class="live-dot"></div>
        LIVE DATA
      </div>
      <div class="updated">Last updated: <?= $updated ?></div>
    </div>
  </header>

  <!-- ── METRICS ─────────────────────────────────────────── -->
  <div class="section-label">
    Key Performance Indicators &mdash; <?= $metric_count ?> metrics loaded from stats.json
  </div>

  <div class="grid">
    <?php foreach($metrics as $m):
      $val    = $m['value']       ?? 0;
      $unit   = $m['unit']        ?? '';
      $label  = htmlspecialchars($m['label']       ?? 'Metric');
      $desc   = htmlspecialchars($m['description'] ?? '');
      $icon   = $m['icon']        ?? 'chart';
      $trend  = htmlspecialchars($m['trend']       ?? '');
      $up     = !empty($m['trend_up']);
      $color  = safe_color($m['color'] ?? '#2dd4bf');
    ?>
    <div class="card" style="--c:<?= $color ?>">
      <div class="card-shine"></div>

      <div class="card-header">
        <div class="card-label"><?= $label ?></div>
        <div class="card-icon-wrap">
          <svg viewBox="0 0 24 24"><?= get_icon($icon) ?></svg>
        </div>
      </div>

      <div class="card-value-wrap">
        <div class="card-value"><?= fmt((float)$val, $unit) ?></div>
      </div>

      <?php if($desc): ?>
        <p class="card-desc"><?= $desc ?></p>
      <?php endif; ?>

      <?php if($trend): ?>
        <span class="card-trend <?= $up ? 'trend-up' : 'trend-dn' ?>">
          <?= $up ? '↑' : '↓' ?> <?= $trend ?>
        </span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>


</div><!-- /wrap -->

<!-- ── SITE FOOTER (matches climagroanalytics.com) ───────── -->
<footer class="site-footer">
  <div class="footer-grid">

    <!-- Col 1: Quick Links -->
    <div class="footer-col">
      <h4>Quick Links</h4>
      <ul class="footer-links">
        <li><a href="https://www.climagroanalytics.com/">Home</a></li>
        <li><a href="https://www.climagroanalytics.com/about-us.php">About</a></li>
        <li><a href="https://www.climagroanalytics.com/solutions.php">Offerings</a></li>
        <li><a href="https://www.climagroanalytics.com/contact-us.php">Contact</a></li>
      </ul>
    </div>

    <!-- Col 2: Contact -->
    <div class="footer-col">
      <h4>Contact</h4>
      <div class="contact-list">
        <div class="contact-item">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <span>contact@climagroanalytics.com</span>
        </div>
        <div class="contact-item">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8a19.79 19.79 0 01-3.07-8.68A2 2 0 012 .9h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          <span>+91 92772 96270</span>
        </div>
        <div class="contact-item">
          <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <span>CSJM Innovation Foundation Chhatrapati Shahu Ji Maharaj University, Kanpur&ndash; 208024</span>
        </div>
      </div>
    </div>

    <!-- Col 3: Subscribe -->
    <div class="footer-col">
      <h4>Subscribe</h4>
      <div class="sub-form">
        <input type="email" placeholder="Enter your email"/>
        <button class="sub-btn" type="button" aria-label="Subscribe">
          <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>

  </div><!-- /footer-grid -->

  <!-- Bottom bar -->
  <div class="footer-bottom">
    <div class="footer-copy">
      &copy; <?= date('Y') ?> ClimAgro Analytics All rights reserved<br>
      Startup India Certificate Number &ndash; DIPP129220
    </div>

    <!-- Social icons -->
    <div class="social-row">
      <!-- Facebook -->
      <a href="https://www.facebook.com" class="social-link" target="_blank" rel="noopener" aria-label="Facebook">
        <svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
      </a>
      <!-- LinkedIn -->
      <a href="https://www.linkedin.com/company/climagroanalytics" class="social-link" target="_blank" rel="noopener" aria-label="LinkedIn">
        <svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
      </a>
      <!-- Twitter / X -->
      <a href="https://twitter.com" class="social-link" target="_blank" rel="noopener" aria-label="Twitter">
        <svg viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
      </a>
      <!-- Instagram -->
      <a href="https://www.instagram.com" class="social-link" target="_blank" rel="noopener" aria-label="Instagram">
        <svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
      </a>

      <!-- Scroll to top -->
      <button class="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">
        <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
      </button>
    </div>
  </div>

</footer>
</body>
</html>
