<?php
/**
 * Sistema de logging para debug
 */

class Logger {
    private static $logDir = 'logs/';
    
    public static function init() {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
    
    public static function log($type, $message, $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $logFile = self::$logDir . $type . '_' . date('Y-m-d') . '.log';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        $logLine = $timestamp . ' [' . strtoupper($type) . '] ' . $message;
        if (!empty($context)) {
            $logLine .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $logLine .= ' | Memory: ' . self::formatBytes($logEntry['memory_usage']) . PHP_EOL;
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        return $logEntry;
    }
    
    public static function debug($message, $context = []) {
        return self::log('debug', $message, $context);
    }
    
    public static function info($message, $context = []) {
        return self::log('info', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        return self::log('warning', $message, $context);
    }
    
    public static function error($message, $context = []) {
        return self::log('error', $message, $context);
    }
    
    public static function deletion($message, $context = []) {
        return self::log('deletion', $message, $context);
    }
    
    private static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public static function getLogFiles($type = null) {
        self::init();
        
        $pattern = self::$logDir . ($type ? $type . '_*.log' : '*.log');
        $files = glob($pattern);
        
        // Ordenar por fecha modificación (más reciente primero)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files;
    }
    
    public static function getRecentLogs($type = 'deletion', $lines = 50) {
        $files = self::getLogFiles($type);
        if (empty($files)) {
            return [];
        }
        
        $logs = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $fileLines = array_filter(explode(PHP_EOL, $content));
            $logs = array_merge($logs, $fileLines);
            
            if (count($logs) >= $lines) {
                break;
            }
        }
        
        return array_slice(array_reverse($logs), -$lines);
    }
    
    public static function clearLogs($type = null, $olderThan = null) {
        $files = self::getLogFiles($type);
        $deleted = 0;
        
        foreach ($files as $file) {
            $shouldDelete = false;
            
            if ($olderThan) {
                $fileTime = filemtime($file);
                $cutoffTime = strtotime($olderThan);
                $shouldDelete = $fileTime < $cutoffTime;
            } else {
                $shouldDelete = true;
            }
            
            if ($shouldDelete) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}

// Función helper global
function logDebug($message, $context = []) {
    return Logger::debug($message, $context);
}

function logDeletion($message, $context = []) {
    return Logger::deletion($message, $context);
}

function logError($message, $context = []) {
    return Logger::error($message, $context);
}
?>
