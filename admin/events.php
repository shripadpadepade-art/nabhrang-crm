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
        $pdo->prepare("UPDATE events SET status='archived' WHERE id=?")->execute([$id]);
        audit($pdo, 'archived', 'event', $id);
        $message = 'कार्यक्रम संग्रहात हलवला.';
    } elseif ($action === 'restore') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE events SET status='draft' WHERE id=?")->execute([$id]);
        audit($pdo, 'restored', 'event', $id);
        $message = 'कार्यक्रम पुनर्संचयित केला (मसुदा म्हणून).';
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $id = (int) $_POST['id'];
        $status = $action === 'publish' ? 'published' : 'draft';
        $pdo->prepare('UPDATE events SET status=? WHERE id=?')->execute([$status, $id]);
        audit($pdo, $action, 'event', $id);
        $message = 'स्थिती बदलली.';
    } else {
        $poster = save_upload($_FILES['poster'] ?? [], 'events', ['jpg','jpeg','png','webp']);
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $params = [
            'title_mr'         => trim($_POST['title_mr'] ?? ''),
            'title_en'         => trim($_POST['title_en'] ?? ''),
            'description_mr'   => trim($_POST['description_mr'] ?? ''),
            'event_date'       => $_POST['event_date'] ?: null,
            'event_time'       => $_POST['event_time'] ?: null,
            'venue'            => trim($_POST['venue'] ?? ''),
            'registration_url' => trim($_POST['registration_url'] ?? ''),
            'status'           => $status,
        ];
        if ($editId) {
            $sql = 'UPDATE events SET title_mr=:title_mr,title_en=:title_en,description_mr=:description_mr,event_date=:event_date,event_time=:event_time,venue=:venue,registration_url=:registration_url,status=:status';
            if ($poster) $sql .= ',poster_path=:poster_path';
            $sql .= ' WHERE id=:id';
            $params['id'] = $editId;
            if ($poster) $params['poster_path'] = $poster;
            $pdo->prepare($sql)->execute($params);
            audit($pdo, 'updated', 'event', $editId);
            $message = 'कार्यक्रम जतन केला.';
        } else {
            $params['poster_path'] = $poster;
            $pdo->prepare('INSERT INTO events(title_mr,title_en,description_mr,poster_path,event_date,event_time,venue,registration_url,status) VALUES(:title_mr,:title_en,:description_mr,:poster_path,:event_date,:event_time,:venue,:registration_url,:status)')->execute($params);
            audit($pdo, 'created', 'event', (int) $pdo->lastInsertId());
            $message = 'नवीन कार्यक्रम जतन केला.';
            $editId = 0;
        }
    }
}

$event = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM events WHERE id=?');
    $s->execute([$editId]);
    $event = $s->fetch() ?: null;
}
$showArchived = ($_GET['view'] ?? '') === 'archived';
$rows = $pdo->query("SELECT * FROM events WHERE status" . ($showArchived ? "='archived'" : "<>'archived'") . " ORDER BY event_date DESC, id DESC LIMIT 100")->fetchAll();

admin_header('कार्यक्रम');
?>
<div class="admin-title"><h1 data-testid="events-title">कार्यक्रम व्यवस्थापन</h1><p>नाट्यप्रयोग, कार्यशाळा आणि सांस्कृतिक कार्यक्रमांची यादी.</p></div>
<?php if ($message): ?><div class="notice" data-testid="events-flash"><?= e($message) ?></div><?php endif; ?>
<div class="dashboard-grid">
    <section class="panel">
        <h2><?= $editId ? 'कार्यक्रम संपादन' : 'नवीन कार्यक्रम' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="setting-form">
                <div class="field full"><label>शीर्षक (मराठी)</label><input name="title_mr" value="<?= e($event['title_mr'] ?? '') ?>" required data-testid="event-title-mr-input"></div>
                <div class="field full"><label>Title (English)</label><input name="title_en" value="<?= e($event['title_en'] ?? '') ?>" data-testid="event-title-en-input"></div>
                <div class="field"><label>दिनांक</label><input type="date" name="event_date" value="<?= e($event['event_date'] ?? '') ?>" data-testid="event-date-input"></div>
                <div class="field"><label>वेळ</label><input type="time" name="event_time" value="<?= e($event['event_time'] ?? '') ?>" data-testid="event-time-input"></div>
                <div class="field full"><label>स्थळ</label><input name="venue" value="<?= e($event['venue'] ?? '') ?>" data-testid="event-venue-input"></div>
                <div class="field full"><label>तपशील</label><textarea name="description_mr" data-testid="event-description-input"><?= e($event['description_mr'] ?? '') ?></textarea></div>
                <div class="field full"><label>नोंदणी लिंक (ऐच्छिक)</label><input type="url" name="registration_url" value="<?= e($event['registration_url'] ?? '') ?>" data-testid="event-url-input"></div>
                <div class="field"><label>पोस्टर</label><input type="file" name="poster" accept="image/*" data-testid="event-poster-input"><?php if (!empty($event['poster_path'])): ?><small>सध्या: <?= e($event['poster_path']) ?></small><?php endif; ?></div>
                <div class="field"><label>स्थिती</label>
                    <select name="status" data-testid="event-status-select">
                        <?php foreach (['draft'=>'मसुदा','published'=>'प्रकाशित','archived'=>'संग्रहित'] as $k=>$v): ?>
                            <option value="<?= e($k) ?>" <?= (($event['status'] ?? 'draft') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($editId): ?><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/events.php" data-testid="event-cancel-button">रद्द करा</a><?php endif; ?>
                <button class="btn btn-gold" type="submit" data-testid="event-save-button"><?= $editId ? 'बदल जतन करा' : 'कार्यक्रम जतन करा' ?></button>
            </div>
        </form>
    </section>
    <section class="panel">
        <h2 style="display:flex;justify-content:space-between;align-items:center;gap:10px"><?= $showArchived ? 'संग्रहित कार्यक्रम' : 'कार्यक्रम सूची' ?>
            <a href="<?= $showArchived ? '/admin/events.php' : '/admin/events.php?view=archived' ?>" style="font:600 14px 'Mukta',sans-serif;color:var(--velvet)" data-testid="events-archived-toggle"><?= $showArchived ? '← सक्रिय कार्यक्रम' : 'संग्रह पहा' ?></a>
        </h2>
        <?php if (!$rows): ?><p style="color:var(--muted)"><?= $showArchived ? 'संग्रहात काही नाही.' : 'अजून कोणताही कार्यक्रम नाही.' ?></p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0" data-testid="event-row">
                <strong><?= e($row['title_mr']) ?></strong>
                <div style="color:var(--muted);font-size:14px"><?= e((string) ($row['event_date'] ?? '')) ?> <?= e((string) ($row['event_time'] ?? '')) ?> · <?= e($row['venue'] ?? '') ?> · <?= e($row['status']) ?></div>
                <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                    <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" href="?edit=<?= e((string) $row['id']) ?>" data-testid="event-edit-button">संपादन</a>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                        <?php if ($row['status'] === 'archived'): ?>
                            <button class="btn btn-gold" style="padding:6px 12px" name="action" value="restore" data-testid="event-restore-button">पुनर्संचयन</button>
                        <?php else: ?>
                            <?php if ($row['status'] === 'published'): ?>
                                <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="unpublish" data-testid="event-unpublish-button">अप्रकाशित</button>
                            <?php else: ?>
                                <button class="btn btn-gold" style="padding:6px 12px" name="action" value="publish" data-testid="event-publish-button">प्रकाशित करा</button>
                            <?php endif; ?>
                            <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="delete" onclick="return confirm('कार्यक्रम संग्रहात हलवायचा?')" data-testid="event-delete-button">संग्रह</button>
                        <?php endif; ?>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php admin_footer(); ?>
