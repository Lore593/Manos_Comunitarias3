<?php
declare(strict_types=1);

/*
 * Configuración de la conexión a MySQL.
 * En XAMPP normalmente el usuario es root y la contraseña está vacía.
 */
function db(): PDO
{
    static $conexion = null;

    if ($conexion instanceof PDO) {
        return $conexion;
    }

    $host = '127.0.0.1';
    $nombre = 'manos_comunitarios';
    $usuario = 'root';
    $password = '';

    $dsn = "mysql:host={$host};dbname={$nombre};charset=utf8mb4";

    $conexion = new PDO($dsn, $usuario, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    return $conexion;
}
