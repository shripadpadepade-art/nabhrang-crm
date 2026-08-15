<?php
require __DIR__ . '/../config/bootstrap.php';
require_member();
$id = (int) $_SESSION['member_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (float) ($_POST['amount'] ?? 0);
    $utr = trim($_POST['utr'] ?? '');
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    if ($amount > 0 && $utr) {
        $screenshot = save_upload($_FILES['screenshot'] ?? [], 'payments', ['jpg','jpeg','png','webp']);
        $p = $pdo->prepare('INSERT INTO payments(member_id,amount,utr,payment_date,payment_method,screenshot_path,status) VALUES(?,?,?,?,?,?,?)');
        $p->execute([$id, $amount, $utr, $date, 'UPI / QR', $screenshot, 'submitted']);
        $paymentId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO payment_status_history(payment_id,old_status,new_status,changed_by,remarks) VALUES(?,?,?,?,?)')
            ->execute([$paymentId, null, 'submitted', null, 'Member submitted']);
        $pdo->prepare('UPDATE members SET payment_status="submitted" WHERE id=?')->execute([$id]);
        member_history($pdo, $id, 'payment_submitted', $utr);
        $message = 'देयक तपशील पडताळणीसाठी पाठवले आहेत.';
    } else {
        $error = 'कृपया वैध रक्कम आणि UTR क्रमांक भरा.';
    }
}

$s = $pdo->prepare('SELECT m.*, t.name_mr AS type_name, t.fee FROM members m LEFT JOIN membership_types t ON t.id=m.membership_type_id WHERE m.id=?');
$s->execute([$id]);
$member = $s->fetch();
if (!$member) { unset($_SESSION['member_id']); header('Location: /member/login.php'); exit; }

$fieldsStmt = $pdo->prepare('SELECT f.field_key,f.label_mr,v.value_text FROM member_field_values v JOIN registration_fields f ON f.id=v.field_id WHERE v.member_id=? ORDER BY f.sort_order');
$fieldsStmt->execute([$id]);
$fields = $fieldsStmt->fetchAll();
$memberName = '';
foreach ($fields as $f) { if ($f['field_key'] === 'full_name') { $memberName = trim((string) $f['value_text']); break; } }
$paymentsStmt = $pdo->prepare('SELECT amount,utr,payment_date,status,created_at FROM payments WHERE member_id=? ORDER BY id DESC');
$paymentsStmt->execute([$id]);
$payments = $paymentsStmt->fetchAll();
$historyStmt = $pdo->prepare('SELECT action,details,created_at FROM member_history WHERE member_id=? ORDER BY id DESC');
$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$orgName = setting($pdo, 'organization_name');
$qr = setting($pdo, 'payment_qr_url');
$upi = setting($pdo, 'upi_id');
$instr = setting($pdo, 'payment_instructions');
$isApproved = $member['status'] === 'approved' && !empty($member['membership_id']);
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>सदस्य डॅशबोर्ड · <?= e($orgName) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body class="admin-body">
<header class="public-nav" style="position:relative;background:var(--night)">
    <div class="container nav-row">
        <a class="brand" href="/" data-testid="member-dashboard-brand"><?= e($orgName) ?><small>सदस्य कक्ष</small></a>
        <form method="post" action="/member/logout.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-outline" type="submit" data-testid="member-logout-button">बाहेर पडा</button>
        </form>
    </div>
</header>
<main class="container section">
    <div class="admin-title">
        <h1 data-testid="member-dashboard-title">नमस्कार<?= $memberName ? ', ' . e($memberName) : '' ?></h1>
        <p data-testid="member-status">सदस्यत्व स्थिती: <strong><?= e($member['status']) ?></strong> · देयक: <strong><?= e($member['payment_status']) ?></strong></p>
        <?php if ($isApproved): ?>
            <a class="btn btn-gold" href="/member/card.php" data-testid="member-card-link">सदस्यत्व ओळखपत्र पहा / प्रिंट करा</a>
        <?php endif; ?>
    </div>

    <?php if ($message): ?><div class="notice" data-testid="payment-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error" data-testid="payment-error"><?= e($error) ?></div><?php endif; ?>

    <div class="dashboard-grid" style="margin-top:22px">
        <section class="panel">
            <h2>प्रोफाइल</h2>
            <p data-testid="member-email">ईमेल: <?= e($member['email']) ?></p>
            <p data-testid="member-type">प्रकार: <?= e($member['type_name'] ?? '—') ?></p>
            <p data-testid="member-id">सदस्य क्रमांक: <?= e($member['membership_id'] ?: 'पडताळणीनंतर मिळेल') ?></p>
            <?php foreach ($fields as $f): ?>
                <p data-testid="member-field-<?= e($f['label_mr']) ?>"><?= e($f['label_mr']) ?>: <?= e((string) ($f['value_text'] ?? '')) ?></p>
            <?php endforeach; ?>
        </section>

        <section class="panel">
            <h2>QR देयक</h2>
            <p><?= e($instr) ?></p>
            <?php if ($qr): ?>
                <img src="<?= e($qr) ?>" alt="QR" style="max-width:220px;border:6px solid #fff;box-shadow:0 8px 30px rgba(0,0,0,.15);border-radius:6px" data-testid="payment-qr-image">
            <?php else: ?>
                <div class="notice">QR कोड अद्याप सेट केलेला नाही. कृपया प्रशासकाशी संपर्क साधा.</div>
            <?php endif; ?>
            <div class="notice">शुल्क: ₹<?= e((string) $member['fee']) ?><?= $upi ? ' · UPI: ' . e($upi) : '' ?></div>
            <?php if (in_array($member['payment_status'], ['not_submitted','pending','rejected','cancelled'], true)): ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="field"><label>भरलेली रक्कम</label><input type="number" step="0.01" name="amount" required data-testid="payment-amount-input"></div>
                <div class="field"><label>UTR / व्यवहार क्रमांक</label><input name="utr" required data-testid="payment-utr-input"></div>
                <div class="field"><label>देयक दिनांक</label><input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required data-testid="payment-date-input"></div>
                <div class="field"><label>देयक स्क्रीनशॉट (ऐच्छिक)</label><input type="file" name="screenshot" accept="image/*" data-testid="payment-screenshot-input"></div>
                <div class="form-actions"><button class="btn btn-gold" type="submit" data-testid="payment-submit-button">देयक तपशील पाठवा</button></div>
            </form>
            <?php else: ?>
                <p style="color:var(--muted);margin-top:10px">तुमचे देयक तपशील प्रशासकाकडे नोंदवले आहेत.</p>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel" style="margin-top:22px">
        <h2>देयक इतिहास</h2>
        <?php foreach ($payments as $p): ?>
            <p data-testid="payment-history-row">₹<?= e((string) $p['amount']) ?> · UTR: <?= e((string) $p['utr']) ?> · <?= e($p['status']) ?> · <?= e((string) $p['created_at']) ?></p>
        <?php endforeach; ?>
        <?php if (!$payments): ?><p style="color:var(--muted)">अजून कोणतीही देयके नाहीत.</p><?php endif; ?>
    </section>

    <section class="panel" style="margin-top:22px">
        <h2>सदस्य इतिहास</h2>
        <?php foreach ($history as $h): ?>
            <p data-testid="member-history-row"><?= e($h['action']) ?><?= !empty($h['details']) ? ' · ' . e($h['details']) : '' ?> · <?= e((string) $h['created_at']) ?></p>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
