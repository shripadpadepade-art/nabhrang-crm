<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();
require __DIR__ . '/_layout.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE notifications SET status='archived' WHERE id=?")->execute([$id]);
        audit($pdo, 'archived', 'notification', $id);
        $message = 'सूचना संग्रहात हलवली.';
    } else {
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $stmt = $pdo->prepare('INSERT INTO notifications(title_mr,body_mr,status,publish_at) VALUES(?,?,?,IF(?="published",NOW(),NULL))');
        $stmt->execute([trim($_POST['title_mr'] ?? ''), trim($_POST['body_mr'] ?? ''), $status, $status]);
        audit($pdo, 'created', 'notification', (int) $pdo->lastInsertId());
        $message = 'सूचना जतन केली.';
    }
}
$rows = $pdo->query("SELECT * FROM notifications WHERE status<>'archived' ORDER BY publish_at DESC, id DESC LIMIT 100")->fetchAll();

admin_header('सूचना');
?>
<div class="admin-title"><h1 data-testid="notifications-title">सूचना व घोषणा</h1><p>सदस्य आणि जनतेसाठी अल्पकालीन घोषणा प्रकाशित करा.</p></div>
<?php if ($message): ?><div class="notice" data-testid="notifications-flash"><?= e($message) ?></div><?php endif; ?>
<div class="dashboard-grid">
    <section class="panel">
        <h2>नवीन सूचना</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="field"><label>शीर्षक</label><input name="title_mr" required data-testid="notif-title-input"></div>
            <div class="field"><label>तपशील</label><textarea name="body_mr" data-testid="notif-body-input"></textarea></div>
            <div class="field"><label>स्थिती</label>
                <select name="status" data-testid="notif-status-select">
                    <option value="draft">मसुदा</option>
                    <option value="published" selected>प्रकाशित</option>
                </select>
            </div>
            <div class="form-actions"><button class="btn btn-gold" type="submit" data-testid="notif-save-button">प्रकाशित करा</button></div>
        </form>
    </section>
    <section class="panel">
        <h2>सक्रिय सूचना</h2>
        <?php if (!$rows): ?><p style="color:var(--muted)">अजून कोणतीही सूचना नाही.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0" data-testid="notif-row">
                <strong><?= e($row['title_mr']) ?></strong>
                <div style="color:var(--muted);font-size:14px"><?= e($row['status']) ?> · <?= e((string) ($row['publish_at'] ?? $row['created_at'])) ?></div>
                <p style="margin:6px 0 0"><?= e($row['body_mr'] ?? '') ?></p>
                <form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                    <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="delete" onclick="return confirm('सूचना काढायची?')" data-testid="notif-delete-button">संग्रह</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php admin_footer(); ?>
