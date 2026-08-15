<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();
require __DIR__ . '/_layout.php';

$message = '';
$albumId = (int) ($_GET['album'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_album') {
        $cover = save_upload($_FILES['cover'] ?? [], 'gallery', ['jpg','jpeg','png','webp']);
        $stmt = $pdo->prepare('INSERT INTO gallery_albums(title_mr,title_en,description_mr,cover_path,status) VALUES(?,?,?,?,?)');
        $stmt->execute([trim($_POST['title_mr']), trim($_POST['title_en']), trim($_POST['description_mr']), $cover, 'active']);
        $newAlbum = (int) $pdo->lastInsertId();
        audit($pdo, 'created', 'gallery_album', $newAlbum);
        $message = 'नवीन अल्बम तयार केला.';
    } elseif ($action === 'archive_album') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE gallery_albums SET status='archived' WHERE id=?")->execute([$id]);
        audit($pdo, 'archived', 'gallery_album', $id);
        $message = 'अल्बम संग्रहात हलवला.';
    } elseif ($action === 'restore_album') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE gallery_albums SET status='active' WHERE id=?")->execute([$id]);
        audit($pdo, 'restored', 'gallery_album', $id);
        $message = 'अल्बम पुनर्संचयित केला.';
    } elseif ($action === 'upload_photo' && $albumId) {
        $count = 0;
        if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
            $files = $_FILES['photos'];
            for ($i = 0; $i < count($files['name']); $i++) {
                $one = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                $path = save_upload($one, 'gallery/album-' . $albumId, ['jpg','jpeg','png','webp']);
                if ($path) {
                    $pdo->prepare('INSERT INTO gallery_photos(album_id,file_path,caption_mr) VALUES(?,?,?)')->execute([$albumId, $path, trim($_POST['caption_mr'] ?? '')]);
                    $count++;
                }
            }
        }
        audit($pdo, 'photos_uploaded', 'gallery_album', $albumId);
        $message = $count . ' छायाचित्रे जतन केली.';
    } elseif ($action === 'archive_photo') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE gallery_photos SET status='archived' WHERE id=?")->execute([$id]);
        $message = 'छायाचित्र संग्रहात हलवले.';
    }
}

$showArchived = ($_GET['view'] ?? '') === 'archived';
$albums = $pdo->query("SELECT * FROM gallery_albums WHERE status='" . ($showArchived ? 'archived' : 'active') . "' ORDER BY id DESC")->fetchAll();
$activeAlbum = null;
$photos = [];
if ($albumId) {
    $s = $pdo->prepare('SELECT * FROM gallery_albums WHERE id=?');
    $s->execute([$albumId]);
    $activeAlbum = $s->fetch() ?: null;
    $p = $pdo->prepare("SELECT * FROM gallery_photos WHERE album_id=? AND status='active' ORDER BY id DESC");
    $p->execute([$albumId]);
    $photos = $p->fetchAll();
}

admin_header('चित्रदालन');
?>
<div class="admin-title"><h1 data-testid="gallery-title">चित्रदालन</h1><p>अल्बम तयार करा आणि प्रत्येक अल्बममध्ये छायाचित्रे जोडा.</p></div>
<?php if ($message): ?><div class="notice" data-testid="gallery-flash"><?= e($message) ?></div><?php endif; ?>

<div class="dashboard-grid">
    <section class="panel">
        <h2>नवीन अल्बम</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_album">
            <div class="field"><label>शीर्षक (मराठी)</label><input name="title_mr" required data-testid="album-title-mr-input"></div>
            <div class="field"><label>Title (English)</label><input name="title_en" data-testid="album-title-en-input"></div>
            <div class="field"><label>वर्णन</label><textarea name="description_mr" data-testid="album-desc-input"></textarea></div>
            <div class="field"><label>मुखपृष्ठ छायाचित्र</label><input type="file" name="cover" accept="image/*" data-testid="album-cover-input"></div>
            <div class="form-actions"><button class="btn btn-gold" type="submit" data-testid="album-create-button">अल्बम तयार करा</button></div>
        </form>
    </section>

    <section class="panel">
        <h2 style="display:flex;justify-content:space-between;align-items:center;gap:10px"><?= $showArchived ? 'संग्रहित अल्बम' : 'अल्बम सूची' ?>
            <a href="<?= $showArchived ? '/admin/gallery.php' : '/admin/gallery.php?view=archived' ?>" style="font:600 14px 'Mukta',sans-serif;color:var(--velvet)" data-testid="albums-archived-toggle"><?= $showArchived ? '← सक्रिय अल्बम' : 'संग्रह पहा' ?></a>
        </h2>
        <?php if (!$albums): ?><p style="color:var(--muted)"><?= $showArchived ? 'संग्रहात काही नाही.' : 'अजून कोणताही अल्बम नाही.' ?></p><?php endif; ?>
        <?php foreach ($albums as $a): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0;display:flex;gap:14px;align-items:center" data-testid="album-row">
                <?php if (!empty($a['cover_path'])): ?><img src="<?= e($a['cover_path']) ?>" alt="" style="width:68px;height:68px;object-fit:cover;border-radius:3px"><?php endif; ?>
                <div style="flex:1">
                    <strong><?= e($a['title_mr']) ?></strong>
                    <div style="color:var(--muted);font-size:14px"><?= e((string) $a['created_at']) ?></div>
                </div>
                <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" href="?album=<?= e((string) $a['id']) ?>" data-testid="album-open-button">उघडा</a>
                <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $a['id']) ?>">
                    <?php if ($showArchived): ?>
                        <button class="btn btn-gold" style="padding:6px 12px" name="action" value="restore_album" data-testid="album-restore-button">पुनर्संचयन</button>
                    <?php else: ?>
                        <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="archive_album" onclick="return confirm('अल्बम संग्रहात हलवायचा?')" data-testid="album-archive-button">संग्रह</button>
                    <?php endif; ?>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<?php if ($activeAlbum): ?>
<section class="panel" style="margin-top:22px">
    <h2>अल्बम: <?= e($activeAlbum['title_mr']) ?></h2>
    <form method="post" enctype="multipart/form-data" style="margin-bottom:18px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload_photo">
        <div class="setting-form">
            <div class="field full"><label>छायाचित्रे निवडा (एकाच वेळी अनेक)</label><input type="file" name="photos[]" accept="image/*" multiple required data-testid="photos-input"></div>
            <div class="field full"><label>कॅप्शन (सर्व छायाचित्रांसाठी समान)</label><input name="caption_mr" data-testid="photos-caption-input"></div>
        </div>
        <div class="form-actions"><button class="btn btn-gold" type="submit" data-testid="photos-upload-button">छायाचित्रे अपलोड करा</button></div>
    </form>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px">
        <?php foreach ($photos as $ph): ?>
            <figure style="margin:0;position:relative" data-testid="photo-tile">
                <img src="<?= e($ph['file_path']) ?>" alt="<?= e($ph['caption_mr'] ?? '') ?>" style="width:100%;height:150px;object-fit:cover;border-radius:4px">
                <figcaption style="font-size:12px;color:var(--muted);margin-top:4px"><?= e($ph['caption_mr'] ?? '') ?></figcaption>
                <form method="post" style="position:absolute;top:6px;right:6px"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $ph['id']) ?>">
                    <button class="btn btn-outline" style="color:#fff;background:rgba(88,17,21,.8);border-color:transparent;padding:4px 8px;font-size:12px" name="action" value="archive_photo" onclick="return confirm('छायाचित्र काढायचे?')" data-testid="photo-archive-button">✕</button>
                </form>
            </figure>
        <?php endforeach; ?>
        <?php if (!$photos): ?><p style="color:var(--muted)">या अल्बममध्ये अजून छायाचित्रे नाहीत.</p><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php admin_footer(); ?>
