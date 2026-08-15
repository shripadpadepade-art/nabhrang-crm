-- नभरंग · Nabhrang — MySQL schema (utf8mb4, InnoDB)
-- Import via cPanel > phpMyAdmin, then run: php scripts/create_admin.php <username> "<full name>"

CREATE DATABASE IF NOT EXISTS `nabhrang` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nabhrang`;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value_mr TEXT NULL, setting_value_en TEXT NULL, group_name VARCHAR(60) NOT NULL DEFAULT 'general',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS site_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, section_key VARCHAR(80) NOT NULL UNIQUE,
  title_mr VARCHAR(255) NULL, title_en VARCHAR(255) NULL, content_mr TEXT NULL, content_en TEXT NULL,
  image_path VARCHAR(255) NULL, button_label_mr VARCHAR(100) NULL, button_label_en VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', sort_order INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  full_name_mr VARCHAR(150) NOT NULL, full_name_en VARCHAR(150) NULL, email VARCHAR(190) NULL UNIQUE,
  role ENUM('super_admin','editor') NOT NULL DEFAULT 'editor', status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  last_login DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED NULL, action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL, entity_id INT UNSIGNED NULL, ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (admin_id), INDEX (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS membership_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name_mr VARCHAR(150) NOT NULL, name_en VARCHAR(150) NULL,
  description_mr TEXT NULL, description_en TEXT NULL, fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registration_fields (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, field_key VARCHAR(80) NOT NULL UNIQUE, label_mr VARCHAR(150) NOT NULL,
  label_en VARCHAR(150) NULL, field_type ENUM('text','email','tel','date','textarea','select','file') NOT NULL DEFAULT 'text',
  options_json JSON NULL, is_required TINYINT(1) NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  membership_type_id INT UNSIGNED NULL, membership_id VARCHAR(50) NULL UNIQUE,
  photo_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected','suspended','archived') NOT NULL DEFAULT 'pending',
  payment_status ENUM('not_submitted','pending','submitted','verified','rejected','cancelled') NOT NULL DEFAULT 'not_submitted', joined_date DATE NULL,
  valid_until DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status), CONSTRAINT fk_member_type FOREIGN KEY (membership_type_id) REFERENCES membership_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS member_field_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, field_id INT UNSIGNED NOT NULL, value_text TEXT NULL,
  UNIQUE KEY member_field (member_id,field_id), CONSTRAINT fk_value_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_value_field FOREIGN KEY (field_id) REFERENCES registration_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, amount DECIMAL(10,2) NOT NULL, utr VARCHAR(120) NULL,
  payment_date DATE NULL, payment_method VARCHAR(60) NULL, screenshot_path VARCHAR(255) NULL,
  status ENUM('pending','submitted','verified','rejected','cancelled','refunded','failed') NOT NULL DEFAULT 'pending',
  verified_by INT UNSIGNED NULL, verified_at DATETIME NULL, admin_note TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(status), INDEX(utr),
  CONSTRAINT fk_payment_member FOREIGN KEY(member_id) REFERENCES members(id),
  CONSTRAINT fk_payment_admin FOREIGN KEY(verified_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payment_id BIGINT UNSIGNED NOT NULL, old_status VARCHAR(30) NULL, new_status VARCHAR(30) NOT NULL,
  changed_by INT UNSIGNED NULL, remarks TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_history_payment FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS member_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, action VARCHAR(100) NOT NULL, details TEXT NULL,
  changed_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_member_history_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blogs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title_mr VARCHAR(255) NOT NULL, title_en VARCHAR(255) NULL,
  slug VARCHAR(255) NOT NULL UNIQUE, short_description_mr TEXT NULL, content_mr LONGTEXT NULL, content_en LONGTEXT NULL,
  featured_image VARCHAR(255) NULL, category VARCHAR(120) NULL, tags VARCHAR(255) NULL, author VARCHAR(120) NULL,
  seo_title VARCHAR(255) NULL, seo_description VARCHAR(500) NULL,
  status ENUM('draft','published','archived') DEFAULT 'draft', publish_date DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status), INDEX(publish_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title_mr VARCHAR(255) NOT NULL, title_en VARCHAR(255) NULL,
  description_mr TEXT NULL, poster_path VARCHAR(255) NULL,
  event_date DATE NULL, event_time TIME NULL, venue VARCHAR(255) NULL, registration_url VARCHAR(255) NULL,
  status ENUM('draft','published','archived') DEFAULT 'draft', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(status), INDEX(event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gallery_albums (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title_mr VARCHAR(255) NOT NULL, title_en VARCHAR(255) NULL,
  description_mr TEXT NULL, cover_path VARCHAR(255) NULL,
  status ENUM('active','archived') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gallery_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, album_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL, caption_mr VARCHAR(255) NULL, sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','archived') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(album_id),
  CONSTRAINT fk_photo_album FOREIGN KEY(album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title_mr VARCHAR(255) NOT NULL, youtube_url VARCHAR(500) NOT NULL,
  description_mr TEXT NULL, category VARCHAR(120) NULL, thumbnail_url VARCHAR(500) NULL, published_on DATE NULL,
  status ENUM('draft','published','archived') DEFAULT 'draft', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS publications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title_mr VARCHAR(255) NOT NULL, title_en VARCHAR(255) NULL,
  year SMALLINT NULL, description_mr TEXT NULL, cover_path VARCHAR(255) NULL, pdf_path VARCHAR(255) NULL,
  status ENUM('draft','published','archived') DEFAULT 'draft', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title_mr VARCHAR(255) NOT NULL, body_mr TEXT NULL,
  status ENUM('draft','published','archived') DEFAULT 'draft', publish_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: organisation, homepage, payment and site settings (Marathi + English)
INSERT INTO settings (setting_key, setting_value_mr, setting_value_en, group_name) VALUES
('organization_name', 'नभरंग', 'Nabhrang', 'organization'),
('tagline', 'रंगभूमी, साहित्य आणि कलेचा उत्सव', 'A celebration of theatre, literature and art', 'organization'),
('about_text', 'कला, संस्कृती आणि समुदायाला एकत्र आणणारे व्यासपीठ.', 'A platform bringing art, culture and community together.', 'organization'),
('registration_number', '', '', 'organization'),
('logo_path', '', '', 'organization'),
('favicon_path', '', '', 'organization'),
('phone', '', '', 'contact'),
('whatsapp', '', '', 'contact'),
('email', '', '', 'contact'),
('address', '', '', 'contact'),
('website', '', '', 'contact'),
('youtube_channel', '', '', 'social'),
('facebook_url', '', '', 'social'),
('instagram_url', '', '', 'social'),
('twitter_url', '', '', 'social'),
('hero_title', 'कलेला नवा रंग', 'Give art a new colour', 'homepage'),
('hero_subtitle', 'रंगभूमी, साहित्य आणि लोककलेच्या प्रवासात सहभागी व्हा.', 'Join a living journey through theatre, literature and folk art.', 'homepage'),
('footer_text', 'कलेसोबत, समुदायासाठी.', 'With art, for community.', 'footer'),
('seo_meta_description', 'नभरंग · रंगभूमी, साहित्य आणि कलेचा उत्सव', 'Nabhrang — a celebration of theatre, literature and art', 'seo'),
('maintenance_mode', '0', '0', 'system'),
('maintenance_message', 'साइट देखभालीसाठी काही वेळ बंद आहे. लवकरच पुन्हा भेटू.', 'The site is briefly under maintenance. We will be back soon.', 'system')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO settings (setting_key, setting_value_mr, setting_value_en, group_name) VALUES
('payment_qr_url', '', '', 'payments'),
('upi_id', '', '', 'payments'),
('payment_instructions', 'कृपया QR कोडद्वारे शुल्क भरून UTR क्रमांक नोंदवा.', 'Pay using the QR code and enter the UTR number.', 'payments'),
('membership_id_prefix', 'NB', 'NB', 'payments'),
('default_membership_fee', '500', '500', 'payments')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO site_sections (section_key, title_mr, title_en, content_mr, content_en, status, sort_order) VALUES
('hero', 'कलेला नवा रंग', 'Give art a new colour', 'रंगभूमी, साहित्य आणि लोककलेच्या प्रवासात सहभागी व्हा.', 'Join a living journey through theatre, literature and folk art.', 'active', 1),
('about', 'आमच्याविषयी', 'About us', 'कला, संस्कृती आणि समुदायाला एकत्र आणणारे व्यासपीठ.', 'A platform bringing art, culture and community together.', 'active', 2),
('membership', 'सदस्य व्हा', 'Become a member', 'नभरंगच्या सांस्कृतिक परिवाराचा भाग व्हा.', 'Become a part of the Nabhrang cultural family.', 'active', 3),
('blogs', 'नवीनतम ब्लॉग', 'Latest blogs', 'आमच्या लेखकांच्या ताज्या नोंदी वाचा.', 'Read the newest entries from our writers.', 'active', 4),
('events', 'आगामी कार्यक्रम', 'Upcoming events', 'नाट्यप्रयोग, कार्यशाळा आणि सांस्कृतिक कार्यक्रम.', 'Plays, workshops and cultural gatherings.', 'active', 5),
('videos', 'व्हिडिओ', 'Videos', 'नभरंगच्या यूट्यूब चॅनेलवरील निवडक व्हिडिओ.', 'Selected videos from the Nabhrang channel.', 'active', 6),
('gallery', 'चित्रदालन', 'Gallery', 'कार्यक्रमांचे क्षण, चित्ररूपात.', 'Moments from our events, captured in frames.', 'active', 7),
('publications', 'प्रकाशने', 'Publications', 'दिवाळी अंक, वार्षिक पुस्तिका आणि सांस्कृतिक दस्तऐवज.', 'Diwali issues, annual books and cultural documents.', 'active', 8),
('contact', 'संपर्क', 'Contact', 'तुमच्या प्रश्नांसाठी आम्हाला कधीही लिहा.', 'Write to us any time with your questions.', 'active', 9)
ON DUPLICATE KEY UPDATE section_key = VALUES(section_key);

UPDATE site_sections SET button_label_mr='सदस्य व्हा', button_label_en='Become a member', button_url='/member/register.php' WHERE section_key='membership';
UPDATE site_sections SET button_label_mr='आमच्याशी बोला', button_label_en='Contact us', button_url='#contact' WHERE section_key='about';

INSERT INTO membership_types (name_mr, name_en, description_mr, fee) VALUES
('सामान्य सदस्य', 'General Member', 'नभरंग परिवाराचा भाग व्हा.', 500)
ON DUPLICATE KEY UPDATE name_mr = VALUES(name_mr);

INSERT INTO registration_fields (field_key, label_mr, label_en, field_type, is_required, sort_order) VALUES
('full_name', 'पूर्ण नाव', 'Full name', 'text', 1, 1),
('phone', 'मोबाइल', 'Mobile', 'tel', 1, 2),
('date_of_birth', 'जन्मतारीख', 'Date of birth', 'date', 0, 3),
('city', 'शहर', 'City', 'text', 0, 4),
('profession', 'व्यवसाय', 'Profession', 'text', 0, 5),
('art_field', 'कला क्षेत्र', 'Art field', 'text', 0, 6),
('short_introduction', 'थोडक्यात परिचय', 'Short introduction', 'textarea', 0, 7)
ON DUPLICATE KEY UPDATE field_key = VALUES(field_key);

-- After importing, create the first super admin:
-- php scripts/create_admin.php admin "प्रशासक"
