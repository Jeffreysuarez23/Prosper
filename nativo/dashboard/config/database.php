<?php
date_default_timezone_set('America/Bogota');
class Database {
    private $host = "127.0.0.1";
    private $db_name = "prosper";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Devolver error en JSON si falla la conexion
            header('Content-Type: application/json');
            echo json_encode(["error" => "Error de conexión: " . $exception->getMessage()]);
            exit;
        }
        return $this->conn;
    }
}
?>
