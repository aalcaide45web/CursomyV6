<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Logs - Debug</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white">
    <div class="container mx-auto p-4">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-file-text mr-2"></i>
                Visor de Logs de Debug
            </h1>
            <div class="flex gap-2">
                <button onclick="refreshLogs()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                    <i class="fas fa-refresh mr-1"></i>
                    Actualizar
                </button>
                <button onclick="clearLogs()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded">
                    <i class="fas fa-trash mr-1"></i>
                    Limpiar
                </button>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded inline-block">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Volver
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-gray-800 p-4 rounded-lg mb-6">
            <div class="flex flex-wrap gap-4 items-center">
                <div>
                    <label class="block text-sm font-medium mb-1">Tipo de Log:</label>
                    <select id="logType" class="bg-gray-900 border border-gray-700 rounded px-3 py-2 text-white">
                        <option value="deletion">Eliminación</option>
                        <option value="debug">Debug</option>
                        <option value="error">Errores</option>
                        <option value="info">Info</option>
                        <option value="">Todos</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Líneas:</label>
                    <select id="logLines" class="bg-gray-900 border border-gray-700 rounded px-3 py-2 text-white">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="loadLogs()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded">
                        <i class="fas fa-search mr-1"></i>
                        Buscar
                    </button>
                </div>
            </div>
        </div>

        <!-- Estado -->
        <div id="status" class="mb-4 p-3 rounded hidden">
        </div>

        <!-- Logs -->
        <div class="bg-gray-800 rounded-lg">
            <div class="p-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold">
                    <i class="fas fa-list mr-2"></i>
                    Logs Recientes
                </h2>
                <div id="logInfo" class="text-sm text-gray-400 mt-1">
                    Cargando...
                </div>
            </div>
            <div id="logContent" class="p-4 font-mono text-sm max-h-96 overflow-y-auto">
                <div class="text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Cargando logs...
                </div>
            </div>
        </div>

        <!-- Archivos de log disponibles -->
        <div class="bg-gray-800 rounded-lg mt-6">
            <div class="p-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold">
                    <i class="fas fa-folder mr-2"></i>
                    Archivos de Log
                </h2>
            </div>
            <div id="logFiles" class="p-4">
                <div class="text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Cargando archivos...
                </div>
            </div>
        </div>
    </div>

    <script>
        // Cargar logs al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadLogs();
            loadLogFiles();
        });

        async function loadLogs() {
            const type = document.getElementById('logType').value;
            const lines = document.getElementById('logLines').value;
            
            try {
                showStatus('Cargando logs...', 'info');
                
                const response = await fetch(`api/logs.php?action=recent&type=${type}&lines=${lines}`);
                const data = await response.json();
                
                if (data.success) {
                    displayLogs(data.logs, data.type);
                    document.getElementById('logInfo').textContent = 
                        `Mostrando ${data.logs.length} líneas de logs tipo "${data.type}"`;
                    hideStatus();
                } else {
                    showStatus('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showStatus('Error de conexión: ' + error.message, 'error');
            }
        }

        async function loadLogFiles() {
            try {
                const response = await fetch('api/logs.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    displayLogFiles(data.files);
                } else {
                    document.getElementById('logFiles').innerHTML = 
                        '<div class="text-red-400">Error: ' + data.error + '</div>';
                }
            } catch (error) {
                document.getElementById('logFiles').innerHTML = 
                    '<div class="text-red-400">Error de conexión: ' + error.message + '</div>';
            }
        }

        function displayLogs(logs, type) {
            const content = document.getElementById('logContent');
            
            if (logs.length === 0) {
                content.innerHTML = '<div class="text-center text-gray-400">No hay logs disponibles</div>';
                return;
            }
            
            const html = logs.map(log => {
                // Colorear según el tipo de mensaje
                let className = 'text-gray-300';
                if (log.includes('[ERROR]')) className = 'text-red-400';
                else if (log.includes('[WARNING]')) className = 'text-yellow-400';
                else if (log.includes('[DEBUG]')) className = 'text-blue-400';
                else if (log.includes('[INFO]')) className = 'text-green-400';
                else if (log.includes('✓')) className = 'text-green-400';
                else if (log.includes('✗') || log.includes('❌')) className = 'text-red-400';
                else if (log.includes('⚠️')) className = 'text-yellow-400';
                
                return `<div class="${className} mb-1 leading-relaxed">${escapeHtml(log)}</div>`;
            }).join('');
            
            content.innerHTML = html;
            
            // Scroll al final
            content.scrollTop = content.scrollHeight;
        }

        function displayLogFiles(files) {
            const container = document.getElementById('logFiles');
            
            if (files.length === 0) {
                container.innerHTML = '<div class="text-gray-400">No hay archivos de log</div>';
                return;
            }
            
            const html = files.map(file => `
                <div class="flex items-center justify-between p-3 border-b border-gray-700 last:border-b-0">
                    <div>
                        <div class="font-medium">${file.file}</div>
                        <div class="text-sm text-gray-400">
                            ${file.type} • ${formatBytes(file.size)} • ${file.modified}
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="viewLogFile('${file.file}')" 
                                class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm">
                            <i class="fas fa-eye mr-1"></i>
                            Ver
                        </button>
                        <button onclick="downloadLogFile('${file.file}')" 
                                class="bg-gray-600 hover:bg-gray-700 px-3 py-1 rounded text-sm">
                            <i class="fas fa-download mr-1"></i>
                            Descargar
                        </button>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;
        }

        async function viewLogFile(filename) {
            try {
                showStatus('Cargando archivo...', 'info');
                
                const response = await fetch(`api/logs.php?action=read&file=${filename}&lines=200`);
                const data = await response.json();
                
                if (data.success) {
                    displayLogs(data.content, 'archivo');
                    document.getElementById('logInfo').textContent = 
                        `Archivo: ${data.file} (${data.showing_lines}/${data.total_lines} líneas)`;
                    hideStatus();
                } else {
                    showStatus('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showStatus('Error: ' + error.message, 'error');
            }
        }

        function downloadLogFile(filename) {
            window.open(`logs/${filename}`, '_blank');
        }

        async function clearLogs() {
            if (!confirm('¿Eliminar todos los logs? Esta acción no se puede deshacer.')) {
                return;
            }
            
            try {
                showStatus('Eliminando logs...', 'info');
                
                const response = await fetch('api/logs.php?action=clear');
                const data = await response.json();
                
                if (data.success) {
                    showStatus(`${data.deleted} archivos eliminados`, 'success');
                    setTimeout(() => {
                        refreshLogs();
                    }, 1000);
                } else {
                    showStatus('Error: ' + data.error, 'error');
                }
            } catch (error) {
                showStatus('Error: ' + error.message, 'error');
            }
        }

        function refreshLogs() {
            loadLogs();
            loadLogFiles();
        }

        function showStatus(message, type) {
            const status = document.getElementById('status');
            status.className = `mb-4 p-3 rounded ${getStatusClass(type)}`;
            status.textContent = message;
            status.classList.remove('hidden');
        }

        function hideStatus() {
            document.getElementById('status').classList.add('hidden');
        }

        function getStatusClass(type) {
            switch (type) {
                case 'success': return 'bg-green-600 text-white';
                case 'error': return 'bg-red-600 text-white';
                case 'warning': return 'bg-yellow-600 text-white';
                default: return 'bg-blue-600 text-white';
            }
        }

        function formatBytes(bytes) {
            const sizes = ['B', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 B';
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
