<?php
// File: src/models/Settings.php

require_once __DIR__ . '/BaseModel.php';

class Settings extends BaseModel {
    protected $table = 'settings';

    /**
     * Get all settings as an associative array (key => value).
     * @return array
     */
    public function getSettingsMap() {
        $sql = "SELECT name, value FROM {$this->table}";
        $stmt = $this->query($sql);
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $settings;
    }

    /**
     * Get a single setting value by its name.
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get($name, $default = null) {
        $sql = "SELECT value FROM {$this->table} WHERE name = :name LIMIT 1";
        $stmt = $this->query($sql, ['name' => $name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['value'] ?? $default;
    }
}