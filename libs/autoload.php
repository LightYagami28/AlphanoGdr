<?php
declare(strict_types=1);
/** Autoloader PSR-4 per le classi storiche estratte dalle librerie procedurali. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Alphano\\Legacy\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $relative = substr($class, strlen($prefix));
    if ($relative === '' || !preg_match('/^[A-Za-z0-9_\\\\]+$/D', $relative)) { return; }
    $file = __DIR__ . '/legacy/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) { require_once $file; }
});
