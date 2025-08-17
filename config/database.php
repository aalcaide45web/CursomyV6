<?php
function getDatabase() {
    $dbPath = __DIR__ . '/../database/cursosmy.db';
    
    // Crear directorio si no existe
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Habilitar foreign keys para SQLite
        $pdo->exec('PRAGMA foreign_keys = ON');
        
        // Crear tablas si no existen
        createTables($pdo);
        
        return $pdo;
    } catch (PDOException $e) {
        die('Error de conexión: ' . $e->getMessage());
    }
}

function createTables($pdo) {
    $tables = [
        // Tabla de temáticas
        "CREATE TABLE IF NOT EXISTS tematicas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(255) NOT NULL UNIQUE,
            descripcion TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Tabla de instructores
        "CREATE TABLE IF NOT EXISTS instructores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            bio TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Tabla de cursos
        "CREATE TABLE IF NOT EXISTS cursos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo VARCHAR(255) NOT NULL,
            tematica_id INTEGER,
            instructor_id INTEGER,
            comentarios TEXT,
            imagen VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tematica_id) REFERENCES tematicas(id) ON DELETE SET NULL,
            FOREIGN KEY (instructor_id) REFERENCES instructores(id) ON DELETE SET NULL
        )",
        
        // Tabla de secciones
        "CREATE TABLE IF NOT EXISTS secciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            curso_id INTEGER NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            orden INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
        )",
        
        // Tabla de clases
        "CREATE TABLE IF NOT EXISTS clases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            seccion_id INTEGER NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            archivo_video VARCHAR(255) NOT NULL,
            duracion INTEGER DEFAULT 0,
            orden INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE CASCADE
        )",
        
        // Tabla de progreso de clases
        "CREATE TABLE IF NOT EXISTS progreso_clases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clase_id INTEGER NOT NULL,
            tiempo_visto INTEGER DEFAULT 0,
            ultima_visualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            completada BOOLEAN DEFAULT 0,
            FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE,
            UNIQUE(clase_id)
        )",
        
        // Tabla de notas de clases
        "CREATE TABLE IF NOT EXISTS notas_clases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clase_id INTEGER NOT NULL,
            tiempo_video INTEGER NOT NULL,
            contenido_nota TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE
        )",
        
        // Tabla de comentarios de clases
        "CREATE TABLE IF NOT EXISTS comentarios_clases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clase_id INTEGER NOT NULL,
            comentario TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE
        )",

        // Tabla de recursos descargables por clase
        "CREATE TABLE IF NOT EXISTS recursos_clases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clase_id INTEGER NOT NULL,
            nombre_archivo VARCHAR(255) NOT NULL,
            archivo_path VARCHAR(255) NOT NULL,
            tipo_mime VARCHAR(100),
            tamano_bytes INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE
        )",

        // Tabla de enlaces asociados a clases
        "CREATE TABLE IF NOT EXISTS enlaces_clases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            clase_id INTEGER NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (clase_id) REFERENCES clases(id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    
    // Insertar datos de ejemplo si las tablas están vacías
    insertSampleData($pdo);
}

function insertSampleData($pdo) {
    // Verificar si ya hay datos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tematicas");
    $tematicasCount = $stmt->fetch()['count'];
    
    if ($tematicasCount == 0) {
        // Insertar temáticas de ejemplo
        $tematicas = [
            ['Programación', 'Cursos relacionados con desarrollo de software'],
            ['Diseño Web', 'Cursos de diseño y experiencia de usuario'],
            ['Marketing Digital', 'Estrategias de marketing online'],
            ['Data Science', 'Análisis de datos y machine learning'],
            ['Idiomas', 'Aprendizaje de idiomas extranjeros']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO tematicas (nombre, descripcion) VALUES (?, ?)");
        foreach ($tematicas as $tematica) {
            $stmt->execute($tematica);
        }
        
        // Insertar instructores de ejemplo
        $instructores = [
            ['Juan Pérez', 'juan@example.com', 'Desarrollador Full Stack con 10 años de experiencia'],
            ['María García', 'maria@example.com', 'Diseñadora UX/UI especializada en interfaces modernas'],
            ['Carlos López', 'carlos@example.com', 'Experto en Marketing Digital y SEO'],
            ['Ana Martínez', 'ana@example.com', 'Data Scientist con especialización en Machine Learning'],
            ['Pedro Sánchez', 'pedro@example.com', 'Profesor de idiomas certificado']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO instructores (nombre, email, bio) VALUES (?, ?, ?)");
        foreach ($instructores as $instructor) {
            $stmt->execute($instructor);
        }
    }
}
?>
