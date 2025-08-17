<?php
require_once 'config/database.php';
require_once 'config/config.php';

// Función para mostrar información de debug en formato JSON
function debugInfo() {
    $info = [
        'php_config' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'file_uploads' => ini_get('file_uploads') ? 'enabled' : 'disabled',
            'max_file_uploads' => ini_get('max_file_uploads'),
        ],
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        ],
        'directories' => [
            'upload_dir' => [
                'path' => UPLOAD_DIR,
                'exists' => is_dir(UPLOAD_DIR),
                'writable' => is_writable(UPLOAD_DIR),
                'permissions' => is_dir(UPLOAD_DIR) ? substr(sprintf('%o', fileperms(UPLOAD_DIR)), -4) : 'N/A'
            ],
            'videos_dir' => [
                'path' => VIDEOS_DIR,
                'exists' => is_dir(VIDEOS_DIR),
                'writable' => is_writable(VIDEOS_DIR),
                'permissions' => is_dir(VIDEOS_DIR) ? substr(sprintf('%o', fileperms(VIDEOS_DIR)), -4) : 'N/A'
            ],
            'images_dir' => [
                'path' => IMAGES_DIR,
                'exists' => is_dir(IMAGES_DIR),
                'writable' => is_writable(IMAGES_DIR),
                'permissions' => is_dir(IMAGES_DIR) ? substr(sprintf('%o', fileperms(IMAGES_DIR)), -4) : 'N/A'
            ]
        ],
        'constants' => [
            'max_video_size' => MAX_VIDEO_SIZE,
            'max_video_size_formatted' => formatBytes(MAX_VIDEO_SIZE),
            'allowed_video_types' => ALLOWED_VIDEO_TYPES,
        ],
        'database' => []
    ];
    
    // Información de base de datos
    try {
        $db = getDatabase();
        $info['database']['connection'] = 'success';
        
        $cursos = $db->query("SELECT COUNT(*) as count FROM cursos")->fetch();
        $info['database']['cursos_count'] = $cursos['count'];
        
        $secciones = $db->query("SELECT COUNT(*) as count FROM secciones")->fetch();
        $info['database']['secciones_count'] = $secciones['count'];
        
        // Obtener algunos cursos para debug
        $cursos = $db->query("SELECT id, titulo FROM cursos LIMIT 5")->fetchAll();
        $info['database']['sample_cursos'] = $cursos;
        
        foreach ($cursos as $curso) {
            $secciones = $db->query("SELECT id, nombre FROM secciones WHERE curso_id = " . $curso['id'])->fetchAll();
            $info['database']['sample_cursos_secciones'][$curso['id']] = $secciones;
        }
        
    } catch (Exception $e) {
        $info['database']['connection'] = 'error';
        $info['database']['error'] = $e->getMessage();
    }
    
    return $info;
}

// Si es una petición AJAX, devolver JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode(debugInfo(), JSON_PRETTY_PRINT);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CursosMy - Debug Console</title>
    <link href="css/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .debug-section {
            margin-bottom: 1rem;
        }
        .debug-key {
            color: #fbbf24;
            font-weight: bold;
        }
        .debug-value {
            color: #a78bfa;
        }
        .debug-error {
            color: #f87171;
        }
        .debug-success {
            color: #4ade80;
        }
        .console-output {
            background: #1f2937;
            color: #e5e7eb;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 1rem;
            border-radius: 0.5rem;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <!-- Navegación -->
    <nav class="glass-dark rounded-lg m-4 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold text-white">
                    <i class="fas fa-bug mr-2"></i>CursosMy - Debug Console
                </h1>
            </div>
            <div class="flex space-x-2">
                <button onclick="refreshDebugInfo()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-sync-alt mr-2"></i>Actualizar
                </button>
                <button onclick="clearConsole()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-trash mr-2"></i>Limpiar
                </button>
                <a href="index.php" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Volver
                </a>
            </div>
        </div>
    </nav>

    <div class="mx-4 mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Información del Sistema -->
            <div class="glass-dark rounded-lg p-6">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-server mr-2"></i>Información del Sistema
                </h2>
                <div id="systemInfo" class="console-output">
                    Cargando información del sistema...
                </div>
            </div>

            <!-- Console Log Simulator -->
            <div class="glass-dark rounded-lg p-6">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-terminal mr-2"></i>Console Log (Simulador F12)
                </h2>
                <div id="consoleLog" class="console-output">
                    Console iniciada...<br>
                    Esperando eventos de debug...<br>
                </div>
            </div>

            <!-- Test de Upload -->
            <div class="glass-dark rounded-lg p-6 lg:col-span-2">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-upload mr-2"></i>Test de Upload con Debug
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-white text-sm font-bold mb-2">Curso ID</label>
                        <select id="debugCursoId" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white">
                            <option value="">Seleccionar curso...</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-white text-sm font-bold mb-2">Sección ID</label>
                        <select id="debugSeccionId" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white">
                            <option value="">Seleccionar sección...</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-white text-sm font-bold mb-2">Archivo de Test</label>
                    <input type="file" id="debugFile" accept=".mp4" class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white">
                    <p class="text-gray-400 text-xs mt-1">Selecciona un archivo MP4 para probar el upload</p>
                </div>
                
                <div class="flex space-x-3">
                    <button onclick="testUpload()" id="testUploadBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-play mr-2"></i>Test Upload
                    </button>
                    <button onclick="checkFileSize()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-ruler mr-2"></i>Verificar Tamaño
                    </button>
                    <button onclick="simulateError()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Simular Error
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let debugData = null;

        // Función para log en el console simulado
        function debugLog(message, type = 'info') {
            const consoleDiv = document.getElementById('consoleLog');
            const timestamp = new Date().toLocaleTimeString();
            let color = '#e5e7eb';
            
            switch(type) {
                case 'error':
                    color = '#f87171';
                    break;
                case 'warning':
                    color = '#fbbf24';
                    break;
                case 'success':
                    color = '#4ade80';
                    break;
                case 'info':
                    color = '#60a5fa';
                    break;
            }
            
            consoleDiv.innerHTML += `<span style="color: #9ca3af">[${timestamp}]</span> <span style="color: ${color}">${message}</span><br>`;
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        }

        // Función para limpiar console
        function clearConsole() {
            document.getElementById('consoleLog').innerHTML = 'Console limpiada...<br>';
        }

        // Función para cargar información del sistema
        async function refreshDebugInfo() {
            debugLog('Cargando información del sistema...', 'info');
            
            try {
                const response = await fetch('debug-console.php?ajax=1');
                const data = await response.json();
                debugData = data;
                
                displaySystemInfo(data);
                populateSelects(data);
                debugLog('Información del sistema cargada correctamente', 'success');
                
            } catch (error) {
                debugLog('Error al cargar información del sistema: ' + error.message, 'error');
            }
        }

        // Función para mostrar información del sistema
        function displaySystemInfo(data) {
            const systemDiv = document.getElementById('systemInfo');
            let html = '';
            
            // Configuración PHP
            html += '<strong style="color: #fbbf24">📋 CONFIGURACIÓN PHP:</strong><br>';
            html += `upload_max_filesize: <span style="color: #a78bfa">${data.php_config.upload_max_filesize}</span><br>`;
            html += `post_max_size: <span style="color: #a78bfa">${data.php_config.post_max_size}</span><br>`;
            html += `max_execution_time: <span style="color: #a78bfa">${data.php_config.max_execution_time}</span><br>`;
            html += `memory_limit: <span style="color: #a78bfa">${data.php_config.memory_limit}</span><br>`;
            html += `file_uploads: <span style="color: ${data.php_config.file_uploads === 'enabled' ? '#4ade80' : '#f87171'}">${data.php_config.file_uploads}</span><br><br>`;
            
            // Directorios
            html += '<strong style="color: #fbbf24">📁 DIRECTORIOS:</strong><br>';
            Object.keys(data.directories).forEach(key => {
                const dir = data.directories[key];
                const status = dir.exists && dir.writable ? '✅' : '❌';
                html += `${key}: ${status} <span style="color: #a78bfa">${dir.path}</span> (${dir.permissions})<br>`;
            });
            html += '<br>';
            
            // Constantes
            html += '<strong style="color: #fbbf24">⚙️ CONFIGURACIÓN:</strong><br>';
            html += `max_video_size: <span style="color: #a78bfa">${data.constants.max_video_size_formatted}</span><br>`;
            html += `allowed_types: <span style="color: #a78bfa">${data.constants.allowed_video_types.join(', ')}</span><br><br>`;
            
            // Base de datos
            html += '<strong style="color: #fbbf24">🗄️ BASE DE DATOS:</strong><br>';
            if (data.database.connection === 'success') {
                html += `Conexión: <span style="color: #4ade80">✅ Exitosa</span><br>`;
                html += `Cursos: <span style="color: #a78bfa">${data.database.cursos_count}</span><br>`;
                html += `Secciones: <span style="color: #a78bfa">${data.database.secciones_count}</span><br>`;
            } else {
                html += `Conexión: <span style="color: #f87171">❌ Error</span><br>`;
                html += `Error: <span style="color: #f87171">${data.database.error}</span><br>`;
            }
            
            systemDiv.innerHTML = html;
        }

        // Función para poblar los selects
        function populateSelects(data) {
            const cursoSelect = document.getElementById('debugCursoId');
            const seccionSelect = document.getElementById('debugSeccionId');
            
            // Limpiar selects
            cursoSelect.innerHTML = '<option value="">Seleccionar curso...</option>';
            seccionSelect.innerHTML = '<option value="">Seleccionar sección...</option>';
            
            if (data.database.sample_cursos) {
                data.database.sample_cursos.forEach(curso => {
                    const option = document.createElement('option');
                    option.value = curso.id;
                    option.textContent = `${curso.id} - ${curso.titulo}`;
                    cursoSelect.appendChild(option);
                });
            }
            
            // Listener para cambio de curso
            cursoSelect.addEventListener('change', function() {
                const cursoId = this.value;
                seccionSelect.innerHTML = '<option value="">Seleccionar sección...</option>';
                
                if (cursoId && data.database.sample_cursos_secciones && data.database.sample_cursos_secciones[cursoId]) {
                    data.database.sample_cursos_secciones[cursoId].forEach(seccion => {
                        const option = document.createElement('option');
                        option.value = seccion.id;
                        option.textContent = `${seccion.id} - ${seccion.nombre}`;
                        seccionSelect.appendChild(option);
                    });
                }
            });
        }

        // Función para verificar tamaño de archivo
        function checkFileSize() {
            const fileInput = document.getElementById('debugFile');
            const file = fileInput.files[0];
            
            if (!file) {
                debugLog('No se ha seleccionado ningún archivo', 'warning');
                return;
            }
            
            const fileSize = file.size;
            const maxSize = debugData ? debugData.constants.max_video_size : 500 * 1024 * 1024 * 1024; // 500GB por defecto
            
            debugLog(`📁 Archivo seleccionado: ${file.name}`, 'info');
            debugLog(`📏 Tamaño del archivo: ${formatBytes(fileSize)}`, 'info');
            debugLog(`📐 Límite máximo: ${formatBytes(maxSize)}`, 'info');
            
            if (fileSize > maxSize) {
                debugLog('❌ ARCHIVO DEMASIADO GRANDE - Este es el problema!', 'error');
                debugLog(`💡 Solución: Reduce el tamaño del archivo o aumenta los límites de PHP`, 'warning');
            } else {
                debugLog('✅ Tamaño de archivo válido', 'success');
            }
            
            // Verificar límites de PHP
            if (debugData && debugData.php_config) {
                const postMaxSize = parseSize(debugData.php_config.post_max_size);
                const uploadMaxSize = parseSize(debugData.php_config.upload_max_filesize);
                
                debugLog(`📤 post_max_size: ${debugData.php_config.post_max_size} (${formatBytes(postMaxSize)})`, 'info');
                debugLog(`📥 upload_max_filesize: ${debugData.php_config.upload_max_filesize} (${formatBytes(uploadMaxSize)})`, 'info');
                
                if (fileSize > postMaxSize) {
                    debugLog('❌ Archivo excede post_max_size de PHP', 'error');
                }
                if (fileSize > uploadMaxSize) {
                    debugLog('❌ Archivo excede upload_max_filesize de PHP', 'error');
                }
            }
        }

        // Función para test de upload
        async function testUpload() {
            const cursoId = document.getElementById('debugCursoId').value;
            const seccionId = document.getElementById('debugSeccionId').value;
            const fileInput = document.getElementById('debugFile');
            const file = fileInput.files[0];
            
            if (!cursoId || !seccionId) {
                debugLog('❌ Debes seleccionar curso y sección', 'error');
                return;
            }
            
            if (!file) {
                debugLog('❌ Debes seleccionar un archivo', 'error');
                return;
            }
            
            debugLog('🚀 Iniciando test de upload...', 'info');
            debugLog(`📋 Curso ID: ${cursoId}`, 'info');
            debugLog(`📋 Sección ID: ${seccionId}`, 'info');
            debugLog(`📁 Archivo: ${file.name} (${formatBytes(file.size)})`, 'info');
            
            const formData = new FormData();
            formData.append('curso_id', cursoId);
            formData.append('seccion_id', seccionId);
            formData.append('videos[]', file);
            
            try {
                debugLog('📤 Enviando petición a la API...', 'info');
                
                const response = await fetch('api/upload-videos.php', {
                    method: 'POST',
                    body: formData
                });
                
                debugLog(`📥 Respuesta recibida: ${response.status} ${response.statusText}`, response.ok ? 'success' : 'error');
                
                const responseText = await response.text();
                debugLog('📄 Contenido de la respuesta:', 'info');
                debugLog(responseText, 'info');
                
                try {
                    const result = JSON.parse(responseText);
                    debugLog('✅ JSON válido parseado', 'success');
                    debugLog(`Resultado: ${JSON.stringify(result, null, 2)}`, 'info');
                } catch (parseError) {
                    debugLog('❌ Error al parsear JSON:', 'error');
                    debugLog(parseError.message, 'error');
                    debugLog('🔍 La respuesta contiene HTML/errores de PHP antes del JSON', 'warning');
                }
                
            } catch (error) {
                debugLog('❌ Error en la petición:', 'error');
                debugLog(error.message, 'error');
            }
        }

        // Función para simular error
        function simulateError() {
            debugLog('🎭 Simulando error típico...', 'warning');
            debugLog('POST http://localhost/api/upload-videos.php 400 (Bad Request)', 'error');
            debugLog('Response text: <br /><b>Warning</b>: POST Content-Length exceeds limit', 'error');
            debugLog('JSON Parse Error: Unexpected token \'<\'', 'error');
            debugLog('💡 Este error indica que PHP está mostrando warnings antes del JSON', 'warning');
        }

        // Utilidades
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function parseSize(size) {
            const units = {
                'K': 1024,
                'M': 1024 * 1024,
                'G': 1024 * 1024 * 1024
            };
            
            const match = size.match(/^(\d+)([KMG]?)$/i);
            if (!match) return 0;
            
            const value = parseInt(match[1]);
            const unit = match[2].toUpperCase();
            
            return value * (units[unit] || 1);
        }

        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            debugLog('🔧 Debug Console iniciada', 'success');
            refreshDebugInfo();
        });
    </script>
</body>
</html>
