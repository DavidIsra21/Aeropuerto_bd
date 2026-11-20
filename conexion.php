<?php

// Datos de la conexión
$host     = 'localhost';        // Equipo donde está PostgreSQL (el nuestro)
$port     = '5432';             // Puerto por defecto de PostgreSQL
$dbname   = 'aeropuerto_bd';    // Nombre de la BD tal como aparece en pgAdmin
$user     = 'administrador';    // Usuario de PostgreSQL (por ahora el administrador)
$password = 'Biblioteca123';    // Contraseña de ese usuario (cámbiala si es otra)

try {
    // DSN (Data Source Name), indica el motor de BD, host, puerto y nombre de la BD
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    // PDO = PHP Data Objects, la forma estándar de PHP para hablar con BD.
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// Funciones de apoyo que usan todos los formularios
// ---------------------------------------------------------------------------

// Escapa texto para mostrarlo en HTML
function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Función segura para pasar textos de PHP a JavaScript
function jse($valor) {
    return addslashes(htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'));
}

// Saca el mensaje limpio de un RAISE EXCEPTION de los triggers.
// PDO lo entrega como "ERROR:  mensaje\nCONTEXT: ..." y aquí nos quedamos solo con "mensaje".
function mensajeTrigger(PDOException $ex) {
    $texto = $ex->errorInfo[2] ?? $ex->getMessage();
    if (preg_match('/ERROR:\s+(.+)/', $texto, $m)) {
        return trim($m[1]);
    }
    return $texto;
}
