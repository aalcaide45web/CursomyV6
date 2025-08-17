<?php
// Recalcula y/o devuelve duraciones de clases usando ffprobe
// GET params:
// - curso_id (requerido)
// - force=1 (opcional) para recalcular todas, incluso si ya tienen duración

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['curso_id'])) {
        jsonResponse(['success' => false, 'message' => 'curso_id requerido'], 400);
    }
    
    $cursoId = (int)$_GET['curso_id'];
    $force = isset($_GET['force']) && ($_GET['force'] == '1' || strtolower($_GET['force']) === 'true');
    
    $db = getDatabase();
    
    // Obtener clases del curso
    $stmt = $db->prepare("SELECT c.id, c.archivo_video, c.duracion FROM clases c JOIN secciones s ON c.seccion_id = s.id WHERE s.curso_id = ?");
    $stmt->execute([$cursoId]);
    $clases = $stmt->fetchAll();
    
    $updated = [];
    foreach ($clases as $clase) {
        $dur = (int)$clase['duracion'];
        if ($force || $dur === 0) {
            $path = VIDEOS_DIR . $cursoId . '/' . $clase['archivo_video'];
            if (is_file($path)) {
                $newDur = getVideoDuration($path);
                if ($newDur > 0) {
                    $upd = $db->prepare('UPDATE clases SET duracion = ? WHERE id = ?');
                    $upd->execute([$newDur, $clase['id']]);
                    $dur = $newDur;
                } else {
                    // Log básico en respuesta para debug
                    $updated[] = [ 'id' => (int)$clase['id'], 'duracion' => (int)$dur, 'ffprobe' => 'no-duration' ];
                    continue;
                }
            }
        }
        $updated[] = [ 'id' => (int)$clase['id'], 'duracion' => (int)$dur ];
    }
    
    jsonResponse(['success' => true, 'data' => $updated]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>


