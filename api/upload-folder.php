<?php
// Endpoint para crear curso/secciones/clases desde una carpeta comprimida
// Estrategia: el usuario sube un ZIP con la estructura de carpetas.
// Estructura esperada del ZIP:
// CursoName/
//   cover.(jpg|png)            (opcional)
//   Seccion 1/
//     Clase 1.mp4
//     Clase 2.mp4
//     recursos/                (opcional)
//       guia.pdf
//       dataset.zip
//     enlaces.txt              (opcional) - formato: Titulo|URL por línea
//   Seccion 2/
//     ...

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
}

$db = getDatabase();

try {
    if (!isset($_FILES['package']) || $_FILES['package']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'Archivo ZIP no recibido'], 400);
    }

    $zipFile = $_FILES['package'];
    // Validación básica
    $ext = strtolower(pathinfo($zipFile['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        jsonResponse(['success' => false, 'message' => 'El archivo debe ser un .zip'], 400);
    }

    // Carpeta temporal
    $tempDir = sys_get_temp_dir() . '/curso_import_' . uniqid();
    if (!mkdir($tempDir, 0777, true)) {
        jsonResponse(['success' => false, 'message' => 'No se pudo crear directorio temporal'], 500);
    }

    $zipPath = $tempDir . '/package.zip';
    if (!move_uploaded_file($zipFile['tmp_name'], $zipPath)) {
        jsonResponse(['success' => false, 'message' => 'No se pudo mover el ZIP'], 500);
    }

    // Extraer ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        jsonResponse(['success' => false, 'message' => 'No se pudo abrir el ZIP'], 500);
    }
    $zip->extractTo($tempDir);
    $zip->close();

    // Detectar carpeta raíz (primer directorio dentro de temp)
    $items = array_values(array_filter(scandir($tempDir), function ($f) {
        return $f !== '.' && $f !== '..' && is_dir($GLOBALS['tempDir'] . '/' . $f);
    }));
    if (empty($items)) {
        jsonResponse(['success' => false, 'message' => 'Estructura inválida en ZIP'], 400);
    }
    $rootFolder = $tempDir . '/' . $items[0];

    // Nombre del curso por carpeta raíz
    $courseTitle = basename($rootFolder);

    $db->beginTransaction();

    // Crear curso
    $stmt = $db->prepare('INSERT INTO cursos (titulo) VALUES (?)');
    $stmt->execute([$courseTitle]);
    $cursoId = $db->lastInsertId();

    // Intentar imagen de portada (cover.* en raíz)
    $coverCandidates = glob($rootFolder . '/cover.{jpg,jpeg,png,webp}', GLOB_BRACE);
    if (!empty($coverCandidates)) {
        $coverPath = $coverCandidates[0];
        $fileName = generateUniqueFileName(basename($coverPath), IMAGES_DIR);
        if (!is_dir(IMAGES_DIR)) mkdir(IMAGES_DIR, 0755, true);
        copy($coverPath, IMAGES_DIR . $fileName);
        $db->prepare('UPDATE cursos SET imagen = ? WHERE id = ?')->execute([$fileName, $cursoId]);
    }

    // Crear directorios de curso
    $courseVideosDir = VIDEOS_DIR . $cursoId . '/';
    if (!is_dir($courseVideosDir)) mkdir($courseVideosDir, 0755, true);
    $courseResDir = RESOURCES_DIR . $cursoId . '/';
    if (!is_dir($courseResDir)) mkdir($courseResDir, 0755, true);

    // Recorrer subcarpetas como secciones
    $sectionFolders = array_values(array_filter(glob($rootFolder . '/*'), 'is_dir'));
    $sectionOrder = 1;
    foreach ($sectionFolders as $sectionPath) {
        $sectionName = basename($sectionPath);
        
        // Crear sección
        $stmt = $db->prepare('INSERT INTO secciones (curso_id, nombre, orden) VALUES (?, ?, ?)');
        $stmt->execute([$cursoId, $sectionName, $sectionOrder++]);
        $seccionId = $db->lastInsertId();

        // Cargar enlaces si hay enlaces.txt
        $linksFile = $sectionPath . '/enlaces.txt';
        $links = [];
        if (file_exists($linksFile)) {
            $lines = file($linksFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode('|', $line, 2);
                if (count($parts) === 2) {
                    $links[] = ['titulo' => trim($parts[0]), 'url' => trim($parts[1])];
                }
            }
        }

        // Videos en la carpeta de sección (solo mp4)
        $videoFiles = glob($sectionPath . '/*.mp4');
        $classOrder = 1;
        foreach ($videoFiles as $videoSrc) {
            $videoNameOriginal = basename($videoSrc);
            $videoDestName = generateUniqueFileName($videoNameOriginal, $courseVideosDir);
            copy($videoSrc, $courseVideosDir . $videoDestName);

            // Obtener duración real con ffprobe (si existe)
            $duracion = getVideoDuration($courseVideosDir . $videoDestName);
            // Crear clase
            $tituloClase = pathinfo($videoNameOriginal, PATHINFO_FILENAME);
            $stmt = $db->prepare('INSERT INTO clases (seccion_id, titulo, archivo_video, duracion, orden) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$seccionId, ucwords(str_replace(['_', '-'], ' ', $tituloClase)), $videoDestName, $duracion, $classOrder++]);
            $claseId = $db->lastInsertId();

            // Recursos descargables: carpeta recursos dentro de la sección
            $resourcesDir = $sectionPath . '/recursos';
            if (is_dir($resourcesDir)) {
                $resourceFiles = array_filter(glob($resourcesDir . '/*'), 'is_file');
                foreach ($resourceFiles as $resSrc) {
                    $resBase = basename($resSrc);
                    $resDestName = generateUniqueFileName($resBase, $courseResDir);
                    copy($resSrc, $courseResDir . $resDestName);
                    $mime = mime_content_type($resSrc) ?: null;
                    $size = filesize($resSrc) ?: 0;
                    $db->prepare('INSERT INTO recursos_clases (clase_id, nombre_archivo, archivo_path, tipo_mime, tamano_bytes) VALUES (?, ?, ?, ?, ?)')
                       ->execute([$claseId, $resBase, $resDestName, $mime, $size]);
                }
            }

            // Enlaces globales de sección, asociarlos a cada clase (o decidir solo a la 1ra)
            foreach ($links as $lnk) {
                $db->prepare('INSERT INTO enlaces_clases (clase_id, titulo, url) VALUES (?, ?, ?)')
                   ->execute([$claseId, $lnk['titulo'], $lnk['url']]);
            }
        }
    }

    $db->commit();

    jsonResponse(['success' => true, 'message' => 'Curso importado correctamente', 'curso_id' => $cursoId]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    jsonResponse(['success' => false, 'message' => 'Error en importación: ' . $e->getMessage()], 500);
}

?>


