CREATE USER IF NOT EXISTS 'nabhrang'@'127.0.0.1' IDENTIFIED BY 'nabhrang_pass_2026';
CREATE USER IF NOT EXISTS 'nabhrang'@'localhost' IDENTIFIED BY 'nabhrang_pass_2026';
GRANT ALL PRIVILEGES ON nabhrang.* TO 'nabhrang'@'127.0.0.1';
GRANT ALL PRIVILEGES ON nabhrang.* TO 'nabhrang'@'localhost';
FLUSH PRIVILEGES;
USE nabhrang;
INSERT INTO admin_users (username, password_hash, full_name_mr, role)
SELECT 'admin', '$2y$10$jsG9hnAoVWvWTqx9VLNSBu/LjGBS3v4b19XTIrpb5VcJ0WzMOPXW2', 'प्रशासक', 'super_admin'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE username='admin');
UPDATE settings SET setting_value_mr='/uploads/qr/sample_qr.png'
WHERE setting_key='payment_qr_url' AND (setting_value_mr='' OR setting_value_mr IS NULL);
