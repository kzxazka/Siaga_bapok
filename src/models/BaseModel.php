<?php
require_once __DIR__ . '/Database.php';

class BaseModel {
    protected $table = '';
    protected $fillable = [];
    protected $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Execute a query with parameters
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            
            // Check if parameters are an associative array (e.g., [':id' => 1])
            // or a numeric array (e.g., [1, 'text'])
            $isAssociative = is_string(key($params));

            if ($isAssociative) {
                // Bind parameters by name
                foreach ($params as $key => $value) {
                    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue($key, $value, $paramType);
                }
            } else {
                // Bind parameters by position (1-based index)
                foreach ($params as $key => $value) {
                    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    // bindValue uses 1-based index for positional parameters
                    $stmt->bindValue($key + 1, $value, $paramType); 
                }
            }
            
            $stmt->execute();
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . "\nSQL: $sql");
            throw $e;
        }
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paginate($query, $params = [], $perPage = 10, $page = 1) {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
        $total = $this->query($countQuery, $params)->fetch()['total'];
        
        // Add pagination to query
        $query .= " LIMIT :offset, :perPage";
        $params['offset'] = $offset; // Passing as named parameters
        $params['perPage'] = $perPage; // Passing as named parameters

        // Execute the paginated query
        $stmt = $this->query($query, $params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $results,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }
    
    public function all($page = 1, $perPage = 10) {
        $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
        return $this->paginate($query, [], $perPage, $page);
    }
    
    public function find($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->query($query, ['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function create(array $data) {
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $query = "INSERT INTO " . $this->table . " ($fields) VALUES ($placeholders)";
        $this->query($query, $data);
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, array $data) {
        $updates = [];
        foreach ($data as $key => $value) {
            $updates[] = "$key = :$key";
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $updates) . " WHERE id = :id";
        $data['id'] = $id;
        
        $stmt = $this->query($query, $data);
        return $stmt->rowCount();
    }
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->query($query, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}