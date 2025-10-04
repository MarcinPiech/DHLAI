-- Migracja 002: Tabela lokalizacji
-- Data: 2025-01-03

CREATE TABLE IF NOT EXISTS locations (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	week_id INT UNSIGNED NOT NULL,
	version_id INT UNSIGNED NULL,
	row_number INT UNSIGNED NOT NULL COMMENT 'Numer wiersza w Excel',
	
	-- Adres (kolumny C, E-H)
	dhl_hs VARCHAR(255) NULL COMMENT 'Kolumna C: kod DHL HS lub "poza HS"',
	address_street VARCHAR(255) NULL COMMENT 'Kolumna E',
	address_city VARCHAR(100) NULL COMMENT 'Kolumna F',
	address_postal VARCHAR(20) NULL COMMENT 'Kolumna G',
	address_coords VARCHAR(100) NULL COMMENT 'Kolumna H: koordynaty GPS',
	
	-- Monterzy - podłoże/prąd/serwis (kolumny O-Q)
	team_substrate VARCHAR(100) NULL COMMENT 'Kolumna O: podłoże',
	team_electric VARCHAR(100) NULL COMMENT 'Kolumna P: prąd',
	team_service VARCHAR(100) NULL COMMENT 'Kolumna Q: serwis',
	
	-- Monterzy - montaż (kolumny U-W)
	team_assembly1 VARCHAR(100) NULL COMMENT 'Kolumna U',
	team_assembly2 VARCHAR(100) NULL COMMENT 'Kolumna V',
	team_assembly3 VARCHAR(100) NULL COMMENT 'Kolumna W',
	
	-- Transport - automaty (kolumny B-H, S-U, AB-AE, BL)
	transport_auto_company VARCHAR(100) NULL COMMENT 'Kolumna Z lub AE',
	transport_auto_data JSON NULL COMMENT 'Dane transportu automatów',
	
	-- Transport - płyty jumbo (kolumny B-H, X-Z)
	transport_jumbo_company VARCHAR(100) NULL COMMENT 'Kolumna Z lub AE',
	transport_jumbo_data JSON NULL COMMENT 'Dane transportu płyt',
	
	-- Pliki i protokoły
	protocol_path VARCHAR(255) NULL COMMENT 'Ścieżka do protokołu przedinstalacyjnego',
	photos_paths JSON NULL COMMENT 'Lista ścieżek do zdjęć',
	
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
	FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
	FOREIGN KEY (version_id) REFERENCES week_versions(id) ON DELETE SET NULL,
	INDEX idx_week (week_id),
	INDEX idx_version (version_id),
	INDEX idx_teams (team_substrate, team_electric, team_service),
	INDEX idx_transport (transport_auto_company, transport_jumbo_company)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;-- Migration: 002_create_locations.sql
