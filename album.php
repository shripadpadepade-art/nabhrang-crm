<?php
require __DIR__ . '/config/bootstrap.php';
maintenance_guard($pdo);
$id = (int) ($_GET['id'] ?? 0);
$album = null;
if ($id) {
    $s = $pdo->prepare("SELECT * FROM gallery_albums WHERE id=? AND status='active'");
    $s->execute([$id]);
    $album = $s->fetch() ?: null;
}
$name = setting($pdo, 'organization_name');
if (!$album) {
    http_response_code(404);
    echo '<!doctype html><html lang="mr"><head><meta charset="utf-8"><title>' . e($name) . '</title><link rel="stylesheet" href="/assets/css/nabhrang.css"></head><body class="login-page"><main class="login-card" data-testid="album-not-found"><h1>अल्बम सापडला नाही</h1><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/" data-testid="back-home-link">← मुख्य पानावर परत</a></main></body></html>';
    exit;
}
$photos = $pdo->prepare("SELECT file_path,caption_mr FROM gallery_photos WHERE album_id=? AND status='active' ORDER BY sort_order,id DESC");
$photos->execute([$id]);
$photos = $photos->fetchAll();
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($album['title_mr']) ?> · <?= e($name) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body>
<nav class="public-nav"><div class="container nav-row"><a class="brand" href="/" data-testid="album-nav-brand"><?= e($name) ?></a><a class="btn btn-outline" href="/#gallery" data-testid="album-back-link">← चित्रदालनाकडे</a></div></nav>
<main class="container section">
    <div class="section-head">
        <div class="section-kicker">चित्रदालन</div>
        <h1 data-testid="album-title" style="font:600 clamp(32px,4vw,48px)/1.1 'Playfair Display',serif;margin:12px 0"><?= e($album['title_mr']) ?></h1>
        <?php if (!empty($album['description_mr'])): ?><p><?= e($album['description_mr']) ?></p><?php endif; ?>
    </div>
    <div class="gallery-grid">
        <?php foreach ($photos as $p): ?>
            <a class="gallery-tile" href="<?= e($p['file_path']) ?>" target="_blank" data-testid="album-photo-tile" style="background-image:url('<?= e($p['file_path']) ?>')">
                <?php if (!empty($p['caption_mr'])): ?><span class="gallery-title"><?= e($p['caption_mr']) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$photos): ?><p style="color:var(--muted)">या अल्बममध्ये अजून छायाचित्रे नाहीत.</p><?php endif; ?>
    </div>
</main>
</body>
</html>
