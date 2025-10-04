<?php

namespace App\Models;

use App\Database;

/**
 * Model Location
 * Reprezentuje lokalizację (wiersz z pliku Excel)
 */
class Location
{
    public ?int $id = null;
    public int $week_id;
    public ?int $version_id = null;
    public int $row_number;
    
    // Adres
    public ?string $dhl_hs = null;
    public ?string $address_street = null;
    public ?string $address_city = null;
    public ?string $address_postal = null;
    public ?string $address_coords = null;
    
    // Monterzy podłoże/prąd/serwis
    public ?string $team_substrate = null;
    public ?string $team_electric = null;
    public ?string $team_service = null;
    
    // Monterzy montaż
    public ?string $team_assembly1 = null;
    public ?string $team_assembly2 = null;
    public ?string $team_assembly3 = null;
    
    // Transport
    public ?string $transport_auto_company = null;
    public ?array $transport_auto_data = null;
    public ?string $transport_jumbo_company = null;
    public ?array $transport_jumbo_data = null;
    
    // Pliki
    public ?string $protocol_path = null;
    public ?array $photos_paths = null;

    /**
     * Znajdź lokalizację po ID
     */
    public static function find(int $id): ?self
    {
        $sql = "SELECT * FROM locations WHERE id = ? LIMIT 1";
        $result = Database::query($sql, [$id]);
        
        return $result ? self::hydrate($result[0]) : null;
    }

    /**
     * Znajdź lokalizacje dla tygodnia
     */
    public static function findByWeek(int $weekId, ?int $versionId = null): array
    {
        if ($versionId) {
            $sql = "SELECT * FROM locations WHERE week_id = ? AND version_id = ? ORDER BY row_number";
            $results = Database::query($sql, [$weekId, $versionId]);
        } else {
            $sql = "SELECT * FROM locations WHERE week_id = ? ORDER BY row_number";
            $results = Database::query($sql, [$weekId]);
        }
        
        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Znajdź lokalizacje dla konkretnego montera
     */
    public static function findByTeamMember(int $weekId, string $memberName): array
    {
        $sql = "SELECT * FROM locations 
                WHERE week_id = ? 
                AND (team_substrate LIKE ? 
                     OR team_electric LIKE ? 
                     OR team_service LIKE ?
                     OR team_assembly1 LIKE ?
                     OR team_assembly2 LIKE ?
                     OR team_assembly3 LIKE ?)
                ORDER BY row_number";
        
        $pattern = "%{$memberName}%";
        $params = [$weekId, $pattern, $pattern, $pattern, $pattern, $pattern, $pattern];
        
        $results = Database::query($sql, $params);
        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Znajdź lokalizacje dla przewoźnika
     */
    public static function findByTransportCompany(int $weekId, string $company): array
    {
        $sql = "SELECT * FROM locations 
                WHERE week_id = ? 
                AND (transport_auto_company = ? OR transport_jumbo_company = ?)
                ORDER BY row_number";
        
        $results = Database::query($sql, [$weekId, $company, $company]);
        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Zapisz lokalizację
     */
    public function save(): bool
    {
        if ($this->id) {
            return $this->update();
        }
        return $this->insert();
    }

    /**
     * Wstaw nowy rekord
     */
    private function insert(): bool
    {
        $sql = "INSERT INTO locations (
                    week_id, version_id, row_number, dhl_hs, 
                    address_street, address_city, address_postal, address_coords,
                    team_substrate, team_electric, team_service,
                    team_assembly1, team_assembly2, team_assembly3,
                    transport_auto_company, transport_auto_data,
                    transport_jumbo_company, transport_jumbo_data,
                    protocol_path, photos_paths
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $success = Database::execute($sql, [
            $this->week_id,
            $this->version_id,
            $this->row_number,
            $this->dhl_hs,
            $this->address_street,
            $this->address_city,
            $this->address_postal,
            $this->address_coords,
            $this->team_substrate,
            $this->team_electric,
            $this->team_service,
            $this->team_assembly1,
            $this->team_assembly2,
            $this->team_assembly3,
            $this->transport_auto_company,
            json_encode($this->transport_auto_data),
            $this->transport_jumbo_company,
            json_encode($this->transport_jumbo_data),
            $this->protocol_path,
            json_encode($this->photos_paths)
        ]);
        
        if ($success) {
            $this->id = (int) Database::lastInsertId();
        }
        
        return $success;
    }

    /**
     * Aktualizuj rekord
     */
    private function update(): bool
    {
        $sql = "UPDATE locations SET
                    week_id = ?, version_id = ?, row_number = ?, dhl_hs = ?,
                    address_street = ?, address_city = ?, address_postal = ?, address_coords = ?,
                    team_substrate = ?, team_electric = ?, team_service = ?,
                    team_assembly1 = ?, team_assembly2 = ?, team_assembly3 = ?,
                    transport_auto_company = ?, transport_auto_data = ?,
                    transport_jumbo_company = ?, transport_jumbo_data = ?,
                    protocol_path = ?, photos_paths = ?
                WHERE id = ?";
        
        return Database::execute($sql, [
            $this->week_id,
            $this->version_id,
            $this->row_number,
            $this->dhl_hs,
            $this->address_street,
            $this->address_city,
            $this->address_postal,
            $this->address_coords,
            $this->team_substrate,
            $this->team_electric,
            $this->team_service,
            $this->team_assembly1,
            $this->team_assembly2,
            $this->team_assembly3,
            $this->transport_auto_company,
            json_encode($this->transport_auto_data),
            $this->transport_jumbo_company,
            json_encode($this->transport_jumbo_data),
            $this->protocol_path,
            json_encode($this->photos_paths),
            $this->id
        ]);
    }

    /**
     * Pobierz pełny adres (używając reguł z dokumentacji)
     */
    public function getFullAddress(): string
    {
        // Jeśli C != "poza HS" -> użyj C
        if ($this->dhl_hs && $this->dhl_hs !== 'poza HS') {
            return $this->dhl_hs;
        }
        
        // Jeśli C = "poza HS" -> użyj E-H
        $parts = array_filter([
            $this->address_street,
            $this->address_postal,
            $this->address_city,
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Pobierz wszystkich monterów dla tej lokalizacji
     */
    public function getAllTeamMembers(): array
    {
        return array_filter([
            $this->team_substrate,
            $this->team_electric,
            $this->team_service,
            $this->team_assembly1,
            $this->team_assembly2,
            $this->team_assembly3,
        ]);
    }

    /**
     * Konwertuj array z bazy na obiekt
     */
    private static function hydrate(array $data): self
    {
        $location = new self();
        $location->id = (int) $data['id'];
        $location->week_id = (int) $data['week_id'];
        $location->version_id = $data['version_id'] ? (int) $data['version_id'] : null;
        $location->row_number = (int) $data['row_number'];
        
        $location->dhl_hs = $data['dhl_hs'];
        $location->address_street = $data['address_street'];
        $location->address_city = $data['address_city'];
        $location->address_postal = $data['address_postal'];
        $location->address_coords = $data['address_coords'];
        
        $location->team_substrate = $data['team_substrate'];
        $location->team_electric = $data['team_electric'];
        $location->team_service = $data['team_service'];
        $location->team_assembly1 = $data['team_assembly1'];
        $location->team_assembly2 = $data['team_assembly2'];
        $location->team_assembly3 = $data['team_assembly3'];
        
        $location->transport_auto_company = $data['transport_auto_company'];
        $location->transport_auto_data = json_decode($data['transport_auto_data'], true);
        $location->transport_jumbo_company = $data['transport_jumbo_company'];
        $location->transport_jumbo_data = json_decode($data['transport_jumbo_data'], true);
        
        $location->protocol_path = $data['protocol_path'];
        $location->photos_paths = json_decode($data['photos_paths'], true);
        
        return $location;
    }

    /**
     * Konwertuj na array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'week_id' => $this->week_id,
            'row_number' => $this->row_number,
            'full_address' => $this->getFullAddress(),
            'dhl_hs' => $this->dhl_hs,
            'teams' => $this->getAllTeamMembers(),
            'transport_auto_company' => $this->transport_auto_company,
            'transport_jumbo_company' => $this->transport_jumbo_company,
        ];
    }
}