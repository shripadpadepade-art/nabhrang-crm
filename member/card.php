<?php
require __DIR__ . '/../config/bootstrap.php';
require_member();
$id = (int) $_SESSION['member_id'];

$s = $pdo->prepare('SELECT m.*, t.name_mr AS type_name, t.fee FROM members m LEFT JOIN membership_types t ON t.id=m.membership_type_id WHERE m.id=?');
$s->execute([$id]);
$member = $s->fetch();
if (!$member || $member['status'] !== 'approved' || empty($member['membership_id'])) {
    header('Location: /member/index.php');
    exit;
}
$fieldRows = $pdo->prepare("SELECT f.field_key, v.value_text FROM member_field_values v JOIN registration_fields f ON f.id=v.field_id WHERE v.member_id=?");
$fieldRows->execute([$id]);
$data = [];
foreach ($fieldRows as $row) { $data[$row['field_key']] = $row['value_text']; }
$fullName = $data['full_name'] ?? $member['email'];
$phone    = $data['phone'] ?? '';
$city     = $data['city'] ?? '';
$orgName  = setting($pdo, 'organization_name');
$logo     = setting($pdo, 'logo_path');
$valid    = $member['valid_until'] ?: date('Y-m-d', strtotime('+1 year', strtotime((string) ($member['joined_date'] ?? date('Y-m-d')))));
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>सदस्यत्व ओळखपत्र · <?= e($orgName) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
    <style>
        .card-wrap{min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#1a0e0f,#3a1618);padding:36px 16px}
        .id-card{width:min(440px,100%);background:linear-gradient(160deg,#faf6f0 0%,#f2e6d0 100%);border-radius:14px;box-shadow:0 30px 80px rgba(0,0,0,.5);overflow:hidden;position:relative;border:2px solid var(--gold)}
        .id-card:before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 100% 0,rgba(212,175,55,.25),transparent 60%);pointer-events:none}
        .id-head{background:var(--velvet);color:#fff;padding:20px 22px;display:flex;align-items:center;gap:12px}
        .id-head img{max-height:38px}
        .id-head h1{margin:0;font:600 20px 'Playfair Display',serif;letter-spacing:.02em}
        .id-head small{color:#f0d18a;letter-spacing:.16em;text-transform:uppercase;font-size:11px;display:block;margin-top:2px}
        .id-body{padding:22px;display:flex;gap:16px;align-items:center}
        .id-photo{width:96px;height:120px;background:#f2e6d0 center/cover no-repeat;border:2px solid var(--gold);border-radius:6px;flex:0 0 auto;display:grid;place-items:center;color:var(--muted);font-size:12px}
        .id-info{flex:1;min-width:0}
        .id-info .name{font:600 22px 'Playfair Display',serif;color:var(--ink);margin:0}
        .id-info dl{margin:12px 0 0;display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:14px}
        .id-info dt{color:var(--muted);text-transform:uppercase;letter-spacing:.1em;font-size:11px;align-self:center}
        .id-info dd{margin:0;color:var(--ink);font-weight:600}
        .id-foot{padding:14px 22px 22px;border-top:1px dashed rgba(139,30,36,.3);display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--muted)}
        .id-badge{background:var(--gold);color:#241416;padding:4px 10px;border-radius:999px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:11px}
        .actions{display:flex;gap:10px;margin-top:20px;justify-content:center;flex-wrap:wrap}
        @media print{
            body{background:#fff}
            .card-wrap{min-height:auto;background:#fff;padding:0}
            .actions,.no-print{display:none !important}
            .id-card{box-shadow:none;border-color:var(--velvet)}
        }
    </style>
<link rel="manifest" href="/manifest.json"><meta name="theme-color" content="#120b0c"><link rel="apple-touch-icon" href="/assets/icons/icon-180.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><script>if("serviceWorker" in navigator)navigator.serviceWorker.register("/sw.js");</script></head>
<body>
<div class="card-wrap">
    <div>
        <div class="id-card" data-testid="member-id-card">
            <div class="id-head">
                <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="logo"><?php endif; ?>
                <div>
                    <h1><?= e($orgName) ?></h1>
                    <small>सदस्यत्व ओळखपत्र</small>
                </div>
            </div>
            <div class="id-body">
                <div class="id-photo" style="<?= !empty($member['photo_path']) ? 'background-image:url(' . e($member['photo_path']) . ')' : '' ?>">
                    <?php if (empty($member['photo_path'])): ?>छायाचित्र<?php endif; ?>
                </div>
                <div class="id-info">
                    <p class="name" data-testid="card-member-name"><?= e($fullName) ?></p>
                    <dl>
                        <dt>सदस्य क्र.</dt><dd data-testid="card-membership-id"><?= e((string) $member['membership_id']) ?></dd>
                        <dt>प्रकार</dt><dd data-testid="card-membership-type"><?= e($member['type_name'] ?? '—') ?></dd>
                        <?php if ($phone): ?><dt>मोबाइल</dt><dd><?= e($phone) ?></dd><?php endif; ?>
                        <?php if ($city): ?><dt>शहर</dt><dd><?= e($city) ?></dd><?php endif; ?>
                        <dt>वैध पर्यंत</dt><dd data-testid="card-valid-until"><?= e((string) $valid) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="id-foot">
                <span>दिनांक: <?= e((string) ($member['joined_date'] ?? '—')) ?></span>
                <span class="id-badge">Nabhrang · नभरंग</span>
            </div>
        </div>
        <div class="actions no-print">
            <button class="btn btn-gold" onclick="window.print()" data-testid="card-print-button">प्रिंट / PDF सेव्ह करा</button>
            <a class="btn btn-outline" style="color:#fff;border-color:#e5bd56" href="/member/index.php" data-testid="card-back-button">डॅशबोर्डवर परत</a>
        </div>
    </div>
</div>
</body>
</html>
