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

INSERT INTO settings (setting_key, setting_value_mr, setting_value_en, group_name) VALUES
('organization_name', 'नभरंग', 'Nabhrang', 'organization'),
('tagline', 'रंगभूमी, साहित्य आणि कलेचा उत्सव', 'A celebration of theatre, literature and art', 'organization'),
('about_text', 'कला, संस्कृती आणि समुदायाला एकत्र आणणारे व्यासपीठ.', 'A platform bringing art, culture and community together.', 'organization'),
('phone', '', '', 'contact'), ('email', '', '', 'contact'), ('address', '', '', 'contact'),
('hero_title', 'कलेला नवा रंग', 'Give art a new colour', 'homepage'),
('hero_subtitle', 'रंगभूमी, साहित्य आणि लोककलेच्या प्रवासात सहभागी व्हा.', 'Join a living journey through theatre, literature and folk art.', 'homepage'),
('footer_text', 'कलेसोबत, समुदायासाठी.', 'With art, for community.', 'footer')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

INSERT INTO site_sections (section_key, title_mr, title_en, content_mr, content_en, status, sort_order) VALUES
('hero', 'कलेला नवा रंग', 'Give art a new colour', 'रंगभूमी, साहित्य आणि लोककलेच्या प्रवासात सहभागी व्हा.', 'Join a living journey through theatre, literature and folk art.', 'active', 1),
('about', 'आमच्याविषयी', 'About us', 'कला, संस्कृती आणि समुदायाला एकत्र आणणारे व्यासपीठ.', 'A platform bringing art, culture and community together.', 'active', 2),
('membership', 'सदस्य व्हा', 'Become a member', 'नभरंगच्या सांस्कृतिक परिवाराचा भाग व्हा.', 'Become a part of the Nabhrang cultural family.', 'active', 3)
ON DUPLICATE KEY UPDATE section_key = VALUES(section_key);
UPDATE site_sections SET button_label_mr='सदस्य व्हा', button_label_en='Become a member', button_url='#membership' WHERE section_key='membership';
UPDATE site_sections SET button_label_mr='आमच्याशी बोला', button_label_en='Contact us', button_url='#contact' WHERE section_key='about';

-- Create an admin after import using: php scripts/create_admin.php