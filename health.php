<?php

/**
 * Health Check Endpoint
 * Sprawdza status systemu - dla monitoringu zewnętrznego
 * 
 * URL: https://twoja-domena.pl/health.php
 */

// Dla dhosting - ścieżka do aplikacji
define('APP_PATH', dirname(__DIR__) . '/private_html');

require_once APP_PATH . '/vendor/autoload.php';

// Load .env
if (file_exists(APP_PATH . '/.env')) {
	$lines = file(APP_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		if (strpos(trim($line), '#') === 0) continue;
		if (strpos($line, '=') === false) continue;
		list($name, $value) = explode('=', $line, 2);
		putenv(trim($name) . '=' . trim($value));
	}
}

// Initialize database
\App\Database::setConfig(require APP_PATH . '/config/database.php');

header('Content-Type: application/json');

try {
	$monitoring = new \App\Services\MonitoringService();
	
	// Query parameter dla różnych akcji
	$action = $_GET['action'] ?? 'health';
	
	switch ($action) {
		case 'health':
			$response = $monitoring->healthCheck();
			break;
			
		case 'stats':
			$response = [
				'success' => true,
				'stats' => $monitoring->getStats()
			];
			break;
			
		case 'summary':
			$response = [
				'success' => true,
				'summary' => $monitoring->dailySummary()
			];
			break;
			
		default:
			$response = [
				'success' => false,
				'error' => 'Unknown action'
			];
	}
	
	// Status code based on health
	if (isset($response['healthy'])) {
		http_response_code($response['healthy'] ? 200 : 503);
	}
	
	echo json_encode($response, JSON_PRETTY_PRINT);
	
} catch (\Exception $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error' => $e->getMessage()
	]);
}