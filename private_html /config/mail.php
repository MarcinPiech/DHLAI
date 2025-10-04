<?php
/**
 * Mail Configuration
 * Konfiguracja serwera SMTP i opcji wysyłki
 */

return [
	'smtp' => [
		'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
		'port' => getenv('MAIL_PORT') ?: 587,
		'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls', // tls lub ssl
		'username' => getenv('MAIL_USERNAME'),
		'password' => getenv('MAIL_PASSWORD'),
	],
	
	'from' => [
		'address' => getenv('MAIL_FROM_ADDRESS') ?: 'operacyjne@apm-service.com',
		'name' => getenv('MAIL_FROM_NAME') ?: 'APM Operacyjne',
	],
	
	'default_cc' => [
		'operacyjne@apm-service.com'
	],
	
	'options' => [
		'read_receipt' => true, // Potwierdzenia odbioru
		'tracking_pixel' => true, // Tracking otwarć
		'retry_failed' => true, // Ponowna próba przy błędzie
		'max_retries' => 3,
	],
	
	// Limity wysyłki (zabezpieczenie)
	'limits' => [
		'per_minute' => 30,
		'per_hour' => 100,
	],
];