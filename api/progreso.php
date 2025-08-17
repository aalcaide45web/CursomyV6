<?php
// Desactivar mostrar errores para evitar que interfieran con JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()], 500);
}

function handleGet($db) {
    if (isset($_GET['clase_id'])) {
        // Obtener progreso de una clase específica
        $stmt = $db->prepare("SELECT * FROM progreso_clases WHERE clase_id = ?");
        $stmt->execute([$_GET['clase_id']]);
        $progreso = $stmt->fetch();
        
        if ($progreso) {
            jsonResponse(['success' => true, 'data' => $progreso]);
        } else {
            jsonResponse(['success' => true, 'data' => null]);
        }
    } else if (isset($_GET['curso_id'])) {
        // Obtener progreso de todas las clases de un curso (incluye clases sin progreso)
        $query = "
            SELECT c.id as clase_id, COALESCE(c.duracion, 0) as duracion, s.id as seccion_id, COALESCE(pc.tiempo_visto, 0) as tiempo_visto, COALESCE(pc.completada, 0) as completada, pc.ultima_visualizacion
            FROM clases c
            JOIN secciones s ON c.seccion_id = s.id
            LEFT JOIN progreso_clases pc ON pc.clase_id = c.id
            WHERE s.curso_id = ?
            ORDER BY s.orden, c.orden
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$_GET['curso_id']]);
        $progresos = $stmt->fetchAll();
        
        jsonResponse(['success' => true, 'data' => $progresos]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Parámetro clase_id o curso_id requerido'], 400);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['clase_id']) || !isset($input['tiempo_visto'])) {
        jsonResponse(['success' => false, 'message' => 'clase_id y tiempo_visto son obligatorios'], 400);
    }
    
    $clase_id = (int)$input['clase_id'];
    $tiempo_visto = (int)$input['tiempo_visto'];
    $completada = isset($input['completada']) ? (bool)$input['completada'] : false;
    
    // Verificar que la clase existe
    $stmt = $db->prepare("SELECT id FROM clases WHERE id = ?");
    $stmt->execute([$clase_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
    }
    
    try {
        // Usar INSERT OR REPLACE para SQLite (equivalente a UPSERT)
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO progreso_clases 
            (clase_id, tiempo_visto, ultima_visualizacion, completada) 
            VALUES (?, ?, CURRENT_TIMESTAMP, ?)
        ");
        $stmt->execute([$clase_id, $tiempo_visto, $completada ? 1 : 0]);
        
        jsonResponse(['success' => true, 'message' => 'Progreso guardado exitosamente']);
    } catch (PDOException $e) {
        throw $e;
    }
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'JSON requerido'], 400);
    }
    
    // Reset por clase, sección o curso completo
    if (isset($input['clase_id'])) {
        $stmt = $db->prepare("DELETE FROM progreso_clases WHERE clase_id = ?");
        $stmt->execute([(int)$input['clase_id']]);
        jsonResponse(['success' => true, 'message' => 'Progreso de la clase reiniciado']);
    } elseif (isset($input['seccion_id'])) {
        $stmt = $db->prepare("DELETE FROM progreso_clases WHERE clase_id IN (SELECT id FROM clases WHERE seccion_id = ?)");
        $stmt->execute([(int)$input['seccion_id']]);
        jsonResponse(['success' => true, 'message' => 'Progreso de la sección reiniciado']);
    } elseif (isset($input['curso_id'])) {
        $stmt = $db->prepare("DELETE FROM progreso_clases WHERE clase_id IN (
            SELECT c.id FROM clases c JOIN secciones s ON c.seccion_id = s.id WHERE s.curso_id = ?)");
        $stmt->execute([(int)$input['curso_id']]);
        jsonResponse(['success' => true, 'message' => 'Progreso del curso reiniciado']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Especifica clase_id, seccion_id o curso_id'], 400);
    }
}
?>
