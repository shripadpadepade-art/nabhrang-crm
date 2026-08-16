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
        $pdo->prepare("UPDATE blogs SET status='archived' WHERE id=?")->execute([$id]);
        audit($pdo, 'archived', 'blog', $id);
        $message = 'ब्लॉग संग्रहात हलवला.';
    } elseif ($action === 'restore') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE blogs SET status='draft' WHERE id=?")->execute([$id]);
        audit($pdo, 'restored', 'blog', $id);
        $message = 'ब्लॉग पुनर्संचयित केला (मसुदा म्हणून).';
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $id = (int) $_POST['id'];
        $status = $action === 'publish' ? 'published' : 'draft';
        $pdo->prepare('UPDATE blogs SET status=?, publish_date=IF(?="published",NOW(),publish_date) WHERE id=?')->execute([$status, $status, $id]);
        audit($pdo, $action, 'blog', $id);
        $message = 'स्थिती बदलली.';
    } else {
        $title = trim($_POST['title_mr'] ?? '');
        $slug  = unique_blog_slug($pdo, $title ?: 'blog', $editId ?: null);
        $img   = save_upload($_FILES['featured_image'] ?? [], 'blogs', ['jpg','jpeg','png','webp']);
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $publishDate = $status === 'published' ? date('Y-m-d H:i:s') : null;

        $params = [
            'title_mr'    => $title,
            'title_en'    => trim($_POST['title_en'] ?? ''),
            'slug'        => $slug,
            'short_description_mr' => trim($_POST['short_description_mr'] ?? ''),
            'content_mr'  => trim($_POST['content_mr'] ?? ''),
            'category'    => trim($_POST['category'] ?? ''),
            'tags'        => trim($_POST['tags'] ?? ''),
            'author'      => trim($_POST['author'] ?? ''),
            'seo_title'   => trim($_POST['seo_title'] ?? ''),
            'seo_description' => trim($_POST['seo_description'] ?? ''),
            'status'      => $status,
            'publish_date'=> $publishDate,
        ];

        if ($editId) {
            $sql = 'UPDATE blogs SET title_mr=:title_mr,title_en=:title_en,slug=:slug,short_description_mr=:short_description_mr,content_mr=:content_mr,category=:category,tags=:tags,author=:author,seo_title=:seo_title,seo_description=:seo_description,status=:status,publish_date=COALESCE(:publish_date,publish_date)';
            if ($img) $sql .= ',featured_image=:featured_image';
            $sql .= ' WHERE id=:id';
            $params['id'] = $editId;
            if ($img) $params['featured_image'] = $img;
            $pdo->prepare($sql)->execute($params);
            audit($pdo, 'updated', 'blog', $editId);
            $message = 'ब्लॉग जतन केला.';
        } else {
            $params['featured_image'] = $img;
            $pdo->prepare('INSERT INTO blogs(title_mr,title_en,slug,short_description_mr,content_mr,featured_image,category,tags,author,seo_title,seo_description,status,publish_date) VALUES(:title_mr,:title_en,:slug,:short_description_mr,:content_mr,:featured_image,:category,:tags,:author,:seo_title,:seo_description,:status,:publish_date)')->execute($params);
            $newId = (int) $pdo->lastInsertId();
            audit($pdo, 'created', 'blog', $newId);
            $message = 'नवीन ब्लॉग जतन केला.';
            $editId = 0;
        }
    }
}

$blog = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM blogs WHERE id=?');
    $s->execute([$editId]);
    $blog = $s->fetch() ?: null;
}
$showArchived = ($_GET['view'] ?? '') === 'archived';
$rows = $pdo->query("SELECT id,title_mr,slug,status,publish_date,created_at FROM blogs WHERE status" . ($showArchived ? "='archived'" : "<>'archived'") . " ORDER BY created_at DESC LIMIT 100")->fetchAll();

admin_header('ब्लॉग');
?>
<div class="admin-title">
    <h1 data-testid="blogs-title">ब्लॉग व्यवस्थापन</h1>
    <p>ब्लॉग लिहा, प्रकाशित करा आणि होमपेजवर दाखवा.</p>
</div>
<?php if ($message): ?><div class="notice" data-testid="blogs-flash"><?= e($message) ?></div><?php endif; ?>

<div class="dashboard-grid">
    <section class="panel">
        <h2><?= $editId ? 'ब्लॉग संपादन' : 'नवीन ब्लॉग' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="setting-form">
                <div class="field full"><label>शीर्षक (मराठी)</label><input name="title_mr" value="<?= e($blog['title_mr'] ?? '') ?>" required data-testid="blog-title-mr-input"></div>
                <div class="field full"><label>Title (English, ऐच्छिक)</label><input name="title_en" value="<?= e($blog['title_en'] ?? '') ?>" data-testid="blog-title-en-input"></div>
                <div class="field"><label>लेखक</label><input name="author" value="<?= e($blog['author'] ?? '') ?>" data-testid="blog-author-input"></div>
                <div class="field"><label>वर्ग</label><input name="category" value="<?= e($blog['category'] ?? '') ?>" data-testid="blog-category-input"></div>
                <div class="field full"><label>टॅग्स (स्वल्पविरामाने)</label><input name="tags" value="<?= e($blog['tags'] ?? '') ?>" data-testid="blog-tags-input"></div>
                <div class="field full"><label>थोडक्यात परिचय</label><textarea name="short_description_mr" data-testid="blog-short-input"><?= e($blog['short_description_mr'] ?? '') ?></textarea></div>
                <div class="field full"><label>पूर्ण मजकूर</label><textarea name="content_mr" style="min-height:220px" data-testid="blog-content-input"><?= e($blog['content_mr'] ?? '') ?></textarea></div>
                <div class="field"><label>प्रमुख चित्र</label><input type="file" name="featured_image" accept="image/*" data-testid="blog-image-input"><?php if (!empty($blog['featured_image'])): ?><small>सध्या: <?= e($blog['featured_image']) ?></small><?php endif; ?></div>
                <div class="field"><label>स्थिती</label>
                    <select name="status" data-testid="blog-status-select">
                        <?php foreach (['draft'=>'मसुदा','published'=>'प्रकाशित','archived'=>'संग्रहित'] as $k=>$v): ?>
                            <option value="<?= e($k) ?>" <?= (($blog['status'] ?? 'draft') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full"><label>SEO शीर्षक</label><input name="seo_title" value="<?= e($blog['seo_title'] ?? '') ?>" data-testid="blog-seo-title-input"></div>
                <div class="field full"><label>SEO मेटा वर्णन</label><textarea name="seo_description" data-testid="blog-seo-desc-input"><?= e($blog['seo_description'] ?? '') ?></textarea></div>
            </div>
            <div class="form-actions">
                <?php if ($editId): ?><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/blogs.php" data-testid="blog-cancel-button">रद्द करा</a><?php endif; ?>
                <button class="btn btn-gold" type="submit" data-testid="blog-save-button"><?= $editId ? 'बदल जतन करा' : 'ब्लॉग जतन करा' ?></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2 style="display:flex;justify-content:space-between;align-items:center;gap:10px"><?= $showArchived ? 'संग्रहित ब्लॉग' : 'अलीकडील ब्लॉग' ?>
            <a href="<?= $showArchived ? '/admin/blogs.php' : '/admin/blogs.php?view=archived' ?>" style="font:600 14px 'Mukta',sans-serif;color:var(--velvet)" data-testid="blogs-archived-toggle"><?= $showArchived ? '← सक्रिय ब्लॉग' : 'संग्रह पहा' ?></a>
        </h2>
        <?php if (!$rows): ?><p style="color:var(--muted)"><?= $showArchived ? 'संग्रहात काही नाही.' : 'अजून कोणताही ब्लॉग नाही.' ?></p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article style="border-bottom:1px solid var(--line);padding:14px 0" data-testid="blog-row">
                <strong><?= e($row['title_mr']) ?></strong>
                <div style="color:var(--muted);font-size:14px"><?= e($row['status']) ?> · <?= e((string) ($row['publish_date'] ?? $row['created_at'])) ?></div>
                <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                    <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" href="?edit=<?= e((string) $row['id']) ?>" data-testid="blog-edit-button">संपादन</a>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                        <?php if ($row['status'] === 'archived'): ?>
                            <button class="btn btn-gold" style="padding:6px 12px" name="action" value="restore" data-testid="blog-restore-button">पुनर्संचयन</button>
                        <?php else: ?>
                            <?php if ($row['status'] === 'published'): ?>
                                <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="unpublish" data-testid="blog-unpublish-button">अप्रकाशित करा</button>
                            <?php else: ?>
                                <button class="btn btn-gold" style="padding:6px 12px" name="action" value="publish" data-testid="blog-publish-button">प्रकाशित करा</button>
                            <?php endif; ?>
                            <button class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet);padding:6px 12px" name="action" value="delete" onclick="return confirm('ब्लॉग संग्रहात हलवायचा?')" data-testid="blog-delete-button">संग्रह</button>
                        <?php endif; ?>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php admin_footer(); ?>
