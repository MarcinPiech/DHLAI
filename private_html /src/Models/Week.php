<?php

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model Week
 * Reprezentuje tydzień z planem tras
 */
class Week
{
    public ?int $id = null;
    public string $week_number;
    public int $year;
    public string $status = 'draft';
    public ?string $file_path = null;
    public ?string $uploaded_at = null;
    public ?string $sent_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Znajdź tydzień po ID
     */
    public static function find(int $id): ?self
    {
        $sql = "SELECT * FROM weeks WHERE id = ? LIMIT 1";
        $result = Database::query($sql, [$id]);
        
        return $result ? self::hydrate($result[0]) : null;
    }

    /**
     * Znajdź tydzień po numerze i roku
     */
    public static function findByNumber(string $weekNumber, int $year): ?self
    {
        $sql = "SELECT * FROM weeks WHERE week_number = ? AND year = ? LIMIT 1";
        $result = Database::query($sql, [$weekNumber, $year]);
        
        return $result ? self::hydrate($result[0]) : null;
    }

    /**
     * Pobierz wszystkie tygodnie
     */
    public static function all(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM weeks ORDER BY year DESC, week_number DESC LIMIT ? OFFSET ?";
        $results = Database::query($sql, [$limit, $offset]);
        
        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Znajdź lub utwórz tydzień
     */
    public static function firstOrCreate(string $weekNumber, int $year): self
    {
        $existing = self::findByNumber($weekNumber, $year);
        
        if ($existing) {
            return $existing;
        }
        
        $week = new self();
        $week->week_number = $weekNumber;
        $week->year = $year;
        $week->save();
        
        return $week;
    }

    /**
     * Zapisz tydzień (insert lub update)
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
        $sql = "INSERT INTO weeks (week_number, year, status, file_path, uploaded_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $success = Database::execute($sql, [
            $this->week_number,
            $this->year,
            $this->status,
            $this->file_path
        ]);
        
        if ($success) {
            $this->id = (int) Database::lastInsertId();
            $this->uploaded_at = date('Y-m-d H:i:s');
        }
        
        return $success;
    }

    /**
     * Aktualizuj istniejący rekord
     */
    private function update(): bool
    {
        $sql = "UPDATE weeks 
                SET week_number = ?, year = ?, status = ?, file_path = ?, 
                    uploaded_at = ?, sent_at = ?
                WHERE id = ?";
        
        return Database::execute($sql, [
            $this->week_number,
            $this->year,
            $this->status,
            $this->file_path,
            $this->uploaded_at,
            $this->sent_at,
            $this->id
        ]);
    }

    /**
     * Usuń tydzień
     */
    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }
        
        $sql = "DELETE FROM weeks WHERE id = ?";
        return Database::execute($sql, [$this->id]);
    }

    /**
     * Pobierz wersje tygodnia
     */
    public function versions(): array
    {
        if (!$this->id) {
            return [];
        }
        
        $sql = "SELECT * FROM week_versions WHERE week_id = ? ORDER BY version DESC";
        return Database::query($sql, [$this->id]);
    }

    /**
     * Pobierz lokalizacje tygodnia
     */
    public function locations(): array
    {
        if (!$this->id) {
            return [];
        }
        
        return Location::findByWeek($this->id);
    }

    /**
     * Pobierz drafty maili
     */
    public function emailDrafts(): array
    {
        if (!$this->id) {
            return [];
        }
        
        return EmailDraft::findByWeek($this->id);
    }

    /**
     * Sprawdź czy tydzień został wysłany
     */
    public function isSent(): bool
    {
        return $this->status === 'sent' && $this->sent_at !== null;
    }

    /**
     * Oznacz jako wysłany
     */
    public function markAsSent(): bool
    {
        $this->status = 'sent';
        $this->sent_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Konwertuj array z bazy na obiekt
     */
    private static function hydrate(array $data): self
    {
        $week = new self();
        $week->id = (int) $data['id'];
        $week->week_number = $data['week_number'];
        $week->year = (int) $data['year'];
        $week->status = $data['status'];
        $week->file_path = $data['file_path'];
        $week->uploaded_at = $data['uploaded_at'];
        $week->sent_at = $data['sent_at'];
        $week->created_at = $data['created_at'];
        $week->updated_at = $data['updated_at'];
        
        return $week;
    }

    /**
     * Konwertuj obiekt na array (dla JSON)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'week_number' => $this->week_number,
            'year' => $this->year,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'uploaded_at' => $this->uploaded_at,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }