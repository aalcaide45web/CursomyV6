<?php
require_once 'config/database.php';
require_once 'config/config.php';

$db = getDatabase();

// Obtener ID de la clase
$claseId = isset($_GET['clase']) ? (int)$_GET['clase'] : 0;
$editMode = isset($_GET['edit']) ? true : false;

if (!$claseId) {
    header('Location: index.php');
    exit;
}

// Obtener información de la clase
$query = "
    SELECT c.*, s.nombre as seccion_nombre, s.curso_id, s.id as seccion_id,
           cr.titulo as curso_titulo
    FROM clases c
    JOIN secciones s ON c.seccion_id = s.id
    JOIN cursos cr ON s.curso_id = cr.id
    WHERE c.id = ?
";
$stmt = $db->prepare($query);
$stmt->execute([$claseId]);
$clase = $stmt->fetch();

if (!$clase) {
    header('Location: index.php');
    exit;
}

// Obtener progreso de la clase
$stmt = $db->prepare("SELECT * FROM progreso_clases WHERE clase_id = ?");
$stmt->execute([$claseId]);
$progreso = $stmt->fetch();

// Obtener notas de la clase
$stmt = $db->prepare("SELECT * FROM notas_clases WHERE clase_id = ? ORDER BY tiempo_video");
$stmt->execute([$claseId]);
$notas = $stmt->fetchAll();

// Obtener comentarios de la clase
$stmt = $db->prepare("SELECT * FROM comentarios_clases WHERE clase_id = ? ORDER BY created_at DESC");
$stmt->execute([$claseId]);
$comentarios = $stmt->fetchAll();

// Obtener todas las clases de la sección para navegación
$stmt = $db->prepare("SELECT * FROM clases WHERE seccion_id = ? ORDER BY orden, id");
$stmt->execute([$clase['seccion_id']]);
$clasesSeccion = $stmt->fetchAll();

// Encontrar clase anterior y siguiente
$currentIndex = array_search($claseId, array_column($clasesSeccion, 'id'));
$claseAnterior = $currentIndex > 0 ? $clasesSeccion[$currentIndex - 1] : null;
$claseSiguiente = $currentIndex < count($clasesSeccion) - 1 ? $clasesSeccion[$currentIndex + 1] : null;

// Obtener secciones para el selector (si está en modo edición)
$secciones = [];
if ($editMode) {
    $stmt = $db->prepare("SELECT * FROM secciones WHERE curso_id = ? ORDER BY orden, id");
    $stmt->execute([$clase['curso_id']]);
    $secciones = $stmt->fetchAll();
}

$videoPath = 'uploads/videos/' . $clase['curso_id'] . '/' . $clase['archivo_video'];
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CursosMy - <?php echo htmlspecialchars($clase['titulo']); ?></title>
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
        .video-container {
            position: relative;
            background: #000;
        }
        .video-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 20px 15px 15px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .video-container:hover .video-controls {
            opacity: 1;
        }
        .progress-bar {
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .progress-filled {
            height: 100%;
            background: #8b5cf6;
            border-radius: 2px;
            transition: width 0.1s;
        }
        .note-marker {
            position: absolute;
            top: -2px;
            width: 8px;
            height: 8px;
            background: #fbbf24;
            border-radius: 50%;
            cursor: pointer;
            transform: translateX(-50%);
        }
        .note-marker:hover {
            transform: translateX(-50%) scale(1.2);
        }
        
        /* Estilos para controles adicionales */
        .slider {
            background: linear-gradient(to right, #8b5cf6 0%, #8b5cf6 100%, #4b5563 100%, #4b5563 100%);
        }
        
        .slider::-webkit-slider-thumb {
            appearance: none;
            height: 12px;
            width: 12px;
            border-radius: 50%;
            background: #8b5cf6;
            cursor: pointer;
            border: 2px solid #ffffff;
        }
        
        .slider::-moz-range-thumb {
            height: 12px;
            width: 12px;
            border-radius: 50%;
            background: #8b5cf6;
            cursor: pointer;
            border: 2px solid #ffffff;
        }
        
        /* Estilos mejorados para dropdowns */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }
        
        .control-button {
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
            min-width: 60px;
        }
        
        .control-button:hover {
            background: rgba(139, 92, 246, 0.3) !important;
            border-color: rgba(139, 92, 246, 0.5) !important;
        }
        
        .dropdown-menu {
            z-index: 99999 !important;
            backdrop-filter: blur(10px);
            animation: dropdownShow 0.2s ease-out;
            transform-origin: bottom center;
            max-height: 300px;
            overflow-y: auto;
            position: absolute !important;
            bottom: 100% !important;
            margin-bottom: 8px;
        }
        
        .dropdown-menu.hidden {
            animation: dropdownHide 0.15s ease-in;
        }
        
        @keyframes dropdownShow {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @keyframes dropdownHide {
            from {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateY(10px) scale(0.95);
            }
        }
        
        .dropdown-item {
            transition: all 0.15s ease;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .dropdown-item:hover {
            background: rgba(139, 92, 246, 0.6) !important;
            transform: translateX(2px);
        }
        
        .dropdown-item.bg-purple-600 {
            background: rgba(139, 92, 246, 0.8) !important;
        }
        
        .dropdown-item i {
            width: 16px;
            text-align: center;
        }
        
        /* Scrollbar personalizado para dropdowns */
        .dropdown-menu::-webkit-scrollbar {
            width: 4px;
        }
        
        .dropdown-menu::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 2px;
        }
        
        .dropdown-menu::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.6);
            border-radius: 2px;
        }
        
        .dropdown-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.8);
        }
        
        /* Posicionamiento específico para cada menú */
        #speedMenu {
            left: 0 !important;
            right: auto !important;
        }
        
        #qualityMenu {
            left: auto !important;
            right: 0 !important;
        }
        
        /* Ocultar controles nativos del video */
        video::-webkit-media-controls {
            display: none !important;
        }
        
        video::-webkit-media-controls-enclosure {
            display: none !important;
        }
        
        video::-webkit-media-controls-panel {
            display: none !important;
        }
        
        video::-moz-media-controls {
            display: none !important;
        }
        
        video::-ms-media-controls {
            display: none !important;
        }
        
        /* Asegurar que el video no tenga controles nativos */
        video {
            outline: none;
            cursor: pointer;
            transition: filter 0.2s ease;
        }
        
        video:hover {
            filter: brightness(1.1);
        }
        
        /* Indicador visual de click en video */
        .video-container {
            position: relative;
        }
        
        .video-click-indicator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 4rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            text-shadow: 0 0 20px rgba(0,0,0,0.8);
            z-index: 1000;
        }
        
        /* Fullscreen styles */
        .video-container:-webkit-full-screen {
            width: 100vw;
            height: 100vh;
            background: #000;
        }
        
        .video-container:-moz-full-screen {
            width: 100vw;
            height: 100vh;
            background: #000;
        }
        
        .video-container:fullscreen {
            width: 100vw;
            height: 100vh;
            background: #000;
        }
        
        .video-container:-webkit-full-screen video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .video-container:-moz-full-screen video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .video-container:fullscreen video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen">
    <!-- Navegación -->
    <nav class="glass-dark rounded-lg m-4 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold text-white">
                    <i class="fas fa-graduation-cap mr-2"></i>CursosMy
                </h1>
                <div class="text-sm text-gray-300">
                    <a href="index.php" class="hover:text-white"><?php echo htmlspecialchars($clase['curso_titulo']); ?></a>
                    <span class="mx-2">›</span>
                    <span><?php echo htmlspecialchars($clase['seccion_nombre']); ?></span>
                    <span class="mx-2">›</span>
                    <span class="text-white"><?php echo htmlspecialchars($clase['titulo']); ?></span>
                </div>
            </div>
            <div class="flex space-x-2">
                <?php if ($editMode): ?>
                    <button onclick="saveChanges()" class="bg-green-600 hover:bg-green-700 text-white px-2 sm:px-4 py-2 rounded-lg transition-colors text-xs sm:text-sm">
                        <i class="fas fa-save mr-2"></i><span class="hidden sm:inline">Guardar</span>
                    </button>
                    <a href="reproductor.php?clase=<?php echo $claseId; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-2 sm:px-4 py-2 rounded-lg transition-colors text-xs sm:text-sm">
                        <i class="fas fa-times mr-2"></i><span class="hidden sm:inline">Cancelar</span>
                    </a>
                <?php else: ?>
                    <a href="reproductor.php?clase=<?php echo $claseId; ?>&edit=1" class="bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-4 py-2 rounded-lg transition-colors text-xs sm:text-sm">
                        <i class="fas fa-edit mr-2"></i><span class="hidden sm:inline">Editar</span>
                    </a>
                <?php endif; ?>
                <button onclick="openGlobalSearchModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-2 sm:px-4 py-2 rounded-lg transition-colors text-xs sm:text-sm">
                    <i class="fas fa-search mr-2"></i><span class="hidden sm:inline">Buscador Global</span>
                </button>
                <a href="curso.php?id=<?php echo $clase['curso_id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-2 sm:px-4 py-2 rounded-lg transition-colors text-xs sm:text-sm">
                    <i class="fas fa-arrow-left mr-2"></i><span class="hidden sm:inline">Volver al Curso</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="mx-4 mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Reproductor de video -->
            <div class="lg:col-span-3">
                <div class="glass-dark rounded-lg overflow-hidden">
                    <!-- Información de la clase -->
                    <div class="p-4 border-b border-white/10">
                        <?php if ($editMode): ?>
                            <input type="text" id="editTitulo" value="<?php echo htmlspecialchars($clase['titulo']); ?>" 
                                   class="text-xl font-semibold bg-transparent text-white border-b border-white/20 focus:border-purple-500 focus:outline-none w-full">
                            <div class="flex space-x-4 mt-2">
                                <select id="editSeccion" class="bg-gray-900 border border-gray-700 rounded px-3 py-1 text-white text-sm">
                                    <?php foreach ($secciones as $seccion): ?>
                                        <option value="<?php echo $seccion['id']; ?>" <?php echo $seccion['id'] == $clase['seccion_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($seccion['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" id="editOrden" value="<?php echo $clase['orden']; ?>" min="1" 
                                       placeholder="Orden" class="bg-black/20 border border-white/20 rounded px-3 py-1 text-white text-sm w-20">
                                <input type="number" id="editDuracion" value="<?php echo $clase['duracion']; ?>" min="0" 
                                       placeholder="Duración (seg)" class="bg-black/20 border border-white/20 rounded px-3 py-1 text-white text-sm w-32">
                            </div>
                        <?php else: ?>
                            <h2 class="text-xl font-semibold text-white"><?php echo htmlspecialchars($clase['titulo']); ?></h2>
                            <div class="flex items-center space-x-4 text-sm text-gray-400 mt-1">
                                <span><i class="fas fa-list mr-1"></i><?php echo htmlspecialchars($clase['seccion_nombre']); ?></span>
                                <?php if ($clase['duracion']): ?>
                                    <span><i class="fas fa-clock mr-1"></i><?php echo gmdate("H:i:s", $clase['duracion']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Video Player -->
                    <div class="video-container">
                        <video id="videoPlayer" class="w-full" preload="metadata"
                               <?php if ($progreso && $progreso['tiempo_visto'] > 0): ?>
                                   data-start-time="<?php echo $progreso['tiempo_visto']; ?>"
                               <?php endif; ?>>
                            <source src="<?php echo $videoPath; ?>" type="video/mp4">
                            Tu navegador no soporta el elemento video.
                        </video>
                        
                        <!-- Indicador de click en video -->
                        <div class="video-click-indicator" id="videoClickIndicator">
                            <i class="fas fa-play"></i>
                        </div>
                        
                        <!-- Controles personalizados -->
                        <div class="video-controls relative">
                            <!-- Barra de progreso con marcadores de notas -->
                            <div class="progress-bar" id="progressBar">
                                <div class="progress-filled" id="progressFilled"></div>
                                <?php foreach ($notas as $nota): ?>
                                    <div class="note-marker" 
                                         style="left: 0%" 
                                         data-time="<?php echo $nota['tiempo_video']; ?>"
                                         data-content="<?php echo htmlspecialchars($nota['contenido_nota']); ?>"
                                         title="<?php echo htmlspecialchars($nota['contenido_nota']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Controles de reproducción -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <button id="playPauseBtn" class="text-white hover:text-purple-300">
                                        <i class="fas fa-play text-lg"></i>
                                    </button>
                                    <span id="timeDisplay" class="text-white text-sm">00:00 / 00:00</span>
                                    <button id="persistDurationBtn" class="hidden text-xs bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded" title="Guardar duración exacta">Guardar duración</button>
                                </div>
                                
                                <div class="flex items-center space-x-3">
                                    <!-- Control de velocidad -->
                                    <div class="relative dropdown-container">
                                        <button id="speedBtn" class="control-button text-white hover:text-purple-300 text-sm px-3 py-1 bg-black/30 rounded border border-white/20" title="Velocidad de reproducción">
                                            <i class="fas fa-tachometer-alt mr-1"></i>1x
                                        </button>
                                        <div id="speedMenu" class="dropdown-menu bg-gray-900 rounded-lg shadow-2xl border border-white/20 hidden min-w-max left-0">
                                            <div class="p-1">
                                                <div class="text-xs text-gray-400 px-2 py-1 border-b border-white/10 mb-1">Velocidad</div>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="0.25">0.25x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="0.5">0.5x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="0.75">0.75x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded bg-purple-600" data-speed="1">1x Normal</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="1.25">1.25x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="1.5">1.5x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="2">2x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="3">3x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="5">5x</button>
                                                <button class="speed-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-speed="10">10x</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Control de calidad -->
                                    <div class="relative dropdown-container">
                                        <button id="qualityBtn" class="hidden sm:inline-flex control-button text-white hover:text-purple-300 text-sm px-3 py-1 bg-black/30 rounded border border-white/20" title="Calidad del video">
                                            <i class="fas fa-cog mr-1"></i>Auto
                                        </button>
                                        <div id="qualityMenu" class="dropdown-menu bg-gray-900 rounded-lg shadow-2xl border border-white/20 hidden min-w-max right-0">
                                            <div class="p-1">
                                                <div class="text-xs text-gray-400 px-2 py-1 border-b border-white/10 mb-1">Calidad</div>
                                                <button class="quality-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded bg-purple-600" data-quality="auto">
                                                    <i class="fas fa-magic mr-2"></i>Auto
                                                </button>
                                                <button class="quality-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-quality="1080p">
                                                    <i class="fas fa-video mr-2"></i>1080p HD
                                                </button>
                                                <button class="quality-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-quality="720p">
                                                    <i class="fas fa-video mr-2"></i>720p HD
                                                </button>
                                                <button class="quality-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-quality="480p">
                                                    <i class="fas fa-video mr-2"></i>480p
                                                </button>
                                                <button class="quality-option dropdown-item text-white hover:bg-purple-600 text-sm px-3 py-2 w-full text-left rounded" data-quality="360p">
                                                    <i class="fas fa-video mr-2"></i>360p
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button onclick="openNotesModal()" class="hidden sm:inline-flex text-white hover:text-yellow-300 px-2 sm:px-3 py-1 rounded text-xs sm:text-sm bg-black/30 border border-white/20" title="Agregar nota">
                                        <i class="fas fa-sticky-note"></i><span class="hidden sm:inline ml-1">Nota</span>
                                    </button>
                                    
                                    <!-- Atajos de teclado -->
                                    <button id="keyboardShortcutsBtn" class="hidden sm:inline-flex text-white hover:text-blue-300 px-2 sm:px-3 py-1 rounded text-xs sm:text-sm bg-black/30 border border-white/20" title="Ver atajos de teclado">
                                        <i class="fas fa-keyboard"></i><span class="hidden sm:inline ml-1">Atajos</span>
                                    </button>
                                    
                                    <!-- Control de volumen con slider -->
                                    <div class="flex items-center space-x-1">
                                        <button id="volumeBtn" class="text-white hover:text-purple-300">
                                            <i class="fas fa-volume-up"></i>
                                        </button>
                                        <input type="range" id="volumeSlider" min="0" max="100" value="100" 
                                               class="w-16 sm:w-24 h-1 bg-gray-600 rounded-lg appearance-none cursor-pointer slider">
                                    </div>
                                    <!-- Overlay de volumen para móvil -->
                                    <div id="mobileVolumeOverlay" class="sm:hidden hidden absolute bottom-14 right-28 bg-black/80 border border-white/20 rounded p-3 z-50">
                                         <input id="mobileVolumeSlider" type="range" min="0" max="100" value="100" class="h-28 w-6 transform -rotate-90 origin-center slider" disabled>
                                    </div>
                                    
                                    <button id="fullscreenBtn" class="text-white hover:text-purple-300">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navegación entre clases -->
                    <div class="p-4 border-t border-white/10 flex justify-between">
                        <?php if ($claseAnterior): ?>
                            <a href="reproductor.php?clase=<?php echo $claseAnterior['id']; ?>" 
                               class="flex items-center text-purple-300 hover:text-white transition-colors">
                                <i class="fas fa-chevron-left mr-2"></i>
                                <span class="hidden sm:inline">Anterior: </span><?php echo htmlspecialchars($claseAnterior['titulo']); ?>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <?php if ($claseSiguiente): ?>
                            <a href="reproductor.php?clase=<?php echo $claseSiguiente['id']; ?>" 
                               class="flex items-center text-purple-300 hover:text-white transition-colors">
                                <span class="hidden sm:inline">Siguiente: </span><?php echo htmlspecialchars($claseSiguiente['titulo']); ?>
                                <i class="fas fa-chevron-right ml-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Panel lateral -->
            <div class="space-y-6">
                <!-- Lista de clases de la sección -->
                <div class="glass-dark rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-white">
                            <i class="fas fa-list mr-2"></i><?php echo htmlspecialchars($clase['seccion_nombre']); ?>
                        </h3>
                        <input id="classesSearch" type="text" placeholder="Buscar clases..." class="bg-black/20 border border-white/20 rounded px-2 py-1 text-white text-sm" />
                    </div>
                    <div class="space-y-2 max-h-64 overflow-y-auto" id="classesList">
                        <?php foreach ($clasesSeccion as $claseItem): ?>
                            <a href="reproductor.php?clase=<?php echo $claseItem['id']; ?>" 
                               class="block p-3 rounded-lg transition-colors <?php echo $claseItem['id'] == $claseId ? 'bg-purple-600' : 'bg-black/20 hover:bg-black/40'; ?>">
                                <div class="flex items-center space-x-3">
                                    <div class="bg-purple-500 rounded p-2">
                                        <i class="fas fa-play text-white text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white text-sm font-medium truncate"><?php echo htmlspecialchars($claseItem['titulo']); ?></p>
                                        <?php if ($claseItem['duracion']): ?>
                                            <p class="text-gray-400 text-xs"><?php echo gmdate("H:i:s", $claseItem['duracion']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Notas -->
                <div class="glass-dark rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-white">
                            <i class="fas fa-sticky-note mr-2"></i>Notas
                        </h3>
                    </div>
                    <!-- Lista con scroll (similar a comentarios) -->
                    <div class="space-y-3 max-h-64 overflow-y-auto pr-1" id="notesList">
                        <?php if (empty($notas)): ?>
                            <p class="text-gray-400 text-sm text-center py-4">No hay notas</p>
                        <?php else: ?>
                            <?php foreach ($notas as $nota): ?>
                                <div class="bg-black/20 rounded-lg p-3 border border-white/10" data-note-id="<?php echo $nota['id']; ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <button onclick="seekToTime(<?php echo $nota['tiempo_video']; ?>)" 
                                                class="text-yellow-400 hover:text-yellow-300 text-sm font-medium">
                                            <?php echo gmdate("H:i:s", $nota['tiempo_video']); ?>
                                        </button>
                                        <div class="flex gap-2">
                                            <button onclick="editNote(<?php echo $nota['id']; ?>)" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit text-xs"></i></button>
                                            <button onclick="deleteNote(<?php echo $nota['id']; ?>)" class="text-red-400 hover:text-red-300"><i class="fas fa-trash text-xs"></i></button>
                                        </div>
                                    </div>
                                    <p class="text-gray-300 text-sm" data-note-content><?php echo nl2br(htmlspecialchars($nota['contenido_nota'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Botón + debajo de la lista -->
                    <div class="mt-4 mb-2 flex">
                        <button onclick="openNotesModal()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded" title="Añadir nota">
                            <i class="fas fa-plus mr-1"></i><span>Nota</span>
                        </button>
                    </div>
                    <!-- Barra de búsqueda -->
                    <div class="mt-3">
                        <input id="notesSearch" type="text" placeholder="Buscar notas..." class="w-full bg-black/20 border border-white/20 rounded px-2 py-1 text-white text-sm" />
                    </div>
                </div>

                <!-- Comentarios -->
                <div class="glass-dark rounded-lg p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-white">
                            <i class="fas fa-comments mr-2"></i>Comentarios
                        </h3>
                    </div>
                    
                    <!-- Agregar comentario -->
                    <form id="commentForm" class="mb-4">
                        <textarea id="commentText" placeholder="Escribe un comentario..." 
                                  class="w-full bg-black/20 border border-white/20 rounded-lg px-3 py-2 text-white placeholder-gray-400 text-sm resize-none" 
                                  rows="3"></textarea>
                        <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                            <i class="fas fa-paper-plane mr-1"></i>Comentar
                        </button>
                    </form>
                    
                    <!-- Lista de comentarios -->
                    <div class="space-y-3 max-h-64 overflow-y-auto" id="commentsList">
                        <?php if (empty($comentarios)): ?>
                            <p class="text-gray-400 text-sm text-center py-4">No hay comentarios</p>
                        <?php else: ?>
                            <?php foreach ($comentarios as $comentario): ?>
                                <div class="bg-black/20 rounded-lg p-3 border border-white/10" data-comment-id="<?php echo $comentario['id']; ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-gray-400 text-xs">
                                            <?php echo date('d/m/Y H:i', strtotime($comentario['created_at'])); ?>
                                        </span>
                                        <div class="flex gap-2">
                                            <button onclick="editComment(<?php echo $comentario['id']; ?>)" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit text-xs"></i></button>
                                            <button onclick="deleteComment(<?php echo $comentario['id']; ?>)" class="text-red-400 hover:text-red-300"><i class="fas fa-trash text-xs"></i></button>
                                        </div>
                                    </div>
                                    <p class="text-gray-300 text-sm" data-comment-content><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <input id="commentsSearch" type="text" placeholder="Buscar comentarios..." class="w-full bg-black/20 border border-white/20 rounded px-2 py-1 text-white text-sm" />
                    </div>
                </div>

                <!-- Recursos -->
                <div class="glass-dark rounded-lg p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-white">
                            <i class="fas fa-paperclip mr-2"></i>Recursos
                        </h3>
                    </div>
                    <div id="resourcesList" class="space-y-2">
                        <?php
                        // Cargar recursos de la clase
                        $stmt = $db->prepare("SELECT * FROM recursos_clases WHERE clase_id = ? ORDER BY created_at DESC");
                        $stmt->execute([$claseId]);
                        $resList = $stmt->fetchAll();
                        ?>
                        <?php if (empty($resList)): ?>
                            <p class="text-gray-400 text-sm">No hay recursos adjuntos</p>
                        <?php else: ?>
                            <?php foreach ($resList as $rec): ?>
                                <div class="flex items-center justify-between bg-black/20 rounded p-2 border border-white/10">
                                    <a href="uploads/resources/<?php echo $clase['curso_id']; ?>/<?php echo htmlspecialchars($rec['archivo_path']); ?>" download
                                       class="hover:text-white hover:underline"
                                       title="<?php echo htmlspecialchars($rec['nombre_archivo']); ?>">
                                        <i class="fas fa-file mr-2"></i><?php echo htmlspecialchars($rec['nombre_archivo']); ?>
                                    </a>
                                    <button class="text-red-400 hover:text-red-300 text-sm" onclick="deleteResourcePlayer(<?php echo (int)$rec['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar notas -->
    <div id="notesModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="glass-dark rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-bold text-white mb-4">
                    <i class="fas fa-sticky-note mr-2"></i>Agregar Nota
                </h2>
                
                <form id="noteForm">
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Tiempo del video</label>
                        <input type="text" id="noteTime" readonly 
                               class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-white text-sm font-bold mb-2">Contenido de la nota</label>
                        <textarea id="noteContent" rows="4" required 
                                  class="w-full bg-black/20 border border-white/20 rounded-lg px-4 py-2 text-white placeholder-gray-400 focus:border-yellow-500 focus:outline-none"
                                  placeholder="Escribe tu nota aquí..."></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar Nota
                        </button>
                        <button type="button" onclick="closeNotesModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de atajos de teclado -->
    <div id="keyboardShortcutsModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
            <div class="glass-dark rounded-lg p-6 max-w-lg w-full mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-keyboard mr-3 text-blue-400"></i>Atajos de Teclado
                </h3>
                <button onclick="closeKeyboardShortcutsModal()" class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-3">
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Reproducir/Pausar</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">Espacio</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Retroceder 10 segundos</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">←</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Avanzar 10 segundos</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">→</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Silenciar/Activar audio</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">M</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Pantalla completa</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">F</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Disminuir velocidad</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">-</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Aumentar velocidad</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">+</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Velocidad normal (1x)</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">R</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-black/20 rounded-lg">
                        <span class="text-white">Cerrar menús</span>
                        <kbd class="px-2 py-1 bg-gray-700 text-white rounded text-sm">ESC</kbd>
                    </div>
                </div>
                <div class="mt-6 p-3 bg-blue-900/30 rounded-lg border border-blue-500/30">
                    <p class="text-blue-200 text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Tip:</strong> Estos atajos funcionan cuando el reproductor está enfocado y no estás escribiendo en un campo de texto.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CLASE_ID = <?php echo $claseId; ?>;
        const EDIT_MODE = <?php echo $editMode ? 'true' : 'false'; ?>;
        const START_TIME = <?php echo isset($_GET['t']) ? (int)$_GET['t'] : ($progreso ? $progreso['tiempo_visto'] : 0); ?>;
        const INIT_CLASS_DURATION = <?php echo (int)$clase['duracion']; ?>;
        const CURSO_ID = <?php echo (int)$clase['curso_id']; ?>;
        const AUTOPLAY_ENABLED = <?php echo isset($_GET['autoplay']) && $_GET['autoplay'] == '1' ? 'true' : 'false'; ?>;
    </script>
    <script src="js/reproductor.js"></script>
    <script>
        // Modal de búsqueda global en reproductor
        function openGlobalSearchModal() {
            let modal = document.getElementById('globalSearchModal');
            if (!modal) {
                const wrapper = document.createElement('div');
                wrapper.id = 'globalSearchModal';
                wrapper.className = 'fixed inset-0 bg-black/60 z-50 flex items-start justify-center p-6';
                wrapper.innerHTML = `
                    <div class="glass-dark rounded-lg p-6 w-full max-w-3xl mt-10">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-white"><i class="fas fa-search mr-2"></i>Buscar en el curso</h3>
                            <button class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded" onclick="closeGlobalSearchModal()"><i class="fas fa-times"></i></button>
                        </div>
                        <input id="globalSearchInput" type="text" placeholder="Escribe para buscar en títulos de clases, notas (por tiempo), comentarios y adjuntos..." class="w-full bg-black/20 border border-white/20 rounded px-3 py-2 text-white" />
                        <div id="globalSearchResults" class="mt-4 max-h-[60vh] overflow-y-auto space-y-2"></div>
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
        function formatTimeLocal(totalSeconds) {
            const s = Math.max(0, parseInt(totalSeconds || 0, 10));
            const h = Math.floor(s / 3600); const m = Math.floor((s % 3600) / 60); const sec = s % 60;
            const hh = h.toString().padStart(2, '0'); const mm = m.toString().padStart(2, '0'); const ss = sec.toString().padStart(2, '0');
            return h > 0 ? `${hh}:${mm}:${ss}` : `${mm}:${ss}`;
        }
        function appendResultLocal(container, tipo, texto, onClick) {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between bg-black/30 border border-white/10 rounded px-3 py-2 text-sm text-gray-200 hover:bg-black/40 cursor-pointer';
            let strong = '<strong class="text-purple-300">';
            if (tipo === 'Nota') strong = '<strong class="text-orange-400" style="color:#fb923c">';
            else if (tipo === 'Clase') strong = '<strong class="text-green-400" style="color:#4ade80">';
            else if (tipo === 'Adjunto') strong = '<strong class="text-blue-400" style="color:#60a5fa">';
            else if (tipo === 'Comentario') strong = '<strong class="text-pink-400" style="color:#f472b6">';
            row.innerHTML = `<span>${strong}${tipo}:</strong> ${texto}</span><i class="fas fa-arrow-right text-white/60"></i>`;
            row.addEventListener('click', onClick);
            container.appendChild(row);
        }
        async function performGlobalSearch(term) {
            const results = document.getElementById('globalSearchResults');
            results.innerHTML = '';
            if (!term) return;
            try {
                const res = await fetch(`api/search.php?q=${encodeURIComponent(term)}&curso_id=${CURSO_ID}`);
                const json = await res.json();
                if (!json.success) return;
                (json.data || []).forEach(item => {
                    if (item.type === 'Clase') appendResultLocal(results, 'Clase', item.label, () => window.location.href = `reproductor.php?clase=${item.clase_id}`);
                    else if (item.type === 'Nota') appendResultLocal(results, 'Nota', `${formatTimeLocal(item.time)} • ${item.label}`, () => window.location.href = `reproductor.php?clase=${item.clase_id}&t=${item.time}`);
                    else if (item.type === 'Comentario') appendResultLocal(results, 'Comentario', item.label, () => window.location.href = `reproductor.php?clase=${item.clase_id}`);
                    else if (item.type === 'Adjunto') appendResultLocal(results, 'Adjunto', item.label, () => window.location.href = `reproductor.php?clase=${item.clase_id}`);
                });
            } catch (_) {}
        }
    </script>
</body>
</html>


