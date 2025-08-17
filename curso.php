<?php
require_once 'config/database.php';
require_once 'config/config.php';

$db = getDatabase();

// Obtener ID del curso
$cursoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cursoId) {
    header('Location: index.php');
    exit;
}

// Obtener información del curso
$query = "
    SELECT c.*, 
           i.nombre as instructor_nombre,
           t.nombre as tematica_nombre
    FROM cursos c
    LEFT JOIN instructores i ON c.instructor_id = i.id
    LEFT JOIN tematicas t ON c.tematica_id = t.id
    WHERE c.id = ?
";
$stmt = $db->prepare($query);
$stmt->execute([$cursoId]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: index.php');
    exit;
}

// Obtener secciones y clases del curso
$query = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM clases WHERE seccion_id = s.id) as total_clases,
           (SELECT SUM(duracion) FROM clases WHERE seccion_id = s.id) as duracion_total
    FROM secciones s
    WHERE s.curso_id = ?
    ORDER BY s.orden, s.id
";
$secciones = $db->prepare($query);
$secciones->execute([$cursoId]);
$secciones = $secciones->fetchAll();

// Obtener todas las clases organizadas por sección
$clasesPorSeccion = [];
foreach ($secciones as $seccion) {
    $query = "SELECT * FROM clases WHERE seccion_id = ? ORDER BY orden, id";
    $stmt = $db->prepare($query);
    $stmt->execute([$seccion['id']]);
    $clasesPorSeccion[$seccion['id']] = $stmt->fetchAll();
}

// Prefetch de recursos por clase para pintar en la lista
$allClassIds = [];
foreach ($clasesPorSeccion as $lista) {
    foreach ($lista as $c) { $allClassIds[] = (int)$c['id']; }
}
$recursosPorClase = [];
if (!empty($allClassIds)) {
    $in = implode(',', array_fill(0, count($allClassIds), '?'));
    $stmt = $db->prepare("SELECT * FROM recursos_clases WHERE clase_id IN ($in) ORDER BY created_at DESC");
    $stmt->execute($allClassIds);
    $allRec = $stmt->fetchAll();
    foreach ($allRec as $r) {
        $cid = (int)$r['clase_id'];
        if (!isset($recursosPorClase[$cid])) $recursosPorClase[$cid] = [];
        $recursosPorClase[$cid][] = $r;
    }
}

// Helper para tamaños legibles
function humanFileSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units)-1);
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Obtener instructores y temáticas para edición
$instructores = $db->query("SELECT * FROM instructores ORDER BY nombre")->fetchAll();
$tematicas = $db->query("SELECT * FROM tematicas ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CursosMy - <?php echo htmlspecialchars($curso['titulo']); ?></title>
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
        .drag-over {
            border-color: #8b5cf6 !important;
            background-color: rgba(139, 92, 246, 0.1) !important;
        }
        /* Scrollbar sutil para cajas con overflow */
        .custom-scroll { scrollbar-width: thin; scrollbar-color: #8b5cf6 #1f2937; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #1f2937; border-radius: 3px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #8b5cf6; border-radius: 3px; }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <!-- Navegación unificada -->
    <nav class="m-4">
        <div class="glass-dark rounded-xl px-4 py-3 md:px-6 md:py-4 flex items-center justify-between shadow-lg sticky top-4 z-40">
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
            <div class="flex items-center gap-2">
                <button onclick="openGlobalSearchModal()" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-purple-400/40 bg-black/20 text-purple-200 hover:bg-black/30 text-xs sm:text-sm">
                    <i class="fas fa-search"></i><span class="hidden sm:inline">Buscador Global</span>
                </button>
                <button onclick="openEditCourseModal()" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-blue-400/40 bg-black/20 text-blue-200 hover:bg-black/30 text-xs sm:text-sm">
                    <i class="fas fa-edit"></i><span class="hidden sm:inline">Editar Curso</span>
                </button>
                <a href="index.php" class="inline-flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-gray-400/40 bg-black/20 text-gray-200 hover:bg-black/30 text-xs sm:text-sm">
                    <i class="fas fa-arrow-left"></i><span class="hidden sm:inline">Volver</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Información del curso -->
    <div class="glass-dark rounded-lg mx-4 mb-6 p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <h2 class="text-3xl font-bold text-white mb-4"><?php echo htmlspecialchars($curso['titulo']); ?></h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <?php if ($curso['instructor_nombre']): ?>
                        <p class="text-gray-300">
                            <i class="fas fa-user-tie mr-2 text-blue-400"></i>
                            <strong>Instructor:</strong> <?php echo htmlspecialchars($curso['instructor_nombre']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($curso['tematica_nombre']): ?>
                        <p class="text-gray-300">
                            <i class="fas fa-tag mr-2 text-green-400"></i>
                            <strong>Temática:</strong> <?php echo htmlspecialchars($curso['tematica_nombre']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <p class="text-gray-300">
                        <i class="fas fa-list mr-2 text-purple-400"></i>
                        <strong>Secciones:</strong> <?php echo count($secciones); ?>
                    </p>
                    
                    <p class="text-gray-300">
                        <i class="fas fa-video mr-2 text-red-400"></i>
                        <strong>Clases:</strong> <?php 
                            $totalClases = 0;
                            foreach ($secciones as $seccion) {
                                $totalClases += $seccion['total_clases'];
                            }
                            echo $totalClases;
                        ?>
                    </p>
                </div>
                
                <?php if ($curso['comentarios']): ?>
                    <div class="bg-black/20 rounded-lg p-4 border border-white/10">
                        <h4 class="text-white font-semibold mb-2">Comentarios:</h4>
                        <div style="max-height: 28.0rem; overflow-y: auto;" class="pr-2 custom-scroll">
                            <div class="text-gray-300 whitespace-pre-line break-words"><?php echo htmlspecialchars($curso['comentarios']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="flex flex-col space-y-4">
                <?php if ($curso['imagen']): ?>
                    <img src="uploads/images/<?php echo htmlspecialchars($curso['imagen']); ?>" 
                         alt="<?php echo htmlspecialchars($curso['titulo']); ?>" 
                         class="w-full h-48 object-cover rounded-lg">
                <?php else: ?>
                    <div class="w-full h-48 bg-gradient-to-br from-purple-600 to-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-play-circle text-6xl text-white opacity-50"></i>
                    </div>
                <?php endif; ?>
                
                <div class="bg-black/20 rounded-lg p-4 border border-white/10">
                    <h4 class="text-white font-semibold mb-2">Progreso</h4>
                    <div id="courseProgress" class="text-gray-300 text-sm pr-2" style="max-height:10vh; overflow-y:auto; scrollbar-width: thin; -webkit-overflow-scrolling: touch;">Cargando...</div>
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <button id="resumeLastBtn" class="bg-green-600 hover:bg-green-700 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm disabled:opacity-50" title="Reanudar" disabled>
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="resetSectionBtn" class="hidden bg-yellow-600 hover:bg-yellow-700 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Reiniciar sección"></button>
                        <button id="resetCourseBtn" class="bg-red-600 hover:bg-red-700 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Reiniciar curso">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                        <button id="syncDurationsBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Sincronizar duraciones">
                            <i class="fas fa-arrows-rotate"></i>
                        </button>
                        <button id="syncDurationsForceBtn" class="hidden bg-purple-600 hover:bg-purple-700 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Forzar sincronización"></button>
                    </div>
                    
                    <!-- Opción de autoplay -->
                    <div class="flex items-center gap-2 mt-3 p-2 bg-gray-800/50 rounded">
                        <input type="checkbox" id="autoplayToggle" class="w-4 h-4 text-green-600 bg-gray-700 border-gray-600 rounded focus:ring-green-500 focus:ring-2" checked>
                        <label for="autoplayToggle" class="text-gray-300 text-sm cursor-pointer">
                            <i class="fas fa-play-circle mr-1"></i>
                            Reproducir automáticamente al abrir video
                        </label>
                    </div>
                </div>
                
                <button onclick="openUploadModal()" class="bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg transition-colors">
                    <i class="fas fa-upload mr-2"></i>Subir Videos
                </button>
                
                <button onclick="openSectionModal()" class="bg-purple-600 hover:bg-purple-700 text-white py-3 px-4 rounded-lg transition-colors">
                    <i class="fas fa-plus mr-2"></i>Nueva Sección
                </button>
            </div>
        </div>
    </div>

    <!-- Contenido del curso -->
    <div class="mx-4 mb-8">
        <?php if (empty($secciones)): ?>
            <div class="glass-dark rounded-lg p-8 text-center">
                <i class="fas fa-folder-open text-6xl text-gray-500 mb-4"></i>
                <h3 class="text-xl font-semibold text-white mb-2">No hay secciones creadas</h3>
                <p class="text-gray-400 mb-4">Comienza creando una sección para organizar las clases de tu curso.</p>
                <button onclick="openSectionModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg transition-colors">
                    <i class="fas fa-plus mr-2"></i>Crear Primera Sección
                </button>
            </div>
        <?php else: ?>
            <!-- Controles superiores (siempre visibles) -->
            <div class="flex justify-end mb-3 gap-2">
                <button id="expandAllBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">Expandir todas</button>
                <button id="collapseAllBtn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">Contraer todas</button>
                <button id="toggleSortBtn" class="bg-black/40 hover:bg-black/50 text-white px-3 py-1 rounded text-sm" title="Bloquear/Desbloquear orden">
                    <i class="fas fa-lock"></i>
                    <span class="ml-1">Orden bloqueado</span>
                </button>
                <!-- Botón de búsqueda duplicado ocultado por petición -->
            </div>
            
            <!-- Lista de secciones con scroll -->
            <div class="sections-container max-h-[70vh] overflow-y-auto pr-2" style="scrollbar-width: thin;">
            <?php foreach ($secciones as $seccion): ?>
                <div class="glass-dark rounded-lg mb-6 overflow-hidden" data-section-id="<?php echo $seccion['id']; ?>">
                    <!-- Header de la sección -->
                    <div class="bg-black/30 p-4 border-b border-white/10">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <button class="toggleSection bg-black/40 hover:bg-black/50 text-white px-2 py-1 rounded text-sm" data-target="#sec-<?php echo $seccion['id']; ?>">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <h3 class="text-xl font-semibold text-white"><?php echo htmlspecialchars($seccion['nombre']); ?></h3>
                                <span class="text-gray-400 text-sm">(<?php echo $seccion['total_clases']; ?> clases<?php if ($seccion['duracion_total']): ?> • <?php echo gmdate("H:i:s", $seccion['duracion_total']); ?> total<?php endif; ?>)</span>
                                <span class="ml-2 text-purple-300 text-sm" id="secProg-<?php echo $seccion['id']; ?>">0%</span>
                            </div>
                            <div class="flex space-x-2">
                                <button class="resetSection bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded transition-colors" data-section-id="<?php echo $seccion['id']; ?>" title="Reiniciar progreso de la sección">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button onclick="editSection(<?php echo $seccion['id']; ?>)" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded transition-colors">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteSection(<?php echo $seccion['id']; ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-colors">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Clases de la sección -->
                    <div class="p-4" id="sec-<?php echo $seccion['id']; ?>">
                        <?php if (empty($clasesPorSeccion[$seccion['id']])): ?>
                            <p class="text-gray-400 text-center py-4">No hay clases en esta sección</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($clasesPorSeccion[$seccion['id']] as $clase): ?>
                                    <div class="class-item bg-black/20 rounded-lg p-4 border border-white/10" data-class-id="<?php echo $clase['id']; ?>" data-section-id="<?php echo $seccion['id']; ?>" data-duration="<?php echo (int)$clase['duracion']; ?>" data-video-src="uploads/videos/<?php echo $curso['id']; ?>/<?php echo htmlspecialchars($clase['archivo_video']); ?>">
                                            <div class="flex items-start justify-between gap-4">
                                            <div class="drag-handle hidden bg-purple-600 rounded-lg p-3 cursor-move" title="Arrastrar para ordenar">
                                                <i class="fas fa-play text-white"></i>
                                            </div>
                                                <div class="flex-1 min-w-0">
                                                <h4 class="text-white font-medium"><?php echo htmlspecialchars($clase['titulo']); ?></h4>
                                                <p class="text-gray-400 text-sm">
                                                    <span class="ml-0">
                                                        <i class="fas fa-file-video mr-1"></i><?php echo htmlspecialchars($clase['archivo_video']); ?>
                                                    </span>
                                                    <span class="ml-3 text-purple-300">
                                                        <i class="fas fa-history mr-1"></i>
                                                        <span id="clsSeen-<?php echo $clase['id']; ?>">00:00:00</span>
                                                        <span class="mx-1 text-white/50">/</span>
                                                        <span id="clsTotal-<?php echo $clase['id']; ?>"><?php echo $clase['duracion'] ? gmdate('H:i:s', $clase['duracion']) : '00:00:00'; ?></span>
                                                    </span>
                                                    <span class="ml-3 text-green-300 font-medium" id="clsProg-<?php echo $clase['id']; ?>">0%</span>
                                                </p>
                                                    <?php $recList = $recursosPorClase[$clase['id']] ?? []; if (!empty($recList)): ?>
                                                         <div id="resBox-<?php echo $clase['id']; ?>" class="mt-2 text-xs bg-black/30 border border-white/10 rounded p-2">
                                                            <div class="text-gray-300 mb-1"><i class="fas fa-paperclip mr-1"></i>Recursos (<span id="resCount-<?php echo $clase['id']; ?>"><?php echo count($recList); ?></span>):</div>
                                                            <div class="flex flex-wrap gap-2">
                                                                <?php foreach ($recList as $rec): ?>
                                                                     <span class="res-chip inline-flex items-center gap-2 px-2 py-1 rounded bg-black/40 border border-white/10 text-gray-200" data-resource-id="<?php echo (int)$rec['id']; ?>">
                                                                        <a href="uploads/resources/<?php echo $curso['id']; ?>/<?php echo htmlspecialchars($rec['archivo_path']); ?>" download
                                                                           class="inline-flex items-center gap-1 hover:text-white hover:underline"
                                                                           title="<?php echo htmlspecialchars($rec['nombre_archivo']); ?> (<?php echo humanFileSize($rec['tamano_bytes']); ?>)">
                                                                            <i class="fas fa-file"></i>
                                                                            <span class="max-w-[200px] truncate"><?php echo htmlspecialchars($rec['nombre_archivo']); ?></span>
                                                                        </a>
                                                                        <button class="text-red-400 hover:text-red-300" title="Eliminar recurso" onclick="deleteResource(<?php echo (int)$rec['id']; ?>, <?php echo (int)$clase['id']; ?>)"><i class="fas fa-times"></i></button>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                            </div>
                                        </div>
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <a href="reproductor.php?clase=<?php echo $clase['id']; ?>" 
                                                   class="video-link bg-green-600 hover:bg-green-700 text-white w-8 h-8 flex items-center justify-center rounded transition-colors" 
                                                   title="Ver" data-clase-id="<?php echo $clase['id']; ?>">
                                                <i class="fas fa-play"></i>
                                            </a>
                                                <button onclick="openInlineEditClass(<?php echo $clase['id']; ?>, '<?php echo htmlspecialchars($clase['titulo'], ENT_QUOTES); ?>')" 
                                                        class="bg-blue-600 hover:bg-blue-700 text-white w-8 h-8 flex items-center justify-center rounded transition-colors" title="Renombrar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                                <button onclick="openResourceModal(<?php echo $clase['id']; ?>)" 
                                                        class="bg-yellow-600 hover:bg-yellow-700 text-white w-8 h-8 flex items-center justify-center rounded transition-colors" title="Adjuntar recursos">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <button onclick="deleteAllResources(<?php echo $clase['id']; ?>)" 
                                                        class="bg-purple-600 hover:bg-purple-700 text-white w-8 h-8 flex items-center justify-center rounded transition-colors" title="Eliminar todos los adjuntos de esta clase">
                                                    <i class="fas fa-broom"></i>
                                                </button>
                                                <button onclick="resetClassProgress(<?php echo $clase['id']; ?>)" 
                                                        class="bg-yellow-700 hover:bg-yellow-800 text-white w-8 h-8 flex items-center justify-center rounded transition-colors" title="Reiniciar progreso de esta clase">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <button onclick="deleteClass(<?php echo $clase['id']; ?>)" 
                                                        class="bg-red-600 hover:bg-red-700 text-white w-8 h-8 flex items-center justify-center rounded transition-colors" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div> <!-- Fin del div sections-container con scroll -->
        <?php endif; ?>
    </div>

    <!-- Modal Editar Curso -->
    <div id="editCourseModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-edit mr-2"></i>Editar Curso
                </h2>
                
                <form id="editCourseForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $curso['id']; ?>">
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Título del Curso *</label>
                        <input type="text" name="titulo" value="<?php echo htmlspecialchars($curso['titulo']); ?>" required 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Temática</label>
                        <select name="tematica_id" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                            <option value="">Seleccionar temática</option>
                            <?php foreach ($tematicas as $tematica): ?>
                                <option value="<?php echo $tematica['id']; ?>" <?php echo $curso['tematica_id'] == $tematica['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tematica['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Instructor</label>
                        <select name="instructor_id" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                            <option value="">Seleccionar instructor</option>
                            <?php foreach ($instructores as $instructor): ?>
                                <option value="<?php echo $instructor['id']; ?>" <?php echo $curso['instructor_id'] == $instructor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($instructor['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Imagen del Curso</label>
                        <div class="space-y-2">
                            <?php if ($curso['imagen']): ?>
                                <img src="uploads/images/<?php echo htmlspecialchars($curso['imagen']); ?>" alt="<?php echo htmlspecialchars($curso['titulo']); ?>" class="w-full h-40 object-cover rounded-lg">
                                <label class="inline-flex items-center text-sm text-gray-300">
                                    <input type="checkbox" name="delete_image" value="1" class="mr-2">
                                    Eliminar imagen y usar la predeterminada
                                </label>
                            <?php endif; ?>
                            <input type="file" name="imagen" accept="image/*" class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Comentarios</label>
                        <textarea name="comentarios" rows="3" 
                                  class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none"><?php echo htmlspecialchars($curso['comentarios']); ?></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Actualizar
                        </button>
                        <button type="button" onclick="closeEditCourseModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Sección -->
    <div id="sectionModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-md">
                <h2 id="sectionModalTitle" class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-plus mr-2"></i>Nueva Sección
                </h2>
                
                <form id="sectionForm">
                    <input type="hidden" id="sectionId" name="id">
                    <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Nombre de la Sección *</label>
                        <input type="text" id="sectionNombre" name="nombre" required 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Orden</label>
                        <input type="number" id="sectionOrden" name="orden" min="1" value="1" 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-purple-500 focus:outline-none">
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar
                        </button>
                        <button type="button" onclick="closeSectionModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Subir Videos -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-2xl">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-upload mr-2"></i>Subir Videos
                </h2>
                
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="curso_id" value="<?php echo $curso['id']; ?>">
                    
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Sección de Destino *</label>
                        <select name="seccion_id" id="uploadSeccionSelect" required 
                                class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-purple-500 focus:outline-none">
                            <option value="">Seleccionar sección</option>
                            <?php foreach ($secciones as $seccion): ?>
                                <option value="<?php echo $seccion['id']; ?>"><?php echo htmlspecialchars($seccion['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Videos (MP4, máximo 500GB cada uno)</label>
                        <div id="dropZone" class="border-2 border-dashed border-white/20 rounded-lg p-8 text-center hover:border-purple-500 transition-colors cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                            <p class="text-white mb-2">Arrastra los videos aquí o haz clic para seleccionar</p>
                            <p class="text-gray-400 text-sm">Formatos soportados: MP4 • Tamaño máximo: 500GB por archivo</p>
                            <input type="file" id="videoFiles" name="videos[]" multiple accept=".mp4" class="hidden">
                        </div>
                        
                        <div id="fileList" class="mt-4 space-y-2"></div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" id="uploadButton" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition-colors" disabled>
                            <i class="fas fa-upload mr-2"></i>Subir Videos
                        </button>
                        <button type="button" onclick="closeUploadModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
                
                <div id="uploadProgress" class="hidden mt-4">
                    <div class="bg-black/20 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-white">Subiendo videos...</span>
                            <span id="progressText" class="text-white">0%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div id="progressBar" class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Recursos por Clase -->
    <div id="resourceModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header fijo -->
                <div class="p-6 pb-4 border-b border-white/10">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-file-arrow-up mr-2"></i>Añadir Recursos a la Clase
                    </h2>
                </div>
                
                <!-- Contenido con scroll -->
                <div class="flex-1 overflow-y-auto p-6 pt-4">
                    <form id="resourceForm" enctype="multipart/form-data">
                        <input type="hidden" name="clase_id" id="resourceClaseId" />
                        <div class="mb-4">
                            <label class="block text-white text-sm font-bold mb-2">Archivos</label>
                            <div class="border-2 border-dashed border-white/20 rounded-lg p-6 text-center hover:border-cyan-500 transition-colors cursor-pointer" onclick="document.getElementById('resourceFiles').click()">
                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                <p class="text-white mb-1">Arrastra los archivos aquí o haz clic para seleccionar</p>
                                <p class="text-gray-400 text-xs">Se acepta cualquier tipo de archivo • Sin límite de tamaño</p>
                            </div>
                            <input type="file" id="resourceFiles" multiple class="hidden" />
                            
                            <!-- Lista de archivos con scroll -->
                            <div id="resourceFileList" class="mt-3 max-h-64 overflow-y-auto space-y-1 text-gray-300 text-sm bg-black/20 rounded-lg p-3 border border-white/10"></div>
                        </div>
                        <div id="resourceProgress" class="hidden mb-4 text-sm text-white bg-black/30 rounded p-2"></div>
                    </form>
                </div>
                
                <!-- Footer fijo con botones -->
                <div class="p-6 pt-4 border-t border-white/10">
                    <div class="flex space-x-3">
                        <button type="submit" form="resourceForm" class="flex-1 bg-cyan-700 hover:bg-cyan-800 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-upload mr-2"></i>Subir
                        </button>
                        <button type="button" onclick="closeResourceModal()" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Búsqueda Global -->
    <div id="globalSearchModal" class="fixed inset-0 bg-black/60 hidden z-50">
        <div class="flex items-start justify-center min-h-screen p-6">
            <div class="glass-dark rounded-lg p-6 w-full max-w-3xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-white"><i class="fas fa-search mr-2"></i>Buscar en el curso</h3>
                    <button class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded" onclick="closeGlobalSearchModal()"><i class="fas fa-times"></i></button>
                </div>
                <input id="globalSearchInput" type="text" placeholder="Escribe para buscar en títulos de clases, notas (por tiempo), comentarios y adjuntos..." class="w-full bg-black/20 border border-white/20 rounded px-3 py-2 text-white" />
                <div id="globalSearchResults" class="mt-4 max-h-[60vh] overflow-y-auto space-y-2"></div>
            </div>
        </div>
    </div>

    <script>
        const CURSO_ID = <?php echo $curso['id']; ?>;
        const SECCIONES = <?php echo json_encode(array_map(fn($s) => ['id'=>$s['id'], 'nombre'=>$s['nombre']], $secciones)); ?>;
    </script>
    <script src="js/curso.js"></script>
    <script>
        // Sincronización automática SOLO la primera vez que se abre el curso tras crearlo
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                const onceKey = `courseSyncedOnce:${CURSO_ID}`;
                if (!localStorage.getItem(onceKey)) {
                    console.log('[COURSE][AUTO_SYNC] primera vez, sincronizando duraciones...');
                    await fetch(`api/sync-durations.php?curso_id=${CURSO_ID}`);
                    try { await scanDurations({ force: false }); } catch (e) { console.warn('[COURSE][AUTO_SCAN] fallo', e); }
                    localStorage.setItem(onceKey, '1');
                }
            } catch (e) { console.warn('[COURSE][AUTO_SYNC] error', e); }
        });
        // Cargar y pintar progreso del curso y habilitar acciones
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                const res = await fetch(`api/progreso.php?curso_id=${CURSO_ID}`);
                const data = await res.json();
                const container = document.getElementById('courseProgress');
                if (data.success) {
                    // Construir mapa claseId->tiempo
                    const bySection = new Map();
                    (SECCIONES || []).forEach(s => bySection.set(s.id, { nombre: s.nombre, vistos: 0, total: 0 }));
                    // Contar clases por sección (desde el DOM)
                    document.querySelectorAll('[data-section-id]').forEach(el => {
                        const sid = parseInt(el.dataset.sectionId);
                        const info = bySection.get(sid) || { nombre: 'Sección', vistos: 0, total: 0 };
                        info.total += el.querySelectorAll('[data-class-id]').length;
                        bySection.set(sid, info);
                    });
                    // Encontrar el último video reproducido (con tiempo_visto > 0 y fecha más reciente)
                    let lastWatched = null;
                    (data.data || []).forEach(p => {
                        if (p && p.tiempo_visto > 0 && p.ultima_visualizacion) {
                            if (!lastWatched || new Date(p.ultima_visualizacion) > new Date(lastWatched.ultima_visualizacion)) {
                                lastWatched = p;
                            }
                        }
                    });
                    
                    // Render simple
                    let html = '';
                    bySection.forEach((info) => {
                        html += `<div class="mb-1 text-sm">${info.nombre}: <span class="text-purple-300">${info.vistos}/${info.total}</span></div>`;
                    });
                    container.innerHTML = html || 'Sin datos de progreso';
                    
                    const btn = document.getElementById('resumeLastBtn');
                    if (lastWatched && lastWatched.clase_id) {
                        btn.disabled = false;
                        btn.addEventListener('click', () => {
                            // Incluir parámetro &t= para reanudar desde donde se quedó
                            const timeParam = lastWatched.tiempo_visto > 0 ? `&t=${lastWatched.tiempo_visto}` : '';
                            
                            // Verificar si autoplay está activado
                            const autoplayToggle = document.getElementById('autoplayToggle');
                            const autoplayParam = (autoplayToggle && autoplayToggle.checked) ? '&autoplay=1' : '';
                            
                            window.location.href = `reproductor.php?clase=${lastWatched.clase_id}${timeParam}${autoplayParam}`;
                        });
                    }
                } else {
                    container.textContent = 'No se pudo cargar el progreso';
                }
            } catch (e) {
                document.getElementById('courseProgress').textContent = 'Error al cargar progreso';
            }

            // Reset por sección (oculto)
            const resetSectionBtn = document.getElementById('resetSectionBtn');
            if (resetSectionBtn) {
                resetSectionBtn.classList.add('hidden');
            }

            // Reset curso completo
            document.getElementById('resetCourseBtn').addEventListener('click', async () => {
                if (!confirm('¿Reiniciar progreso del curso completo?')) return;
                await fetch('api/progreso.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ curso_id: CURSO_ID }) });
                window.location.reload();
            });

            // Manejar autoplay
            setupAutoplayFeature();
        });

        // Función para configurar autoplay
        function setupAutoplayFeature() {
            const autoplayToggle = document.getElementById('autoplayToggle');
            const videoLinks = document.querySelectorAll('.video-link');
            
            // Cargar preferencia guardada
            const savedAutoplay = localStorage.getItem('autoplay-enabled');
            if (savedAutoplay !== null) {
                autoplayToggle.checked = savedAutoplay === 'true';
            }
            
            // Guardar preferencia cuando cambie
            autoplayToggle.addEventListener('change', () => {
                localStorage.setItem('autoplay-enabled', autoplayToggle.checked);
                updateVideoLinks();
            });
            
            // Actualizar enlaces cuando cambie la configuración
            function updateVideoLinks() {
                videoLinks.forEach(link => {
                    const claseId = link.dataset.claseId;
                    const baseUrl = `reproductor.php?clase=${claseId}`;
                    
                    if (autoplayToggle.checked) {
                        // Si autoplay está activado, agregar parámetro
                        if (!link.href.includes('autoplay=1')) {
                            const separator = link.href.includes('?') ? '&' : '?';
                            link.href = baseUrl + '&autoplay=1';
                        }
                    } else {
                        // Si autoplay está desactivado, quitar parámetro
                        link.href = baseUrl;
                    }
                });
            }
            
            // Actualizar enlaces inicialmente
            updateVideoLinks();
            
            // También actualizar el botón de reanudar
            const resumeBtn = document.getElementById('resumeLastBtn');
            if (resumeBtn) {
                resumeBtn.addEventListener('click', (e) => {
                    if (autoplayToggle.checked) {
                        // Interceptar click para agregar autoplay
                        e.preventDefault();
                        const originalHref = resumeBtn.getAttribute('data-original-href') || resumeBtn.parentElement.querySelector('a')?.href;
                        if (originalHref) {
                            const url = new URL(originalHref, window.location.origin);
                            url.searchParams.set('autoplay', '1');
                            window.location.href = url.toString();
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>
