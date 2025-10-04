<?php

namespace App\Controllers;

use App\Models\Week;
use App\Models\EmailDraft;
use App\Services\EmailSenderService;

/**
 * EmailController
 * Obsługa wysyłki i zarządzania mailami
 */
class EmailController
{
	private EmailSenderService $senderService;

	public function __construct()
	{
		$this->senderService = new EmailSenderService();
	}

	/**
	 * Wyślij wszystkie zatwierdzone drafty dla tygodnia
	 */
	public function sendWeek(int $weekId): array
	{
		try {
			$week = Week::find($weekId);
			
			if (!$week) {
				return [
					'success' => false,
					'error' => 'Tydzień nie został znaleziony'
				];
			}

			if ($week->isSent()) {
				return [
					'success' => false,
					'error' => 'Tydzień został już wysłany'
				];
			}

			// Wyślij wszystkie drafty ze statusem 'ready'
			$results = $this->senderService->sendWeekDrafts($weekId);

			// Oznacz tydzień jako wysłany
			if ($results['sent'] > 0 && $results['failed'] === 0) {
				$week->markAsSent();
			}

			return [
				'success' => true,
				'message' => "Wysłano {$results['sent']} maili",
				'sent' => $results['sent'],
				'failed' => $results['failed'],
				'errors' => $results['errors']
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Wyślij pojedynczy draft
	 */
	public function sendDraft(int $draftId): array
	{
		try {
			$draft = EmailDraft::find($draftId);
			
			if (!$draft) {
				return [
					'success' => false,
					'error' => 'Draft nie został znaleziony'
				];
			}

			if ($draft->status === 'sent') {
				return [
					'success' => false,
					'error' => 'Mail został już wysłany'
				];
			}

			$this->senderService->sendDraft($draft);

			return [
				'success' => true,
				'message' => 'Mail został wysłany'
			];
			
		} catch (\Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Podgląd drafta
	 */
	public function preview(int $draftId): array
	{
		$draft = EmailDraft::find($draftId);
		
		if (!$draft) {
			return [
				'success' => false,
				'error' => 'Draft nie został znaleziony'
			];
		}

		return [
			'success' => true,
			'draft' => $draft->toArray(),
			'html' => $draft->body_html,
			'plain' => $draft->body_plain
		];
	}

	/**
	 * Pobierz statystyki wysyłki
	 */
	public function stats(int $weekId): array
	{
		$stats = $this->senderService->getWeekStats($weekId);

		return [
			'success' => true,
			'stats' => $stats
		];
	}

	/**
	 * Logi wysyłek dla tygodnia
	 */
	public function logs(int $weekId): array
	{
		$sql = "SELECT * FROM email_logs WHERE week_id = ? ORDER BY sent_at DESC";
		$logs = \App\Database::query($sql, [$weekId]);

		return [
			'success' => true,
			'logs' => $logs
		];
	}
}