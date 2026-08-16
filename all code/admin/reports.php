<?php
require __DIR__ . '/../config/bootstrap.php';
require_role('super_admin');

$export = $_GET['export'] ?? '';

if ($export === 'members') {
    $stmt = $pdo->query("SELECT m.membership_id,m.email,t.name_mr AS type_name,m.status,m.payment_status,m.joined_date,m.created_at FROM members m LEFT JOIN membership_types t ON t.id=m.membership_type_id ORDER BY m.created_at DESC");
    csv_download('nabhrang-members-' . date('Ymd') . '.csv',
        ['सदस्य क्रमांक','ईमेल','सदस्यत्व प्रकार','सदस्य स्थिती','देयक स्थिती','सदस्यत्व दिनांक','नोंदणी दिनांक'],
        (function () use ($stmt) { while ($r = $stmt->fetch()) yield $r; })()
    );
}

if ($export === 'payments') {
    $stmt = $pdo->query('SELECT p.id,m.membership_id,m.email,p.amount,p.utr,p.payment_date,p.status,p.verified_at,p.admin_note FROM payments p JOIN members m ON m.id=p.member_id ORDER BY p.created_at DESC');
    csv_download('nabhrang-payments-' . date('Ymd') . '.csv',
        ['देयक क्र.','सदस्य क्रमांक','ईमेल','रक्कम','UTR','देयक दिनांक','स्थिती','पडताळणी दिनांक','टीप'],
        (function () use ($stmt) { while ($r = $stmt->fetch()) yield $r; })()
    );
}

require __DIR__ . '/_layout.php';

$totals = [
    'सर्व सदस्य'         => (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn(),
    'मंजूर सदस्य'        => (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='approved'")->fetchColumn(),
    'प्रलंबित सदस्य'      => (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='pending'")->fetchColumn(),
    'पडताळणीसाठी देयके'  => (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status IN('submitted','pending')")->fetchColumn(),
    'पडताळलेली देयके'     => (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status='verified'")->fetchColumn(),
    'एकूण जमा (₹)'       => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'")->fetchColumn(),
];

$byMonth = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS c FROM members GROUP BY ym ORDER BY ym DESC LIMIT 12")->fetchAll();
$byType  = $pdo->query('SELECT t.name_mr, COUNT(m.id) AS c FROM membership_types t LEFT JOIN members m ON m.membership_type_id=t.id GROUP BY t.id ORDER BY c DESC')->fetchAll();
$payMonth = $pdo->query("SELECT DATE_FORMAT(payment_date,'%Y-%m') AS ym, COALESCE(SUM(amount),0) AS s FROM payments WHERE status='verified' GROUP BY ym ORDER BY ym DESC LIMIT 12")->fetchAll();

admin_header('अहवाल व निर्यात');
?>
<div class="admin-title"><h1 data-testid="reports-title">अहवाल व निर्यात</h1><p>सदस्य आणि आर्थिक स्थितीचा त्वरित आढावा.</p></div>
<div class="stats">
    <?php foreach ($totals as $label => $value): ?>
        <article class="stat-card" data-testid="report-stat-<?= e($label) ?>">
            <span class="icon">✦</span>
            <div class="label"><?= e($label) ?></div>
            <div class="value" data-testid="report-value-<?= e($label) ?>"><?= is_float($value) ? '₹' . number_format($value, 2) : e((string) $value) ?></div>
        </article>
    <?php endforeach; ?>
</div>
<div class="dashboard-grid">
    <section class="panel">
        <h2>महिन्यानुसार नवीन सदस्य</h2>
        <table style="width:100%;border-collapse:collapse" data-testid="members-by-month-table">
            <thead><tr><th style="text-align:left;padding:8px 0;border-bottom:1px solid var(--line)">महिना</th><th style="text-align:right;padding:8px 0;border-bottom:1px solid var(--line)">संख्या</th></tr></thead>
            <tbody>
                <?php foreach ($byMonth as $r): ?><tr><td style="padding:8px 0"><?= e($r['ym']) ?></td><td style="text-align:right"><?= e((string) $r['c']) ?></td></tr><?php endforeach; ?>
                <?php if (!$byMonth): ?><tr><td colspan="2" style="color:var(--muted);padding:8px 0">डेटा उपलब्ध नाही.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>सदस्यत्व प्रकारानुसार</h2>
        <table style="width:100%;border-collapse:collapse" data-testid="members-by-type-table">
            <thead><tr><th style="text-align:left;padding:8px 0;border-bottom:1px solid var(--line)">प्रकार</th><th style="text-align:right;padding:8px 0;border-bottom:1px solid var(--line)">सदस्य</th></tr></thead>
            <tbody>
                <?php foreach ($byType as $r): ?><tr><td style="padding:8px 0"><?= e($r['name_mr']) ?></td><td style="text-align:right"><?= e((string) $r['c']) ?></td></tr><?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>महिन्यानुसार जमा (₹)</h2>
        <table style="width:100%;border-collapse:collapse" data-testid="payments-by-month-table">
            <thead><tr><th style="text-align:left;padding:8px 0;border-bottom:1px solid var(--line)">महिना</th><th style="text-align:right;padding:8px 0;border-bottom:1px solid var(--line)">रक्कम</th></tr></thead>
            <tbody>
                <?php foreach ($payMonth as $r): ?><tr><td style="padding:8px 0"><?= e($r['ym']) ?></td><td style="text-align:right">₹<?= e(number_format((float) $r['s'], 2)) ?></td></tr><?php endforeach; ?>
                <?php if (!$payMonth): ?><tr><td colspan="2" style="color:var(--muted);padding:8px 0">डेटा उपलब्ध नाही.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>CSV निर्यात</h2>
        <p style="color:var(--muted)">Excel मध्ये उघडण्यायोग्य CSV फाइल्स डाउनलोड करा.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
            <a class="btn btn-gold" href="?export=members" data-testid="export-members-button">सदस्य CSV डाउनलोड</a>
            <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="?export=payments" data-testid="export-payments-button">देयक CSV डाउनलोड</a>
        </div>
    </section>
</div>
<?php admin_footer(); ?>
