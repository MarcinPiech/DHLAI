<?php

namespace App\Services;

use App\Models\Week;
use App\Models\Location;
use App\Models\EmailDraft;
use App\Models\Contact;

/**
 * EmailGeneratorService
 * Generowanie draftów maili na podstawie danych z bazy
 */
class EmailGeneratorService
{
    private array $mailConfig;
    private array $appConfig;

    public function __construct()
    {
        $this->mailConfig = require dirname(__DIR__, 2) . '/config/mail.php';
        $this->appConfig = require dirname(__DIR__, 2) . '/config/app.php';
    }

    /**
     * Generuj wszystkie drafty dla tygodnia
     */
    public function generateAllDrafts(Week $week): array
    {
        $results = [
            'team_substrate' => $this->generateTeamSubstrateDrafts($week),
            'team_service' => $this->generateTeamServiceDrafts($week),
            'team_assembly' => $this->generateTeamAssemblyDrafts($week),
            'transport_auto' => $this->generateTransportAutoDrafts($week),
            'transport_jumbo' => $this->generateTransportJumboDrafts($week),
            'bags' => $this->generateBagsDrafts($week),
        ];

        return $results;
    }

    /**
     * BLOK 1A: Maile dla ekip podłożowo-prądowych
     */
    public function generateTeamSubstrateDrafts(Week $week): array
    {
        $locations = $week->locations();
        $drafts = [];
        
        // Grupuj według monterów (kolumny O-Q)
        $grouped = $this->groupByTeamMembers($locations, ['team_substrate', 'team_electric']);
        
        foreach ($grouped as $teamMember => $teamLocations) {
            $contact = $this->findContact($teamMember);
            
            if (!$contact) {
                continue; // Pomiń jeśli brak kontaktu
            }
            
            $draft = new EmailDraft();
            $draft->week_id = $week->id;
            $draft->recipient_email = $contact['email'];
            $draft->recipient_name = $teamMember;
            $draft->cc_emails = $this->mailConfig['default_cc'];
            $draft->subject = "Plan prac {$week->week_number} - Podłoże/Prąd";
            $draft->email_type = 'team_substrate';
            $draft->priority = 1;
            
            // Generuj treść HTML
            $draft->body_html = $this->renderTemplate('team-substrate', [
                'week' => $week,
                'team_member' => $teamMember,
                'locations' => $teamLocations,
                'columns' => ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'O', 'P', 'Q', 'R']
            ]);
            
            $draft->body_plain = strip_tags($draft->body_html);
            
            if ($draft->save()) {
                $drafts[] = $draft;
            }
        }
        
        return $drafts;
    }

    /**
     * BLOK 1B: Maile dla ekip serwisowych
     */
    public function generateTeamServiceDrafts(Week $week): array
    {
        $locations = $week->locations();
        $drafts = [];
        
        // Grupuj według monterów serwisowych (kolumny O-Q)
        $grouped = $this->groupByTeamMembers($locations, ['team_service']);
        
        foreach ($grouped as $teamMember => $teamLocations) {
            $contact = $this->findContact($teamMember);
            
            if (!$contact) {
                continue;
            }
            
            $draft = new EmailDraft();
            $draft->week_id = $week->id;
            $draft->recipient_email = $contact['email'];
            $draft->recipient_name = $teamMember;
            $draft->cc_emails = $this->mailConfig['default_cc'];
            $draft->subject = "Plan prac {$week->week_number} - Serwis";
            $draft->email_type = 'team_service';
            $draft->priority = 1;
            
            $draft->body_html = $this->renderTemplate('team-service', [
                'week' => $week,
                'team_member' => $teamMember,
                'locations' => $teamLocations,
                'columns' => ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'N', 'O', 'P', 'Q', 'R']
            ]);
            
            $draft->body_plain = strip_tags($draft->body_html);
            
            if ($draft->save()) {
                $drafts[] = $draft;
            }
        }
        
        return $drafts;
    }

    /**
     * BLOK 1C: Maile dla ekip montażowych
     */
    public function generateTeamAssemblyDrafts(Week $week): array
    {
        $locations = $week->locations();
        $drafts = [];
        
        // Grupuj według monterów montażu (kolumny U-W)
        $grouped = $this->groupByTeamMembers($locations, ['team_assembly1', 'team_assembly2', 'team_assembly3']);
        
        foreach ($grouped as $teamMember => $teamLocations) {
            $contact = $this->findContact($teamMember);
            
            if (!$contact) {
                continue;
            }
            
            $draft = new EmailDraft();
            $draft->week_id = $week->id;
            $draft->recipient_email = $contact['email'];
            $draft->recipient_name = $teamMember;
            $draft->cc_emails = $this->mailConfig['default_cc'];
            $draft->subject = "Plan prac {$week->week_number} - Montaż";
            $draft->email_type = 'team_assembly';
            $draft->priority = 1;
            
            $draft->body_html = $this->renderTemplate('team-assembly', [
                'week' => $week,
                'team_member' => $teamMember,
                'locations' => $teamLocations,
                'columns' => ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'S', 'T', 'U', 'V', 'W', 'AE', 'BL']
            ]);
            
            $draft->body_plain = strip_tags($draft->body_html);
            
            if ($draft->save()) {
                $drafts[] = $draft;
            }
        }
        
        return $drafts;
    }

    /**
     * BLOK 5.1: Maile dla przewoźników - Automaty
     */
    public function generateTransportAutoDrafts(Week $week): array
    {
        $locations = $week->locations();
        $drafts = [];
        
        $companies = [
            'WAŁĘGA BĘDZIN' => 'walegatransport@gmail.com',
            'HUBTRANS' => 'hubert_czub@op.pl',
            'STYPCZYŃSCY' => 'biuro@stypczynski.pl'
        ];
        
        foreach ($companies as $companyName => $email) {
            $companyLocations = array_filter($locations, function($loc) use ($companyName) {
                return $loc->transport_auto_company === $companyName;
            });
            
            if (empty($companyLocations)) {
                continue;
            }
            
            $draft = new EmailDraft();
            $draft->week_id = $week->id;
            $draft->recipient_email = $email;
            $draft->recipient_name = $companyName;
            $draft->cc_emails = $this->mailConfig['default_cc'];
            $draft->subject = "Transport automatów {$week->week_number} - {$companyName}";
            $draft->email_type = 'transport_auto';
            $draft->priority = 2;
            
            // Dla Hubtrans: sprawdź czy są zdjęcia
            $requirePhotos = ($companyName === 'HUBTRANS');
            
            $draft->body_html = $this->renderTemplate('transport-auto', [
                'week' => $week,
                'company' => $companyName,
                'locations' => $companyLocations,
                'require_photos' => $requirePhotos,
                'columns' => ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'S', 'T', 'U', 'AB', 'AC', 'AD', 'AE', 'BL']
            ]);
            
            $draft->body_plain = strip_tags($draft->body_html);
            
            // Dołącz zdjęcia jako załączniki
            $draft->attachments = $this->collectPhotosForLocations($companyLocations);
            
            // Walidacja: Hubtrans musi mieć zdjęcia
            if ($requirePhotos && empty($draft->attachments)) {
                $draft->addValidationError('Brak zdjęć miejsca rozładunku dla Hubtrans');
            }
            
            if ($draft->save()) {
                $drafts[] = $draft;
            }
        }
        
        return $drafts;
    }

    /**
     * BLOK 5.2: Maile dla przewoźników - Płyty Jumbo
     */
    public function generateTransportJumboDrafts(Week $week): array
    {
        $locations = $week->locations();
        $drafts = [];
        
        $companies = [
            'WAŁĘGA BĘDZIN' => 'walegatransport@gmail.com',
            'HUBTRANS' => 'hubert_czub@op.pl',
            'STYPCZYŃSCY' => 'biuro@stypczynski.pl'
        ];
        
        foreach ($companies as $companyName => $email) {
            $companyLocations = array_filter($locations, function($loc) use ($companyName) {
                return $loc->transport_jumbo_company === $companyName;
            });
            
            if (empty($companyLocations)) {
                continue;
            }
            
            $draft = new EmailDraft();
            $draft->week_id = $week->id;
            $draft->recipient_email = $email;
            $draft->recipient_name = $companyName;
            $draft->cc_emails = $this->mailConfig['default_cc'];
            $draft->subject = "Transport płyt jumbo {$week->week_number} - {$companyName}";
            $draft->email_type = 'transport_jumbo';
            $draft->priority = 2;
            
            $draft->body_html = $this->renderTemplate('transport-jumbo', [
                'week' => $week,
                'company' => $companyName,
                'locations' => $companyLocations,
                'columns' => ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'X', 'Y', 'Z']
            ]);
            
            $draft->body_plain = strip_tags($draft->body_html);
            
            // Dołącz zdjęcia miejsc montażu (dla właściwego rozładunku płyt)
            $draft->attachments = $this->collectPhotosForLocations($companyLocations);
            
            if ($draft->save()) {
                $drafts[] = $draft;
            }
        }
        
        return $drafts;
    }

    /**
     * BLOK 6: Maile dla big-bagów
     */
    public function generateBagsDrafts(Week $week): array
    {
        $sql = "SELECT * FROM bags WHERE week_id = ? ORDER BY load_date";
        $bags = \App\Database::query($sql, [$week->id]);
        
        if (empty($bags)) {
            return [];
        }
        
        // Grupuj według firm HDS
        $grouped = [];
        foreach ($bags as $bag) {
            $company = $bag['hds_company'];
            if (!isset($grouped[$company])) {
                $grouped[$company] = [];
            }
            $grouped[$company][] = $bag;
        }
        
        $drafts = [];
        
        foreach ($grouped as $hdsCompany => $companyBags) {
            // TODO: Znaleźć email dla firmy HDS
            $email = 'operacyjne@apm-service.com'; // Placeholder
            
            $draft = new EmailDraft();
            $draft->week_id = $week->id;
            $draft->recipient_email = $email;
            $draft->recipient_name = $hdsCompany;
            $draft->cc_emails = $this->mailConfig['default_cc'];
            $draft->subject = "Odbiór big-bagów {$week->week_number} - {$hdsCompany}";
            $draft->email_type = 'bags';
            $draft->priority = 3;
            
            $draft->body_html = $this->renderTemplate('bags', [
                'week' => $week,
                'hds_company' => $hdsCompany,
                'bags' => $companyBags
            ]);
            
            $draft->body_plain = strip_tags($draft->body_html);
            
            if ($draft->save()) {
                $drafts[] = $draft;
            }
        }
        
        return $drafts;
    }

    /**
     * Grupuj lokalizacje według członków ekipy
     */
    private function groupByTeamMembers(array $locations, array $fields): array
    {
        $grouped = [];
        
        foreach ($locations as $location) {
            foreach ($fields as $field) {
                $member = $location->$field;
                
                if (empty($member)) {
                    continue;
                }
                
                if (!isset($grouped[$member])) {
                    $grouped[$member] = [];
                }
                
                $grouped[$member][] = $location;
            }
        }
        
        return $grouped;
    }

    /**
     * Znajdź kontakt dla członka ekipy
     */
    private function findContact(string $name): ?array
    {
        $sql = "SELECT * FROM contacts WHERE full_name LIKE ? AND active = 1 LIMIT 1";
        $result = \App\Database::query($sql, ["%{$name}%"]);
        
        return $result[0] ?? null;
    }

    /**
     * Zbierz zdjęcia dla lokalizacji
     */
    private function collectPhotosForLocations(array $locations): array
    {
        $photos = [];
        
        foreach ($locations as $location) {
            if (!empty($location->photos_paths)) {
                foreach ($location->photos_paths as $photoPath) {
                    if (file_exists($photoPath)) {
                        $photos[] = [
                            'path' => $photoPath,
                            'name' => basename($photoPath),
                            'type' => mime_content_type($photoPath),
                            'size' => filesize($photoPath)
                        ];
                    }
                }
            }
        }
        
        return $photos;
    }

    /**
     * Renderuj szablon email
     */
    private function renderTemplate(string $template, array $data): string
    {
        $templatePath = dirname(__DIR__) . "/Views/emails/{$template}.php";
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Szablon {$template} nie istnieje");
        }
        
        extract($data);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}