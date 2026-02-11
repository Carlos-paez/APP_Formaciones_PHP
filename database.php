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
            if (!file_exists(dirname($this->dbFile))) {
                mkdir(dirname($this->dbFile), 0755, true);
            }

            if (!is_writable(dirname($this->dbFile))) {
                throw new Exception("El directorio no tiene permisos de escritura: " . dirname($this->dbFile));
            }

            $this->db = new PDO("sqlite:" . $this->dbFile);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->db->exec("PRAGMA journal_mode=WAL;");
            $this->db->exec("PRAGMA foreign_keys=ON;");

            $this->createTable();

            error_log("✅ Conexión a base de datos establecida: " . $this->dbFile);

        } catch(PDOException $e) {
            error_log("❌ Error de conexión a base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    private function createTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ubicacion TEXT NOT NULL,
                formador TEXT NOT NULL,
                hora_inicio TEXT NOT NULL,
                hora_fin TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

            $this->db->exec($sql);
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_hora_fin ON events(hora_fin)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_created ON events(created_at)");

            error_log("✅ Tabla 'events' verificada/creada");

        } catch(PDOException $e) {
            error_log("❌ Error creando tabla: " . $e->getMessage());
            throw new Exception("Error creando tabla: " . $e->getMessage());
        }
    }

    public function saveEvent($ubicacion, $formador, $hora_inicio, $hora_fin) {
        try {
            if (empty($ubicacion) || empty($formador) || empty($hora_inicio) || empty($hora_fin)) {
                return ['success' => false, 'message' => 'Todos los campos son requeridos'];
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO events (ubicacion, formador, hora_inicio, hora_fin) 
                                       VALUES (:ubicacion, :formador, :hora_inicio, :hora_fin)");

            $stmt->bindParam(':ubicacion', $ubicacion);
            $stmt->bindParam(':formador', $formador);
            $stmt->bindParam(':hora_inicio', $hora_inicio);
            $stmt->bindParam(':hora_fin', $hora_fin);

            $stmt->execute();
            $lastId = $this->db->lastInsertId();

            $this->db->commit();

            error_log("✅ Evento guardado con ID: $lastId");

            return [
                'success' => true,
                'message' => 'Evento guardado correctamente',
                'id' => $lastId
            ];

        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("❌ Error guardando evento: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el evento: ' . $e->getMessage()];
        }
    }

    public function getEvents() {
        try {
            $stmt = $this->db->query("SELECT * FROM events ORDER BY created_at DESC");
            $events = $stmt->fetchAll();

            error_log("📊 Obtenidos " . count($events) . " eventos de la base de datos");

            return $events;
        } catch(PDOException $e) {
            error_log("❌ Error obteniendo eventos: " . $e->getMessage());
            return [];
        }
    }

    public function deleteEvent($id) {
        try {
            // Validar ID
            if (!is_numeric($id)) {
                error_log("❌ ID inválido (no numérico): " . var_export($id, true));
                return ['success' => false, 'message' => 'ID de evento inválido'];
            }

            $id = intval($id);

            if ($id <= 0) {
                error_log("❌ ID inválido (<= 0): $id");
                return ['success' => false, 'message' => 'ID de evento inválido'];
            }

            error_log("🔍 Intentando eliminar evento con ID: $id");

            // Verificar si el evento existe ANTES de intentar eliminar
            $checkStmt = $this->db->prepare("SELECT id, ubicacion, formador FROM events WHERE id = :id");
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();

            $eventExists = $checkStmt->fetch();

            if (!$eventExists) {
                // Obtener todos los IDs para diagnóstico
                $allIdsStmt = $this->db->query("SELECT id FROM events ORDER BY id");
                $allIds = $allIdsStmt->fetchAll(PDO::FETCH_COLUMN);

                error_log("❌ Evento NO ENCONTRADO con ID: $id");
                error_log("📋 IDs disponibles en la base de datos: " . implode(', ', $allIds));

                return [
                    'success' => false,
                    'message' => "Evento no encontrado con ID: $id",
                    'available_ids' => $allIds
                ];
            }

            error_log("✅ Evento encontrado para eliminar: ID $id - {$eventExists['ubicacion']} - {$eventExists['formador']}");

            // Iniciar transacción
            $this->db->beginTransaction();

            // Eliminar evento
            $stmt = $this->db->prepare("DELETE FROM events WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            $result = $stmt->execute();
            $affectedRows = $stmt->rowCount();

            if ($affectedRows > 0) {
                $this->db->commit();

                error_log("✅✅ Evento ELIMINADO correctamente. ID: $id, Filas afectadas: $affectedRows");

                // Verificar que realmente se eliminó
                $verifyStmt = $this->db->prepare("SELECT COUNT(*) as count FROM events WHERE id = :id");
                $verifyStmt->bindParam(':id', $id, PDO::PARAM_INT);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->fetch();

                if ($verifyResult['count'] == 0) {
                    error_log("✅ Verificación exitosa: El evento ID $id ya no existe en la base de datos");
                } else {
                    error_log("⚠️ Advertencia: El evento ID $id aún existe después de la eliminación");
                }

                return [
                    'success' => true,
                    'message' => "Evento '{$eventExists['ubicacion']}' eliminado correctamente",
                    'deleted_id' => $id,
                    'rows_affected' => $affectedRows
                ];
            } else {
                $this->db->rollBack();
                error_log("❌ No se afectaron filas al intentar eliminar ID: $id");
                return [
                    'success' => false,
                    'message' => "No se pudo eliminar el evento. ID: $id",
                    'rows_affected' => $affectedRows
                ];
            }

        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("❌❌ ERROR PDO al eliminar evento ID $id: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_info' => $e->errorInfo
            ];
        } catch(Exception $e) {
            $this->db->rollBack();
            error_log("❌❌ ERROR GENERAL al eliminar evento ID $id: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getDb() {
        return $this->db;
    }

    public function getDbPath() {
        return $this->dbFile;
    }

    public function getEventById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM events WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $event = $stmt->fetch();

            if ($event) {
                error_log("✅ Evento encontrado con ID: $id");
            } else {
                error_log("❌ Evento NO encontrado con ID: $id");
            }

            return $event;

        } catch(PDOException $e) {
            error_log("❌ Error obteniendo evento por ID: " . $e->getMessage());
            return null;
        }
    }

    public function countEvents() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM events");
            $result = $stmt->fetch();
            return $result['count'];
        } catch(PDOException $e) {
            error_log("❌ Error contando eventos: " . $e->getMessage());
            return 0;
        }
    }
}
?>