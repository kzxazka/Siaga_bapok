class AuthMiddleware {
    public static function checkRole($allowedRoles) {
        if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], (array)$allowedRoles)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized access']);
                exit;
            } else {
                header('Location: /login.php');
                exit;
            }
        }
    }

    public static function adminOnly() {
        self::checkRole('admin');
    }

    public static function uptdOnly() {
        self::checkRole('uptd');
    }

    public static function publicOnly() {
        if (isset($_SESSION['user'])) {
            header('Location: /dashboard.php');
            exit;
        }
    }
}