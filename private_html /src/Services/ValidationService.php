<?php

namespace App\Services;

use App\Models\Week;
use App\Models\Location;
use App\Models\EmailDraft;

/**
 * ValidationService
 * Walidacja kompletności danych przed wysyłką
 */
class ValidationService
{
    private array $errors = [];
    private array $warnings = [];

    /**
     * Waliduj tydzień przed wysyłką
     */
    public function validateWeek(Week $week): array
    {
        $this->errors = [];
        $this->warnings = [];

        // Sprawdź lokalizacje
        $this->validateLocations($week);
        
        // Sprawdź kontakty
        $this->validateContacts($week);
        
        // Sprawdź zdjęcia
        $this->validatePhotos($week);
        
        // Sprawdź drafty maili
        $this->validateDrafts($week);

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    /**
     * Waliduj lokalizacje
     */
    private function validateLocations(Week $week): void
    {
        $locations = $week->locations();

        if (empty($locations)) {
            $this->errors[] = 'Brak lokalizacji w planie tras';
            return;
        }

        foreach ($locations as $location) {
            // Sprawdź adres
            if (empty($location->dhl_hs) && empty($location->address_street)) {
                $this->errors[] = "Wiersz {$location->row_number}: Brak adresu (kolumna C lub E-H)";
            }

            // Sprawdź monterów
            $hasTeam = !empty($location->team_substrate) 
                    || !empty($location->team_electric) 
                    || !empty($location->team_service)
                    || !empty($location->team_assembly1)
                    || !empty($location->team_assembly2)
                    || !empty($location->team_assembly3);

            if (!$hasTeam) {
                $this->warnings[] = "Wiersz {$location->row_number}: Brak przypisanych monterów";
            }
        }
    }

    /**
     * Waliduj kontakty
     */
    private function validateContacts(Week $week): void
    {
        $locations = $week->locations();
        $missingContacts = [];

        foreach ($locations as $location) {
            $teams = $location->getAllTeamMembers();
            
            foreach ($teams as $teamMember) {
                $contact = $this->findContact($teamMember);
                
                if (!$contact) {
                    $missingContacts[$teamMember] = true;
                }
            }
        }

        foreach (array_keys($missingContacts) as $member) {
            $this->errors[] = "Brak kontaktu dla: {$member}";
        }
    }

    /**
     * Waliduj zdjęcia (szczególnie dla Hubtrans)
     */
    private function validatePhotos(Week $week): void
    {
        $locations = $week->locations();

        foreach ($locations as $location) {
            // Hubtrans wymaga zdjęć
            if ($location->transport_auto_company === 'HUBTRANS' 
                || $location->transport_jumbo_company === 'HUBTRANS') {
                
                if (empty($location->photos_paths)) {
                    $this->errors[] = "Wiersz {$location->row_number}: Brak zdjęć dla HUBTRANS";
                } else {
                    // Sprawdź czy pliki istnieją
                    foreach ($location->photos_paths as $photoPath) {
                        if (!file_exists($photoPath)) {
                            $this->errors[] = "Wiersz {$location->row_number}: Plik zdjęcia nie istnieje: " . basename($photoPath);
                        }
                    }
                }
            }

            // Ostrzeżenie dla innych przewoźników bez zdjęć
            if (!empty($location->transport_auto_company) 
                && $location->transport_auto_company !== 'HUBTRANS' 
                && empty($location->photos_paths)) {
                
                $this->warnings[] = "Wiersz {$location->row_number}: Brak zdjęć dla {$location->transport_auto_company} (zalecane)";
            }
        }
    }

    /**
     * Waliduj drafty maili
     */
    private function validateDrafts(Week $week): void
    {
        $drafts = $week->emailDrafts();

        if (empty($drafts)) {
            $this->errors[] = 'Brak wygenerowanych draftów maili';
            return;
        }

        foreach ($drafts as $draft) {
            // Sprawdź czy draft ma błędy walidacji
            if ($draft->hasErrors()) {
                foreach ($draft->validation_errors as $error) {
                    $this->errors[] = "Draft #{$draft->id} ({$draft->email_type}): {$error}";
                }
            }

            // Sprawdź wymagane pola
            if (empty($draft->recipient_email)) {
                $this->errors[] = "Draft #{$draft->id}: Brak adresu email odbiorcy";
            }

            if (empty($draft->subject)) {
                $this->errors[] = "Draft #{$draft->id}: Brak tematu maila";
            }

            if (empty($draft->body_html)) {
                $this->errors[] = "Draft #{$draft->id}: Brak treści maila";
            }
        }
    }

    /**
     * Waliduj pojedynczy draft
     */
    public function validateDraft(EmailDraft $draft): bool
    {
        $this->errors = [];

        if (empty($draft->recipient_email)) {
            $this->errors[] = 'Brak adresu email odbiorcy';
        } elseif (!filter_var($draft->recipient_email, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Nieprawidłowy adres email odbiorcy';
        }

        if (empty($draft->subject)) {
            $this->errors[] = 'Brak tematu maila';
        }

        if (empty($draft->body_html)) {
            $this->errors[] = 'Brak treści maila';
        }

        // Sprawdź załączniki
        if (!empty($draft->attachments)) {
            foreach ($draft->attachments as $attachment) {
                if (!file_exists($attachment['path'])) {
                    $this->errors[] = 'Plik załącznika nie istnieje: ' . $attachment['name'];
                }
            }
        }

        // Zapisz błędy w drafcie
        if (!empty($this->errors)) {
            $draft->validation_errors = $this->errors;
            $draft->save();
            return false;
        }

        return true;
    }

    /**
     * Pobierz błędy
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Pobierz ostrzeżenia
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Znajdź kontakt
     */
    private function findContact(string $name): ?array
    {
        $sql = "SELECT * FROM contacts WHERE full_name LIKE ? AND active = 1 LIMIT 1";
        $result = \App\Database::query($sql, ["%{$name}%"]);
        
        return $result[0] ?? null;
    }
}