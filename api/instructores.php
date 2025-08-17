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
        // Obtener instructor específico
        $stmt = $db->prepare("SELECT * FROM instructores WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $instructor = $stmt->fetch();
        
        if ($instructor) {
            jsonResponse(['success' => true, 'data' => $instructor]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Instructor no encontrado'], 404);
        }
    } else {
        // Obtener todos los instructores
        $instructores = $db->query("SELECT * FROM instructores ORDER BY nombre")->fetchAll();
        jsonResponse(['success' => true, 'data' => $instructores]);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['nombre']) || empty(trim($input['nombre']))) {
        jsonResponse(['success' => false, 'message' => 'El nombre es obligatorio'], 400);
    }
    
    $nombre = cleanInput($input['nombre']);
    $email = isset($input['email']) ? cleanInput($input['email']) : null;
    $bio = isset($input['bio']) ? cleanInput($input['bio']) : null;
    
    // Validar email si se proporciona
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Email inválido'], 400);
    }
    
    // Verificar si el email ya existe
    if ($email) {
        $stmt = $db->prepare("SELECT id FROM instructores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'El email ya está registrado'], 400);
        }
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO instructores (nombre, email, bio) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $email, $bio]);
        
        $instructorId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true, 
            'message' => 'Instructor creado exitosamente',
            'data' => ['id' => $instructorId]
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Constraint violation
            jsonResponse(['success' => false, 'message' => 'El email ya está registrado'], 400);
        }
        throw $e;
    }
}

function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id']) || !isset($input['nombre']) || empty(trim($input['nombre']))) {
        jsonResponse(['success' => false, 'message' => 'ID y nombre son obligatorios'], 400);
    }
    
    $id = (int)$input['id'];
    $nombre = cleanInput($input['nombre']);
    $email = isset($input['email']) ? cleanInput($input['email']) : null;
    $bio = isset($input['bio']) ? cleanInput($input['bio']) : null;
    
    // Validar email si se proporciona
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Email inválido'], 400);
    }
    
    // Verificar si el instructor existe
    $stmt = $db->prepare("SELECT id FROM instructores WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Instructor no encontrado'], 404);
    }
    
    // Verificar si el email ya existe en otro instructor
    if ($email) {
        $stmt = $db->prepare("SELECT id FROM instructores WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'El email ya está registrado'], 400);
        }
    }
    
    try {
        $stmt = $db->prepare("UPDATE instructores SET nombre = ?, email = ?, bio = ? WHERE id = ?");
        $stmt->execute([$nombre, $email, $bio, $id]);
        
        jsonResponse(['success' => true, 'message' => 'Instructor actualizado exitosamente']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Constraint violation
            jsonResponse(['success' => false, 'message' => 'El email ya está registrado'], 400);
        }
        throw $e;
    }
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID es obligatorio'], 400);
    }
    
    $id = (int)$input['id'];
    
    // Verificar si el instructor existe
    $stmt = $db->prepare("SELECT id FROM instructores WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Instructor no encontrado'], 404);
    }
    
    // Verificar si el instructor tiene cursos asociados
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cursos WHERE instructor_id = ?");
    $stmt->execute([$id]);
    $cursosCount = $stmt->fetch()['count'];
    
    if ($cursosCount > 0) {
        jsonResponse([
            'success' => false, 
            'message' => "No se puede eliminar el instructor porque tiene {$cursosCount} curso(s) asociado(s)"
        ], 400);
    }
    
    $stmt = $db->prepare("DELETE FROM instructores WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Instructor eliminado exitosamente']);
}
?>
