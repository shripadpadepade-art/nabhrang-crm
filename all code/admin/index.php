<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();
require __DIR__ . '/_layout.php';

$stats = [
    'सर्व सदस्य'          => (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn(),
    'प्रलंबित सदस्य'       => (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='pending'")->fetchColumn(),
    'पडताळणीसाठी देयके'   => (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status IN('submitted','pending')")->fetchColumn(),
    'एकूण जमा (₹)'        => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'")->fetchColumn(),
    'प्रकाशित ब्लॉग'       => (int) $pdo->query("SELECT COUNT(*) FROM blogs WHERE status='published'")->fetchColumn(),
    'आगामी कार्यक्रम'       => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status='published' AND (event_date IS NULL OR event_date >= CURDATE())")->fetchColumn(),
    'व्हिडिओ'              => (int) $pdo->query("SELECT COUNT(*) FROM videos WHERE status='published'")->fetchColumn(),
    'प्रकाशने'             => (int) $pdo->query("SELECT COUNT(*) FROM publications WHERE status='published'")->fetchColumn(),
];

$recentMembers  = $pdo->query('SELECT id,email,status,created_at FROM members ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recentPayments = $pdo->query('SELECT p.id,p.amount,p.utr,p.status,m.email FROM payments p JOIN members m ON m.id=p.member_id ORDER BY p.created_at DESC LIMIT 5')->fetchAll();
$recentBlogs    = $pdo->query("SELECT id,title_mr,status,created_at FROM blogs WHERE status<>'archived' ORDER BY created_at DESC LIMIT 5")->fetchAll();
$upcomingEvents = $pdo->query("SELECT id,title_mr,event_date FROM events WHERE status='published' AND (event_date IS NULL OR event_date >= CURDATE()) ORDER BY event_date ASC LIMIT 5")->fetchAll();

admin_header('डॅशबोर्ड');
?>
<div class="admin-title">
    <h1 data-testid="dashboard-title">नमस्कार, <?= e($_SESSION['admin_name'] ?? '') ?></h1>
    <p data-testid="dashboard-subtitle"><?= e(setting($pdo,'organization_name')) ?>च्या डिजिटल व्यासपीठाचा आजचा आढावा.</p>
</div>
<div class="stats">
    <?php foreach ($stats as $label => $value): ?>
        <article class="stat-card" data-testid="stat-card-<?= e($label) ?>">
            <span class="icon">✦</span>
            <div class="label"><?= e($label) ?></div>
            <div class="value" data-testid="stat-value-<?= e($label) ?>"><?= is_float($value) ? '₹' . number_format($value, 2) : e((string) $value) ?></div>
        </article>
    <?php endforeach; ?>
</div>
<div class="dashboard-grid">
    <section class="panel">
        <h2 data-testid="quick-actions-title">जलद कृती</h2>
        <p>तुमच्या साइटची भाषा, संपर्क आणि पहिल्या पानाचा अनुभव एका ठिकाणी व्यवस्थापित करा.</p>
        <div class="hero-actions">
            <a class="btn btn-gold" href="/admin/settings.php" data-testid="dashboard-settings-button">सेटिंग्ज संपादित करा</a>
            <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/" data-testid="dashboard-view-site-button">साइट पहा</a>
            <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/reports.php" data-testid="dashboard-reports-button">अहवाल</a>
        </div>
    </section>
    <section class="panel">
        <h2 data-testid="health-title">सिस्टम स्थिती</h2>
        <div class="notice" data-testid="system-status">सर्व मूलभूत सेवा तयार आहेत.</div>
        <p style="margin:0;color:var(--muted)">सुरक्षित सत्र · PDO तयार स्टेटमेंट्स · ऑडिट लॉग सक्रिय <?= setting($pdo,'maintenance_mode')==='1' ? ' · देखभाल मोड सुरू' : '' ?></p>
    </section>
</div>
<div class="dashboard-grid" style="margin-top:22px">
    <section class="panel">
        <h2>अलीकडील नोंदणी</h2>
        <?php foreach ($recentMembers as $r): ?>
            <p data-testid="recent-member-row"><strong><?= e($r['email']) ?></strong> · <?= e($r['status']) ?> · <?= e((string) $r['created_at']) ?></p>
        <?php endforeach; ?>
        <?php if (!$recentMembers): ?><p style="color:var(--muted)">अजून कोणतीही नोंदणी नाही.</p><?php endif; ?>
    </section>
    <section class="panel">
        <h2>अलीकडील देयके</h2>
        <?php foreach ($recentPayments as $r): ?>
            <p data-testid="recent-payment-row"><?= e($r['email']) ?> · ₹<?= e((string) $r['amount']) ?> · <?= e($r['status']) ?> · UTR: <?= e($r['utr'] ?? '') ?></p>
        <?php endforeach; ?>
        <?php if (!$recentPayments): ?><p style="color:var(--muted)">अजून कोणतीही देयके नाहीत.</p><?php endif; ?>
    </section>
    <section class="panel">
        <h2>अलीकडील ब्लॉग</h2>
        <?php foreach ($recentBlogs as $r): ?>
            <p data-testid="recent-blog-row"><strong><?= e($r['title_mr']) ?></strong> · <?= e($r['status']) ?></p>
        <?php endforeach; ?>
        <?php if (!$recentBlogs): ?><p style="color:var(--muted)">अजून कोणताही ब्लॉग नाही.</p><?php endif; ?>
    </section>
    <section class="panel">
        <h2>आगामी कार्यक्रम</h2>
        <?php foreach ($upcomingEvents as $r): ?>
            <p data-testid="upcoming-event-row"><strong><?= e($r['title_mr']) ?></strong> · <?= e((string) ($r['event_date'] ?? '')) ?></p>
        <?php endforeach; ?>
        <?php if (!$upcomingEvents): ?><p style="color:var(--muted)">आगामी कार्यक्रम नाहीत.</p><?php endif; ?>
    </section>
</div>
<?php admin_footer(); ?>
