<?php
require __DIR__ . '/config/bootstrap.php';
maintenance_guard($pdo);
$slug = trim($_GET['slug'] ?? '');
$blog = null;
if ($slug !== '') {
    $s = $pdo->prepare("SELECT * FROM blogs WHERE slug=? AND status='published' LIMIT 1");
    $s->execute([$slug]);
    $blog = $s->fetch() ?: null;
}
if (!$blog) {
    if ($slug !== '') {
        http_response_code(404);
        $name = setting($pdo, 'organization_name');
        echo '<!doctype html><html lang="mr"><head><meta charset="utf-8"><title>' . e($name) . '</title><link rel="stylesheet" href="/assets/css/nabhrang.css"></head><body class="login-page"><main class="login-card" data-testid="blog-not-found"><h1>ब्लॉग सापडला नाही</h1><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/blog.php" data-testid="back-home-link">← सर्व ब्लॉग</a></main></body></html>';
        exit;
    }
    $name = setting($pdo, 'organization_name');
    $list = $pdo->query("SELECT slug,title_mr,short_description_mr,featured_image,category,publish_date FROM blogs WHERE status='published' ORDER BY publish_date DESC, id DESC LIMIT 60")->fetchAll();
    ?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>सर्व ब्लॉग · <?= e($name) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body>
<nav class="public-nav"><div class="container nav-row"><a class="brand" href="/" data-testid="blog-list-brand"><?= e($name) ?></a><a class="btn btn-outline" href="/" data-testid="blog-list-home-link">← मुख्य पान</a></div></nav>
<main class="container section">
    <div class="section-head"><div class="section-kicker">ब्लॉग</div><h1 style="font:600 clamp(32px,4vw,52px)/1.1 'Playfair Display',serif;margin:12px 0" data-testid="blog-list-title">सर्व ब्लॉग</h1></div>
    <?php if (!$list): ?><p style="color:var(--muted)" data-testid="blog-list-empty">अजून कोणताही ब्लॉग प्रकाशित नाही.</p><?php endif; ?>
    <div class="card-grid">
        <?php foreach ($list as $b): ?>
            <article class="card" data-testid="blog-list-card">
                <?php if (!empty($b['featured_image'])): ?><div class="card-image" style="background-image:url('<?= e($b['featured_image']) ?>')"></div><?php endif; ?>
                <div class="card-body">
                    <p class="section-kicker" style="margin:0 0 6px"><?= e($b['category'] ?: 'ब्लॉग') ?> · <?= e((string) $b['publish_date']) ?></p>
                    <h3><?= e($b['title_mr']) ?></h3>
                    <p><?= e((string) ($b['short_description_mr'] ?? '')) ?></p>
                    <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/blog.php?slug=<?= e($b['slug']) ?>" data-testid="blog-list-read-link">वाचा</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
<?php
    exit;
}
$name = setting($pdo, 'organization_name');
$seoTitle = $blog['seo_title'] ?: $blog['title_mr'];
$seoDesc  = $blog['seo_description'] ?: $blog['short_description_mr'];
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($seoTitle) ?> · <?= e($name) ?></title>
    <meta name="description" content="<?= e((string) $seoDesc) ?>">
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body>
<nav class="public-nav"><div class="container nav-row"><a class="brand" href="/" data-testid="blog-nav-brand"><?= e($name) ?></a><a class="btn btn-outline" href="/" data-testid="blog-back-home-link">← मुख्य पान</a></div></nav>
<main class="container section" style="max-width:820px">
    <p class="section-kicker" data-testid="blog-meta"><?= e($blog['category'] ?? 'ब्लॉग') ?> · <?= e((string) ($blog['publish_date'] ?? $blog['created_at'])) ?><?= !empty($blog['author']) ? ' · ' . e($blog['author']) : '' ?></p>
    <h1 data-testid="blog-title" style="font:600 clamp(32px,4vw,52px)/1.1 'Playfair Display',serif;margin:12px 0"><?= e($blog['title_mr']) ?></h1>
    <?php if (!empty($blog['featured_image'])): ?><img src="<?= e($blog['featured_image']) ?>" alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:6px;margin:20px 0" data-testid="blog-featured-image"><?php endif; ?>
    <div data-testid="blog-content" style="font-size:19px;line-height:1.8;white-space:pre-wrap"><?= nl2br(e($blog['content_mr'] ?? '')) ?></div>
</main>
</body>
</html>
