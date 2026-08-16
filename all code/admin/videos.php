<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();
require __DIR__ . '/_layout.php';

$message = '';
$editId = (int) ($_GET['edit'] ?? 0);

/** Extract YouTube ID and thumbnail URL from any common YouTube link. */
function yt_thumbnail(string $url): ?string {
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $url, $m)) {
        return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE videos SET status='archived' WHERE id=?")->execute([$id]);
        audit($pdo, 'archived', 'video', $id);
        $message = 'व्हिडिओ संग्रहात हलवला.';
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $id = (int) $_POST['id'];
        $status = $action === 'publish' ? 'published' : 'draft';
        $pdo->prepare('UPDATE videos SET status=? WHERE id=?')->execute([$status, $id]);
        audit($pdo, $action, 'video', $id);
        $message = 'स्थिती बदलली.';
    } else {
        $url = trim($_POST['youtube_url'] ?? '');
        $thumb = yt_thumbnail($url);
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $params = [
            'title_mr'       => trim($_POST['title_mr'] ?? ''),
            'youtube_url'    => $url,
            'description_mr' => trim($_POST['description_mr'] ?? ''),
            'category'       => trim($_POST['category'] ?? ''),
            'thumbnail_url'  => $thumb,
            'published_on'   => $_POST['published_on'] ?: null,
            'status'         => $status,
        ];
        if ($editId) {
            $params['id'] = $editId;
            $pdo->prepare('UPDATE videos SET title_mr=:title_mr,youtube_url=:youtube_url,description_mr=:description_mr,category=:category,thumbnail_url=:thumbnail_url,published_on=:published_on,status=:status WHERE id=:id')->execute($params);
            audit($pdo, 'updated', 'video', $editId);
            $message = 'व्हिडिओ जतन केला.';
        } else {
            $pdo->prepare('INSERT INTO videos(title_mr,youtube_url,description_mr,category,thumbnail_url,published_on,status) VALUES(:title_mr,:youtube_url,:description_mr,:category,:thumbnail_url,:published_on,:status)')->execute($params);
            audit($pdo, 'created', 'video', (int) $pdo->lastInsertId());
            $message = 'नवीन व्हिडिओ जतन केला.';
            $editId = 0;
        }
    }
}

$video = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM videos WHERE id=?');
    $s->execute([$editId]);
    $video = $s->fetch() ?: null;
}
$rows = $pdo->query("SELECT * FROM videos WHERE status<>'archived' ORDER BY published_on DESC, id DESC LIMIT 100")->fetchAll();

admin_header('व्हिडिओ');
?>
<div class="admin-title"><h1 data-testid="videos-title">व्हिडिओ व्यवस्थापन</h1><p>यूट्यूब चॅनेलवरील व्हिडिओ जोडा, थंबनेल आपोआप घेतले जाते.</p></div>
<?php if ($message): ?><div class="notice" data-testid="videos-flash"><?= e($message) ?></div><?php endif; ?>
<div class="dashboard-grid">
    <section class="panel">
        <h2><?= $editId ? 'व्हिडिओ संपादन' : 'नवीन व्हिडिओ' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="setting-form">
                <div class="field full"><label>शीर्षक (मराठी)</label><input name="title_mr" value="<?= e($video['title_mr'] ?? '') ?>" required data-testid="video-title-input"></div>
                <div class="field full"><label>YouTube URL</label><input type="url" name="youtube_url" value="<?= e($video['youtube_url'] ?? '') ?>" placeholder="https://youtu.be/..." required data-testid="video-url-input"></div>
                <div class="field"><label>वर्ग</label><input name="category" value="<?= e($video['category'] ?? '') ?>" data-testid="video-category-input"></div>
                <div class="field"><label>प्रकाशन दिनांक</label><input type="date" name="published_on" value="<?= e($video['published_on'] ?? '') ?>" data-testid="video-date-input"></div>
                <div class="field full"><label>वर्णन</label><textarea name="description_mr" data-testid="video-description-input"><?= e($video['description_mr'] ?? '') ?></textarea></div>
                <div class="field"><label>स्थिती</label>
                    <select name="status" data-testid="video-status-select">
                        <?php foreach (['draft'=>'मसुदा','published'=>'प्रकाशित','archived'=>'संग्रहित'] as $k=>$v): ?>
                            <option value="<?= e($k) ?>" <?= (($video['status'] ?? 'draft') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($editId): ?><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/videos.php" data-testid="video-cancel-button">रद्द करा</a><?php endif; ?>
                <button class="btn btn-gold" type="submit" data-testid="video-save-button"><?= $editId ? 'बदल जतन करा' : 'व्हिडिओ जतन करा' ?></button>
            </div>
        </form>
    </section>
    <section class="panel">
        <h2>व्हिडिओ सूची</h2>
        <?php if (!$rows): ?><p style="color:var(--muted)">अजून कोणताही व्हिडिओ नाही.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0;display:flex;gap:12px" data-testid="video-row">
                <?php if (!empty($row['thumbnail_url'])): ?><img src="<?= e($row['thumbnail_url']) ?>" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:3px"><?php endif; ?>
                <div style="flex:1">
                    <strong><?= e($row['title_mr']) ?></strong>
                    <div style="color:var(--muted);font-size:14px"><?= e($row['category'] ?? '') ?> · <?= e($row['status']) ?></div>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                        <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" href="?edit=<?= e((string) $row['id']) ?>" data-testid="video-edit-button">संपादन</a>
                        <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                            <?php if ($row['status'] === 'published'): ?>
                                <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="unpublish" data-testid="video-unpublish-button">अप्रकाशित</button>
                            <?php else: ?>
                                <button class="btn btn-gold" style="padding:6px 12px" name="action" value="publish" data-testid="video-publish-button">प्रकाशित</button>
                            <?php endif; ?>
                            <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="delete" onclick="return confirm('व्हिडिओ संग्रहात हलवायचा?')" data-testid="video-delete-button">संग्रह</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php admin_footer(); ?>
