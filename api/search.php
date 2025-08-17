<?php
// Búsqueda global/local (por curso) en cursos, clases, notas, comentarios y adjuntos
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

$db = getDatabase();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$cursoId = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : null;

if ($q === '') {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

// Normalizar para LIKE
$like = '%' . $q . '%';

try {
    $results = [];

    // Clases (título)
    $sqlClases = "SELECT c.id as clase_id, c.titulo, s.curso_id
                  FROM clases c JOIN secciones s ON c.seccion_id = s.id
                  WHERE c.titulo LIKE ?" . ($cursoId ? " AND s.curso_id = ?" : "") . "
                  ORDER BY c.id DESC LIMIT 100";
    $stmt = $db->prepare($sqlClases);
    $stmt->execute($cursoId ? [$like, $cursoId] : [$like]);
    foreach ($stmt->fetchAll() as $r) {
        $results[] = [
            'type' => 'Clase',
            'label' => $r['titulo'],
            'clase_id' => (int)$r['clase_id'],
            'curso_id' => (int)$r['curso_id'],
        ];
    }

    // Notas (texto) y también permitir búsqueda por tiempo HH:MM:SS o segundos
    $sqlNotas = "SELECT n.id, n.clase_id, n.tiempo_video, n.contenido_nota, s.curso_id
                 FROM notas_clases n JOIN clases c ON n.clase_id=c.id JOIN secciones s ON c.seccion_id=s.id
                 WHERE (n.contenido_nota LIKE ? OR CAST(n.tiempo_video AS TEXT) LIKE ?)" . ($cursoId ? " AND s.curso_id = ?" : "") . "
                 ORDER BY n.id DESC LIMIT 200";
    $stmt = $db->prepare($sqlNotas);
    $stmt->execute($cursoId ? [$like, $like, $cursoId] : [$like, $like]);
    foreach ($stmt->fetchAll() as $r) {
        $results[] = [
            'type' => 'Nota',
            'label' => $r['contenido_nota'],
            'clase_id' => (int)$r['clase_id'],
            'curso_id' => (int)$r['curso_id'],
            'time' => (int)$r['tiempo_video'],
        ];
    }

    // Comentarios (texto)
    $sqlCom = "SELECT cm.id, cm.clase_id, cm.comentario, s.curso_id
               FROM comentarios_clases cm JOIN clases c ON cm.clase_id=c.id JOIN secciones s ON c.seccion_id=s.id
               WHERE cm.comentario LIKE ?" . ($cursoId ? " AND s.curso_id = ?" : "") . "
               ORDER BY cm.id DESC LIMIT 200";
    $stmt = $db->prepare($sqlCom);
    $stmt->execute($cursoId ? [$like, $cursoId] : [$like]);
    foreach ($stmt->fetchAll() as $r) {
        $results[] = [
            'type' => 'Comentario',
            'label' => $r['comentario'],
            'clase_id' => (int)$r['clase_id'],
            'curso_id' => (int)$r['curso_id'],
        ];
    }

    // Adjuntos (recursos)
    $sqlRec = "SELECT rc.id, rc.clase_id, rc.nombre_archivo, s.curso_id
               FROM recursos_clases rc JOIN clases c ON rc.clase_id=c.id JOIN secciones s ON c.seccion_id=s.id
               WHERE rc.nombre_archivo LIKE ?" . ($cursoId ? " AND s.curso_id = ?" : "") . "
               ORDER BY rc.id DESC LIMIT 200";
    $stmt = $db->prepare($sqlRec);
    $stmt->execute($cursoId ? [$like, $cursoId] : [$like]);
    foreach ($stmt->fetchAll() as $r) {
        $results[] = [
            'type' => 'Adjunto',
            'label' => $r['nombre_archivo'],
            'clase_id' => (int)$r['clase_id'],
            'curso_id' => (int)$r['curso_id'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


