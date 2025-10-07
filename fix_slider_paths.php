<?php
require_once __DIR__ . '/src/models/Database.php';
require_once __DIR__ . '/src/models/BaseModel.php';
require_once __DIR__ . '/src/models/Slider.php';

class SliderPathFixer {
    private $db;
    private $sliderModel;
    private $basePath = '/SIAGABAPOK/Siaga_bapok/public';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->sliderModel = new Slider();
    }
    
    public function fixPaths() {
        try {
            // Get all sliders
            $sliders = $this->sliderModel->all(1, 100)['data'] ?? [];
            
            if (empty($sliders)) {
                echo "No sliders found in the database.\n";
                return;
            }
            
            $updated = 0;
            
            foreach ($sliders as $slider) {
                $oldPath = $slider['image_path'];
                
                // Skip if path is already correct
                if (strpos($oldPath, $this->basePath) === 0) {
                    echo "Skipping (already correct): $oldPath\n";
                    continue;
                }
                
                // Fix the path
                $newPath = $this->basePath . $oldPath;
                
                // Check if file exists
                $filePath = __DIR__ . str_replace('/SIAGABAPOK/Siaga_bapok', '', $newPath);
                if (!file_exists($filePath)) {
                    echo "File not found: $filePath\n";
                    continue;
                }
                
                // Update in database
                $this->db->execute(
                    "UPDATE sliders SET image_path = ? WHERE id = ?",
                    [$newPath, $slider['id']]
                );
                
                echo "Updated: $oldPath -> $newPath\n";
                $updated++;
            }
            
            echo "\nTotal sliders processed: " . count($sliders) . "\n";
            echo "Updated paths: $updated\n";
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    public function checkPaths() {
        $sliders = $this->sliderModel->all(1, 100)['data'] ?? [];
        
        echo "Current Slider Paths:\n";
        echo str_repeat("-", 100) . "\n";
        
        foreach ($sliders as $slider) {
            $filePath = __DIR__ . str_replace('/SIAGABAPOK/Siaga_bapok', '', $slider['image_path']);
            $exists = file_exists($filePath) ? '✅' : '❌';
            
            echo "ID: {$slider['id']} | " . 
                 "Exists: $exists | " . 
                 "Path: {$slider['image_path']}\n";
        }
    }
}

// Run the fixer
$fixer = new SliderPathFixer();

// Check if we should just show current paths or fix them
$action = $argv[1] ?? 'check';

if ($action === 'fix') {
    echo "Fixing slider paths...\n";
    $fixer->fixPaths();
} else {
    echo "Checking current slider paths...\n";
    $fixer->checkPaths();
    echo "\nTo fix the paths, run: php fix_slider_paths.php fix\n";
}
