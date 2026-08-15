<?php
require __DIR__ . '/../config/bootstrap.php';
if (!empty($_SESSION['member_id'])) { header('Location:/member/index.php'); exit; }
$error = '';
$types  = $pdo->query("SELECT id,name_mr,fee FROM membership_types WHERE status='active' ORDER BY id")->fetchAll();
$fields = $pdo->query("SELECT * FROM registration_fields WHERE status='active' ORDER BY sort_order,id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $typeId = (int) ($_POST['membership_type_id'] ?? 0);

    $typeCheck = $pdo->prepare("SELECT id FROM membership_types WHERE id=? AND status='active'");
    $typeCheck->execute([$typeId]);

    $missing = [];
    foreach ($fields as $field) {
        if ($field['is_required'] && !trim((string) ($_POST[$field['field_key']] ?? ''))) {
            $missing[] = $field['label_mr'];
        }
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !$typeCheck->fetch() || $missing) {
        $error = $missing
            ? 'कृपया आवश्यक फील्ड भरा: ' . implode(', ', $missing)
            : 'कृपया वैध ईमेल, किमान ८ अक्षरांचा पासवर्ड आणि सक्रिय सदस्यत्व प्रकार निवडा.';
    } else {
        try {
            $pdo->beginTransaction();
            $photo = save_upload($_FILES['photo'] ?? [], 'members', ['jpg','jpeg','png','webp']);
            $stmt = $pdo->prepare('INSERT INTO members(email,password_hash,membership_type_id,photo_path) VALUES(?,?,?,?)');
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $typeId, $photo]);
            $memberId = (int) $pdo->lastInsertId();

            $value = $pdo->prepare('INSERT INTO member_field_values(member_id,field_id,value_text) VALUES(?,?,?)');
            foreach ($fields as $field) {
                $value->execute([$memberId, $field['id'], trim((string) ($_POST[$field['field_key']] ?? ''))]);
            }
            member_history($pdo, $memberId, 'registration_submitted');
            $pdo->commit();
            $_SESSION['member_id'] = $memberId;
            header('Location:/member/index.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'हे ईमेल आधीपासून नोंदणीकृत असू शकते.';
        }
    }
}
$orgName = setting($pdo, 'organization_name');
?>
<!doctype html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>सदस्य नोंदणी · <?= e($orgName) ?></title>
    <link rel="stylesheet" href="/assets/css/nabhrang.css">
</head>
<body class="admin-body">
<main class="container section" style="max-width:820px">
    <div class="section-head">
        <div class="section-kicker">नभरंग परिवार</div>
        <h1 style="font:600 40px 'Playfair Display',serif;margin:12px 0">सदस्य नोंदणी</h1>
        <p>तुमची माहिती भरा. नोंदणी केल्यानंतर शुल्क आणि QR देयक प्रक्रिया पाहता येईल.</p>
    </div>
    <?php if ($error): ?><div class="error" data-testid="registration-error"><?= e($error) ?></div><?php endif; ?>
    <form class="panel" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="setting-form">
            <?php foreach ($fields as $field): ?>
                <div class="field <?= in_array($field['field_type'], ['textarea'], true) ? 'full' : '' ?>">
                    <label><?= e($field['label_mr']) ?><?= $field['is_required'] ? ' *' : '' ?></label>
                    <?php if ($field['field_type'] === 'textarea'): ?>
                        <textarea name="<?= e($field['field_key']) ?>" <?= $field['is_required'] ? 'required' : '' ?> data-testid="registration-<?= e($field['field_key']) ?>-input"></textarea>
                    <?php else: ?>
                        <input type="<?= e($field['field_type']) ?>" name="<?= e($field['field_key']) ?>" <?= $field['is_required'] ? 'required' : '' ?> data-testid="registration-<?= e($field['field_key']) ?>-input">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="field"><label>छायाचित्र (ऐच्छिक)</label><input type="file" name="photo" accept="image/*" data-testid="registration-photo-input"></div>
            <div class="field"><label>ईमेल *</label><input type="email" name="email" required data-testid="registration-email-input"></div>
            <div class="field"><label>पासवर्ड *</label><input type="password" name="password" minlength="8" required data-testid="registration-password-input"></div>
            <div class="field full"><label>सदस्यत्व प्रकार *</label>
                <select name="membership_type_id" required data-testid="registration-membership-type-select">
                    <option value="">प्रकार निवडा</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= e((string) $t['id']) ?>"><?= e($t['name_mr']) ?> · ₹<?= e((string) $t['fee']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/member/login.php" data-testid="registration-login-link">आधीच नोंदणी आहे? लॉगिन</a>
            <button class="btn btn-gold" type="submit" data-testid="registration-submit-button">नोंदणी पूर्ण करा</button>
        </div>
    </form>
</main>
</body>
</html>
