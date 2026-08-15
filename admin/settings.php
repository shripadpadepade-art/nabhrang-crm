<?php
require __DIR__ . '/../config/bootstrap.php';
require_admin();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // General text settings (mr/en pairs)
    $textFields = [
        'organization_name' => 'organization',
        'tagline'           => 'organization',
        'about_text'        => 'organization',
        'registration_number' => 'organization',
        'hero_title'        => 'homepage',
        'hero_subtitle'     => 'homepage',
        'phone'             => 'contact',
        'whatsapp'          => 'contact',
        'email'             => 'contact',
        'address'           => 'contact',
        'website'           => 'contact',
        'youtube_channel'   => 'social',
        'facebook_url'      => 'social',
        'instagram_url'     => 'social',
        'twitter_url'       => 'social',
        'footer_text'       => 'footer',
        'seo_meta_description' => 'seo',
        'upi_id'            => 'payments',
        'payment_instructions' => 'payments',
        'membership_id_prefix' => 'payments',
        'default_membership_fee' => 'payments',
        'maintenance_message' => 'system',
    ];
    $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value_mr,setting_value_en,group_name) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE setting_value_mr=VALUES(setting_value_mr), setting_value_en=VALUES(setting_value_en), group_name=VALUES(group_name)');
    foreach ($textFields as $key => $group) {
        $stmt->execute([$key, trim($_POST[$key . '_mr'] ?? ''), trim($_POST[$key . '_en'] ?? ''), $group]);
    }

    // Boolean: maintenance_mode
    $mm = isset($_POST['maintenance_mode']) ? '1' : '0';
    $stmt->execute(['maintenance_mode', $mm, $mm, 'system']);

    // Uploads: logo, favicon, payment QR — do not delete existing values on failure
    $logo = save_upload($_FILES['logo'] ?? [], 'brand', ['jpg','jpeg','png','webp']);
    if ($logo) $stmt->execute(['logo_path', $logo, $logo, 'organization']);
    $fav  = save_upload($_FILES['favicon'] ?? [], 'brand', ['png','webp','jpg','jpeg']);
    if ($fav)  $stmt->execute(['favicon_path', $fav, $fav, 'organization']);
    $qr   = save_upload($_FILES['payment_qr'] ?? [], 'qr', ['png','jpg','jpeg','webp']);
    if ($qr)   $stmt->execute(['payment_qr_url', $qr, $qr, 'payments']);

    audit($pdo, 'updated', 'settings');
    $saved = true;
}

require __DIR__ . '/_layout.php';
admin_header('संस्था सेटिंग्ज');
?>
<div class="admin-title"><h1 data-testid="settings-title">संस्था सेटिंग्ज</h1><p data-testid="settings-subtitle">साइटवरील माहिती कोड न बदलता व्यवस्थापित करा.</p></div>
<?php if ($saved): ?><div class="notice" data-testid="settings-success">सेटिंग्ज यशस्वीरित्या जतन केल्या.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <section class="panel">
        <h2>मूलभूत ओळख</h2>
        <div class="setting-form">
            <?php
            $labels = [
                'organization_name'    => ['संस्थेचे नाव', false],
                'tagline'              => ['ब्रीदवाक्य', false],
                'registration_number'  => ['नोंदणी क्रमांक', false],
                'about_text'           => ['आमच्याविषयी', true],
                'hero_title'           => ['होम पेज मुख्य शीर्षक', false],
                'hero_subtitle'        => ['होम पेज उपशीर्षक', true],
                'footer_text'          => ['फूटर मजकूर', true],
                'seo_meta_description' => ['SEO मेटा वर्णन', true],
            ];
            foreach ($labels as $key => [$label, $isLong]):
            ?>
            <div class="field <?= $isLong ? 'full' : '' ?>">
                <label><?= e($label) ?></label>
                <?php if ($isLong): ?>
                    <textarea name="<?= e($key) ?>_mr" data-testid="settings-<?= e($key) ?>-mr-input"><?= e(setting($pdo, $key, 'mr')) ?></textarea>
                <?php else: ?>
                    <input name="<?= e($key) ?>_mr" value="<?= e(setting($pdo, $key, 'mr')) ?>" data-testid="settings-<?= e($key) ?>-mr-input">
                <?php endif; ?>
                <small style="color:var(--muted)">मराठी</small>
                <?php if ($isLong): ?>
                    <textarea name="<?= e($key) ?>_en" placeholder="English (optional)" data-testid="settings-<?= e($key) ?>-en-input"><?= e(setting($pdo, $key, 'en')) ?></textarea>
                <?php else: ?>
                    <input name="<?= e($key) ?>_en" placeholder="English (optional)" value="<?= e(setting($pdo, $key, 'en')) ?>" data-testid="settings-<?= e($key) ?>-en-input">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel" style="margin-top:22px">
        <h2>ब्रँड (लोगो / फेवआयकॉन)</h2>
        <div class="setting-form">
            <div class="field"><label>लोगो अपलोड</label><input type="file" name="logo" accept="image/*" data-testid="settings-logo-input">
                <?php if ($cur = setting($pdo,'logo_path')): ?><img src="<?= e($cur) ?>" alt="logo" style="max-height:56px;margin-top:8px"><?php endif; ?>
            </div>
            <div class="field"><label>फेवआयकॉन</label><input type="file" name="favicon" accept="image/*" data-testid="settings-favicon-input">
                <?php if ($cur = setting($pdo,'favicon_path')): ?><img src="<?= e($cur) ?>" alt="favicon" style="max-height:32px;margin-top:8px"><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:22px">
        <h2>संपर्क</h2>
        <div class="setting-form">
            <?php foreach (['phone'=>'मोबाइल','whatsapp'=>'WhatsApp','email'=>'ईमेल','website'=>'वेबसाइट'] as $key=>$label): ?>
                <div class="field"><label><?= e($label) ?></label><input name="<?= e($key) ?>_mr" value="<?= e(setting($pdo,$key,'mr')) ?>" data-testid="settings-<?= e($key) ?>-mr-input"><input type="hidden" name="<?= e($key) ?>_en" value="<?= e(setting($pdo,$key,'en')) ?>"></div>
            <?php endforeach; ?>
            <div class="field full"><label>पत्ता</label><textarea name="address_mr" data-testid="settings-address-mr-input"><?= e(setting($pdo,'address','mr')) ?></textarea><input type="hidden" name="address_en" value="<?= e(setting($pdo,'address','en')) ?>"></div>
        </div>
    </section>

    <section class="panel" style="margin-top:22px">
        <h2>सोशल मीडिया</h2>
        <div class="setting-form">
            <?php foreach (['youtube_channel'=>'YouTube चॅनेल','facebook_url'=>'Facebook','instagram_url'=>'Instagram','twitter_url'=>'X (Twitter)'] as $key=>$label): ?>
                <div class="field"><label><?= e($label) ?></label><input type="url" name="<?= e($key) ?>_mr" value="<?= e(setting($pdo,$key,'mr')) ?>" data-testid="settings-<?= e($key) ?>-input"><input type="hidden" name="<?= e($key) ?>_en" value="<?= e(setting($pdo,$key,'en')) ?>"></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel" style="margin-top:22px">
        <h2>देयक सेटिंग्ज</h2>
        <div class="setting-form">
            <div class="field"><label>UPI ID</label><input name="upi_id_mr" value="<?= e(setting($pdo,'upi_id','mr')) ?>" data-testid="settings-upi-input"><input type="hidden" name="upi_id_en" value="<?= e(setting($pdo,'upi_id','en')) ?>"></div>
            <div class="field"><label>सदस्यत्व क्रमांक उपसर्ग</label><input name="membership_id_prefix_mr" value="<?= e(setting($pdo,'membership_id_prefix','mr')) ?>" data-testid="settings-prefix-input"><input type="hidden" name="membership_id_prefix_en" value="<?= e(setting($pdo,'membership_id_prefix','en')) ?>"></div>
            <div class="field"><label>डिफॉल्ट शुल्क (₹)</label><input type="number" step="0.01" min="0" name="default_membership_fee_mr" value="<?= e(setting($pdo,'default_membership_fee','mr')) ?>" data-testid="settings-default-fee-input"><input type="hidden" name="default_membership_fee_en" value="<?= e(setting($pdo,'default_membership_fee','en')) ?>"></div>
            <div class="field full"><label>देयक सूचना</label><textarea name="payment_instructions_mr" data-testid="settings-payment-instructions-input"><?= e(setting($pdo,'payment_instructions','mr')) ?></textarea><input type="hidden" name="payment_instructions_en" value="<?= e(setting($pdo,'payment_instructions','en')) ?>"></div>
            <div class="field"><label>QR कोड (नवीन अपलोड केल्यासच बदलेल)</label><input type="file" name="payment_qr" accept="image/*" data-testid="settings-qr-input">
                <?php if ($qr = setting($pdo,'payment_qr_url')): ?><img src="<?= e($qr) ?>" alt="QR" style="max-height:120px;margin-top:8px"><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:22px">
        <h2>देखभाल मोड</h2>
        <label style="display:flex;gap:10px;align-items:center;margin-bottom:10px">
            <input type="checkbox" name="maintenance_mode" value="1" <?= setting($pdo,'maintenance_mode') === '1' ? 'checked' : '' ?> data-testid="settings-maintenance-toggle">
            देखभाल मोड सुरू करा (साइट फक्त प्रशासकांसाठी उपलब्ध राहील)
        </label>
        <div class="field full"><label>देखभाल पान संदेश</label><textarea name="maintenance_message_mr" data-testid="settings-maintenance-message-input"><?= e(setting($pdo,'maintenance_message','mr')) ?></textarea><input type="hidden" name="maintenance_message_en" value="<?= e(setting($pdo,'maintenance_message','en')) ?>"></div>
    </section>

    <div class="form-actions" style="margin-top:22px"><button class="btn btn-gold" type="submit" data-testid="settings-save-button">सेटिंग्ज जतन करा</button></div>
</form>
<?php admin_footer(); ?>
