<?php

namespace App\Services;

use App\Models\EmailDraft;
use App\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * EmailSenderService
 * Wysyłka maili przez PHPMailer
 */
class EmailSenderService
{
    private array $config;
    private int $sentCount = 0;
    private float $lastSentTime = 0;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/mail.php';
    }

    /**
     * Wyślij wszystkie gotowe drafty dla tygodnia
     */
    public function sendWeekDrafts(int $weekId): array
    {
        $drafts = EmailDraft::findByWeek($weekId, 'ready');
        
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($drafts as $draft) {
            $this->rateLimitCheck();
            
            try {
                if ($this->sendDraft($draft)) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Wyślij pojedynczy draft
     */
    public function sendDraft(EmailDraft $draft): bool
    {
        $mail = new PHPMailer(true);
        
        try {
            // Konfiguracja SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];
            $mail->CharSet = 'UTF-8';
            
            // Nadawca
            $mail->setFrom(
                $this->config['from']['address'],
                $this->config['from']['name']
            );
            
            // Odbiorca
            $mail->addAddress($draft->recipient_email, $draft->recipient_name);
            
            // CC
            if (!empty($draft->cc_emails)) {
                foreach ($draft->cc_emails as $cc) {
                    $mail->addCC($cc);
                }
            }
            
            // Potwierdzenie odbioru
            if ($this->config['options']['read_receipt']) {
                $mail->addCustomHeader('Disposition-Notification-To', $this->config['from']['address']);
                $mail->addCustomHeader('Return-Receipt-To', $this->config['from']['address']);
                $mail->addCustomHeader('X-Confirm-Reading-To', $this->config['from']['address']);
            }
            
            // Treść
            $mail->isHTML(true);
            $mail->Subject = $draft->subject;
            
            // Dodaj tracking pixel jeśli włączony
            $bodyHtml = $draft->body_html;
            if ($this->config['options']['tracking_pixel']) {
                $bodyHtml .= $this->generateTrackingPixel($draft->id);
            }
            
            $mail->Body = $bodyHtml;
            $mail->AltBody = $draft->body_plain ?: strip_tags($draft->body_html);
            
            // Załączniki
            if (!empty($draft->attachments)) {
                foreach ($draft->attachments as $attachment) {
                    if (file_exists($attachment['path'])) {
                        $mail->addAttachment(
                            $attachment['path'],
                            $attachment['name']
                        );
                    }
                }
            }
            
            // Wyślij
            $mail->send();
            
            // Logowanie
            $this->logEmail($draft, 'sent', $mail->getLastMessageID());
            
            // Aktualizuj draft
            $draft->markAsSent();
            
            // Rate limiting
            $this->sentCount++;
            $this->lastSentTime = microtime(true);
            
            return true;
            
        } catch (Exception $e) {
            // Logowanie błędu
            $this->logEmail($draft, 'failed', null, $e->getMessage());
            
            // Retry jeśli włączony
            if ($this->config['options']['retry_failed']) {
                return $this->retryDraft($draft);
            }
            
            throw $e;
        }
    }

    /**
     * Ponowna próba wysyłki
     */
    private function retryDraft(EmailDraft $draft, int $attempt = 1): bool
    {
        $maxRetries = $this->config['options']['max_retries'] ?? 3;
        
        if ($attempt > $maxRetries) {
            return false;
        }
        
        // Czekaj przed kolejną próbą (exponential backoff)
        sleep(pow(2, $attempt));
        
        try {
            return $this->sendDraft($draft);
        } catch (\Exception $e) {
            return $this->retryDraft($draft, $attempt + 1);
        }
    }

    /**
     * Rate limiting - sprawdź limity wysyłki
     */
    private function rateLimitCheck(): void
    {
        $limits = $this->config['limits'];
        
        // Limit per minute
        if ($this->sentCount >= $limits['per_minute']) {
            $elapsed = microtime(true) - $this->lastSentTime;
            if ($elapsed < 60) {
                sleep((int)(60 - $elapsed));
                $this->sentCount = 0;
            }
        }
    }

    /**
     * Zaloguj wysyłkę maila
     */
    private function logEmail(
        EmailDraft $draft, 
        string $status, 
        ?string $messageId = null,
        ?string $errorMessage = null
    ): void {
        $sql = "INSERT INTO email_logs (
                    draft_id, week_id, recipient_email, subject, 
                    status, message_id, error_message, read_receipt_requested
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        Database::execute($sql, [
            $draft->id,
            $draft->week_id,
            $draft->recipient_email,
            $draft->subject,
            $status,
            $messageId,
            $errorMessage,
            $this->config['options']['read_receipt']
        ]);
    }

    /**
     * Generuj tracking pixel
     */
    private function generateTrackingPixel(int $draftId): string
    {
        $url = getenv('APP_URL') . "/track.php?id=" . base64_encode($draftId);
        return "<img src='{$url}' width='1' height='1' style='display:none' />";
    }

    /**
     * Oznacz email jako otwarty (wywołane przez tracking pixel)
     */
    public static function markAsOpened(int $logId): bool
    {
        $sql = "UPDATE email_logs SET opened_at = NOW(), status = 'opened' 
                WHERE id = ? AND opened_at IS NULL";
        
        return Database::execute($sql, [$logId]);
    }

    /**
     * Pobierz statystyki wysyłki dla tygodnia
     */
    public function getWeekStats(int $weekId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
                    SUM(CASE WHEN read_receipt_received_at IS NOT NULL THEN 1 ELSE 0 END) as read_receipts
                FROM email_logs 
                WHERE week_id = ?";
        
        $result = Database::query($sql, [$weekId]);
        return $result[0] ?? [];
    }
}