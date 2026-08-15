<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();
require __DIR__ . '/_layout.php';

$message = '';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE publications SET status='archived' WHERE id=?")->execute([$id]);
        audit($pdo, 'archived', 'publication', $id);
        $message = 'प्रकाशन संग्रहात हलवले.';
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $id = (int) $_POST['id'];
        $status = $action === 'publish' ? 'published' : 'draft';
        $pdo->prepare('UPDATE publications SET status=? WHERE id=?')->execute([$status, $id]);
        audit($pdo, $action, 'publication', $id);
        $message = 'स्थिती बदलली.';
    } else {
        $cover = save_upload($_FILES['cover'] ?? [], 'publications', ['jpg','jpeg','png','webp']);
        $pdf   = save_upload($_FILES['pdf'] ?? [], 'publications', ['pdf'], 15 * 1024 * 1024);
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $params = [
            'title_mr' => trim($_POST['title_mr'] ?? ''),
            'title_en' => trim($_POST['title_en'] ?? ''),
            'year' => $_POST['year'] !== '' ? (int) $_POST['year'] : null,
            'description_mr' => trim($_POST['description_mr'] ?? ''),
            'status' => $status,
        ];
        if ($editId) {
            $sql = 'UPDATE publications SET title_mr=:title_mr,title_en=:title_en,year=:year,description_mr=:description_mr,status=:status';
            if ($cover) $sql .= ',cover_path=:cover_path';
            if ($pdf)   $sql .= ',pdf_path=:pdf_path';
            $sql .= ' WHERE id=:id';
            $params['id'] = $editId;
            if ($cover) $params['cover_path'] = $cover;
            if ($pdf)   $params['pdf_path'] = $pdf;
            $pdo->prepare($sql)->execute($params);
            audit($pdo, 'updated', 'publication', $editId);
            $message = 'प्रकाशन जतन केले.';
        } else {
            $params['cover_path'] = $cover;
            $params['pdf_path'] = $pdf;
            $pdo->prepare('INSERT INTO publications(title_mr,title_en,year,description_mr,cover_path,pdf_path,status) VALUES(:title_mr,:title_en,:year,:description_mr,:cover_path,:pdf_path,:status)')->execute($params);
            audit($pdo, 'created', 'publication', (int) $pdo->lastInsertId());
            $message = 'नवीन प्रकाशन जतन केले.';
            $editId = 0;
        }
    }
}

$pub = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM publications WHERE id=?');
    $s->execute([$editId]);
    $pub = $s->fetch() ?: null;
}
$rows = $pdo->query("SELECT * FROM publications WHERE status<>'archived' ORDER BY year DESC, id DESC LIMIT 100")->fetchAll();

admin_header('प्रकाशने');
?>
<div class="admin-title"><h1 data-testid="publications-title">प्रकाशने</h1><p>दिवाळी अंक, वार्षिक अहवाल आणि सांस्कृतिक दस्तऐवज.</p></div>
<?php if ($message): ?><div class="notice" data-testid="publications-flash"><?= e($message) ?></div><?php endif; ?>
<div class="dashboard-grid">
    <section class="panel">
        <h2><?= $editId ? 'प्रकाशन संपादन' : 'नवीन प्रकाशन' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="setting-form">
                <div class="field full"><label>शीर्षक (मराठी)</label><input name="title_mr" value="<?= e($pub['title_mr'] ?? '') ?>" required data-testid="pub-title-input"></div>
                <div class="field full"><label>Title (English)</label><input name="title_en" value="<?= e($pub['title_en'] ?? '') ?>" data-testid="pub-title-en-input"></div>
                <div class="field"><label>वर्ष</label><input type="number" name="year" min="1900" max="2099" value="<?= e((string) ($pub['year'] ?? '')) ?>" data-testid="pub-year-input"></div>
                <div class="field"><label>स्थिती</label>
                    <select name="status" data-testid="pub-status-select">
                        <?php foreach (['draft'=>'मसुदा','published'=>'प्रकाशित','archived'=>'संग्रहित'] as $k=>$v): ?>
                            <option value="<?= e($k) ?>" <?= (($pub['status'] ?? 'draft') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full"><label>वर्णन</label><textarea name="description_mr" data-testid="pub-desc-input"><?= e($pub['description_mr'] ?? '') ?></textarea></div>
                <div class="field"><label>मुखपृष्ठ चित्र</label><input type="file" name="cover" accept="image/*" data-testid="pub-cover-input"><?php if (!empty($pub['cover_path'])): ?><small>सध्या: <?= e($pub['cover_path']) ?></small><?php endif; ?></div>
                <div class="field"><label>PDF फाइल</label><input type="file" name="pdf" accept="application/pdf" data-testid="pub-pdf-input"><?php if (!empty($pub['pdf_path'])): ?><small>सध्या: <?= e($pub['pdf_path']) ?></small><?php endif; ?></div>
            </div>
            <div class="form-actions">
                <?php if ($editId): ?><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/publications.php" data-testid="pub-cancel-button">रद्द करा</a><?php endif; ?>
                <button class="btn btn-gold" type="submit" data-testid="pub-save-button"><?= $editId ? 'बदल जतन करा' : 'प्रकाशन जतन करा' ?></button>
            </div>
        </form>
    </section>
    <section class="panel">
        <h2>सूची</h2>
        <?php if (!$rows): ?><p style="color:var(--muted)">अजून कोणतेही प्रकाशन नाही.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0;display:flex;gap:12px" data-testid="pub-row">
                <?php if (!empty($row['cover_path'])): ?><img src="<?= e($row['cover_path']) ?>" alt="" style="width:60px;height:80px;object-fit:cover;border-radius:3px"><?php endif; ?>
                <div style="flex:1">
                    <strong><?= e($row['title_mr']) ?></strong>
                    <div style="color:var(--muted);font-size:14px"><?= e((string) ($row['year'] ?? '')) ?> · <?= e($row['status']) ?></div>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                        <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" href="?edit=<?= e((string) $row['id']) ?>" data-testid="pub-edit-button">संपादन</a>
                        <?php if (!empty($row['pdf_path'])): ?><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" href="<?= e($row['pdf_path']) ?>" target="_blank" data-testid="pub-pdf-link">PDF</a><?php endif; ?>
                        <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                            <?php if ($row['status'] === 'published'): ?>
                                <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="unpublish" data-testid="pub-unpublish-button">अप्रकाशित</button>
                            <?php else: ?>
                                <button class="btn btn-gold" style="padding:6px 12px" name="action" value="publish" data-testid="pub-publish-button">प्रकाशित</button>
                            <?php endif; ?>
                            <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="delete" onclick="return confirm('प्रकाशन संग्रहात हलवायचे?')" data-testid="pub-delete-button">संग्रह</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php admin_footer(); ?>
