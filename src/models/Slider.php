<?php
require_once __DIR__ . '/BaseModel.php';

class Slider extends BaseModel {
    protected $table = 'sliders';
    protected $fillable = ['title', 'description', 'image_path', 'is_active'];
    
    /**
     * Get all active sliders
     */
    public function getActiveSliders() {
        try {
            $stmt = $this->query(
                "SELECT * FROM " . $this->table . " WHERE is_active = 1 ORDER BY created_at DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting active sliders: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new slider with file upload
     */
    public function createWithFile($data, $file) {
        try {
            // Define upload directory (relative to public folder)
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/SIAGABAPOK/Siaga_bapok/public/uploads/sliders/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception('Gagal membuat direktori upload di: ' . $uploadDir);
                }
            }
            
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new Exception('File tidak valid atau gagal diunggah. Error code: ' . $file['error']);
            }
            
            // Generate unique filename
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($fileExt, $allowedExts)) {
                throw new Exception('Format file tidak didukung. Gunakan JPG, PNG, atau GIF');
            }
            
            $fileName = 'slider_' . time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            
            // Debug info
            error_log("Attempting to move uploaded file:");
            error_log("- Source: " . $file['tmp_name']);
            error_log("- Destination: $targetPath");
            error_log("- Upload directory exists: " . (file_exists($uploadDir) ? 'Yes' : 'No'));
            error_log("- Upload directory writable: " . (is_writable($uploadDir) ? 'Yes' : 'No'));
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $error = error_get_last();
                throw new Exception('Gagal menyimpan file. Error: ' . ($error['message'] ?? 'Unknown error'));
            }
            
            // Verify file was actually moved
            if (!file_exists($targetPath)) {
                throw new Exception('File gagal dipindahkan ke direktori upload');
            }
            
            // Set relative path for web access
            $data['image_path'] = '/SIAGABAPOK/Siaga_bapok/public/uploads/sliders/' . $fileName;
            
            // Prepare SQL
            $fields = [];
            $placeholders = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                $fields[] = $key;
                $placeholders[] = ":$key";
                $values[":$key"] = $value;
            }
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") " .
                   "VALUES (" . implode(', ', $placeholders) . ")";
            
            $this->query($sql, $values);
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            // Clean up uploaded file if there was an error
            if (isset($targetPath) && file_exists($targetPath)) {
                unlink($targetPath);
            }
            error_log("Error creating slider: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update a slider with optional file upload
     */
    public function updateWithFile($id, $data, $file = null) {
        try {
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Use the same upload directory as createWithFile
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/SIAGABAPOK/Siaga_bapok/public/uploads/sliders/';
                
                // Create upload directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        throw new Exception('Gagal membuat direktori upload: ' . $uploadDir);
                    }
                }
                
                // Delete old file if exists
                $oldSlider = $this->find($id);
                if ($oldSlider && !empty($oldSlider['image_path'])) {
                    $oldFile = __DIR__ . '/../../../public' . $oldSlider['image_path'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                $fileName = time() . '_' . basename($file['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception('Failed to upload file');
                }
                
                $data['image_path'] = '/uploads/sliders/' . $fileName;
            }
            
            // Prepare SQL
            $updates = [];
            $values = [':id' => $id];
            
            foreach ($data as $key => $value) {
                $updates[] = "$key = :$key";
                $values[":$key"] = $value;
            }
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->query($sql, $values);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("Error updating slider: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete a slider and its associated file
     */
    public function delete($id) {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Get slider data before deletion
            $slider = $this->find($id);
            
            if (!$slider) {
                throw new Exception("Slider not found");
            }
            
            // Delete from database
            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->query($sql, [':id' => $id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("No slider found with ID: $id");
            }
            
            // Delete associated file if it exists
            if (!empty($slider['image_path'])) {
                $filePath = __DIR__ . '/../../../public' . $slider['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Commit transaction
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($this->db) && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error deleting slider: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Find a slider by ID
     */
    public function find($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = :id";
            $stmt = $this->query($sql, [':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error finding slider: " . $e->getMessage());
            return false;
        }
    }
}
