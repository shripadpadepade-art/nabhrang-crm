<?php
require __DIR__ . '/config/bootstrap.php';
maintenance_guard($pdo);
$name = setting($pdo, 'organization_name');
$upcoming = $pdo->query("SELECT * FROM events WHERE status='published' AND (event_date IS NULL OR event_date >= CURDATE()) ORDER BY event_date ASC LIMIT 60")->fetchAll();
$past = $pdo->query("SELECT * FROM events WHERE status='published' AND event_date IS NOT NULL AND event_date < CURDATE() ORDER BY event_date DESC LIMIT 60")->fetchAll();

function event_card(array $ev): void { ?>
    <article class="card" data-testid="events-page-card">
        <?php if (!empty($ev['poster_path'])): ?><div class="card-image" style="background-image:url('<?= e($ev['poster_path']) ?>')"></div><?php endif; ?>
        <div class="card-body">
            <h3><?= e($ev['title_mr']) ?></h3>
            <p style="color:var(--velvet);font-weight:700"><?= e((string) ($ev['event_date'] ?? '')) ?> <?= e((string) ($ev['event_time'] ?? '')) ?><?= !empty($ev['venue']) ? ' · ' . e($ev['venue']) : '' ?></p>
            <p><?= e((string) ($ev['description_mr'] ?? '')) ?></p>
            <?php if (!empty($ev['registration_url'])): ?><a class="btn btn-gold" href="<?= e($ev['registration_url']) ?>" target="_blank" data-testid="events-page-register-link">नोंदणी</a><?php endif; ?>
        </div>
    </article>
<?php } ?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>सर्व कार्यक्रम · <?= e($name) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body>
<nav class="public-nav"><div class="container nav-row"><a class="brand" href="/" data-testid="events-page-brand"><?= e($name) ?></a><a class="btn btn-outline" href="/" data-testid="events-page-home-link">← मुख्य पान</a></div></nav>
<main class="container section">
    <div class="section-head"><div class="section-kicker">कार्यक्रम</div><h1 style="font:600 clamp(32px,4vw,52px)/1.1 'Playfair Display',serif;margin:12px 0" data-testid="events-page-title">आगामी कार्यक्रम</h1></div>
    <?php if (!$upcoming): ?><p style="color:var(--muted)" data-testid="events-page-empty">सध्या कोणताही आगामी कार्यक्रम नाही.</p><?php endif; ?>
    <div class="card-grid"><?php foreach ($upcoming as $ev) event_card($ev); ?></div>

    <?php if ($past): ?>
    <div class="section-head" style="margin-top:60px"><div class="section-kicker">स्मृती</div><h2 data-testid="past-events-title">मागील कार्यक्रम</h2></div>
    <div class="card-grid"><?php foreach ($past as $ev) event_card($ev); ?></div>
    <?php endif; ?>
</main>
</body>
</html>
