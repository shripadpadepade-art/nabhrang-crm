<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'type') {
        $s = $pdo->prepare('INSERT INTO membership_types(name_mr,name_en,description_mr,fee) VALUES(?,?,?,?)');
        $s->execute([trim($_POST['name_mr']), trim($_POST['name_en']), trim($_POST['description_mr']), max(0, (float) $_POST['fee'])]);
        audit($pdo, 'created', 'membership_type', (int) $pdo->lastInsertId());
        $message = 'सदस्यत्व प्रकार जतन केला.';
    } elseif ($action === 'field') {
        $s = $pdo->prepare('INSERT INTO registration_fields(field_key,label_mr,label_en,field_type,is_required,sort_order) VALUES(?,?,?,?,?,?)');
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ($_POST['field_key'] ?? '')));
        $type = in_array($_POST['field_type'] ?? 'text', ['text','email','tel','date','textarea','select','file'], true) ? $_POST['field_type'] : 'text';
        $s->execute([$key, trim($_POST['label_mr']), trim($_POST['label_en']), $type, isset($_POST['is_required']) ? 1 : 0, (int) $_POST['sort_order']]);
        audit($pdo, 'created', 'registration_field', (int) $pdo->lastInsertId());
        $message = 'नोंदणी फील्ड जतन केले.';
    } elseif (in_array($action, ['approve','reject','suspend','archive','restore'], true)) {
        $map = ['approve'=>'approved','reject'=>'rejected','suspend'=>'suspended','archive'=>'archived','restore'=>'pending'];
        $status = $map[$action];
        $mid = $status === 'approved' ? next_membership_id($pdo) : null;
        $s = $pdo->prepare('UPDATE members SET status=?, membership_id=COALESCE(?,membership_id), joined_date=IF(?="approved" AND joined_date IS NULL, CURDATE(), joined_date) WHERE id=?');
        $s->execute([$status, $mid, $status, $id]);
        member_history($pdo, $id, 'member_' . $status);
        audit($pdo, 'updated', 'member', $id);
        $message = 'सदस्य स्थिती अपडेट केली.';
    }
}

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT m.id, m.email, m.status, m.payment_status, m.membership_id, m.created_at, t.name_mr AS type_name FROM members m LEFT JOIN membership_types t ON t.id=m.membership_type_id WHERE 1=1';
$params = [];
if ($search !== '') { $sql .= ' AND (m.email LIKE ? OR m.membership_id LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if (in_array($statusFilter, ['pending','approved','rejected','suspended','archived'], true)) { $sql .= ' AND m.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY m.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$types  = $pdo->query('SELECT * FROM membership_types ORDER BY id DESC')->fetchAll();
$fields = $pdo->query('SELECT * FROM registration_fields ORDER BY sort_order,id')->fetchAll();

require __DIR__ . '/_layout.php';
admin_header('सदस्य व्यवस्थापन');
?>
<div class="admin-title"><h1 data-testid="members-title">सदस्य व्यवस्थापन</h1><p>नोंदणी, सदस्यत्व प्रकार आणि फॉर्म फील्ड एका ठिकाणी.</p></div>
<?php if ($message): ?><div class="notice" data-testid="member-action-success"><?= e($message) ?></div><?php endif; ?>

<section class="panel">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="flex:1;min-width:200px"><label>शोध (ईमेल / सदस्य क्रमांक)</label><input name="q" value="<?= e($search) ?>" data-testid="member-search-input"></div>
        <div class="field"><label>स्थिती</label>
            <select name="status" data-testid="member-status-filter">
                <option value="">सर्व</option>
                <?php foreach (['pending'=>'प्रलंबित','approved'=>'मंजूर','rejected'=>'नाकारलेले','suspended'=>'निलंबित','archived'=>'संग्रहित'] as $k=>$v): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter===$k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-gold" type="submit" data-testid="member-filter-button">फिल्टर लावा</button>
    </form>
</section>

<div class="dashboard-grid" style="margin-top:22px">
    <section class="panel">
        <h2>नवीन सदस्यत्व प्रकार</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="type">
            <div class="field"><label>नाव (मराठी)</label><input name="name_mr" required data-testid="membership-type-name-input"></div>
            <div class="field"><label>शुल्क (₹)</label><input type="number" name="fee" min="0" step="0.01" required data-testid="membership-type-fee-input"></div>
            <div class="field"><label>English name</label><input name="name_en" data-testid="membership-type-en-input"></div>
            <div class="field"><label>वर्णन</label><textarea name="description_mr" data-testid="membership-type-description-input"></textarea></div>
            <button class="btn btn-gold" type="submit" data-testid="membership-type-save-button">जतन करा</button>
        </form>

        <hr style="margin:22px 0;border:0;border-top:1px solid var(--line)">

        <h2>नोंदणी फील्ड जोडा</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="field">
            <div class="field"><label>Field key (a-z, _)</label><input name="field_key" required data-testid="registration-field-key-input"></div>
            <div class="field"><label>लेबल (मराठी)</label><input name="label_mr" required data-testid="registration-field-label-input"></div>
            <div class="field"><label>English label</label><input name="label_en" data-testid="registration-field-label-en-input"></div>
            <div class="field"><label>प्रकार</label>
                <select name="field_type" data-testid="registration-field-type-select">
                    <?php foreach (['text','email','tel','date','textarea','select','file'] as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>क्रम</label><input type="number" name="sort_order" value="99" data-testid="registration-field-sort-input"></div>
            <label style="margin:10px 0"><input type="checkbox" name="is_required" data-testid="registration-field-required-checkbox"> आवश्यक</label>
            <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);margin-top:14px" type="submit" data-testid="registration-field-save-button">फील्ड जोडा</button>
        </form>

        <h2 style="margin-top:22px">वर्तमान फील्ड्स</h2>
        <?php foreach ($fields as $f): ?>
            <p style="margin:4px 0;color:var(--muted)" data-testid="registration-field-row"><strong><?= e($f['label_mr']) ?></strong> · <?= e($f['field_key']) ?> · <?= e($f['field_type']) ?><?= $f['is_required'] ? ' · आवश्यक' : '' ?></p>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <h2>सदस्य सूची</h2>
        <?php if (!$members): ?><p style="color:var(--muted)">कोणतेही सदस्य आढळले नाहीत.</p><?php endif; ?>
        <?php foreach ($members as $m): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0" data-testid="member-row">
                <strong><?= e($m['email']) ?></strong>
                <?php if (!empty($m['membership_id'])): ?><span style="color:var(--muted)"> · <?= e($m['membership_id']) ?></span><?php endif; ?>
                <div style="color:var(--muted);font-size:14px"><?= e($m['type_name'] ?? '—') ?> · सदस्य: <?= e($m['status']) ?> · देयक: <?= e($m['payment_status']) ?> · <?= e((string) $m['created_at']) ?></div>
                <form method="post" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $m['id']) ?>">
                    <?php if ($m['status'] === 'pending'): ?>
                        <button class="btn btn-gold" style="padding:6px 12px" name="action" value="approve" data-testid="approve-member-button">मंजूर</button>
                        <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="reject" data-testid="reject-member-button">नाकार</button>
                    <?php endif; ?>
                    <?php if ($m['status'] === 'approved'): ?>
                        <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="suspend" data-testid="suspend-member-button">निलंबन</button>
                    <?php endif; ?>
                    <?php if ($m['status'] !== 'archived'): ?>
                        <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="archive" onclick="return confirm('संग्रहात हलवायचे?')" data-testid="archive-member-button">संग्रह</button>
                    <?php else: ?>
                        <button class="btn btn-gold" style="padding:6px 12px" name="action" value="restore" data-testid="restore-member-button">पुनर्संचयन</button>
                    <?php endif; ?>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php admin_footer(); ?>
