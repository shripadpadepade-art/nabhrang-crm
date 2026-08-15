<?php
require __DIR__ . '/config/bootstrap.php';
maintenance_guard($pdo);

$name    = setting($pdo, 'organization_name');
$tagline = setting($pdo, 'tagline');
$footer  = setting($pdo, 'footer_text');
$phone   = setting($pdo, 'phone');
$email   = setting($pdo, 'email');
$address = setting($pdo, 'address');
$whatsapp = setting($pdo, 'whatsapp');
$logo    = setting($pdo, 'logo_path');
$favicon = setting($pdo, 'favicon_path');
$seoDesc = setting($pdo, 'seo_meta_description') ?: $tagline;

$sections = [];
foreach ($pdo->query("SELECT section_key,title_mr,content_mr,button_label_mr,button_url,image_path,status FROM site_sections WHERE status='active' ORDER BY sort_order") as $row) {
    $sections[$row['section_key']] = $row;
}
$show = static fn(string $key): bool => isset($sections[$key]);

$blogs = $pdo->query("SELECT id,slug,title_mr,short_description_mr,featured_image,publish_date FROM blogs WHERE status='published' ORDER BY publish_date DESC, id DESC LIMIT 3")->fetchAll();
$events = $pdo->query("SELECT id,title_mr,description_mr,event_date,event_time,venue,poster_path,registration_url FROM events WHERE status='published' AND (event_date IS NULL OR event_date >= CURDATE()) ORDER BY event_date ASC LIMIT 3")->fetchAll();
$videos = $pdo->query("SELECT id,title_mr,youtube_url,thumbnail_url FROM videos WHERE status='published' ORDER BY published_on DESC, id DESC LIMIT 3")->fetchAll();
$galleryAlbums = $pdo->query("SELECT id,title_mr,cover_path FROM gallery_albums WHERE status='active' ORDER BY id DESC LIMIT 6")->fetchAll();
$publications = $pdo->query("SELECT id,title_mr,year,cover_path,pdf_path FROM publications WHERE status='published' ORDER BY year DESC, id DESC LIMIT 4")->fetchAll();
$notifications = $pdo->query("SELECT title_mr,body_mr,publish_at FROM notifications WHERE status='published' ORDER BY publish_at DESC, id DESC LIMIT 3")->fetchAll();

$hero = $sections['hero'] ?? [];
$about = $sections['about'] ?? [];
$membership = $sections['membership'] ?? [];
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($name) ?> · <?= e($tagline) ?></title>
    <meta name="description" content="<?= e($seoDesc) ?>">
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body>
<nav class="public-nav">
    <div class="container nav-row">
        <a class="brand" href="/" data-testid="public-brand">
            <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" style="height:44px;vertical-align:middle;margin-right:10px"><?php endif; ?>
            <?= e($name) ?><small><?= e($tagline) ?></small>
        </a>
        <div class="nav-links">
            <a href="#about" data-testid="public-about-link">आमच्याविषयी</a>
            <a href="#blogs" data-testid="public-blogs-link">ब्लॉग</a>
            <a href="#events" data-testid="public-events-link">कार्यक्रम</a>
            <a href="#gallery" data-testid="public-gallery-link">चित्रदालन</a>
            <a href="#contact" data-testid="public-contact-link">संपर्क</a>
            <a href="/member/login.php" data-testid="member-login-link">लॉगिन</a>
            <a class="btn btn-outline" href="/member/register.php" data-testid="public-register-link">सदस्य व्हा</a>
        </div>
    </div>
</nav>

<main>
    <?php if ($show('hero')): ?>
    <section class="hero">
        <div class="container hero-copy">
            <div class="eyebrow" data-testid="hero-eyebrow"><?= e($tagline) ?></div>
            <h1 data-testid="hero-title"><?= e($hero['title_mr'] ?? '') ?></h1>
            <p data-testid="hero-subtitle"><?= e($hero['content_mr'] ?? '') ?></p>
            <div class="hero-actions">
                <a class="btn btn-gold" href="<?= e($membership['button_url'] ?? '/member/register.php') ?>" data-testid="membership-cta"><?= e($membership['button_label_mr'] ?? 'सदस्य व्हा') ?></a>
                <a class="btn btn-outline" href="#about" data-testid="explore-button"><?= e($about['title_mr'] ?? 'आमच्याविषयी') ?></a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($notifications): ?>
    <section class="section" style="padding:30px 0;background:#f1ead6" id="notices">
        <div class="container">
            <?php foreach ($notifications as $n): ?>
                <div class="notice" data-testid="public-notification-row"><strong><?= e($n['title_mr']) ?></strong> · <?= e((string) ($n['body_mr'] ?? '')) ?></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('about')): ?>
    <section class="section" id="about">
        <div class="container about-grid">
            <div class="about-art" role="img" aria-label="Cultural artwork" data-testid="about-image"></div>
            <div class="section-head">
                <div class="section-kicker"><?= e($name) ?></div>
                <h2 data-testid="about-title"><?= e($about['title_mr'] ?? '') ?></h2>
                <p data-testid="about-content"><?= e($about['content_mr'] ?? '') ?></p>
                <a class="btn btn-gold" href="#contact" data-testid="about-contact-button"><?= e($about['button_label_mr'] ?? 'आमच्याशी बोला') ?></a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('blogs') && $blogs): ?>
    <section class="section" id="blogs" style="background:#fff">
        <div class="container">
            <div class="section-head"><div class="section-kicker"><?= e($sections['blogs']['title_mr'] ?? 'ब्लॉग') ?></div><h2>नवीनतम ब्लॉग</h2><p><?= e($sections['blogs']['content_mr'] ?? '') ?></p><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/blog.php" data-testid="all-blogs-link">सर्व ब्लॉग पहा →</a></div>
            <div class="card-grid">
                <?php foreach ($blogs as $b): ?>
                    <article class="card" data-testid="public-blog-card">
                        <?php if (!empty($b['featured_image'])): ?><div class="card-image" style="background-image:url('<?= e($b['featured_image']) ?>')"></div><?php endif; ?>
                        <div class="card-body">
                            <h3><?= e($b['title_mr']) ?></h3>
                            <p><?= e((string) ($b['short_description_mr'] ?? '')) ?></p>
                            <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/blog.php?slug=<?= e($b['slug']) ?>" data-testid="public-blog-read-more">वाचा</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('events') && $events): ?>
    <section class="section" id="events">
        <div class="container">
            <div class="section-head"><div class="section-kicker"><?= e($sections['events']['title_mr'] ?? 'कार्यक्रम') ?></div><h2>आगामी कार्यक्रम</h2><p><?= e($sections['events']['content_mr'] ?? '') ?></p><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/events.php" data-testid="all-events-link">सर्व कार्यक्रम पहा →</a></div>
            <div class="card-grid">
                <?php foreach ($events as $ev): ?>
                    <article class="card" data-testid="public-event-card">
                        <?php if (!empty($ev['poster_path'])): ?><div class="card-image" style="background-image:url('<?= e($ev['poster_path']) ?>')"></div><?php endif; ?>
                        <div class="card-body">
                            <h3><?= e($ev['title_mr']) ?></h3>
                            <p style="color:var(--velvet);font-weight:700"><?= e((string) ($ev['event_date'] ?? '')) ?> <?= e((string) ($ev['event_time'] ?? '')) ?> · <?= e($ev['venue'] ?? '') ?></p>
                            <p><?= e((string) ($ev['description_mr'] ?? '')) ?></p>
                            <?php if (!empty($ev['registration_url'])): ?><a class="btn btn-gold" href="<?= e($ev['registration_url']) ?>" target="_blank" data-testid="public-event-register-link">नोंदणी</a><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('videos') && $videos): ?>
    <section class="section" id="videos" style="background:#fff">
        <div class="container">
            <div class="section-head"><div class="section-kicker">व्हिडिओ</div><h2><?= e($sections['videos']['title_mr'] ?? 'व्हिडिओ') ?></h2><p><?= e($sections['videos']['content_mr'] ?? '') ?></p></div>
            <div class="card-grid">
                <?php foreach ($videos as $v): ?>
                    <a class="card" href="<?= e($v['youtube_url']) ?>" target="_blank" data-testid="public-video-card">
                        <?php if (!empty($v['thumbnail_url'])): ?><div class="card-image" style="background-image:url('<?= e($v['thumbnail_url']) ?>');position:relative"><span style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:52px;text-shadow:0 4px 20px #000">▶</span></div><?php endif; ?>
                        <div class="card-body"><h3><?= e($v['title_mr']) ?></h3></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('gallery') && $galleryAlbums): ?>
    <section class="section" id="gallery">
        <div class="container">
            <div class="section-head"><div class="section-kicker"><?= e($sections['gallery']['title_mr'] ?? 'चित्रदालन') ?></div><h2>चित्रदालन</h2><p><?= e($sections['gallery']['content_mr'] ?? '') ?></p></div>
            <div class="gallery-grid">
                <?php foreach ($galleryAlbums as $a): ?>
                    <a class="gallery-tile" href="/album.php?id=<?= e((string) $a['id']) ?>" data-testid="public-album-tile" style="<?= !empty($a['cover_path']) ? 'background-image:url(' . e($a['cover_path']) . ')' : '' ?>">
                        <span class="gallery-title"><?= e($a['title_mr']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('publications') && $publications): ?>
    <section class="section" id="publications" style="background:#fff">
        <div class="container">
            <div class="section-head"><div class="section-kicker">प्रकाशने</div><h2><?= e($sections['publications']['title_mr'] ?? 'प्रकाशने') ?></h2><p><?= e($sections['publications']['content_mr'] ?? '') ?></p></div>
            <div class="card-grid">
                <?php foreach ($publications as $p): ?>
                    <article class="card" data-testid="public-publication-card">
                        <?php if (!empty($p['cover_path'])): ?><div class="card-image" style="background-image:url('<?= e($p['cover_path']) ?>')"></div><?php endif; ?>
                        <div class="card-body">
                            <h3><?= e($p['title_mr']) ?></h3>
                            <p style="color:var(--velvet);font-weight:700"><?= e((string) ($p['year'] ?? '')) ?></p>
                            <?php if (!empty($p['pdf_path'])): ?><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="<?= e($p['pdf_path']) ?>" target="_blank" data-testid="public-publication-pdf">PDF वाचा</a><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($show('membership')): ?>
    <section class="section" id="membership">
        <div class="container">
            <div class="callout">
                <div class="section-kicker" style="color:#e7c257"><?= e($membership['title_mr'] ?? 'सदस्य व्हा') ?></div>
                <h2 data-testid="membership-title"><?= e($membership['title_mr'] ?? '') ?></h2>
                <p data-testid="membership-content"><?= e($membership['content_mr'] ?? '') ?></p>
                <a class="btn btn-gold" href="<?= e($membership['button_url'] ?? '/member/register.php') ?>" data-testid="membership-register-button"><?= e($membership['button_label_mr'] ?? 'सदस्य व्हा') ?></a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer class="footer" id="contact">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand" href="/" data-testid="footer-brand"><?= e($name) ?><small><?= e($tagline) ?></small></a>
                <p data-testid="footer-text" style="margin-top:14px"><?= e($footer) ?></p>
            </div>
            <div>
                <h4>संपर्क</h4>
                <?php if ($phone): ?><p data-testid="footer-phone">📞 <?= e($phone) ?></p><?php endif; ?>
                <?php if ($whatsapp): ?><p data-testid="footer-whatsapp">WhatsApp: <?= e($whatsapp) ?></p><?php endif; ?>
                <?php if ($email): ?><p data-testid="footer-email">✉ <?= e($email) ?></p><?php endif; ?>
                <?php if ($address): ?><p data-testid="footer-address"><?= nl2br(e($address)) ?></p><?php endif; ?>
            </div>
            <div>
                <h4>दुवे</h4>
                <p><a href="/member/register.php" data-testid="footer-register-link">सदस्य नोंदणी</a></p>
                <p><a href="/member/login.php" data-testid="footer-login-link">सदस्य लॉगिन</a></p>
                <p><a href="/admin/login.php" data-testid="footer-admin-link">प्रशासकीय प्रवेश</a></p>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
