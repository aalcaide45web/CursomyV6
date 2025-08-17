<?php
require_once 'config/database.php';
require_once 'config/config.php';

// Obtener estadísticas para el dashboard
$db = getDatabase();

$stmt = $db->query("SELECT COUNT(*) as total FROM cursos");
$totalCursos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM instructores");
$totalInstructores = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tematicas");
$totalTematicas = $stmt->fetch()['total'];

// Obtener cursos con información adicional
$query = "
    SELECT c.*, 
           i.nombre as instructor_nombre,
           t.nombre as tematica_nombre,
           (SELECT COUNT(*) FROM secciones s WHERE s.curso_id = c.id) as total_secciones,
           (SELECT COUNT(*) FROM clases cl 
            JOIN secciones s ON cl.seccion_id = s.id 
            WHERE s.curso_id = c.id) as total_clases,
           (SELECT SUM(cl.duracion) FROM clases cl 
            JOIN secciones s ON cl.seccion_id = s.id 
            WHERE s.curso_id = c.id) as duracion_total
    FROM cursos c
    LEFT JOIN instructores i ON c.instructor_id = i.id
    LEFT JOIN tematicas t ON c.tematica_id = t.id
    ORDER BY c.created_at DESC
";
$cursos = $db->query($query)->fetchAll();

// Obtener instructores y temáticas para filtros
$instructores = $db->query("SELECT * FROM instructores ORDER BY nombre")->fetchAll();
$tematicas = $db->query("SELECT * FROM tematicas ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CursosMy - Dashboard</title>
    <link href="css/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .glass-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <!-- Navegación -->
    <nav class="m-4">
        <div class="glass-dark rounded-xl px-4 py-3 md:px-6 md:py-4 flex items-center justify-between shadow-lg">
            <!-- Branding + navegación -->
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-600 to-indigo-600 flex items-center justify-center ring-1 ring-white/20">
                    <i class="fas fa-graduation-cap text-white"></i>
                </div>
                <div class="hidden md:flex items-center gap-2">
                    <a href="index.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-500/40 bg-black/20 text-white hover:bg-black/30">
                        <i class="fas fa-home"></i><span class="hidden sm:inline">Dashboard</span>
                    </a>
                    <a href="instructores-tematicas.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-purple-500/40 bg-black/20 text-white hover:bg-black/30">
                        <i class="fas fa-users"></i><span class="hidden sm:inline">Instructores & Temáticas</span>
                    </a>
                </div>
            </div>

            <!-- Acciones -->
            <div class="flex items-center gap-2">
                <a href="debug-console.php" class="hidden md:inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-yellow-400/40 bg-black/20 text-yellow-300 hover:bg-black/30" title="Consola de Debug">
                    <i class="fas fa-bug"></i><span class="hidden lg:inline">Debug</span>
                </a>
                <a href="logs-viewer.php" class="hidden md:inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-blue-400/40 bg-black/20 text-blue-300 hover:bg-black/30" title="Visor de Logs">
                    <i class="fas fa-file-text"></i><span class="hidden lg:inline">Logs</span>
                </a>
                <button id="cleanupBtn" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-red-400/40 bg-black/20 text-red-200 hover:bg-black/30 text-xs sm:text-sm" title="Limpiar carpetas huérfanas">
                    <i class="fas fa-broom"></i><span class="hidden sm:inline">Limpieza BD</span>
                </button>
                <button onclick="openGlobalSearchModal()" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-purple-400/40 bg-black/20 text-purple-200 hover:bg-black/30 text-xs sm:text-sm" title="Buscar en todos los cursos">
                    <i class="fas fa-search"></i><span class="hidden sm:inline">Buscador Global</span>
                </button>
                <!-- Botón de progreso de importaciones -->
                <button id="queueProgressBtn" onclick="openQueueProgressModal()" class="relative inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-orange-400/40 bg-black/20 text-orange-200 hover:bg-black/30 text-xs sm:text-sm" title="Progreso de importaciones">
                    <i class="fas fa-tasks"></i><span class="hidden sm:inline">Progreso</span>
                    <span id="queueBadge" class="absolute -top-1 -right-1 bg-orange-500 text-white text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center hidden">0</span>
                </button>
                <button onclick="openFolderPicker()" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-blue-400/40 bg-black/20 text-blue-200 hover:bg-black/30 text-xs sm:text-sm" title="Importar carpeta(s)">
                    <i class="fas fa-folder-open"></i><span class="hidden sm:inline">Importar Carpeta</span>
                </button>
                <button onclick="openCreateCourseModal()" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-purple-500/40 bg-black/20 text-purple-200 hover:bg-black/30 text-xs sm:text-sm">
                    <i class="fas fa-plus"></i><span class="hidden sm:inline">Nuevo Curso</span>
                </button>
            </div>
        </div>
        <!-- input oculto para seleccionar carpetas -->
        <input id="folderPicker" type="file" webkitdirectory multiple class="hidden">
    </nav>
    
    <!-- Modal Importar Carpeta: previsualización + progreso -->
    <div id="folderImportModal" class="fixed inset-0 bg-black/60 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg w-full max-w-5xl overflow-hidden">
                <div class="bg-black/30 p-4 border-b border-white/10 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white"><i class="fas fa-folder-open mr-2"></i>Importar desde Carpeta</h3>
                    <div class="flex items-center gap-2">
                        <button id="impExpandAll" class="text-white/80 bg-black/40 hover:bg-black/50 rounded px-2 py-1 text-xs" title="Expandir todas"><i class="fas fa-chevron-down"></i> Expandir todas</button>
                        <button id="impCollapseAll" class="text-white/80 bg-black/40 hover:bg-black/50 rounded px-2 py-1 text-xs" title="Contraer todas"><i class="fas fa-chevron-right"></i> Contraer todas</button>
                        <button onclick="closeFolderImportModal()" class="text-white/70 hover:text-white"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div id="folderImportConfig" class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-4 max-h-[75vh] overflow-y-auto">
                    <div class="lg:col-span-1">
                        <h4 class="text-white font-semibold mb-2">Destino del curso</h4>
                        <div class="space-y-3 text-gray-200 text-sm">
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="courseMode" value="new" checked class="accent-purple-500" onchange="renderCourseMode()">
                                <span>Crear curso nuevo</span>
                            </label>
                            <div id="courseNewBox" class="space-y-2">
                                <label class="block text-white text-xs">Título del curso</label>
                                <input id="importCourseTitle" type="text" class="w-full bg-black/20 border border-white/20 rounded-lg px-3 py-2 text-white">
                            </div>
                            <label class="flex items-center space-x-2 mt-3">
                                <input type="radio" name="courseMode" value="existing" class="accent-purple-500" onchange="renderCourseMode()">
                                <span>Usar curso existente</span>
                            </label>
                            <div id="courseExistingBox" class="space-y-2 hidden">
                                <label class="block text-white text-xs">Seleccionar curso</label>
                                <select id="importCourseSelect" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($cursos as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['titulo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button id="startFolderImportBtn" onclick="addToImportQueue()" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors"><i class="fas fa-plus mr-2"></i>Añadir a Cola</button>
                            <p class="text-gray-400 text-xs mt-2">Subidas simultáneas: máx. 5</p>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <h4 class="text-white font-semibold mb-2">Organización de secciones y archivos</h4>
                        <div id="importWarnings" class="hidden mb-3 p-3 rounded-lg border border-yellow-500/40 bg-yellow-500/10 text-yellow-200 text-sm"></div>
                        <div id="sectionsPreview" class="space-y-3" style="max-height:55vh; overflow-y:auto; padding-right:4px;"></div>
                    </div>
                </div>
                <div id="folderImportProgress" class="hidden p-4" style="max-height:75vh; overflow-y:auto;">
                    <h4 class="text-white font-semibold mb-3">Progreso de subida</h4>
                    <div class="w-full bg-gray-700 rounded-full h-2 mb-4 overflow-hidden">
                        <div id="globalProgressBar" class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div id="fileProgressList" class="space-y-2 text-sm"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Progreso de Cola v2.0 -->
    <div id="queueProgressModal" class="fixed inset-0 bg-black/60 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg w-full max-w-6xl max-h-[90vh] overflow-hidden">
                <!-- Header del modal -->
                <div class="bg-black/30 p-4 border-b border-white/10 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-bold text-white">
                            <i class="fas fa-tasks mr-2"></i>Cola de Importaciones
                        </h3>
                        <div id="queueStats" class="flex items-center gap-4 text-sm">
                            <span class="bg-blue-500/20 text-blue-200 px-2 py-1 rounded" id="queueStatsPending">0 en cola</span>
                            <span class="bg-green-500/20 text-green-200 px-2 py-1 rounded" id="queueStatsCompleted">0 completados</span>
                            <span class="bg-red-500/20 text-red-200 px-2 py-1 rounded" id="queueStatsErrors">0 errores</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="queuePauseBtn" onclick="toggleQueuePause()" class="text-white/80 bg-yellow-600/80 hover:bg-yellow-600 rounded px-3 py-1 text-sm hidden">
                            <i class="fas fa-pause"></i> Pausar
                        </button>
                        <button id="queueResumeBtn" onclick="toggleQueuePause()" class="text-white/80 bg-green-600/80 hover:bg-green-600 rounded px-3 py-1 text-sm hidden">
                            <i class="fas fa-play"></i> Reanudar
                        </button>
                        <button onclick="showErrorReport()" class="text-white/80 bg-red-600/80 hover:bg-red-600 rounded px-3 py-1 text-sm">
                            <i class="fas fa-exclamation-triangle"></i> Informe
                        </button>
                        <button onclick="clearCompletedJobs()" class="text-white/80 bg-gray-600/80 hover:bg-gray-600 rounded px-3 py-1 text-sm">
                            <i class="fas fa-broom"></i> Limpiar
                        </button>
                        <button onclick="closeQueueProgressModal()" class="text-white/70 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Controles globales -->
                <div class="bg-black/20 p-3 border-b border-white/10 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button onclick="cancelAllJobs()" class="bg-red-600/80 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-stop"></i> Cancelar Todo
                        </button>
                        <div class="text-sm text-gray-300">
                            <i class="fas fa-info-circle mr-1"></i>
                            Arrastra para reordenar • Clic derecho para opciones
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-300">
                        <span id="queueGlobalProgress">Preparando...</span>
                        <div class="w-24 bg-gray-700 rounded-full h-2">
                            <div id="queueGlobalProgressBar" class="bg-orange-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Lista de trabajos -->
                <div id="queueJobsList" class="p-4 space-y-3 overflow-y-auto" style="max-height: 60vh;">
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>No hay importaciones en la cola</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Informe de Errores -->
    <div id="errorReportModal" class="fixed inset-0 bg-black/60 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg w-full max-w-4xl max-h-[90vh] overflow-hidden">
                <div class="bg-black/30 p-4 border-b border-white/10 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-exclamation-triangle mr-2 text-red-400"></i>Informe de Errores
                    </h3>
                    <button onclick="closeErrorReportModal()" class="text-white/70 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="errorReportContent" class="p-4 overflow-y-auto" style="max-height: 70vh;">
                    <!-- Contenido dinámico -->
                </div>
                <div class="bg-black/20 p-3 border-t border-white/10 flex justify-end gap-2">
                    <button onclick="exportErrorReport()" class="bg-blue-600/80 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                        <i class="fas fa-download mr-1"></i> Exportar
                    </button>
                    <button onclick="closeErrorReportModal()" class="bg-gray-600/80 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mx-4 mb-6">
        <div class="glass-dark rounded-lg p-6 text-center">
            <i class="fas fa-book text-4xl text-purple-400 mb-2"></i>
            <h3 class="text-xl font-semibold text-white"><?php echo $totalCursos; ?></h3>
            <p class="text-gray-300">Cursos Totales</p>
        </div>
        <div class="glass-dark rounded-lg p-6 text-center">
            <i class="fas fa-user-tie text-4xl text-blue-400 mb-2"></i>
            <h3 class="text-xl font-semibold text-white"><?php echo $totalInstructores; ?></h3>
            <p class="text-gray-300">Instructores</p>
        </div>
        <div class="glass-dark rounded-lg p-6 text-center">
            <i class="fas fa-tags text-4xl text-green-400 mb-2"></i>
            <h3 class="text-xl font-semibold text-white"><?php echo $totalTematicas; ?></h3>
            <p class="text-gray-300">Temáticas</p>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="glass-dark rounded-lg mx-4 mb-6 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" id="searchInput" placeholder="Buscar cursos..." 
                       class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none">
            </div>
            <div>
                <select id="instructorFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                    <option value="">Todos los instructores</option>
                    <?php foreach ($instructores as $instructor): ?>
                        <option value="<?php echo $instructor['id']; ?>"><?php echo htmlspecialchars($instructor['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select id="tematicaFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                    <option value="">Todas las temáticas</option>
                    <?php foreach ($tematicas as $tematica): ?>
                        <option value="<?php echo $tematica['id']; ?>"><?php echo htmlspecialchars($tematica['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select id="sortBy" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                    <option value="created_at">Más recientes</option>
                    <option value="titulo">Nombre A-Z</option>
                    <option value="titulo_desc">Nombre Z-A</option>
                    <option value="duracion">Duración</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Grid de cursos -->
    <div id="coursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mx-4 mb-8">
        <?php foreach ($cursos as $curso): ?>
            <div class="course-card glass-dark rounded-lg overflow-hidden hover:scale-105 transition-transform duration-300"
                 data-titulo="<?php echo strtolower($curso['titulo']); ?>"
                 data-instructor="<?php echo $curso['instructor_id']; ?>"
                 data-tematica="<?php echo $curso['tematica_id']; ?>"
                 data-duracion="<?php echo $curso['duracion_total'] ?? 0; ?>">
                
                <div class="h-48 bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center">
                    <?php if ($curso['imagen']): ?>
                        <img src="uploads/images/<?php echo htmlspecialchars($curso['imagen']); ?>" alt="<?php echo htmlspecialchars($curso['titulo']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-play-circle text-6xl text-white opacity-50"></i>
                    <?php endif; ?>
                </div>
                
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-white mb-2"><?php echo htmlspecialchars($curso['titulo']); ?></h3>
                    
                    <?php if ($curso['instructor_nombre']): ?>
                        <p class="text-sm text-gray-300 mb-1">
                            <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($curso['instructor_nombre']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($curso['tematica_nombre']): ?>
                        <p class="text-sm text-gray-300 mb-1">
                            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($curso['tematica_nombre']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="flex justify-between text-xs text-gray-400 mb-3">
                        <span><i class="fas fa-list mr-1"></i><?php echo $curso['total_secciones']; ?> secciones</span>
                        <span><i class="fas fa-video mr-1"></i><?php echo $curso['total_clases']; ?> clases</span>
                    </div>
                    
                    <?php if ($curso['duracion_total']): ?>
                        <p class="text-xs text-gray-400 mb-3">
                            <i class="fas fa-clock mr-1"></i><?php echo gmdate("H:i:s", $curso['duracion_total']); ?> total
                        </p>
                    <?php endif; ?>
                    
                    <div class="flex space-x-2">
                        <a href="curso.php?id=<?php echo $curso['id']; ?>" 
                           class="flex-1 bg-purple-600 hover:bg-purple-700 text-white text-center py-2 px-3 rounded text-sm transition-colors">
                            <i class="fas fa-eye mr-1"></i>Ver
                        </a>
                        <button onclick="editCourse(<?php echo $curso['id']; ?>)" 
                                class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded text-sm transition-colors">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteCourse(<?php echo $curso['id']; ?>)" 
                                class="bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded text-sm transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal crear curso -->
    <div id="createCourseModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-plus mr-2"></i>Crear Nuevo Curso
                </h2>
                
                <form id="createCourseForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Título del Curso *</label>
                        <input type="text" name="titulo" required 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Temática</label>
                        <select name="tematica_id" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                            <option value="">Seleccionar temática</option>
                            <?php foreach ($tematicas as $tematica): ?>
                                <option value="<?php echo $tematica['id']; ?>"><?php echo htmlspecialchars($tematica['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Instructor</label>
                        <select name="instructor_id" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                            <option value="">Seleccionar instructor</option>
                            <?php foreach ($instructores as $instructor): ?>
                                <option value="<?php echo $instructor['id']; ?>"><?php echo htmlspecialchars($instructor['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Imagen del Curso</label>
                        <input type="file" name="imagen" accept="image/*" 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Comentarios</label>
                        <textarea name="comentarios" rows="3" 
                                  class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none"></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Crear Curso
                        </button>
                        <button type="button" onclick="closeCreateCourseModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script src="js/import-queue.js"></script>
    <script>
        // Modal búsqueda global (dashboard)
        function openGlobalSearchModal() {
            let modal = document.getElementById('globalSearchModal');
            if (!modal) {
                const wrapper = document.createElement('div');
                wrapper.id = 'globalSearchModal';
                wrapper.className = 'fixed inset-0 bg-black/60 z-50 flex items-start justify-center p-6';
                wrapper.innerHTML = `
                    <div class=\"glass-dark rounded-lg p-6 w-full max-w-3xl mt-10\">
                        <div class=\"flex items-center justify-between mb-4\">
                            <h3 class=\"text-xl font-bold text-white\"><i class=\"fas fa-search mr-2\"></i>Buscar en todos los cursos</h3>
                            <button class=\"bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded\" onclick=\"closeGlobalSearchModal()\"><i class=\"fas fa-times\"></i></button>
                        </div>
                        <input id=\"globalSearchInput\" type=\"text\" placeholder=\"Escribe para buscar en clases, notas, comentarios y adjuntos...\" class=\"w-full bg-black/20 border border-white/20 rounded px-3 py-2 text-white\" />
                        <div id=\"globalSearchResults\" class=\"mt-4 max-h-[60vh] overflow-y-auto space-y-2\"></div>
                    </div>`;
                document.body.appendChild(wrapper);
                // Cerrar por clic fuera / ESC
                wrapper.addEventListener('click', (e) => { if (e.target === wrapper) closeGlobalSearchModal(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeGlobalSearchModal(); });
                document.getElementById('globalSearchInput').addEventListener('input', () => performGlobalSearch(document.getElementById('globalSearchInput').value.trim().toLowerCase()));
            } else {
                modal.classList.remove('hidden');
            }
            setTimeout(() => document.getElementById('globalSearchInput').focus(), 50);
        }
        function closeGlobalSearchModal() {
            const modal = document.getElementById('globalSearchModal');
            if (modal) modal.classList.add('hidden');
        }
        function formatTimeLocal(s) {
            s = Math.max(0, parseInt(s||0,10)); const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), sec=s%60;
            const hh=h.toString().padStart(2,'0'), mm=m.toString().padStart(2,'0'), ss=sec.toString().padStart(2,'0');
            return h>0?`${hh}:${mm}:${ss}`:`${mm}:${ss}`;
        }
        function appendResultLocal(container, tipo, texto, onClick) {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between bg-black/30 border border-white/10 rounded px-3 py-2 text-sm text-gray-200 hover:bg-black/40 cursor-pointer';
            let strong = '<strong class="text-purple-300">';
            if (tipo==='Nota') strong='<strong class="text-orange-400" style="color:#fb923c">';
            else if (tipo==='Clase') strong='<strong class="text-green-400" style="color:#4ade80">';
            else if (tipo==='Adjunto') strong='<strong class="text-blue-400" style="color:#60a5fa">';
            else if (tipo==='Comentario') strong='<strong class="text-pink-400" style="color:#f472b6">';
            row.innerHTML = `<span>${strong}${tipo}:</strong> ${texto}</span><i class="fas fa-arrow-right text-white/60"></i>`;
            row.addEventListener('click', onClick);
            container.appendChild(row);
        }
        async function performGlobalSearch(term) {
            const results = document.getElementById('globalSearchResults');
            results.innerHTML = '';
            if (!term) return;
            try {
                const res = await fetch(`api/search.php?q=${encodeURIComponent(term)}`);
                const json = await res.json();
                if (!json.success) return;
                (json.data || []).forEach(item => {
                    if (item.type==='Clase') appendResultLocal(results,'Clase', item.label, () => window.location.href=`reproductor.php?clase=${item.clase_id}`);
                    else if (item.type==='Nota') appendResultLocal(results,'Nota', `${formatTimeLocal(item.time)} • ${item.label}`, () => window.location.href=`reproductor.php?clase=${item.clase_id}&t=${item.time}`);
                    else if (item.type==='Comentario') appendResultLocal(results,'Comentario', item.label, () => window.location.href=`reproductor.php?clase=${item.clase_id}`);
                    else if (item.type==='Adjunto') appendResultLocal(results,'Adjunto', item.label, () => window.location.href=`reproductor.php?clase=${item.clase_id}`);
                });
            } catch(_) {}
        }
    </script>
    <script>
        // Lógica de modal de importación por carpeta
        const folderPicker = document.getElementById('folderPicker');
        const folderModal = document.getElementById('folderImportModal');
        const sectionsPreview = document.getElementById('sectionsPreview');
        const globalProgressBar = document.getElementById('globalProgressBar');
        const fileProgressList = document.getElementById('fileProgressList');
        const progressPanel = document.getElementById('folderImportProgress');
        const configPanel = document.getElementById('folderImportConfig');

        let importFiles = [];
        let importMap = new Map(); // seccion -> [files]
        let importTotal = 0;
        let fileIdToFile = new Map();

        function openFolderImportModal() { folderModal.classList.remove('hidden'); }
        function closeFolderImportModal() { folderModal.classList.add('hidden'); }

        function renderCourseMode() {
            const mode = document.querySelector('input[name="courseMode"]:checked').value;
            document.getElementById('courseNewBox').classList.toggle('hidden', mode !== 'new');
            document.getElementById('courseExistingBox').classList.toggle('hidden', mode !== 'existing');
        }

        window.prepareFolderImport = function(files) {
            // Filtrar solo archivos de video antes de procesarlos
            const videoExtensions = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'm4v', '3gp', 'ogv'];
            const allFiles = Array.from(files);
            const totalFiles = allFiles.length;
            
            importFiles = allFiles.filter(f => {
                const ext = f.name.split('.').pop().toLowerCase();
                const isVideo = videoExtensions.includes(ext);
                if (!isVideo) {
                    console.log('[IMPORT][FILTERED]', { name: f.name, extension: ext, reason: 'no es video' });
                }
                return isVideo;
            });
            
            const filteredCount = totalFiles - importFiles.length;
            if (filteredCount > 0) {
                console.log(`[IMPORT][SUMMARY] ${importFiles.length} videos seleccionados, ${filteredCount} archivos no-video ignorados`);
            }
            
            if (importFiles.length === 0) {
                alert('No se encontraron archivos de video válidos en la carpeta seleccionada.\nFormatos soportados: ' + videoExtensions.join(', ').toUpperCase());
                return;
            }
            
            // Agrupar inteligentemente: si hay subcarpetas reales, crear secciones; si no, una sola sección
            importMap = new Map();
            fileIdToFile = new Map();
            
            // Analizar estructura: detectar si realmente hay subcarpetas con contenido
            const folderStructure = new Map();
            const rootFiles = [];
            
            importFiles.forEach(f => {
                const parts = (f.webkitRelativePath || f.name).split('/');
                if (parts.length > 2) {
                    // Archivo en subcarpeta (ej: MiCurso/Seccion1/video.mp4)
                    const folderName = parts[1];
                    if (!folderStructure.has(folderName)) {
                        folderStructure.set(folderName, []);
                    }
                    folderStructure.get(folderName).push(f);
                } else {
                    // Archivo en raíz del curso (ej: MiCurso/video.mp4)
                    rootFiles.push(f);
                }
            });
            
            console.log('[IMPORT][STRUCTURE]', {
                rootFiles: rootFiles.length,
                subfolders: folderStructure.size,
                folderNames: Array.from(folderStructure.keys())
            });
            
            // Decidir estrategia de agrupación
            let strategyMessage = '';
            if (folderStructure.size === 0) {
                // Todos los archivos están en la raíz → Una sola sección
                console.log('[IMPORT][STRATEGY] Archivos en raíz → Una sección');
                const first = importFiles[0];
                const courseName = first && first.webkitRelativePath ? 
                    first.webkitRelativePath.split('/')[0] : 'Curso Importado';
                importMap.set(courseName, importFiles);
                strategyMessage = `Se creará 1 sección: "${courseName}" con ${importFiles.length} videos.`;
            } else if (folderStructure.size === 1 && rootFiles.length === 0) {
                // Solo hay una subcarpeta con todos los archivos → Una sección con el nombre de la carpeta
                console.log('[IMPORT][STRATEGY] Una subcarpeta → Una sección');
                const folderName = Array.from(folderStructure.keys())[0];
                const files = Array.from(folderStructure.values())[0];
                importMap.set(folderName, files);
                strategyMessage = `Se creará 1 sección: "${folderName}" con ${files.length} videos.`;
            } else {
                // Estructura compleja → Secciones por carpeta
                console.log('[IMPORT][STRATEGY] Múltiples carpetas → Secciones por carpeta');
                
                // Agregar archivos de subcarpetas
                folderStructure.forEach((files, folderName) => {
                    importMap.set(folderName, files);
                });
                
                // Si hay archivos en la raíz, crear sección "General"
                if (rootFiles.length > 0) {
                    importMap.set('General', rootFiles);
                }
                
                const sectionCount = folderStructure.size + (rootFiles.length > 0 ? 1 : 0);
                const sectionNames = Array.from(folderStructure.keys());
                if (rootFiles.length > 0) sectionNames.push('General');
                strategyMessage = `Se crearán ${sectionCount} secciones: ${sectionNames.join(', ')}.`;
            }
            
            // Mostrar notificación informativa
            const totalFilteredFiles = totalFiles - importFiles.length;
            let notificationMessage = `📁 ${importFiles.length} videos encontrados`;
            if (totalFilteredFiles > 0) {
                notificationMessage += ` (${totalFilteredFiles} archivos no-video ignorados)`;
            }
            notificationMessage += `\n\n📋 ${strategyMessage}`;
            
            setTimeout(() => {
                alert(notificationMessage);
            }, 100);
            
            // Procesar archivos para el mapa de IDs
            importFiles.forEach(f => {
                const fid = f.webkitRelativePath || f.name;
                fileIdToFile.set(fid, f);
                // Debug: detectar tamaños cero
                if (!f.size) {
                    console.warn('[IMPORT][ZERO_SIZE]', { name: f.name, path: f.webkitRelativePath, type: f.type, lastModified: f.lastModified });
                } else {
                    console.debug('[IMPORT][FILE]', { name: f.name, sizeBytes: f.size, sizeMB: (f.size/1024/1024).toFixed(2), path: f.webkitRelativePath });
                }
            });
            // Ordenar secciones alfabéticamente para un inicio consistente
            importMap = new Map(Array.from(importMap.entries()).sort((a, b) => a[0].localeCompare(b[0], undefined, { numeric: true, sensitivity: 'base' })));
            // Ordenar archivos por nombre
            importMap.forEach((arr) => arr.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' })));
            importTotal = importFiles.length;
            renderSectionsPreview();
            // Prellenar título con raíz
            const first = importFiles[0];
            if (first && first.webkitRelativePath) {
                const root = first.webkitRelativePath.split('/')[0];
                const titleInput = document.getElementById('importCourseTitle');
                if (titleInput && !titleInput.value) titleInput.value = root;
            }
            openFolderImportModal();
        }

        function renderSectionsPreview() {
            sectionsPreview.innerHTML = '';
            // Lista vertical de secciones, cada una sortable (drag & drop) y reordenables entre sí
            const sectionList = document.createElement('div');
            sectionList.className = 'space-y-3';

            // Orden fijo de secciones
            const entries = Array.from(importMap.entries());
            entries.forEach(([sectionName, files]) => {
                const item = document.createElement('div');
                item.className = 'bg-black/20 rounded-lg border border-white/10';
                item.draggable = true;
                item.dataset.section = sectionName;
                const secId = sectionName.replace(/[^a-z0-9]/gi,'_');
                item.innerHTML = `
                    <div class="flex justify-between items-center p-3 border-b border-white/10 bg-black/30">
                        <div class="flex items-center gap-2">
                            <button type="button" class="toggleImpSec text-white/80 bg-black/40 hover:bg-black/50 rounded px-2 py-1 text-xs" data-target="#impsec-${secId}"><i class="fas fa-chevron-down"></i></button>
                            <input value="${sectionName}" data-original="${sectionName}" class="section-name w-64 bg-black/30 border border-white/10 rounded px-2 py-1 text-white text-sm" />
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-400 text-xs">${files.length} archivo(s)</span>
                            <button type="button" class="remove-section text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded px-2 py-1 text-xs" title="Eliminar sección completa">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="space-y-1 min-h-[10px] p-3" id="impsec-${secId}" data-files="${sectionName}"></div>
                `;
                const list = item.querySelector(`[data-files="${sectionName}"]`);
                files.forEach(file => {
                    const row = document.createElement('div');
                    row.className = 'file-row flex items-center justify-between bg-black/10 rounded px-2 py-1 border border-white/5';
                    row.draggable = true;
                    row.dataset.fileId = file.webkitRelativePath || file.name;
                    row.innerHTML = `
                        <div class="flex items-center gap-2">
                            <i class="fas fa-grip-lines text-white/30"></i>
                            <span class="text-xs text-white/90 truncate max-w-[420px]">${file.name}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-white/40">${file.size ? (file.size/1024/1024).toFixed(2) + ' MB' : '0.00 MB'}</span>
                            <button type="button" class="text-red-400 hover:text-red-300" data-remove title="Quitar"><i class="fas fa-times"></i></button>
                        </div>
                    `;
                    if (!file.size) row.classList.add('border-red-500/40');
                    list.appendChild(row);
                });
                sectionList.appendChild(item);
            });

            sectionsPreview.appendChild(sectionList);

            // Utilidades DnD "tipo iPhone"
            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.file-row:not(.dragging)')];
                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height/2;
                    if (offset < 0 && offset > closest.offset) {
                        return { offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }

            // Drag dentro de listas de archivos con reordenamiento visual
            let draggingRow = null;
            sectionsPreview.querySelectorAll('.file-row').forEach(row => {
                row.addEventListener('dragstart', () => {
                    draggingRow = row;
                    row.classList.add('opacity-60', 'ring-1', 'ring-purple-500/50', 'dragging');
                });
                row.addEventListener('dragend', () => {
                    row.classList.remove('opacity-60', 'ring-1', 'ring-purple-500/50', 'dragging');
                    draggingRow = null;
                    recomputeImportMapFromDOM();
                });
            });

            sectionsPreview.querySelectorAll('[data-files]').forEach(list => {
                list.addEventListener('dragover', e => {
                    e.preventDefault();
                    const afterElement = getDragAfterElement(list, e.clientY);
                    if (!draggingRow) return;
                    if (afterElement == null) {
                        list.appendChild(draggingRow);
                    } else {
                        list.insertBefore(draggingRow, afterElement);
                    }
                });
                list.addEventListener('drop', () => {
                    recomputeImportMapFromDOM();
                });
            });

            // Quitar archivo con X
            sectionsPreview.querySelectorAll('[data-remove]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const row = e.currentTarget.closest('.file-row');
                    if (!row) return;
                    row.remove();
                    recomputeImportMapFromDOM();
                });
            });

            // Eliminar sección completa
            sectionsPreview.querySelectorAll('.remove-section').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const sectionCard = e.currentTarget.closest('[data-section]');
                    if (!sectionCard) return;
                    
                    const sectionName = sectionCard.dataset.section;
                    const fileCount = sectionCard.querySelectorAll('.file-row').length;
                    
                    if (confirm(`¿Eliminar la sección "${sectionName}" con ${fileCount} archivo(s)?`)) {
                        sectionCard.remove();
                        recomputeImportMapFromDOM();
                        
                        // Verificar si quedan secciones
                        const remainingSections = sectionsPreview.querySelectorAll('[data-section]').length;
                        if (remainingSections === 0) {
                            alert('⚠️ No quedan secciones. Debes tener al menos una sección para importar.');
                        }
                    }
                });
            });

            // Reordenar secciones completas
            let draggingSection = null;
            function sectionAfterElement(container, y) {
                const cards = [...container.querySelectorAll('[data-section]:not(.drag-sec)')];
                return cards.reduce((closest, el) => {
                    const box = el.getBoundingClientRect();
                    const offset = y - box.top - box.height/2;
                    if (offset < 0 && offset > closest.offset) {
                        return { offset, element: el };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
            const sectionsContainer = sectionList; // parent
            sectionsPreview.querySelectorAll('[data-section]').forEach(sec => {
                sec.addEventListener('dragstart', () => {
                    draggingSection = sec;
                    sec.classList.add('opacity-70', 'drag-sec');
                });
                sec.addEventListener('dragend', () => {
                    sec.classList.remove('opacity-70', 'drag-sec');
                    draggingSection = null;
                    recomputeImportMapFromDOM();
                });
            });
            sectionsContainer.addEventListener('dragover', e => {
                if (!draggingSection) return;
                e.preventDefault();
                const after = sectionAfterElement(sectionsContainer, e.clientY);
                if (after == null) {
                    sectionsContainer.appendChild(draggingSection);
                } else {
                    sectionsContainer.insertBefore(draggingSection, after);
                }
            });

            // Toggle de secciones en modal (expandir/contraer)
            sectionsPreview.querySelectorAll('.toggleImpSec').forEach(btn => {
                btn.addEventListener('click', () => {
                    const target = document.querySelector(btn.getAttribute('data-target'));
                    if (!target) return;
                    target.classList.toggle('hidden');
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = target.classList.contains('hidden') ? 'fas fa-chevron-right' : 'fas fa-chevron-down';
                });
            });

            // Expandir/contraer todas
            const expAll = document.getElementById('impExpandAll');
            const colAll = document.getElementById('impCollapseAll');
            if (expAll) expAll.onclick = () => {
                sectionsPreview.querySelectorAll('[id^="impsec-"]').forEach(el => el.classList.remove('hidden'));
                sectionsPreview.querySelectorAll('.toggleImpSec i').forEach(i => i.className = 'fas fa-chevron-down');
            };
            if (colAll) colAll.onclick = () => {
                sectionsPreview.querySelectorAll('[id^="impsec-"]').forEach(el => el.classList.add('hidden'));
                sectionsPreview.querySelectorAll('.toggleImpSec i').forEach(i => i.className = 'fas fa-chevron-right');
            };
        }

        function recomputeImportMapFromDOM() {
            const newMap = new Map();
            const sectionCards = sectionsPreview.querySelectorAll('[data-section]');
            sectionCards.forEach(card => {
                const input = card.querySelector('.section-name');
                const sectionName = (input && input.value.trim()) || card.dataset.section;
                const rows = card.querySelectorAll('.file-row');
                const list = [];
                rows.forEach(r => {
                    const fid = r.dataset.fileId;
                    const f = fileIdToFile.get(fid);
                    if (f) list.push(f);
                });
                newMap.set(sectionName, list);
            });
            importMap = newMap;
            console.debug('[IMPORT][MAP_UPDATED]', Array.from(importMap.keys()));
        }

        function moveFileToSection(fileId, newSection) {
            let fileObj;
            let oldSection;
            importMap.forEach((files, section) => {
                const idx = files.findIndex(f => (f.webkitRelativePath || f.name) === fileId);
                if (idx !== -1) {
                    fileObj = files[idx];
                    oldSection = section;
                    files.splice(idx, 1);
                }
            });
            if (!fileObj) return;
            if (!importMap.has(newSection)) importMap.set(newSection, []);
            importMap.get(newSection).push(fileObj);
            renderSectionsPreview();
        }

        // Nueva función para añadir trabajos a la cola
        window.addToImportQueue = function () {
            const mode = document.querySelector('input[name="courseMode"]:checked').value;
            let courseId = null;
            let courseTitle = '';

            // Validar configuración del curso
            if (mode === 'new') {
                courseTitle = (document.getElementById('importCourseTitle').value || '').trim();
                if (!courseTitle) { 
                    alert('Título del curso requerido'); 
                    return; 
                }
            } else {
                courseId = document.getElementById('importCourseSelect').value;
                if (!courseId) { 
                    alert('Selecciona un curso existente'); 
                    return; 
                }
                // Obtener título del curso existente
                const select = document.getElementById('importCourseSelect');
                courseTitle = select.options[select.selectedIndex].text;
            }

            // Recomputar importMap desde DOM para respetar cambios del usuario
            recomputeImportMapFromDOM();
            
            // Validar que hay contenido para importar
            if (importMap.size === 0) {
                alert('No hay secciones para importar');
                return;
            }

            // Crear trabajo en la cola
            const job = window.importQueue.createJob({
                courseTitle: courseTitle,
                courseMode: mode,
                courseId: courseId,
                sections: new Map(importMap) // Clonar el Map actual
            });

            // Mostrar notificación
            showNotification(`✅ Curso "${courseTitle}" añadido a la cola de importación`, 'success');
            
            // Cerrar modal de importación
            closeFolderImportModal();
            
            // Abrir modal de progreso si es el primer trabajo o si el usuario prefiere
            const stats = window.importQueue.getStats();
            if (stats.total === 1) {
                // Primera importación - mostrar automáticamente el progreso
                setTimeout(() => {
                    openQueueProgressModal();
                }, 500);
            } else {
                // Trabajos adicionales - solo mostrar notificación
                showNotification(`Trabajo añadido a la cola. Total: ${stats.pending + stats.processing} en progreso`, 'info');
            }
            
            // Limpiar estado del modal para la siguiente importación
            importFiles = [];
            importMap = new Map();
            fileIdToFile = new Map();
            sectionsPreview.innerHTML = '';
            document.getElementById('importCourseTitle').value = '';
            document.getElementById('importCourseSelect').value = '';
            document.querySelector('input[name="courseMode"][value="new"]').checked = true;
            renderCourseMode();
            
            // Mostrar el botón de progreso si no estaba visible
            if (!queueUI.initialized) initQueueUI();
        }

        // Función legacy mantenida para compatibilidad (pero modificada para usar cola)
        window.startFolderImport = function () {
            // Redirigir a la nueva función
            addToImportQueue();
        }

        // Abrir modal al elegir carpeta
        function openFolderPicker() {
            // al seleccionar, el change lo gestiona js/dashboard.js → prepareFolderImport(files)
            folderPicker.click();
        }
        // Cuando el usuario selecciona carpeta, js/dashboard.js invoca window.prepareFolderImport(files)

        // === SISTEMA DE COLA v2.0 ===
        
        // Variables del sistema de cola
        let queueUI = {
            progressBtn: null,
            badge: null,
            modal: null,
            jobsList: null,
            errorModal: null,
            initialized: false
        };

        // Inicializar UI de cola
        function initQueueUI() {
            if (queueUI.initialized) return;
            
            queueUI.progressBtn = document.getElementById('queueProgressBtn');
            queueUI.badge = document.getElementById('queueBadge');
            queueUI.modal = document.getElementById('queueProgressModal');
            queueUI.jobsList = document.getElementById('queueJobsList');
            queueUI.errorModal = document.getElementById('errorReportModal');
            queueUI.initialized = true;
            
            // Listener del sistema de cola
            window.importQueue.addEventListener((event, data, queue) => {
                updateQueueUI(event, data, queue);
            });
            
            // Actualizar UI inicial
            updateQueueUI('init', null, window.importQueue);
        }

        // Actualizar UI según eventos de la cola
        function updateQueueUI(event, data, queue) {
            if (!queueUI.initialized) return;
            
            // Debug para entender los eventos
            if (event === 'progress_updated') {
                console.log('[UI] Progreso actualizado:', {
                    jobId: data.id,
                    progress: data.progress,
                    percentage: data.getProgressPercentage()
                });
            }
            
            const stats = queue.getStats();
            const hasActive = stats.pending > 0 || stats.processing > 0;
            
            // Botón siempre visible; solo ocultar el badge si no hay activos
            queueUI.badge.classList.toggle('hidden', !hasActive);
            queueUI.badge.textContent = hasActive ? (stats.pending + stats.processing) : '0';
            
            // Actualizar estadísticas del modal
            document.getElementById('queueStatsPending').textContent = `${stats.pending + stats.processing} en cola`;
            document.getElementById('queueStatsCompleted').textContent = `${stats.completed} completados`;
            document.getElementById('queueStatsErrors').textContent = `${stats.error} errores`;
            
            // Actualizar botones de pausa/reanudar
            const isPaused = queue.isPaused;
            const isProcessing = queue.isProcessing;
            document.getElementById('queuePauseBtn').classList.toggle('hidden', !isProcessing || isPaused);
            document.getElementById('queueResumeBtn').classList.toggle('hidden', !isPaused);
            
            // Actualizar lista de trabajos SOLO en eventos estructurales (no en cada tick de progreso)
            const structuralEvents = ['init','job_added','job_cancelled','job_started','job_completed','job_error','section_cancelled','file_cancelled','queue_cleaned'];
            if (structuralEvents.includes(event)) {
                renderQueueJobs(queue);
            } else if (event === 'progress_updated' && data) {
                // Actualizar solo el job afectado para mantener desplegables abiertos
                updateSingleJobUI(data);
            }
            
            // Actualizar progreso global
            updateGlobalProgress(queue);

            // Actualizaciones del grid de cursos (index) para cursos nuevos sin recargar
            handleIndexCoursesUpdates(event, data, queue);
        }

        // Renderizar lista de trabajos
        function renderQueueJobs(queue) {
            const jobs = Array.from(queue.queue.values())
                .sort((a, b) => a.createdAt - b.createdAt);
            
            if (jobs.length === 0) {
                queueUI.jobsList.innerHTML = `
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>No hay importaciones en la cola</p>
                    </div>
                `;
                return;
            }
            
            queueUI.jobsList.innerHTML = jobs.map(job => createJobCard(job)).join('');
            
            // Añadir event listeners para menús contextuales
            setupJobContextMenus();
        }

        // Actualizar SOLO una card de trabajo (barra, textos) sin re-render de la lista
        function updateSingleJobUI(job) {
            try {
                const card = queueUI.jobsList.querySelector(`[data-job-id="${job.id}"]`);
                if (!card) return;
                const progress = job.getProgressPercentage();
                // Barra
                const bar = card.querySelector('.job-progress-bar');
                if (bar) {
                    bar.style.width = progress + '%';
                    bar.style.backgroundColor = getBarColor(job.status);
                }
                // Texto X/Y
                const txt = card.querySelector('.job-progress-text');
                if (txt) txt.textContent = `${job.progress.completed}/${job.progress.total} archivos`;
                // Mensaje actual
                const msg = card.querySelector('.job-progress-msg');
                if (msg) msg.textContent = job.progress.current || '';
            } catch (e) { console.warn('[UI][updateSingleJobUI] error', e); }
        }

        function getBarColor(status) {
            if (status === 'completed') return '#22c55e'; // green-500
            if (status === 'error' || status === 'cancelled') return '#ef4444'; // red-500
            return '#f97316'; // orange-500 processing
        }

        // ======= Gestión de tarjetas en el grid (index) para cursos NUEVOS =======
        function handleIndexCoursesUpdates(event, data, queue) {
            try {
                if (!document.getElementById('coursesGrid')) return; // Solo en index
                if (!data) return;
                const job = data; // para eventos que pasan job directamente
                
                if (event === 'job_course_created' && job.courseMode === 'new') {
                    // Insertar placeholder si no existe
                    const id = `placeholder-job-${job.id}`;
                    if (!document.getElementById(id)) {
                        insertCoursePlaceholderCard(job);
                    }
                }
                
                if (event === 'job_completed' && job.courseMode === 'new') {
                    // Marcar placeholder como completado y enlazar a curso.php
                    completeCoursePlaceholderCard(job);
                }
                
                if (event === 'job_cancelled' && job.courseMode === 'new') {
                    // Quitar placeholder si existiera
                    const el = document.getElementById(`placeholder-job-${job.id}`);
                    if (el) el.remove();
                }
            } catch (e) { console.warn('[INDEX_PLACEHOLDER] error', e); }
        }

        function insertCoursePlaceholderCard(job) {
            const grid = document.getElementById('coursesGrid');
            if (!grid) return;
            const card = document.createElement('div');
            card.id = `placeholder-job-${job.id}`;
            card.className = 'course-card glass-dark rounded-lg overflow-hidden ring-1 ring-yellow-500/40';
            card.innerHTML = `
                <div class="h-48 bg-gradient-to-br from-yellow-600 to-orange-600 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-4xl text-white opacity-80"></i>
                </div>
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-white mb-1">${job.courseTitle}</h3>
                    <p class="text-xs text-yellow-300 mb-3"><i class="fas fa-clock mr-1"></i>Procesando importación…</p>
                    <div class="flex space-x-2 opacity-60 pointer-events-none">
                        <a class="flex-1 bg-gray-700 text-white text-center py-2 px-3 rounded text-sm"><i class="fas fa-eye mr-1"></i>Ver</a>
                        <button class="bg-gray-700 text-white py-2 px-3 rounded text-sm"><i class="fas fa-edit"></i></button>
                        <button class="bg-gray-700 text-white py-2 px-3 rounded text-sm"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            grid.prepend(card);
        }

        function completeCoursePlaceholderCard(job) {
            const card = document.getElementById(`placeholder-job-${job.id}`);
            if (!card) return;
            // Actualizar visual
            const header = card.querySelector('.h-48');
            if (header) header.className = 'h-48 bg-gradient-to-br from-green-600 to-emerald-600 flex items-center justify-center';
            const icon = card.querySelector('.h-48 i');
            if (icon) icon.className = 'fas fa-check-circle text-4xl text-white opacity-90';
            const info = card.querySelector('p');
            if (info) info.className = 'text-xs text-green-300 mb-3';
            if (info) info.innerHTML = '<i class="fas fa-check mr-1"></i>Importación completada';
            
            // Activar acciones básicas si tenemos courseId
            if (job.courseId) {
                const actions = document.createElement('div');
                actions.className = 'flex space-x-2';
                actions.innerHTML = `
                    <a href="curso.php?id=${job.courseId}"
                       class="flex-1 bg-purple-600 hover:bg-purple-700 text-white text-center py-2 px-3 rounded text-sm transition-colors">
                        <i class="fas fa-eye mr-1"></i>Ver
                    </a>
                    <button onclick="editCourse(${job.courseId})" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded text-sm transition-colors">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteCourse(${job.courseId})" class="bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded text-sm transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                const old = card.querySelector('.opacity-60');
                if (old) old.replaceWith(actions);
            }
        }

        // Crear card de trabajo
        function createJobCard(job) {
            const progress = job.getProgressPercentage();
            const statusIcon = window.ImportQueueUI.getStatusIcon(job.status);
            const statusText = window.ImportQueueUI.getStatusText(job.status);
            const duration = window.ImportQueueUI.formatTime(job.getDuration());
            
            return `
                <div class="job-card bg-black/20 border border-white/10 rounded-lg p-4" data-job-id="${job.id}" draggable="true">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="${statusIcon}"></i>
                                <h4 class="text-white font-medium truncate">${job.courseTitle}</h4>
                                <span class="text-xs text-gray-400">${statusText}</span>
                            </div>
                            <div class="text-sm text-gray-300">
                                ${job.courseMode === 'new' ? 'Curso nuevo' : 'Curso existente'} • 
                                ${job.sections.size} sección(es) • 
                                ${job.getTotalFiles()} archivo(s)
                            </div>
                            ${job.progress.current ? `<div class="text-xs text-gray-400 mt-1">${job.progress.current}</div>` : ''}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400">${duration}</span>
                            <button onclick="showAdvancedJobMenu('${job.id}', event)" class="text-white/60 hover:text-white">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Barra de progreso -->
                    <div class="mb-2">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span class="job-progress-text">${job.progress.completed}/${job.progress.total} archivos</span>
                            <span>${progress}%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="job-progress-bar h-2 rounded-full transition-all duration-300" style="width: ${progress}%; background-color: ${getBarColor(job.status)}"></div>
                        </div>
                        ${job.progress.current ? `<div class="text-xs text-gray-400 mt-1 job-progress-msg">${job.progress.current}</div>` : ''}
                    </div>
                    
                    <!-- Errores (si los hay) -->
                    ${job.errors.length > 0 ? `
                        <div class="mt-2 p-2 bg-red-500/10 border border-red-500/20 rounded text-xs">
                            <i class="fas fa-exclamation-triangle text-red-400 mr-1"></i>
                            ${job.errors.length} error(es) - <button onclick="showJobErrors('${job.id}')" class="text-red-300 underline">Ver detalles</button>
                        </div>
                    ` : ''}
                    
                    <!-- Secciones expandibles -->
                    <div class="mt-3">
                        <button onclick="toggleJobSections('${job.id}')" class="text-xs text-gray-400 hover:text-gray-200">
                            <i class="fas fa-chevron-down mr-1"></i>Ver secciones y archivos
                        </button>
                        <div id="job-sections-${job.id}" class="hidden mt-2 space-y-2 text-xs">
                            ${Array.from(job.sections.entries()).map(([sectionName, files]) => {
                                const sectionCancelled = job.cancelledSections.has(sectionName);
                                return `
                                    <div class="bg-black/30 rounded p-2 border border-white/5 ${sectionCancelled ? 'opacity-50' : ''}">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-gray-200 font-medium">${sectionName}</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-400">${files.length} archivo(s)</span>
                                                ${sectionCancelled ? 
                                                    '<span class="text-red-400 text-xs"><i class="fas fa-ban mr-1"></i>Cancelada</span>' :
                                                    `<button onclick="cancelJobSection('${job.id}', '${sectionName}')" class="text-red-400 hover:text-red-300" title="Cancelar sección">
                                                        <i class="fas fa-times"></i>
                                                    </button>`
                                                }
                                            </div>
                                        </div>
                                        
                                        <!-- Lista de archivos -->
                                        <div class="space-y-1 ml-2">
                                            ${files.map(file => {
                                                const fileKey = sectionName + ':' + file.name;
                                                const fileCancelled = job.cancelledFiles.has(fileKey);
                                                return `
                                                    <div class="flex items-center justify-between py-1 px-2 bg-black/20 rounded ${fileCancelled ? 'opacity-50' : ''}">
                                                        <div class="flex items-center gap-2">
                                                            <i class="fas fa-video text-blue-400"></i>
                                                            <span class="text-gray-300 truncate max-w-[200px]">${file.name}</span>
                                                            <span class="text-gray-500 text-xs">${window.ImportQueueUI.formatFileSize(file.size)}</span>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            ${fileCancelled ? 
                                                                '<span class="text-red-400 text-xs"><i class="fas fa-ban"></i></span>' :
                                                                sectionCancelled ? '' :
                                                                `<button onclick="cancelJobFile('${job.id}', '${sectionName}', '${file.name}')" class="text-red-400 hover:text-red-300 text-xs" title="Cancelar archivo">
                                                                    <i class="fas fa-times"></i>
                                                                </button>`
                                                            }
                                                        </div>
                                                    </div>
                                                `;
                                            }).join('')}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        // Funciones de control de cola
        function openQueueProgressModal() {
            if (!queueUI.initialized) initQueueUI();
            queueUI.modal.classList.remove('hidden');
        }

        function closeQueueProgressModal() {
            queueUI.modal.classList.add('hidden');
        }

        function toggleQueuePause() {
            if (window.importQueue.isPaused) {
                window.importQueue.resumeQueue();
            } else {
                window.importQueue.pauseQueue();
            }
        }

        function cancelAllJobs() {
            if (confirm('¿Cancelar todas las importaciones en cola? Los archivos ya subidos se mantendrán.')) {
                window.importQueue.cancelAll();
            }
        }

        function clearCompletedJobs() {
            const stats = window.importQueue.getStats();
            const toClean = stats.completed + stats.cancelled + stats.error;
            
            if (toClean === 0) {
                alert('No hay trabajos completados para limpiar.');
                return;
            }
            
            if (confirm(`¿Limpiar ${toClean} trabajo(s) completado(s)/cancelado(s) de la lista?`)) {
                window.importQueue.clearCompleted();
                showNotification(`${toClean} trabajo(s) limpiado(s) de la cola`, 'info');
            }
        }

        // Funciones adicionales de utilidad
        function pauseJob(jobId) {
            // Para futuras mejoras: pausar trabajos individuales
            const job = window.importQueue.queue.get(jobId);
            if (job && job.status === 'processing') {
                job.setStatus('paused', 'Pausado por el usuario');
                window.importQueue.saveToStorage();
                showNotification(`Trabajo "${job.courseTitle}" pausado`, 'info');
            }
        }

        function resumeJob(jobId) {
            // Para futuras mejoras: reanudar trabajos individuales
            const job = window.importQueue.queue.get(jobId);
            if (job && job.status === 'paused') {
                job.setStatus('pending', 'Reanudado');
                window.importQueue.saveToStorage();
                showNotification(`Trabajo "${job.courseTitle}" reanudado`, 'info');
                
                // Reiniciar procesamiento si no está activo
                if (!window.importQueue.isProcessing) {
                    window.importQueue.processQueue();
                }
            }
        }

        // Mejorar menú contextual con más opciones
        function showAdvancedJobMenu(jobId, event) {
            event.stopPropagation();
            const job = window.importQueue.queue.get(jobId);
            if (!job) return;
            
            const isActive = job.status === 'pending' || job.status === 'processing';
            const hasErrors = job.errors.length > 0;
            
            const menu = document.createElement('div');
            menu.className = 'fixed bg-gray-800 border border-gray-600 rounded-lg shadow-lg p-2 z-50 text-sm';
            menu.style.left = event.clientX + 'px';
            menu.style.top = event.clientY + 'px';
            
            menu.innerHTML = `
                <div class="space-y-1">
                    ${isActive ? `
                        <button onclick="cancelJobAndCloseMenu('${jobId}')" class="w-full text-left px-3 py-2 hover:bg-gray-700 text-red-300 rounded">
                            <i class="fas fa-times mr-2"></i>Cancelar trabajo
                        </button>
                    ` : ''}
                    
                    <button onclick="toggleJobSections('${jobId}'); closeContextMenu()" class="w-full text-left px-3 py-2 hover:bg-gray-700 text-blue-300 rounded">
                        <i class="fas fa-list mr-2"></i>Ver detalles
                    </button>
                    
                    ${hasErrors ? `
                        <button onclick="showJobErrors('${jobId}'); closeContextMenu()" class="w-full text-left px-3 py-2 hover:bg-gray-700 text-red-300 rounded">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Ver errores
                        </button>
                    ` : ''}
                    
                    <hr class="border-gray-600 my-1">
                    
                    <button onclick="copyJobInfo('${jobId}'); closeContextMenu()" class="w-full text-left px-3 py-2 hover:bg-gray-700 text-gray-300 rounded">
                        <i class="fas fa-copy mr-2"></i>Copiar info
                    </button>
                </div>
            `;
            
            document.body.appendChild(menu);
            
            // Cerrar menú al hacer clic fuera
            const closeMenu = (e) => {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            };
            setTimeout(() => document.addEventListener('click', closeMenu), 10);
        }

        function cancelJobAndCloseMenu(jobId) {
            const job = window.importQueue.queue.get(jobId);
            if (job && confirm(`¿Cancelar importación de "${job.courseTitle}"?`)) {
                window.importQueue.cancelJob(jobId);
                showNotification(`Trabajo cancelado: ${job.courseTitle}`, 'warning');
            }
            closeContextMenu();
        }

        function closeContextMenu() {
            const menu = document.querySelector('.fixed.bg-gray-800');
            if (menu) menu.remove();
        }

        function copyJobInfo(jobId) {
            const job = window.importQueue.queue.get(jobId);
            if (!job) return;
            
            const info = `Trabajo de Importación:
Título: ${job.courseTitle}
Estado: ${window.ImportQueueUI.getStatusText(job.status)}
Progreso: ${job.getProgressPercentage()}% (${job.progress.completed}/${job.progress.total} archivos)
Secciones: ${job.sections.size}
Errores: ${job.errors.length}
Creado: ${new Date(job.createdAt).toLocaleString()}
${job.startedAt ? 'Iniciado: ' + new Date(job.startedAt).toLocaleString() : ''}
${job.completedAt ? 'Completado: ' + new Date(job.completedAt).toLocaleString() : ''}`;
            
            navigator.clipboard.writeText(info).then(() => {
                showNotification('Información copiada al portapapeles', 'info');
            }).catch(() => {
                alert('Información del trabajo:\n\n' + info);
            });
        }

        function updateGlobalProgress(queue) {
            const allJobs = Array.from(queue.queue.values());
            const activeJobs = allJobs.filter(job => job.status === 'processing' || job.status === 'pending');
            
            if (activeJobs.length === 0) {
                document.getElementById('queueGlobalProgress').textContent = 'Cola vacía';
                document.getElementById('queueGlobalProgressBar').style.width = '0%';
                return;
            }
            
            const totalFiles = activeJobs.reduce((sum, job) => sum + job.getTotalFiles(), 0);
            const completedFiles = activeJobs.reduce((sum, job) => sum + job.progress.completed, 0);
            const percentage = totalFiles > 0 ? Math.round((completedFiles / totalFiles) * 100) : 0;
            
            document.getElementById('queueGlobalProgress').textContent = 
                `${completedFiles}/${totalFiles} archivos (${activeJobs.length} curso(s))`;
            document.getElementById('queueGlobalProgressBar').style.width = percentage + '%';
        }

        // Funciones de menú contextual
        function showJobContextMenu(jobId, event) {
            event.stopPropagation();
            // Implementar menú contextual con opciones de cancelar, pausar, ver detalles, etc.
            // Por ahora, mostrar un alert con opciones básicas
            const job = window.importQueue.queue.get(jobId);
            if (!job) return;
            
            const options = [
                'Cancelar trabajo',
                'Ver errores',
                'Ver secciones'
            ];
            
            const choice = prompt('Opciones del trabajo:\\n' + options.map((opt, i) => `${i+1}. ${opt}`).join('\\n') + '\\n\\nElige una opción (1-3):');
            
            switch (choice) {
                case '1':
                    if (confirm(`¿Cancelar importación de "${job.courseTitle}"?`)) {
                        window.importQueue.cancelJob(jobId);
                    }
                    break;
                case '2':
                    showJobErrors(jobId);
                    break;
                case '3':
                    toggleJobSections(jobId);
                    break;
            }
        }

        function toggleJobSections(jobId) {
            const sectionsDiv = document.getElementById(`job-sections-${jobId}`);
            if (sectionsDiv) {
                sectionsDiv.classList.toggle('hidden');
                const btn = sectionsDiv.previousElementSibling;
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = sectionsDiv.classList.contains('hidden') ? 
                        'fas fa-chevron-down mr-1' : 'fas fa-chevron-up mr-1';
                }
            }
        }

        function cancelJobSection(jobId, sectionName) {
            const job = window.importQueue.queue.get(jobId);
            if (!job) return;
            
            const files = job.sections.get(sectionName);
            const fileCount = files ? files.length : 0;
            
            if (confirm(`¿Cancelar sección "${sectionName}" con ${fileCount} archivo(s)?`)) {
                window.importQueue.cancelSection(jobId, sectionName);
                showNotification(`Sección "${sectionName}" cancelada`, 'warning');
            }
        }

        function cancelJobFile(jobId, sectionName, fileName) {
            if (confirm(`¿Cancelar archivo "${fileName}"?`)) {
                window.importQueue.cancelFile(jobId, sectionName, fileName);
                showNotification(`Archivo "${fileName}" cancelado`, 'warning');
            }
        }

        function showJobErrors(jobId) {
            const job = window.importQueue.queue.get(jobId);
            if (!job || job.errors.length === 0) {
                alert('No hay errores para este trabajo.');
                return;
            }
            
            showErrorReport(job);
        }

        // Funciones de notificaciones e informes
        function showErrorReport(specificJob = null) {
            const errorReport = specificJob ? 
                { totalJobs: 1, totalErrors: specificJob.errors.length, jobs: [specificJob] } :
                window.importQueue.getErrorReport();
            
            const modal = document.getElementById('errorReportModal');
            const content = document.getElementById('errorReportContent');
            
            if (errorReport.totalErrors === 0) {
                content.innerHTML = `
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-check-circle text-green-400 text-4xl mb-2"></i>
                        <p>¡No hay errores! Todas las importaciones se completaron exitosamente.</p>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="mb-4 p-3 bg-red-500/10 border border-red-500/20 rounded">
                        <h4 class="text-red-200 font-medium mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Resumen de Errores
                        </h4>
                        <div class="text-sm text-gray-300">
                            <p>📊 Total de trabajos con errores: <span class="text-red-300 font-medium">${errorReport.totalJobs}</span></p>
                            <p>🚨 Total de errores: <span class="text-red-300 font-medium">${errorReport.totalErrors}</span></p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        ${errorReport.jobs.map(job => `
                            <div class="bg-black/20 border border-red-500/20 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="text-white font-medium">${job.courseTitle}</h5>
                                    <span class="text-xs text-gray-400">
                                        ${new Date(job.createdAt).toLocaleString()}
                                    </span>
                                </div>
                                
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    ${job.errors.map(error => `
                                        <div class="bg-red-500/10 border border-red-500/20 rounded p-2 text-sm">
                                            <div class="flex items-start gap-2">
                                                <i class="fas fa-exclamation-circle text-red-400 mt-0.5"></i>
                                                <div class="flex-1">
                                                    <div class="text-red-200 font-medium">${error.section} / ${error.file}</div>
                                                    <div class="text-gray-300 text-xs mt-1">${error.error}</div>
                                                    <div class="text-gray-400 text-xs mt-1">
                                                        ${new Date(error.timestamp).toLocaleTimeString()}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            modal.classList.remove('hidden');
        }

        function closeErrorReportModal() {
            document.getElementById('errorReportModal').classList.add('hidden');
        }

        function exportErrorReport() {
            const report = window.importQueue.getErrorReport();
            const content = {
                timestamp: new Date().toISOString(),
                summary: {
                    totalJobsWithErrors: report.totalJobs,
                    totalErrors: report.totalErrors
                },
                jobs: report.jobs.map(job => ({
                    courseTitle: job.courseTitle,
                    status: job.status,
                    createdAt: new Date(job.createdAt).toISOString(),
                    completedAt: job.completedAt ? new Date(job.completedAt).toISOString() : null,
                    errors: job.errors.map(error => ({
                        section: error.section,
                        file: error.file,
                        error: error.error,
                        timestamp: new Date(error.timestamp).toISOString()
                    }))
                }))
            };
            
            const blob = new Blob([JSON.stringify(content, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `informe-errores-${new Date().toISOString().slice(0, 10)}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }

        function setupJobContextMenus() {
            // Drag and drop para reordenar trabajos
            let draggedJob = null;
            
            queueUI.jobsList.querySelectorAll('.job-card').forEach(card => {
                card.addEventListener('dragstart', (e) => {
                    draggedJob = card;
                    card.classList.add('opacity-50');
                });
                
                card.addEventListener('dragend', (e) => {
                    card.classList.remove('opacity-50');
                    draggedJob = null;
                });
                
                card.addEventListener('dragover', (e) => {
                    e.preventDefault();
                });
                
                card.addEventListener('drop', (e) => {
                    e.preventDefault();
                    if (draggedJob && draggedJob !== card) {
                        const container = card.parentNode;
                        const afterElement = e.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2;
                        if (afterElement) {
                            container.insertBefore(draggedJob, card);
                        } else {
                            container.insertBefore(draggedJob, card.nextSibling);
                        }
                        // Aquí podrías reordenar la cola real si es necesario
                    }
                });
            });
        }

        // Inicializar UI de cola cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initQueueUI, 100); // Delay para asegurar que importQueue esté disponible
        });

        // Botón limpieza
        const cleanupBtn = document.getElementById('cleanupBtn');
        if (cleanupBtn) cleanupBtn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar carpetas de videos/recursos que no correspondan a ningún curso?')) return;
            try {
                const res = await fetch('api/storage-cleanup.php', { method: 'POST' });
                const json = await res.json();
                alert((json.success ? 'Limpieza realizada' : 'Error en limpieza') + (json.details ? ('\n' + json.details) : ''));
            } catch (e) { alert('Error en limpieza: ' + e.message); }
        });
    </script>
</body>
</html>
