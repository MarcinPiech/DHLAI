<?php

namespace App\Services;

use App\Models\Week;
use App\Models\Location;
use App\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ExcelParserService
 * Parsowanie plików Excel (Plan tras) do bazy danych
 */
class ExcelParserService
{
    private array $config;
    
    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';
    }

    /**
     * Parsuj plik Excel i zapisz do bazy
     */
    public function parseWeekPlan(string $filePath, Week $week): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('PLAN');
        
        if (!$sheet) {
            throw new \RuntimeException('Arkusz "PLAN" nie został znaleziony w pliku Excel');
        }

        Database::beginTransaction();
        
        try {
            $versionId = $this->createVersion($week, $filePath);
            $locations = $this->parseLocations($sheet, $week->id, $versionId);
            
            Database::commit();
            
            return [
                'success' => true,
                'version_id' => $versionId,
                'locations_count' => count($locations),
                'locations' => $locations
            ];
            
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Utwórz nową wersję pliku
     */
    private function createVersion(Week $week, string $filePath): int
    {
        $sql = "INSERT INTO week_versions (week_id, version, file_path, file_hash) 
                VALUES (?, ?, ?, ?)";
        
        $version = count($week->versions()) + 1;
        $hash = hash_file('sha256', $filePath);
        
        Database::execute($sql, [$week->id, $version, $filePath, $hash]);
        
        return (int) Database::lastInsertId();
    }

    /**
     * Parsuj wszystkie lokalizacje z arkusza
     */
    private function parseLocations(Worksheet $sheet, int $weekId, int $versionId): array
    {
        $locations = [];
        $highestRow = $sheet->getHighestRow();
        
        // Zacznij od wiersza 2 (pierwszy to nagłówki)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Sprawdź czy wiersz nie jest pusty (sprawdź kolumnę C)
            $dhlHs = $this->getCellValue($sheet, 'C', $row);
            
            if (empty($dhlHs)) {
                continue; // Pomiń puste wiersze
            }
            
            $location = $this->parseLocationRow($sheet, $row, $weekId, $versionId);
            
            if ($location->save()) {
                $locations[] = $location;
            }
        }
        
        return $locations;
    }

    /**
     * Parsuj pojedynczy wiersz z lokalizacją
     */
    private function parseLocationRow(
        Worksheet $sheet, 
        int $row, 
        int $weekId, 
        int $versionId
    ): Location {
        $location = new Location();
        $location->week_id = $weekId;
        $location->version_id = $versionId;
        $location->row_number = $row;
        
        // Adres (kolumny C, E-H)
        $location->dhl_hs = $this->getCellValue($sheet, 'C', $row);
        $location->address_street = $this->getCellValue($sheet, 'E', $row);
        $location->address_city = $this->getCellValue($sheet, 'F', $row);
        $location->address_postal = $this->getCellValue($sheet, 'G', $row);
        $location->address_coords = $this->getCellValue($sheet, 'H', $row);
        
        // Monterzy podłoże/prąd/serwis (O-Q)
        $location->team_substrate = $this->getCellValue($sheet, 'O', $row);
        $location->team_electric = $this->getCellValue($sheet, 'P', $row);
        $location->team_service = $this->getCellValue($sheet, 'Q', $row);
        
        // Monterzy montaż (U-W)
        $location->team_assembly1 = $this->getCellValue($sheet, 'U', $row);
        $location->team_assembly2 = $this->getCellValue($sheet, 'V', $row);
        $location->team_assembly3 = $this->getCellValue($sheet, 'W', $row);
        
        // Transport - firma przewozowa (Z lub AE)
        $companyZ = $this->getCellValue($sheet, 'Z', $row);
        $companyAE = $this->getCellValue($sheet, 'AE', $row);
        
        // Automaty: B-H, S-U, AB-AE, BL
        if ($companyZ || $companyAE) {
            $location->transport_auto_company = $companyZ ?: $companyAE;
            $location->transport_auto_data = [
                'columns_b_h' => $this->getColumnRange($sheet, 'B', 'H', $row),
                'columns_s_u' => $this->getColumnRange($sheet, 'S', 'U', $row),
                'columns_ab_ae' => $this->getColumnRange($sheet, 'AB', 'AE', $row),
                'column_bl' => $this->getCellValue($sheet, 'BL', $row),
            ];
        }
        
        // Jumbo: B-H, X-Z
        if ($companyZ || $companyAE) {
            $location->transport_jumbo_company = $companyZ ?: $companyAE;
            $location->transport_jumbo_data = [
                'columns_b_h' => $this->getColumnRange($sheet, 'B', 'H', $row),
                'columns_x_z' => $this->getColumnRange($sheet, 'X', 'Z', $row),
            ];
        }
        
        return $location;
    }

    /**
     * Pobierz wartość komórki
     */
    private function getCellValue(Worksheet $sheet, string $column, int $row): ?string
    {
        $value = $sheet->getCell($column . $row)->getValue();
        
        if ($value === null || $value === '') {
            return null;
        }
        
        return trim((string) $value);
    }

    /**
     * Pobierz zakres kolumn jako array
     */
    private function getColumnRange(
        Worksheet $sheet, 
        string $startCol, 
        string $endCol, 
        int $row
    ): array {
        $data = [];
        $start = ord($startCol);
        $end = ord($endCol);
        
        for ($col = $start; $col <= $end; $col++) {
            $column = chr($col);
            $value = $this->getCellValue($sheet, $column, $row);
            
            if ($value !== null) {
                $data[$column] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Parsuj plik BAG DHL - WYWÓZ
     */
    public function parseBagsFile(string $filePath, Week $week): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        
        Database::beginTransaction();
        
        try {
            $bags = [];
            $highestRow = $sheet->getHighestRow();
            
            for ($row = 2; $row <= $highestRow; $row++) {
                $loadDate = $this->getCellValue($sheet, 'I', $row);
                
                if (empty($loadDate)) {
                    continue;
                }
                
                // Filtruj według tygodnia (kolumna I = data załadunku)
                if ($this->isDateInWeek($loadDate, $week->week_number)) {
                    $hdsCompany = $this->getCellValue($sheet, 'J', $row);
                    
                    $sql = "INSERT INTO bags (week_id, load_date, hds_company, details) 
                            VALUES (?, ?, ?, ?)";
                    
                    $details = json_encode([
                        'row' => $row,
                        'all_data' => $this->getColumnRange($sheet, 'A', 'Z', $row)
                    ]);
                    
                    Database::execute($sql, [
                        $week->id,
                        $loadDate,
                        $hdsCompany,
                        $details
                    ]);
                    
                    $bags[] = [
                        'load_date' => $loadDate,
                        'hds_company' => $hdsCompany
                    ];
                }
            }
            
            Database::commit();
            
            return [
                'success' => true,
                'bags_count' => count($bags),
                'bags' => $bags
            ];
            
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Sprawdź czy data należy do tygodnia Txx
     */
    private function isDateInWeek(string $dateString, string $weekNumber): bool
    {
        // Wyciągnij numer tygodnia z "T35" -> 35
        $weekNum = (int) str_replace('T', '', $weekNumber);
        
        try {
            $date = new \DateTime($dateString);
            $dateWeek = (int) $date->format('W');
            
            return $dateWeek === $weekNum;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Porównaj dwie wersje i znajdź różnice
     */
    public function compareVersions(int $oldVersionId, int $newVersionId): array
    {
        $oldLocations = Location::findByWeek(0, $oldVersionId);
        $newLocations = Location::findByWeek(0, $newVersionId);
        
        $changes = [
            'added' => [],
            'removed' => [],
            'modified' => []
        ];
        
        // Indeksuj po row_number
        $oldByRow = [];
        foreach ($oldLocations as $loc) {
            $oldByRow[$loc->row_number] = $loc;
        }
        
        $newByRow = [];
        foreach ($newLocations as $loc) {
            $newByRow[$loc->row_number] = $loc;
        }
        
        // Znajdź dodane i zmodyfikowane
        foreach ($newByRow as $row => $newLoc) {
            if (!isset($oldByRow[$row])) {
                $changes['added'][] = $row;
            } else {
                $diff = $this->compareLocations($oldByRow[$row], $newLoc);
                if (!empty($diff)) {
                    $changes['modified'][] = [
                        'row' => $row,
                        'changes' => $diff
                    ];
                }
            }
        }
        
        // Znajdź usunięte
        foreach ($oldByRow as $row => $oldLoc) {
            if (!isset($newByRow[$row])) {
                $changes['removed'][] = $row;
            }
        }
        
        return $changes;
    }

    /**
     * Porównaj dwie lokalizacje
     */
    private function compareLocations(Location $old, Location $new): array
    {
        $diff = [];
        
        $fields = [
            'dhl_hs', 'address_street', 'address_city',
            'team_substrate', 'team_electric', 'team_service',
            'team_assembly1', 'team_assembly2', 'team_assembly3',
            'transport_auto_company', 'transport_jumbo_company'
        ];
        
        foreach ($fields as $field) {
            if ($old->$field !== $new->$field) {
                $diff[$field] = [
                    'old' => $old->$field,
                    'new' => $new->$field
                ];
            }
        }
        
        return $diff;
    }
}