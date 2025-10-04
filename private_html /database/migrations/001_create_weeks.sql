-- Migracja 001: Tabela tygodni
-- Data: 2025-01-03

CREATE TABLE IF NOT EXISTS weeks (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	week_number VARCHAR(10) NOT NULL COMMENT 'Numer tygodnia, np. T35',
	year YEAR NOT NULL,
	status ENUM('draft', 'sent', 'updated', 'archived') DEFAULT 'draft',
	file_path VARCHAR(255) NULL COMMENT 'Ścieżka do aktualnego pliku Excel',
	uploaded_at TIMESTAMP NULL,
	sent_at TIMESTAMP NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
	UNIQUE KEY unique_week (week_number, year),
	INDEX idx_status (status),
	INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela wersji (historia zmian pliku)
CREATE TABLE IF NOT EXISTS week_versions (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	week_id INT UNSIGNED NOT NULL,
	version INT UNSIGNED DEFAULT 1,
	file_path VARCHAR(255) NOT NULL,
	file_hash VARCHAR(64) NULL COMMENT 'SHA256 hash pliku',
	changes_summary JSON NULL COMMENT 'Lista zmian względem poprzedniej wersji',
	uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	
	FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
	INDEX idx_week_version (week_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;-- Migration: 001_create_weeks.sql
