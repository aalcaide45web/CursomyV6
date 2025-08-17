<?php
/**
 * API para gestionar logs de debug
 */

require_once '../config/logger.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $type = $_GET['type'] ?? null;
            $files = Logger::getLogFiles($type);
            
            $result = [];
            foreach ($files as $file) {
                $result[] = [
                    'file' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'type' => explode('_', basename($file))[0]
                ];
            }
            
            echo json_encode([
                'success' => true,
                'files' => $result
            ]);
            break;
            
        case 'read':
            $file = $_GET['file'] ?? null;
            $lines = (int)($_GET['lines'] ?? 100);
            
            if (!$file) {
                throw new Exception('Archivo requerido');
            }
            
            $filePath = 'logs/' . basename($file);
            if (!file_exists($filePath)) {
                throw new Exception('Archivo no encontrado');
            }
            
            $content = file_get_contents($filePath);
            $allLines = array_filter(explode(PHP_EOL, $content));
            $recentLines = array_slice($allLines, -$lines);
            
            echo json_encode([
                'success' => true,
                'file' => $file,
                'total_lines' => count($allLines),
                'showing_lines' => count($recentLines),
                'content' => $recentLines
            ]);
            break;
            
        case 'recent':
            $type = $_GET['type'] ?? 'deletion';
            $lines = (int)($_GET['lines'] ?? 50);
            
            $logs = Logger::getRecentLogs($type, $lines);
            
            echo json_encode([
                'success' => true,
                'type' => $type,
                'logs' => $logs
            ]);
            break;
            
        case 'clear':
            $type = $_GET['type'] ?? null;
            $olderThan = $_GET['older_than'] ?? null;
            
            $deleted = Logger::clearLogs($type, $olderThan);
            
            echo json_encode([
                'success' => true,
                'deleted' => $deleted
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
