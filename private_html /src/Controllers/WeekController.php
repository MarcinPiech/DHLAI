<?php

namespace App\Controllers;

use App\Models\Week;
use App\Services\ExcelParserService;
use App\Services\EmailGeneratorService;
use App\Services\ValidationService;

/**
 * WeekController
 * Obsługa operacji na tygodniach
 */
class WeekController
{
	private ExcelParserService $parserService;
	private EmailGeneratorService $generatorService;
	private ValidationService $validationService;

	public function __construct()
	{
		$this->parserService = new ExcelParserService();
		$this->generatorService = new EmailGeneratorService();
		$this->validationService = new ValidationService();
	}

	/**
	 * Lista wszystkich tygodni
	 */
	public function index(): array
	{
		$weeks = Week::all();
		
		return [
			'success' => true,
			'weeks' => array_map(fn($w) => $w->toArray(), $weeks)
		];
	}

	/**
	 * Szczegóły tygodnia
	 */
	public function show(int $weekId): array
	{
		$week = Week::find($weekId);
		
		if (!$week) {
			return [
				'success' => false,
				'error' => 'Tydzień nie został znaleziony'
			];
		}

		return [
			'success' => true,
			'week' => $week->toArray(),
			'locations' => array_map(fn($l) => $l->toArray(), $week->locations()),
			'drafts' => array_map(fn($d) => $d->toArray(), $week->emailDrafts()),
			'versions' => $week->versions()
		];
	}

	/**
	 * Upload pliku Excel z planem tras
	 */
	public function uploadPlan(array $fileData, string $weekNumber): array
	{
		try {
			// Walidacja pliku
			if (!isset($fileData['tmp_name']) || !file_exists($fileData['tmp_name'])) {
				return [
					'success' => false,
					'error' => 'Nie przesłano pliku'
				];
			}

			$year = (int) date('Y');
			
			// Znajdź lub utwórz tydzień
			$week = Week::firstOrCreate($weekNumber, $year);
			
			// Zapisz plik
			$uploadPath = $this->saveUploadedFile($fileData, $weekNumber);
			$week->file_path = $uploadPath;
			$week->save();
			
			// Parsuj Excel
			$parseResult = $this->parserService->parseWeekPlan($uploadPath, $week);
			
			return [
				'success' => true,
				'week_id' => $week->id,
				'message' => "Plan tras {$weekNumber} został wczytany",
				'locations_count' => $parseResult['locations_count']
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Upload pliku BAG DHL - WYWÓZ
	 */
	public function uploadBags(array $fileData, string $weekNumber): array
	{
		try {
			$year = (int) date('Y');
			$week = Week::findByNumber($weekNumber, $year);
			
			if (!$week) {
				return [
					'success' => false,
					'error' => "Tydzień {$weekNumber} nie istnieje. Najpierw wczytaj plan tras."
				];
			}

			$uploadPath = $this->saveUploadedFile($fileData, $weekNumber . '_bags');
			
			// Parsuj plik bags
			$parseResult = $this->parserService->parseBagsFile($uploadPath, $week);
			
			return [
				'success' => true,
				'week_id' => $week->id,
				'message' => "Big-bagi dla {$weekNumber} zostały wczytane",
				'bags_count' => $parseResult['bags_count']
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Generuj drafty maili dla tygodnia
	 */
	public function generateDrafts(int $weekId): array
	{
		try {
			$week = Week::find($weekId);
			
			if (!$week) {
				return [
					'success' => false,
					'error' => 'Tydzień nie został znaleziony'
				];
			}

			// Najpierw walidacja
			$validation = $this->validationService->validateWeek($week);
			
			if (!$validation['valid']) {
				return [
					'success' => false,
					'error' => 'Dane nie przeszły walidacji',
					'errors' => $validation['errors'],
					'warnings' => $validation['warnings']
				];
			}

			// Generuj drafty
			$results = $this->generatorService->generateAllDrafts($week);
			
			$totalDrafts = array_sum(array_map('count', $results));
			
			return [
				'success' => true,
				'message' => "Wygenerowano {$totalDrafts} draftów maili",
				'drafts' => $results,
				'warnings' => $validation['warnings']
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Zaznacz drafty jako gotowe do wysyłki
	 */
	public function approveDrafts(int $weekId, array $draftIds = []): array
	{
		try {
			$week = Week::find($weekId);
			
			if (!$week) {
				return [
					'success' => false,
					'error' => 'Tydzień nie został znaleziony'
				];
			}

			$drafts = empty($draftIds) 
				? $week->emailDrafts() 
				: array_filter($week->emailDrafts(), fn($d) => in_array($d->id, $draftIds));

			$approved = 0;
			foreach ($drafts as $draft) {
				if ($this->validationService->validateDraft($draft)) {
					$draft->markAsReady();
					$approved++;
				}
			}

			return [
				'success' => true,
				'message' => "Zatwierdzono {$approved} draftów do wysyłki"
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Zapisz przesłany plik
	 */
	private function saveUploadedFile(array $fileData, string $identifier): string
	{
		$config = require dirname(__DIR__, 2) . '/config/app.php';
		$uploadDir = $config['paths']['uploads'];
		
		if (!is_dir($uploadDir)) {
			mkdir($uploadDir, 0755, true);
		}

		$extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
		$filename = $identifier . '_' . date('Y-m-d_His') . '.' . $extension;
		$filepath = $uploadDir . '/' . $filename;

		if (!move_uploaded_file($fileData['tmp_name'], $filepath)) {
			throw new \RuntimeException('Nie udało się zapisać pliku');
		}

		return $filepath;
	}

	/**
	 * Usuń tydzień
	 */
	public function delete(int $weekId): array
	{
		try {
			$week = Week::find($weekId);
			
			if (!$week) {
				return [
					'success' => false,
					'error' => 'Tydzień nie został znaleziony'
				];
			}

			// Usuń plik
			if ($week->file_path && file_exists($week->file_path)) {
				unlink($week->file_path);
			}

			$week->delete();

			return [
				'success' => true,
				'message' => "Tydzień {$week->week_number} został usunięty"
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}
}