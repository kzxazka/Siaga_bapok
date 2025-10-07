<?php
// File: src/models/Commodity.php

require_once __DIR__ . '/BaseModel.php';

class Commodity extends BaseModel {
    protected $table = 'commodities';

    /**
     * Get a single commodity by ID.
     * @param int $id
     * @return array|null
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->query($sql, ['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all commodities.
     * @return array
     */
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}