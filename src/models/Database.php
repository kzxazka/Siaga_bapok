<?php
/**
 * Database Class
 * Handles database connections and queries using PDO with singleton pattern
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        try {
            // Load database configuration
            $config = require __DIR__ . '/../../config/database.php';
            
            // Create PDO instance
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->pdo = new PDO(
                $dsn, 
                $config['username'], 
                $config['password'], 
                $config['options'] ?? []
            );
            
            // Set error mode to exception
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch (PDOException $e) {
            // Log error and rethrow with a more user-friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    /**
     * Get the database instance (singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the PDO connection
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Execute a query and return the number of affected rows
     * @param string $sql The SQL query
     * @param array $params The parameters for the query
     * @return int The number of affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Execute a query with parameters
     * @param string $sql The SQL query
     * @param array $params The parameters for the query
     * @return PDOStatement The prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $paramType);
            }
            
            $stmt->execute();
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . "\nSQL: $sql");
            throw $e;
        }
    }
    
    /**
     * Fetch all rows from a query
     * @param string $sql The SQL query
     * @param array $params The parameters for the query
     * @return array The result rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Fetch a single row from a query
     * @param string $sql The SQL query
     * @param array $params The parameters for the query
     * @return array|false The result row or false if not found
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get the last inserted ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
