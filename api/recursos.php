<?php
// Gestión de recursos (archivos) por clase: listar, subir y eliminar

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
            if (isset($_GET['deleteAll']) && isset($_GET['clase_id'])) {
                handleDeleteAll($db);
            } else {
                handleDelete($db);
            }
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()], 500);
}

function handleGet($db) {
    if (!isset($_GET['clase_id'])) {
        jsonResponse(['success' => false, 'message' => 'clase_id requerido'], 400);
    }
    $stmt = $db->prepare('SELECT * FROM recursos_clases WHERE clase_id = ? ORDER BY created_at DESC');
    $stmt->execute([(int)$_GET['clase_id']]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handlePost($db) {
    if (!isset($_POST['clase_id']) || !isset($_FILES['archivo'])) {
        jsonResponse(['success' => false, 'message' => 'clase_id y archivo son obligatorios'], 400);
    }
    $claseId = (int)$_POST['clase_id'];

    // Verificar clase
    $stmt = $db->prepare('SELECT c.id, s.curso_id FROM clases c JOIN secciones s ON c.seccion_id = s.id WHERE c.id = ?');
    $stmt->execute([$claseId]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);

    $cursoId = (int)$row['curso_id'];
    $file = $_FILES['archivo'];
    if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['success' => false, 'message' => 'Error de subida'], 400);

    // Validación de tipos (si hay restricciones definidas)
    if (!empty(ALLOWED_RESOURCE_TYPES)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_RESOURCE_TYPES, true)) {
            jsonResponse(['success' => false, 'message' => 'Tipo de archivo no permitido'], 400);
        }
    }

    // Crear carpeta de recursos del curso si no existe
    $courseResDir = RESOURCES_DIR . $cursoId . '/';
    if (!is_dir($courseResDir)) mkdir($courseResDir, 0755, true);

    // Guardar archivo
    $targetName = generateUniqueFileName($file['name'], $courseResDir);
    if (!move_uploaded_file($file['tmp_name'], $courseResDir . $targetName)) {
        jsonResponse(['success' => false, 'message' => 'No se pudo guardar el archivo'], 500);
    }

    // Persistir
    $mime = mime_content_type($courseResDir . $targetName) ?: null;
    $size = filesize($courseResDir . $targetName) ?: 0;
    $stmt = $db->prepare('INSERT INTO recursos_clases (clase_id, nombre_archivo, archivo_path, tipo_mime, tamano_bytes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$claseId, $file['name'], $targetName, $mime, $size]);
    
    // Obtener el ID del recurso recién creado
    $resourceId = $db->lastInsertId();
    
    // Devolver información del recurso para actualización dinámica
    $resource = [
        'id' => (int)$resourceId,
        'clase_id' => $claseId,
        'nombre_archivo' => $file['name'],
        'archivo_path' => $targetName,
        'tipo_mime' => $mime,
        'tamano_bytes' => $size,
        'curso_id' => $cursoId
    ];
    
    jsonResponse(['success' => true, 'message' => 'Recurso agregado', 'resource' => $resource]);
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'])) jsonResponse(['success' => false, 'message' => 'id requerido'], 400);

    // Obtener info
    $stmt = $db->prepare('SELECT rc.*, s.curso_id FROM recursos_clases rc JOIN clases c ON rc.clase_id = c.id JOIN secciones s ON c.seccion_id = s.id WHERE rc.id = ?');
    $stmt->execute([(int)$input['id']]);
    $res = $stmt->fetch();
    if (!$res) jsonResponse(['success' => false, 'message' => 'Recurso no encontrado'], 404);

    $path = RESOURCES_DIR . $res['curso_id'] . '/' . $res['archivo_path'];
    if (is_file($path)) @unlink($path);
    $del = $db->prepare('DELETE FROM recursos_clases WHERE id = ?');
    $del->execute([(int)$input['id']]);
    jsonResponse(['success' => true, 'message' => 'Recurso eliminado']);
}

function handleDeleteAll($db) {
    $claseId = (int)$_GET['clase_id'];
    
    if (!$claseId) {
        jsonResponse(['success' => false, 'message' => 'clase_id requerido'], 400);
    }
    
    // Verificar que la clase existe y obtener curso_id
    $stmt = $db->prepare('SELECT c.id, s.curso_id FROM clases c JOIN secciones s ON c.seccion_id = s.id WHERE c.id = ?');
    $stmt->execute([$claseId]);
    $clase = $stmt->fetch();
    
    if (!$clase) {
        jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
    }
    
    $cursoId = $clase['curso_id'];
    
    // Obtener todos los recursos de esta clase
    $stmt = $db->prepare('SELECT * FROM recursos_clases WHERE clase_id = ?');
    $stmt->execute([$claseId]);
    $recursos = $stmt->fetchAll();
    
    if (empty($recursos)) {
        jsonResponse(['success' => true, 'message' => 'No hay recursos para eliminar', 'deleted_count' => 0]);
    }
    
    $deletedCount = 0;
    $resourcesDir = RESOURCES_DIR . $cursoId . '/';
    
    // Eliminar archivos físicos
    foreach ($recursos as $recurso) {
        $filePath = $resourcesDir . $recurso['archivo_path'];
        if (file_exists($filePath)) {
            if (@unlink($filePath)) {
                $deletedCount++;
            }
        }
    }
    
    // Eliminar registros de la base de datos
    $stmt = $db->prepare('DELETE FROM recursos_clases WHERE clase_id = ?');
    $stmt->execute([$claseId]);
    
    jsonResponse([
        'success' => true, 
        'message' => "Eliminados {$deletedCount} archivo(s) y " . count($recursos) . " registro(s)",
        'deleted_count' => count($recursos)
    ]);
}
?>


