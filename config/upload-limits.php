<?php
/**
 * Configuración de límites de upload para CursosMy
 * Este archivo debe ser incluido ANTES de cualquier operación de upload
 */

// Función para aumentar límites de PHP
function increaseUploadLimits() {
    // Aumentar límites solo si no están ya configurados
    if (ini_get('upload_max_filesize') != '512000M') {
        @ini_set('upload_max_filesize', '512000M');
    }
    
    if (ini_get('post_max_size') != '512000M') {
        @ini_set('post_max_size', '512000M');
    }
    
    if (ini_get('max_execution_time') != '86400') {
        @ini_set('max_execution_time', '86400');
    }
    
    if (ini_get('memory_limit') != '2048M') {
        @ini_set('memory_limit', '2048M');
    }
    
    if (ini_get('max_file_uploads') != '20') {
        @ini_set('max_file_uploads', '20');
    }
    
    if (ini_get('max_input_vars') != '3000') {
        @ini_set('max_input_vars', '3000');
    }
}

// Función para verificar límites actuales
function getCurrentLimits() {
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'max_input_vars' => ini_get('max_input_vars')
    ];
}

// Función para verificar si los límites son suficientes
function areLimitsSufficient($fileSizeInBytes) {
    $limits = getCurrentLimits();
    
    // Convertir límites a bytes para comparación
    $uploadLimit = return_bytes($limits['upload_max_filesize']);
    $postLimit = return_bytes($limits['post_max_size']);
    
    return $fileSizeInBytes <= $uploadLimit && $fileSizeInBytes <= $postLimit;
}

// Función auxiliar para convertir tamaños a bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

// Función para formatear bytes a formato legible
// (Eliminada para evitar conflicto con config.php)

// Aplicar límites automáticamente
increaseUploadLimits();

// Log de límites aplicados (para debug)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log('[UPLOAD-LIMITS] Límites aplicados: ' . json_encode(getCurrentLimits()));
}
?>
