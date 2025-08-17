<?php
// Limpia subcarpetas numéricas en uploads/videos y uploads/resources que no correspondan a cursos existentes
// También limpia archivos huérfanos dentro de directorios válidos

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    $db = getDatabase();
    $ids = $db->query('SELECT id FROM cursos')->fetchAll(PDO::FETCH_COLUMN);
    $valid = array_map('strval', $ids);
    
    $removed = [];
    $errors = [];
    $filesRemoved = 0;
    
    // Limpiar directorios de cursos inexistentes
    foreach ([VIDEOS_DIR, RESOURCES_DIR] as $base) {
        if (!is_dir($base)) continue;
        $dirs = array_filter(glob($base . '*'), 'is_dir');
        foreach ($dirs as $dir) {
            $name = basename($dir);
            if (!ctype_digit($name)) continue; // solo directorios numéricos
            
            if (!in_array($name, $valid, true)) {
                // Curso no existe, eliminar todo el directorio
                                    $contents = array_diff(scandir($dir), ['.', '..']);
                foreach ($contents as $file) {
                    $filePath = $dir . '/' . $file;
                    if (is_file($filePath)) {
                        // Intentar eliminar con retry logic
                        $deleted = false;
                        for ($attempt = 1; $attempt <= 3; $attempt++) {
                            if (@unlink($filePath)) {
                                $deleted = true;
                                $filesRemoved++;
                                break;
                            }
                            if ($attempt < 3) {
                                usleep(200000); // Esperar 0.2 segundos
                            }
                        }
                        
                        if (!$deleted) {
                            $errors[] = 'No se pudo eliminar archivo: ' . $filePath . ' (posiblemente en uso)';
                        }
                    }
                }
                
                // Eliminar directorio vacío
                $remainingContents = array_diff(scandir($dir), ['.', '..']);
                if (empty($remainingContents)) {
                    if (@rmdir($dir)) {
                        $removed[] = $dir;
                    } else {
                        $errors[] = 'No se pudo eliminar directorio: ' . $dir;
                    }
                }
            } else {
                // Curso existe, limpiar archivos huérfanos dentro del directorio
                if ($base === RESOURCES_DIR) {
                    // Para recursos, verificar que cada archivo tenga registro en BD
                    $cursoId = (int)$name;
                    $validResources = $db->prepare("
                        SELECT DISTINCT rc.archivo_path 
                        FROM recursos_clases rc 
                        JOIN clases cl ON rc.clase_id = cl.id 
                        JOIN secciones s ON cl.seccion_id = s.id 
                        WHERE s.curso_id = ?
                    ");
                    $validResources->execute([$cursoId]);
                    $validResourcePaths = $validResources->fetchAll(PDO::FETCH_COLUMN);
                    
                    $contents = array_diff(scandir($dir), ['.', '..']);
                    foreach ($contents as $file) {
                        if (is_file($dir . '/' . $file) && !in_array($file, $validResourcePaths)) {
                            if (@unlink($dir . '/' . $file)) {
                                $filesRemoved++;
                            } else {
                                $errors[] = 'No se pudo eliminar recurso huérfano: ' . $dir . '/' . $file;
                            }
                        }
                    }
                } else if ($base === VIDEOS_DIR) {
                    // Para videos, verificar que cada archivo tenga registro en BD
                    $cursoId = (int)$name;
                    $validVideos = $db->prepare("
                        SELECT DISTINCT cl.archivo_video 
                        FROM clases cl 
                        JOIN secciones s ON cl.seccion_id = s.id 
                        WHERE s.curso_id = ? AND cl.archivo_video IS NOT NULL
                    ");
                    $validVideos->execute([$cursoId]);
                    $validVideoPaths = $validVideos->fetchAll(PDO::FETCH_COLUMN);
                    
                    $contents = array_diff(scandir($dir), ['.', '..']);
                    foreach ($contents as $file) {
                        if (is_file($dir . '/' . $file) && !in_array($file, $validVideoPaths)) {
                            if (@unlink($dir . '/' . $file)) {
                                $filesRemoved++;
                            } else {
                                $errors[] = 'No se pudo eliminar video huérfano: ' . $dir . '/' . $file;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Limpiar imágenes huérfanas
    if (is_dir(IMAGES_DIR)) {
        $validImages = $db->query("SELECT imagen FROM cursos WHERE imagen IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $imageFiles = array_filter(glob(IMAGES_DIR . '*'), 'is_file');
        foreach ($imageFiles as $imagePath) {
            $imageName = basename($imagePath);
            if (!in_array($imageName, $validImages)) {
                if (@unlink($imagePath)) {
                    $filesRemoved++;
                } else {
                    $errors[] = 'No se pudo eliminar imagen huérfana: ' . $imagePath;
                }
            }
        }
    }
    
    // Limpiar datos huérfanos de la BD también
    $orphanDataRemoved = 0;
    
    // Limpiar progreso huérfano
    $orphanDataRemoved += $db->exec("DELETE FROM progreso_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    
    // Limpiar notas huérfanas
    $orphanDataRemoved += $db->exec("DELETE FROM notas_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    
    // Limpiar comentarios huérfanos
    $orphanDataRemoved += $db->exec("DELETE FROM comentarios_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    
    // Limpiar recursos huérfanos
    $orphanDataRemoved += $db->exec("DELETE FROM recursos_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    
    // Limpiar clases huérfanas
    $orphanDataRemoved += $db->exec("DELETE FROM clases WHERE seccion_id NOT IN (SELECT id FROM secciones)");
    
    // Limpiar secciones huérfanas
    $orphanDataRemoved += $db->exec("DELETE FROM secciones WHERE curso_id NOT IN (SELECT id FROM cursos)");
    
    // Resetear AUTO_INCREMENT si hay diferencia significativa
    $maxCursoId = $db->query("SELECT COALESCE(MAX(id), 0) FROM cursos")->fetchColumn();
    $currentAutoIncrement = $db->query("SELECT COALESCE(seq, 0) FROM sqlite_sequence WHERE name='cursos'")->fetchColumn();
    
    if ($currentAutoIncrement > $maxCursoId + 10) { // Solo si la diferencia es mayor a 10
        $db->exec("UPDATE sqlite_sequence SET seq = $maxCursoId WHERE name = 'cursos'");
        $details = "Directorios eliminados: " . count($removed) . " | Archivos eliminados: $filesRemoved" . " | Datos huérfanos eliminados: $orphanDataRemoved | AUTO_INCREMENT resetado: $currentAutoIncrement → " . ($maxCursoId + 1);
    } else {
        $details = "Directorios eliminados: " . count($removed) . " | Archivos eliminados: $filesRemoved" . " | Datos huérfanos eliminados: $orphanDataRemoved";
    }
    
    if (!empty($errors)) $details .= ' | Errores: ' . implode('; ', $errors);
    jsonResponse(['success' => true, 'details' => $details, 'directories_removed' => count($removed), 'files_removed' => $filesRemoved, 'orphan_data_removed' => $orphanDataRemoved]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>


