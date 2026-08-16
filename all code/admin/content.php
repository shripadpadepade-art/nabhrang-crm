<?php
// Legacy content hub → guides admins to the new dedicated modules.
require __DIR__ . '/../config/bootstrap.php';
require_admin();
require __DIR__ . '/_layout.php';
$kind = $_GET['kind'] ?? '';
$map = [
    'blogs' => '/admin/blogs.php', 'events' => '/admin/events.php', 'videos' => '/admin/videos.php',
    'publications' => '/admin/publications.php', 'notifications' => '/admin/notifications.php',
    'gallery_albums' => '/admin/gallery.php', 'gallery' => '/admin/gallery.php',
];
if (isset($map[$kind])) { header('Location: ' . $map[$kind]); exit; }
admin_header('सामग्री');
?>
<div class="admin-title"><h1 data-testid="content-hub-title">सामग्री व्यवस्थापन</h1><p>प्रत्येक सामग्री प्रकाराचे स्वतंत्र विभाग खाली दिले आहेत.</p></div>
<div class="stats">
    <?php $links = [
        ['ब्लॉग', '/admin/blogs.php', 'content-blogs-link'],
        ['कार्यक्रम', '/admin/events.php', 'content-events-link'],
        ['चित्रदालन', '/admin/gallery.php', 'content-gallery-link'],
        ['व्हिडिओ', '/admin/videos.php', 'content-videos-link'],
        ['प्रकाशने', '/admin/publications.php', 'content-publications-link'],
        ['सूचना', '/admin/notifications.php', 'content-notifications-link'],
    ]; foreach ($links as [$label, $url, $tid]): ?>
        <a class="stat-card" href="<?= e($url) ?>" data-testid="<?= e($tid) ?>" style="text-decoration:none">
            <span class="icon">✦</span>
            <div class="label">विभाग</div>
            <div class="value"><?= e($label) ?></div>
        </a>
    <?php endforeach; ?>
</div>
<?php admin_footer(); ?>
