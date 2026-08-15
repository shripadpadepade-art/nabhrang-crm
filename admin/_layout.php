<?php
function admin_header(string $title): void {
    global $pdo;
    $adminName = $_SESSION['admin_name'] ?? '';
    $adminRole = $_SESSION['admin_role'] ?? '';
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    $orgName = setting($pdo, 'organization_name');
    $items = [
        ['index.php',         '⌂', 'डॅशबोर्ड',            'sidebar-dashboard-link'],
        ['settings.php',      '◈', 'संस्था सेटिंग्ज',       'sidebar-settings-link'],
        ['blogs.php',         '✎', 'ब्लॉग',                'sidebar-blogs-link'],
        ['events.php',        '❖', 'कार्यक्रम',             'sidebar-events-link'],
        ['gallery.php',       '❋', 'चित्रदालन',            'sidebar-gallery-link'],
        ['videos.php',        '▷', 'व्हिडिओ',              'sidebar-videos-link'],
        ['publications.php',  '❦', 'प्रकाशने',              'sidebar-publications-link'],
        ['notifications.php', '✦', 'सूचना',                'sidebar-notifications-link'],
        ['members.php',       '♧', 'सदस्य',                'sidebar-members-link'],
        ['payments.php',      '₹', 'देयके',                 'sidebar-payments-link'],
        ['reports.php',       '☰', 'अहवाल व निर्यात',       'sidebar-reports-link'],
    ];
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · <?= e($orgName) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="sidebar">
        <a class="brand" href="/admin/index.php" data-testid="admin-brand"><?= e($orgName) ?><small>प्रशासन कक्ष</small></a>
        <div class="side-label">मुख्य मेनू</div>
        <?php foreach ($items as [$file, $icon, $label, $testid]): ?>
            <a class="side-link <?= $current === $file ? 'active' : '' ?>" href="/admin/<?= e($file) ?>" data-testid="<?= e($testid) ?>"><span aria-hidden="true"><?= $icon ?></span> <span><?= e($label) ?></span></a>
        <?php endforeach; ?>
        <div class="side-label">सिस्टम</div>
        <span class="side-link" data-testid="sidebar-role-indicator" style="cursor:default;opacity:.75">◷ <span><?= e($adminRole === 'super_admin' ? 'सुपर अ‍ॅडमिन' : 'संपादक') ?></span></span>
        <form method="post" action="/admin/logout.php" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="side-link" style="width:100%;border:0;background:transparent;text-align:left;cursor:pointer" type="submit" data-testid="sidebar-logout-button">↪ <span>बाहेर पडा</span></button>
        </form>
    </aside>
    <div class="admin-main">
        <header class="admin-top">
            <span class="eyebrow">नियंत्रण केंद्र</span>
            <span data-testid="admin-user-name"><?= e($adminName) ?></span>
        </header>
        <div class="admin-content">
<?php
}

function admin_footer(): void { ?>
        </div>
    </div>
</div>
</body>
</html>
<?php }
