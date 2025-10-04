-- Migracja 003: Tabele maili
-- Data: 2025-01-03

-- Kontakty
CREATE TABLE IF NOT EXISTS contacts (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	full_name VARCHAR(255) NOT NULL,
	phone VARCHAR(50) NULL,
	email VARCHAR(255) NOT NULL,
	type ENUM('team', 'transport', 'hds', 'other') DEFAULT 'team',
	company VARCHAR(100) NULL COMMENT 'Firma przewozowa (dla transportu)',
	active BOOLEAN DEFAULT TRUE,
	notes TEXT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
	UNIQUE KEY unique_email (email),
	INDEX idx_type (type),
	INDEX idx_company (company)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drafty maili (przed wysyłką)
CREATE TABLE IF NOT EXISTS email_drafts (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	week_id INT UNSIGNED NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	recipient_name VARCHAR(255) NULL,
	cc_emails JSON NULL COMMENT 'Lista adresów CC',
	subject VARCHAR(500) NOT NULL,
	body_html TEXT NOT NULL,
	body_plain TEXT NULL,
	attachments JSON NULL COMMENT 'Lista załączników: [{path, name, type, size}]',
	email_type ENUM(
		'team_substrate',
		'team_service', 
		'team_assembly',
		'transport_auto',
		'transport_jumbo',
		'hds',
		'bags',
		'update'
	) NOT NULL,
	status ENUM('draft', 'ready', 'sent', 'failed') DEFAULT 'draft',
	validation_errors JSON NULL COMMENT 'Lista błędów walidacji',
	priority INT DEFAULT 0 COMMENT 'Priorytet wysyłki (wyższy = wcześniej)',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
	FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
	INDEX idx_week (week_id),
	INDEX idx_status (status),
	INDEX idx_type (email_type),
	INDEX idx_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logi wysłanych maili
CREATE TABLE IF NOT EXISTS email_logs (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	draft_id INT UNSIGNED NULL,
	week_id INT UNSIGNED NOT NULL,
	recipient_email VARCHAR(255) NOT NULL,
	subject VARCHAR(500) NOT NULL,
	status ENUM('sent', 'failed', 'bounced', 'opened') DEFAULT 'sent',
	sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	opened_at TIMESTAMP NULL COMMENT 'Kiedy odbiorca otworzył mail',
	error_message TEXT NULL,
	message_id VARCHAR(255) NULL COMMENT 'Message-ID z serwera SMTP',
	
	-- Potwierdzenia odbioru
	read_receipt_requested BOOLEAN DEFAULT FALSE,
	read_receipt_received_at TIMESTAMP NULL,
	
	-- Metadata
	ip_address VARCHAR(45) NULL,
	user_agent TEXT NULL,
	
	FOREIGN KEY (draft_id) REFERENCES email_drafts(id) ON DELETE SET NULL,
	FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
	INDEX idx_week (week_id),
	INDEX idx_status (status),
	INDEX idx_sent_at (sent_at),
	INDEX idx_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Big-bagi (odbiór)
CREATE TABLE IF NOT EXISTS bags (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	week_id INT UNSIGNED NOT NULL,
	load_date DATE NOT NULL COMMENT 'Kolumna I: data załadunku',
	hds_company VARCHAR(100) NOT NULL COMMENT 'Kolumna J: firma HDS',
	details JSON NULL COMMENT 'Pozostałe dane z pliku',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	
	FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
	INDEX idx_week (week_id),
	INDEX idx_load_date (load_date),
	INDEX idx_hds (hds_company)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logi zmian (audit trail)
CREATE TABLE IF NOT EXISTS change_logs (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	week_id INT UNSIGNED NULL,
	change_type ENUM('upload', 'update', 'send', 'resend', 'delete') NOT NULL,
	user_id INT UNSIGNED NULL COMMENT 'ID użytkownika (jeśli system logowania)',
	description TEXT NULL,
	old_data JSON NULL,
	new_data JSON NULL,
	ip_address VARCHAR(45) NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	
	FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE SET NULL,
	INDEX idx_week (week_id),
	INDEX idx_type (change_type),
	INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;-- Migration: 003_create_emails.sql
