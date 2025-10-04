<?php

/**
 * Import kontaktów z Excel
 * 
 * Użycie:
 *   php scripts/import_contacts.php path/to/contacts.xlsx
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Load .env
if (file_exists(dirname(__DIR__) . '/.env')) {
	$lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach ($lines as $line) {
		if (strpos(trim($line), '#') === 0) continue;
		if (strpos($line, '=') === false) continue;
		list($name, $value) = explode('=', $line, 2);
		putenv(trim($name) . '=' . trim($value));
	}
}

// Initialize database
App\Database::setConfig(require dirname(__DIR__) . '/config/database.php');

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║          Import kontaktów do APM Automation              ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

// Sprawdź argument
if (!isset($argv[1])) {
	echo "❌ Błąd: Brak ścieżki do pliku Excel\n";
	echo "\nUżycie:\n";
	echo "  php scripts/import_contacts.php path/to/contacts.xlsx\n";
	echo "\nFormat pliku Excel:\n";
	echo "  Kolumna A: Imię i nazwisko\n";
	echo "  Kolumna B: Telefon\n";
	echo "  Kolumna C: Email\n";
	echo "  Kolumna D: Typ (team/transport/hds/other)\n";
	echo "  Kolumna E: Firma (dla przewoźników)\n";
	echo "\n";
	exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
	echo "❌ Błąd: Plik nie istnieje: {$filePath}\n";
	exit(1);
}

echo "📂 Plik: {$filePath}\n";
echo "🔄 Wczytuję dane...\n\n";

try {
	// Wczytaj Excel
	$spreadsheet = IOFactory::load($filePath);
	$sheet = $spreadsheet->getActiveSheet();
	$highestRow = $sheet->getHighestRow();
	
	echo "📊 Znaleziono {$highestRow} wierszy (włącznie z nagłówkiem)\n\n";
	
	$imported = 0;
	$skipped = 0;
	$updated = 0;
	$errors = [];
	
	// Iteruj przez wiersze (pomijamy nagłówek)
	for ($row = 2; $row <= $highestRow; $row++) {
		$fullName = trim($sheet->getCell('A' . $row)->getValue());
		$phone = trim($sheet->getCell('B' . $row)->getValue());
		$email = trim($sheet->getCell('C' . $row)->getValue());
		$type = trim($sheet->getCell('D' . $row)->getValue());
		$company = trim($sheet->getCell('E' . $row)->getValue());
		
		// Walidacja
		if (empty($fullName) || empty($email)) {
			$skipped++;
			$errors[] = "Wiersz {$row}: Brak imienia lub emaila";
			continue;
		}
		
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$skipped++;
			$errors[] = "Wiersz {$row}: Nieprawidłowy email: {$email}";
			continue;
		}
		
		// Typ domyślny
		if (empty($type)) {
			$type = 'team';
		}
		
		// Sprawdź czy kontakt już istnieje
		$existing = App\Database::query(
			"SELECT id FROM contacts WHERE email = ?",
			[$email]
		);
		
		if (!empty($existing)) {
			// Aktualizuj istniejący kontakt
			App\Database::execute(
				"UPDATE contacts SET 
					full_name = ?, 
					phone = ?, 
					type = ?, 
					company = ?,
					updated_at = NOW()
				WHERE email = ?",
				[$fullName, $phone, $type, $company ?: null, $email]
			);
			$updated++;
			echo "🔄 Zaktualizowano: {$fullName} ({$email})\n";
		} else {
			// Dodaj nowy kontakt
			App\Database::execute(
				"INSERT INTO contacts (full_name, phone, email, type, company, active) 
				VALUES (?, ?, ?, ?, ?, 1)",
				[$fullName, $phone, $email, $type, $company ?: null]
			);
			$imported++;
			echo "✅ Dodano: {$fullName} ({$email})\n";
		}
	}
	
	echo "\n";
	echo "╔══════════════════════════════════════════════════════════╗\n";
	echo "║                    PODSUMOWANIE                          ║\n";
	echo "╠══════════════════════════════════════════════════════════╣\n";
	printf("║  ✅ Zaimportowano:  %-3d                                 ║\n", $imported);
	printf("║  🔄 Zaktualizowano: %-3d                                 ║\n", $updated);
	printf("║  ⏭️  Pominięto:      %-3d                                 ║\n", $skipped);
	echo "╚══════════════════════════════════════════════════════════╝\n";
	
	if (!empty($errors)) {
		echo "\n⚠️  Błędy:\n";
		foreach ($errors as $error) {
			echo "   - {$error}\n";
		}
	}
	
	echo "\n✨ Import zakończony!\n\n";
	
} catch (Exception $e) {
	echo "\n❌ Błąd podczas importu: " . $e->getMessage() . "\n";
	exit(1);
}