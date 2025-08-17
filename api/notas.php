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
        case 'PUT':
            handlePut($db);
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
    if (isset($_GET['id'])) {
        // Obtener nota específica
        $stmt = $db->prepare("SELECT * FROM notas_clases WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $nota = $stmt->fetch();
        
        if ($nota) {
            jsonResponse(['success' => true, 'data' => $nota]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Nota no encontrada'], 404);
        }
    } else if (isset($_GET['clase_id'])) {
        // Obtener notas de una clase
        $stmt = $db->prepare("SELECT * FROM notas_clases WHERE clase_id = ? ORDER BY tiempo_video");
        $stmt->execute([$_GET['clase_id']]);
        $notas = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $notas]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Parámetro id o clase_id requerido'], 400);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['clase_id']) || !isset($input['tiempo_video']) || !isset($input['contenido_nota'])) {
        jsonResponse(['success' => false, 'message' => 'clase_id, tiempo_video y contenido_nota son obligatorios'], 400);
    }
    
    $clase_id = (int)$input['clase_id'];
    $tiempo_video = (int)$input['tiempo_video'];
    $contenido_nota = cleanInput($input['contenido_nota']);
    
    if (empty(trim($contenido_nota))) {
        jsonResponse(['success' => false, 'message' => 'El contenido de la nota no puede estar vacío'], 400);
    }
    
    // Verificar que la clase existe
    $stmt = $db->prepare("SELECT id FROM clases WHERE id = ?");
    $stmt->execute([$clase_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
    }
    
    // Validar tiempo de video
    if ($tiempo_video < 0) {
        jsonResponse(['success' => false, 'message' => 'El tiempo del video debe ser positivo'], 400);
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO notas_clases (clase_id, tiempo_video, contenido_nota) VALUES (?, ?, ?)");
        $stmt->execute([$clase_id, $tiempo_video, $contenido_nota]);
        
        $notaId = $db->lastInsertId();
        
        // Obtener la nota completa recién creada
        $stmt = $db->prepare("SELECT * FROM notas_clases WHERE id = ?");
        $stmt->execute([$notaId]);
        $nota = $stmt->fetch();
        
        jsonResponse([
            'success' => true, 
            'message' => 'Nota creada exitosamente',
            'nota' => $nota
        ]);
    } catch (PDOException $e) {
        throw $e;
    }
}

function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id']) || !isset($input['contenido_nota'])) {
        jsonResponse(['success' => false, 'message' => 'ID y contenido_nota son obligatorios'], 400);
    }
    
    $id = (int)$input['id'];
    $contenido_nota = cleanInput($input['contenido_nota']);
    $tiempo_video = isset($input['tiempo_video']) ? (int)$input['tiempo_video'] : null;
    
    if (empty(trim($contenido_nota))) {
        jsonResponse(['success' => false, 'message' => 'El contenido de la nota no puede estar vacío'], 400);
    }
    
    // Verificar si la nota existe
    $stmt = $db->prepare("SELECT id FROM notas_clases WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Nota no encontrada'], 404);
    }
    
    try {
        if ($tiempo_video !== null) {
            // Actualizar contenido y tiempo
            $stmt = $db->prepare("UPDATE notas_clases SET contenido_nota = ?, tiempo_video = ? WHERE id = ?");
            $stmt->execute([$contenido_nota, $tiempo_video, $id]);
        } else {
            // Actualizar solo contenido
            $stmt = $db->prepare("UPDATE notas_clases SET contenido_nota = ? WHERE id = ?");
            $stmt->execute([$contenido_nota, $id]);
        }
        
        jsonResponse(['success' => true, 'message' => 'Nota actualizada exitosamente']);
    } catch (PDOException $e) {
        throw $e;
    }
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID es obligatorio'], 400);
    }
    
    $id = (int)$input['id'];
    
    // Verificar si la nota existe
    $stmt = $db->prepare("SELECT id FROM notas_clases WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Nota no encontrada'], 404);
    }
    
    $stmt = $db->prepare("DELETE FROM notas_clases WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Nota eliminada exitosamente']);
}
?>
