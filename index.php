<?php

/**
 * APM Automation - Front Controller
 * Główny punkt wejścia aplikacji
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables
if (file_exists(dirname(__DIR__) . '/.env')) {
	$lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		if (strpos(trim($line), '#') === 0) {
			continue;
		}
		list($name, $value) = explode('=', $line, 2);
		putenv(trim($name) . '=' . trim($value));
	}
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? '1' : '0');

// Timezone
date_default_timezone_set('Europe/Warsaw');

// CORS headers (jeśli potrzebujesz API z frontendu)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	exit(0);
}

// Router
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Parse URI
$uriParts = explode('?', $requestUri);
$path = trim($uriParts[0], '/');
$segments = explode('/', $path);

// Initialize database
\App\Database::setConfig(require dirname(__DIR__) . '/config/database.php');

try {
	// Routing
	$response = match(true) {
		// Dashboard
		$path === '' || $path === 'dashboard' => handleDashboard(),
		
		// API - Weeks
		$path === 'api/weeks' && $requestMethod === 'GET' => handleWeeksList(),
		preg_match('#^api/weeks/(\d+)$#', $path, $m) && $requestMethod === 'GET' => handleWeekShow((int)$m[1]),
		$path === 'api/weeks/upload' && $requestMethod === 'POST' => handleWeekUpload(),
		preg_match('#^api/weeks/(\d+)$#', $path, $m) && $requestMethod === 'DELETE' => handleWeekDelete((int)$m[1]),
		
		// API - Drafts
		preg_match('#^api/weeks/(\d+)/drafts$#', $path, $m) && $requestMethod === 'POST' => handleGenerateDrafts((int)$m[1]),
		preg_match('#^api/weeks/(\d+)/drafts/approve$#', $path, $m) && $requestMethod === 'POST' => handleApproveDrafts((int)$m[1]),
		preg_match('#^api/drafts/(\d+)/preview$#', $path, $m) && $requestMethod === 'GET' => handleDraftPreview((int)$m[1]),
		
		// API - Emails
		preg_match('#^api/weeks/(\d+)/send$#', $path, $m) && $requestMethod === 'POST' => handleSendWeek((int)$m[1]),
		preg_match('#^api/drafts/(\d+)/send$#', $path, $m) && $requestMethod === 'POST' => handleSendDraft((int)$m[1]),
		preg_match('#^api/weeks/(\d+)/stats$#', $path, $m) && $requestMethod === 'GET' => handleEmailStats((int)$m[1]),
		preg_match('#^api/weeks/(\d+)/logs$#', $path, $m) && $requestMethod === 'GET' => handleEmailLogs((int)$m[1]),
		
		// Tracking pixel
		preg_match('#^track\.php$#', $path) => handleTracking(),
		
		// Webhook (n8n)
		$path === 'webhook/n8n' && $requestMethod === 'POST' => handleWebhook(),
		
		default => ['success' => false, 'error' => 'Not found', 'code' => 404]
	};

	$statusCode = $response['code'] ?? 200;
	http_response_code($statusCode);
	
	if (is_array($response)) {
		header('Content-Type: application/json');
		echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	} else {
		echo $response;
	}

} catch (\Exception $e) {
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode([
		'success' => false,
		'error' => $e->getMessage(),
		'trace' => getenv('APP_DEBUG') === 'true' ? $e->getTraceAsString() : null
	]);
}

// ===== Handler Functions =====

function handleDashboard(): string {
	ob_start();
	include dirname(__DIR__) . '/src/Views/dashboard/layout.php';
	return ob_get_clean();
}

function handleWeeksList(): array {
	$controller = new \App\Controllers\WeekController();
	return