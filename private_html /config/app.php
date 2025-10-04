<?php
/**
 * Application Configuration
 * Główne ustawienia aplikacji
 */

return [
	'name' => 'APM Automation System',
	'version' => '1.0.0',
	'env' => getenv('APP_ENV') ?: 'production', // development, production
	'debug' => getenv('APP_DEBUG') === 'true',
	'timezone' => 'Europe/Warsaw',
	'locale' => 'pl_PL',
	
	'paths' => [
		'root' => dirname(__DIR__),
		'storage' => dirname(__DIR__) . '/storage',
		'uploads' => dirname(__DIR__) . '/public/uploads',
		'logs' => dirname(__DIR__) . '/storage/logs',
		'backups' => dirname(__DIR__) . '/storage/backups',
		'temp' => dirname(__DIR__) . '/storage/temp',
	],
	
	'excel' => [
		'allowed_extensions' => ['xlsx', 'xls'],
		'max_file_size' => 10 * 1024 * 1024, // 10 MB
		'columns' => [
			'dhl_hs' => 'C',
			'address_street' => 'E',
			'address_city' => 'F',
			'address_postal' => 'G',
			'address_coords' => 'H',
			'team_substrate' => 'O',
			'team_electric' => 'P',
			'team_service' => 'Q',
			'team_assembly' => ['U', 'V', 'W'],
			'transport_company' => ['Z', 'AE'],
		],
	],
	
	'dropbox' => [
		'protocols_path' => getenv('DROPBOX_PROTOCOLS_PATH'),
		'teams_path' => getenv('DROPBOX_TEAMS_PATH'),
	],
	
	'onedrive' => [
		'plans_path' => getenv('ONEDRIVE_PLANS_PATH'),
		'bags_path' => getenv('ONEDRIVE_BAGS_PATH'),
		'contacts_path' => getenv('ONEDRIVE_CONTACTS_PATH'),
	],
	
	'scheduler' => [
		'send_day' => 'Friday', // Dzień wysyłki
		'send_time' => '17:00', // Godzina wysyłki
	],
	
	'backup' => [
		'enabled' => true,
		'schedule' => '0 23 * * *', // Codziennie o 23:00
		'retention_days' => 90, // Przechowuj przez 90 dni
	],
];