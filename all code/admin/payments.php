<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $map = ['verify' => 'verified', 'reject' => 'rejected', 'cancel' => 'cancelled'];
    if (isset($map[$action]) && $id) {
        $status = $map[$action];
        $s = $pdo->prepare('SELECT member_id,status FROM payments WHERE id=?');
        $s->execute([$id]);
        $payment = $s->fetch();
        if ($payment) {
            $pdo->prepare('UPDATE payments SET status=?, verified_by=?, verified_at=IF(?="verified",NOW(),NULL), admin_note=? WHERE id=?')
                ->execute([$status, $_SESSION['admin_id'], $status, trim($_POST['note'] ?? ''), $id]);
            $pdo->prepare('INSERT INTO payment_status_history(payment_id,old_status,new_status,changed_by,remarks) VALUES(?,?,?,?,?)')
                ->execute([$id, $payment['status'], $status, $_SESSION['admin_id'], trim($_POST['note'] ?? '')]);
            $pdo->prepare('UPDATE members SET payment_status=? WHERE id=?')->execute([$status, $payment['member_id']]);
            if ($status === 'verified') {
                $pdo->prepare('UPDATE members SET status="approved", membership_id=COALESCE(membership_id,?), joined_date=COALESCE(joined_date,CURDATE()) WHERE id=?')
                    ->execute([next_membership_id($pdo), (int) $payment['member_id']]);
                member_history($pdo, (int) $payment['member_id'], 'payment_verified');
            } elseif ($status === 'rejected' || $status === 'cancelled') {
                member_history($pdo, (int) $payment['member_id'], 'payment_' . $status);
            }
            audit($pdo, 'payment_' . $status, 'payment', $id);
            $message = 'देयक स्थिती अपडेट केली.';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT p.*, m.email, m.membership_id, t.name_mr AS type_name FROM payments p JOIN members m ON m.id=p.member_id LEFT JOIN membership_types t ON t.id=m.membership_type_id WHERE 1=1';
$params = [];
if ($search !== '') { $sql .= ' AND (m.email LIKE ? OR m.membership_id LIKE ? OR p.utr LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if (in_array($statusFilter, ['pending','submitted','verified','rejected','cancelled','refunded','failed'], true)) { $sql .= ' AND p.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY p.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require __DIR__ . '/_layout.php';
admin_header('देयक पडताळणी');
?>
<div class="admin-title"><h1 data-testid="payments-title">देयक पडताळणी</h1><p>मॅन्युअल QR देयक तपासा आणि अधिकृत करा.</p></div>
<?php if ($message): ?><div class="notice" data-testid="payment-action-success"><?= e($message) ?></div><?php endif; ?>

<section class="panel">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="flex:1;min-width:200px"><label>शोध (ईमेल / सदस्य क्र. / UTR)</label><input name="q" value="<?= e($search) ?>" data-testid="payment-search-input"></div>
        <div class="field"><label>स्थिती</label>
            <select name="status" data-testid="payment-status-filter">
                <option value="">सर्व</option>
                <?php foreach (['pending'=>'प्रलंबित','submitted'=>'पडताळणीसाठी','verified'=>'पडताळलेले','rejected'=>'नाकारलेले','cancelled'=>'रद्द केलेले'] as $k=>$v): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter===$k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-gold" type="submit" data-testid="payment-filter-button">फिल्टर लावा</button>
        <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/reports.php?export=payments" data-testid="payment-export-link">CSV निर्यात</a>
    </form>
</section>

<section class="panel" style="margin-top:22px">
    <?php if (!$payments): ?><p style="color:var(--muted)">कोणतीही देयके सापडली नाहीत.</p><?php endif; ?>
    <?php foreach ($payments as $p): ?>
        <article style="border-bottom:1px solid var(--line);padding:14px 0" data-testid="admin-payment-row">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
                <div>
                    <strong><?= e($p['email']) ?></strong>
                    <?php if (!empty($p['membership_id'])): ?><span style="color:var(--muted)"> · <?= e($p['membership_id']) ?></span><?php endif; ?>
                    <div style="color:var(--muted);font-size:14px">₹<?= e((string) $p['amount']) ?> · UTR: <?= e((string) ($p['utr'] ?? '')) ?> · दिनांक: <?= e((string) ($p['payment_date'] ?? '')) ?> · <?= e($p['type_name'] ?? '') ?></div>
                    <div style="color:var(--muted);font-size:13px">स्थिती: <strong><?= e($p['status']) ?></strong><?php if (!empty($p['admin_note'])): ?> · टीप: <?= e($p['admin_note']) ?><?php endif; ?></div>
                </div>
                <?php if (in_array($p['status'], ['submitted','pending'], true)): ?>
                    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                        <input name="note" placeholder="टीप (ऐच्छिक)" data-testid="payment-note-input" style="min-width:180px">
                        <button class="btn btn-gold" name="action" value="verify" type="submit" data-testid="verify-payment-button">पडताळणी करा</button>
                        <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" name="action" value="reject" type="submit" data-testid="reject-payment-button">नाकार</button>
                        <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" name="action" value="cancel" type="submit" data-testid="cancel-payment-button">रद्द</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (!empty($p['screenshot_path'])): ?><a href="<?= e($p['screenshot_path']) ?>" target="_blank" style="color:var(--velvet);font-size:14px" data-testid="payment-screenshot-link">देयक स्क्रीनशॉट पहा</a><?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php admin_footer(); ?>
