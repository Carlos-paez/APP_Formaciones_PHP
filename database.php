<?php
class Database {
    private $db;
    private $dbFile;

    public function __construct() {
        $this->dbFile = __DIR__ . '/events.db';
        $this->connect();
    }

    private function connect() {
        try {
            if (!is_writable(dirname($this->dbFile))) {
                throw new Exception("El directorio no tiene permisos de escritura");
            }

            $this->db = new PDO("sqlite:" . $this->dbFile);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->createTable();
        } catch(PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ubicacion TEXT NOT NULL,
            formador TEXT NOT NULL,
            hora_inicio TEXT NOT NULL,
            hora_fin TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        try {
            $this->db->exec($sql);
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_hora_fin ON events(hora_fin)");
        } catch(PDOException $e) {
            error_log("Error creando tabla: " . $e->getMessage());
            throw new Exception("Error creando tabla: " . $e->getMessage());
        }
    }

    public function saveEvent($ubicacion, $formador, $hora_inicio, $hora_fin) {
        try {
            if (empty($ubicacion) || empty($formador) || empty($hora_inicio) || empty($hora_fin)) {
                return ['success' => false, 'message' => 'Todos los campos son requeridos'];
            }

            $stmt = $this->db->prepare("INSERT INTO events (ubicacion, formador, hora_inicio, hora_fin) 
                                       VALUES (:ubicacion, :formador, :hora_inicio, :hora_fin)");

            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':formador', $formador);
            $stmt->bindParam(':hora_inicio', $hora_inicio);
            $stmt->bindParam(':hora_fin', $hora_fin);

            $stmt->execute();

            return [
                'success' => true,
                'message' => 'Evento guardado correctamente',
                'id' => $this->db->lastInsertId()
            ];
        } catch(PDOException $e) {
            error_log("Error guardando evento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el evento'];
        }
    }

    public function getEvents() {
        try {
            $stmt = $this->db->query("SELECT * FROM events ORDER BY created_at DESC");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error obteniendo eventos: " . $e->getMessage());
            return [];
        }
    }

    public function deleteEvent($id) {
        try {
            // Validar ID
            if (!is_numeric($id) || $id <= 0) {
                return ['success' => false, 'message' => 'ID de evento inválido'];
            }

            $id = intval($id);

            // Verificar si el evento existe
            $checkStmt = $this->db->prepare("SELECT COUNT(*) as count FROM events WHERE id = :id");
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            $row = $checkStmt->fetch();

            if ($row['count'] == 0) {
                return ['success' => false, 'message' => 'Evento no encontrado'];
            }

            // Eliminar evento
            $stmt = $this->db->prepare("DELETE FROM events WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            $result = $stmt->execute();

            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Evento eliminado correctamente',
                    'deleted_id' => $id
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No se pudo eliminar el evento. Filas afectadas: ' . $stmt->rowCount(),
                    'rows_affected' => $stmt->rowCount()
                ];
            }

        } catch(PDOException $e) {
            error_log("Error eliminando evento ID $id: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        } catch(Exception $e) {
            error_log("Error general eliminando evento ID $id: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    // Método para obtener la conexión PDO (AGREGADO)
    public function getDb() {
        return $this->db;
    }

    // Método para obtener la ruta de la base de datos
    public function getDbPath() {
        return $this->dbFile;
    }
}
?>