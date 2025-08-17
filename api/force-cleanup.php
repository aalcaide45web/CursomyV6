<?php
// Endpoint para forzar limpieza de archivos problemáticos
// Especialmente útil para archivos que quedan después de usar botón reanudar

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    $db = getDatabase();
    
    $removed = [];
    $errors = [];
    $filesRemoved = 0;
    $forceActions = [];
    
    // 1. Obtener cursos válidos
    $validCourseIds = $db->query('SELECT id FROM cursos')->fetchAll(PDO::FETCH_COLUMN);
    $validIds = array_map('strval', $validCourseIds);
    
    // 2. Buscar archivos huérfanos con retry agresivo
    foreach ([VIDEOS_DIR, RESOURCES_DIR] as $base) {
        if (!is_dir($base)) continue;
        
        $dirs = array_filter(glob($base . '*'), 'is_dir');
        foreach ($dirs as $dir) {
            $courseId = basename($dir);
            if (!ctype_digit($courseId)) continue;
            
            if (!in_array($courseId, $validIds, true)) {
                $forceActions[] = "Procesando directorio huérfano: $courseId";
                
                // Listar archivos antes de intentar eliminar
                $files = array_diff(scandir($dir), ['.', '..']);
                $forceActions[] = "  Archivos encontrados: " . count($files);
                
                foreach ($files as $file) {
                    $filePath = $dir . '/' . $file;
                    if (!is_file($filePath)) continue;
                    
                    $fileSize = filesize($filePath);
                    $forceActions[] = "  Procesando: $file (" . round($fileSize/1024/1024, 2) . " MB)";
                    
                    // Retry agresivo con diferentes estrategias
                    $deleted = false;
                    
                    // Estrategia 1: Eliminación normal con retry
                    for ($attempt = 1; $attempt <= 5; $attempt++) {
                        if (@unlink($filePath)) {
                            $deleted = true;
                            $filesRemoved++;
                            $forceActions[] = "    ✓ Eliminado en intento $attempt";
                            break;
                        }
                        usleep(500000 * $attempt); // Espera incremental
                    }
                    
                    // Estrategia 2: Si aún no se eliminó, intentar cambiar permisos
                    if (!$deleted && file_exists($filePath)) {
                        @chmod($filePath, 0777);
                        usleep(1000000); // Esperar 1 segundo
                        
                        for ($attempt = 1; $attempt <= 3; $attempt++) {
                            if (@unlink($filePath)) {
                                $deleted = true;
                                $filesRemoved++;
                                $forceActions[] = "    ✓ Eliminado después de cambiar permisos (intento $attempt)";
                                break;
                            }
                            usleep(1000000);
                        }
                    }
                    
                    // Estrategia 3: Si aún persiste, renombrar para eliminación posterior
                    if (!$deleted && file_exists($filePath)) {
                        $tempName = $filePath . '.DELETE_ME_' . time();
                        if (@rename($filePath, $tempName)) {
                            $forceActions[] = "    ⚠ Renombrado para eliminación posterior: " . basename($tempName);
                            if (@unlink($tempName)) {
                                $deleted = true;
                                $filesRemoved++;
                                $forceActions[] = "    ✓ Eliminado después de renombrar";
                            }
                        } else {
                            $errors[] = "No se pudo eliminar ni renombrar: $filePath";
                            $forceActions[] = "    ✗ FALLÓ - archivo puede estar en uso activo";
                        }
                    }
                }
                
                // Intentar eliminar directorio
                $remainingContents = array_diff(scandir($dir), ['.', '..']);
                if (empty($remainingContents)) {
                    if (@rmdir($dir)) {
                        $removed[] = $dir;
                        $forceActions[] = "  ✓ Directorio eliminado";
                    } else {
                        $errors[] = "No se pudo eliminar directorio: $dir";
                        $forceActions[] = "  ✗ Directorio no se pudo eliminar";
                    }
                } else {
                    $forceActions[] = "  ⚠ Directorio no vacío, quedan " . count($remainingContents) . " archivos";
                }
            }
        }
    }
    
    // 3. Limpiar imágenes huérfanas con retry
    if (is_dir(IMAGES_DIR)) {
        $validImages = $db->query("SELECT imagen FROM cursos WHERE imagen IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $imageFiles = array_filter(glob(IMAGES_DIR . '*'), 'is_file');
        
        foreach ($imageFiles as $imagePath) {
            $imageName = basename($imagePath);
            if (!in_array($imageName, $validImages)) {
                $deleted = false;
                for ($attempt = 1; $attempt <= 3; $attempt++) {
                    if (@unlink($imagePath)) {
                        $deleted = true;
                        $filesRemoved++;
                        break;
                    }
                    usleep(300000);
                }
                
                if (!$deleted) {
                    $errors[] = "No se pudo eliminar imagen: $imagePath";
                }
            }
        }
    }
    
    // 4. Limpiar datos huérfanos
    $orphanDataRemoved = 0;
    $orphanDataRemoved += $db->exec("DELETE FROM progreso_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    $orphanDataRemoved += $db->exec("DELETE FROM notas_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    $orphanDataRemoved += $db->exec("DELETE FROM comentarios_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    $orphanDataRemoved += $db->exec("DELETE FROM recursos_clases WHERE clase_id NOT IN (SELECT id FROM clases)");
    $orphanDataRemoved += $db->exec("DELETE FROM clases WHERE seccion_id NOT IN (SELECT id FROM secciones)");
    $orphanDataRemoved += $db->exec("DELETE FROM secciones WHERE curso_id NOT IN (SELECT id FROM cursos)");
    
    // 5. Resetear AUTO_INCREMENT
    $maxCursoId = $db->query("SELECT COALESCE(MAX(id), 0) FROM cursos")->fetchColumn();
    $currentAutoIncrement = $db->query("SELECT COALESCE(seq, 0) FROM sqlite_sequence WHERE name='cursos'")->fetchColumn();
    
    if ($currentAutoIncrement != $maxCursoId) {
        $db->exec("UPDATE sqlite_sequence SET seq = $maxCursoId WHERE name = 'cursos'");
        $forceActions[] = "AUTO_INCREMENT resetado: $currentAutoIncrement → " . ($maxCursoId + 1);
    }
    
    $summary = [
        'success' => true,
        'directories_removed' => count($removed),
        'files_removed' => $filesRemoved,
        'orphan_data_removed' => $orphanDataRemoved,
        'errors' => $errors,
        'force_actions' => $forceActions,
        'details' => "Limpieza forzada completada: " . count($removed) . " directorios, $filesRemoved archivos, $orphanDataRemoved datos huérfanos"
    ];
    
    if (!empty($errors)) {
        $summary['details'] .= ' | ' . count($errors) . ' errores';
    }
    
    jsonResponse($summary);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error en limpieza forzada: ' . $e->getMessage()], 500);
}
?>
