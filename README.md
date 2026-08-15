# नभरंग · Nabhrang — PHP + MySQL Website & Admin Panel

एक पूर्णपणे डायनॅमिक PHP + MySQL अ‍ॅप्लिकेशन. होस्टिंग: कोणतेही सामान्य cPanel + PHP 8+ + MySQL 8+.

## Highlights
- संपूर्णपणे मराठी UI (English फील्ड्स ऐच्छिक — भविष्यात बहुभाषिक)
- डायनॅमिक होमपेज (ब्लॉग, कार्यक्रम, चित्रदालन, व्हिडिओ, प्रकाशने, सूचना, संपर्क)
- सदस्य नोंदणी + मॅन्युअल QR देयक + प्रशासकीय पडताळणी
- सदस्यत्व ID जनरेशन (उदा. NB-2026-00001), प्रिंट-योग्य सदस्यत्व ओळखपत्र
- अहवाल + CSV निर्यात (सदस्य / देयके)
- लोगो, फेवआयकॉन, QR कोड, संपर्क, सोशल लिंक, SEO — सर्व अ‍ॅडमिन पॅनेलमधून
- साइट देखभाल मोड (Maintenance Mode)
- सुरक्षित: PDO Prepared Statements, CSRF, सेशन hardening, password_hash, uploads .htaccess deny
- Soft delete (Archive) — Blogs, Events, Videos, Publications, Members
- Audit logs (सर्व प्रशासकीय कृती नोंदल्या जातात)

## Deployment (cPanel)

1. `database.sql` phpMyAdmin मध्ये आयात करा (नवीन डेटाबेस `nabhrang` तयार होईल)
2. `config/config.example.php` → `config/config.php` म्हणून कॉपी करा आणि DB क्रेडेन्शियल्स भरा
3. सर्व फाइल्स `public_html/` (किंवा subdomain root) मध्ये अपलोड करा
4. `uploads/` फोल्डर writable करा (permissions `0755`, फाइल्स `0644`)
5. पहिला प्रशासक तयार करा (SSH उपलब्ध असल्यास):
   ```
   php scripts/create_admin.php admin "प्रशासक"
   ```
   SSH नसल्यास phpMyAdmin मधून `admin_users` टेबलात एक रो टाका आणि `password_hash` साठी `password_hash('yourpass', PASSWORD_DEFAULT)` वापरा (कोणत्याही PHP शेल स्क्रिप्टवरून hash तयार करा).
6. `/admin/login.php` वर लॉगिन करा → संस्था सेटिंग्ज संपादित करा → QR अपलोड करा

## Folder Map

| पथ | उद्देश |
| --- | --- |
| `/index.php`, `/blog.php`, `/album.php` | सार्वजनिक पाने |
| `/admin/*.php` | प्रशासक पॅनेल |
| `/member/*.php` | सदस्य नोंदणी / लॉगिन / डॅशबोर्ड / ओळखपत्र |
| `/config/` | DB व सेटिंग्ज |
| `/uploads/` | अपलोड केलेली छायाचित्रे / PDF / QR |
| `/assets/` | CSS |
| `/scripts/` | Admin creation |
| `database.sql` | पूर्ण स्कीमा + seed data |

## Security Checklist

- [x] PDO prepared statements
- [x] CSRF tokens on every POST
- [x] `password_hash()` + brute-force limiter on admin login
- [x] `uploads/.htaccess` PHP execution deny
- [x] Session hardening (`httponly`, `samesite=Lax`, regenerate on login)
- [x] Basic MIME + extension + size validation on uploads
- [x] Audit log for every admin write
- [ ] HTTPS (सुनिश्चित करा की cPanel वर SSL सक्रिय आहे)

## Roadmap (Future)

- Android REST API endpoints (business logic already isolated per module)
- Email notifications (registration, verification)
- Membership renewal automation
- Advanced page builder / drag-drop content blocks
