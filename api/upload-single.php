<?php
// Sube un único archivo perteneciente a una sección de un curso.
// Si es MP4 -> crea una clase. Si es otro tipo -> adjunta como recurso a la última clase de la sección.

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(0);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
}

$db = getDatabase();

try {
    $cursoId = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;
    $seccionNombre = isset($_POST['seccion']) ? trim($_POST['seccion']) : '';
    $seccionOrden = isset($_POST['seccion_orden']) ? (int)$_POST['seccion_orden'] : null;
    $videoOrden = isset($_POST['video_orden']) ? (int)$_POST['video_orden'] : null;
    
    if (!$cursoId || $seccionNombre === '' || !isset($_FILES['file'])) {
        jsonResponse(['success' => false, 'message' => 'curso_id, seccion y file son obligatorios'], 400);
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'Error al recibir el archivo: ' . $file['error']], 400);
    }

    // Asegurar sección (crear si no existe)
    $stmt = $db->prepare('SELECT id FROM secciones WHERE curso_id = ? AND nombre = ?');
    $stmt->execute([$cursoId, $seccionNombre]);
    $seccion = $stmt->fetch();
    if (!$seccion) {
        // Usar el orden especificado o calcular el siguiente
        if ($seccionOrden !== null) {
            $orden = $seccionOrden;
        } else {
            $stmt = $db->prepare('SELECT COALESCE(MAX(orden),0) as max_orden FROM secciones WHERE curso_id = ?');
            $stmt->execute([$cursoId]);
            $orden = (int)$stmt->fetch()['max_orden'] + 1;
        }
        $stmt = $db->prepare('INSERT INTO secciones (curso_id, nombre, orden) VALUES (?, ?, ?)');
        $stmt->execute([$cursoId, $seccionNombre, $orden]);
        $seccionId = $db->lastInsertId();
    } else {
        $seccionId = $seccion['id'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isVideo = in_array($ext, ALLOWED_VIDEO_TYPES, true);

    if (!$isVideo) {
        // Rechazar archivos no-video completamente
        jsonResponse(['success' => false, 'message' => "Tipo de archivo no soportado: .$ext. Solo se permiten videos: " . implode(', ', ALLOWED_VIDEO_TYPES)], 400);
    }

    if ($isVideo) {
        // Guardar video
        $videoDir = VIDEOS_DIR . $cursoId . '/';
        if (!is_dir($videoDir)) mkdir($videoDir, 0755, true);
        $targetName = generateUniqueFileName($file['name'], $videoDir);
        $targetPath = $videoDir . $targetName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            jsonResponse(['success' => false, 'message' => 'No se pudo guardar el video'], 500);
        }

        // Usar el orden especificado o calcular el siguiente
        if ($videoOrden !== null) {
            $nextOrder = $videoOrden;
        } else {
            $stmt = $db->prepare('SELECT COALESCE(MAX(orden),0) as max_orden FROM clases WHERE seccion_id = ?');
            $stmt->execute([$seccionId]);
            $nextOrder = (int)$stmt->fetch()['max_orden'] + 1;
        }

        $titulo = pathinfo($file['name'], PATHINFO_FILENAME);
        $titulo = ucwords(str_replace(['_', '-'], ' ', $titulo));

        $stmt = $db->prepare('INSERT INTO clases (seccion_id, titulo, archivo_video, duracion, orden) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$seccionId, $titulo, $targetName, 0, $nextOrder]);
        $claseId = $db->lastInsertId();

        jsonResponse(['success' => true, 'type' => 'video', 'clase_id' => $claseId]);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}

?>


