<?php
declare(strict_types=1);

/**
 * Risolve un log di sessione nella directory attesa, con controllo di contenimento.
 *
 * @return string|null Percorso canonico del file, oppure null se non valido
 */
function alphano_session_log_path(string $root, string $id): ?string
{
    if ($id === '' || ctype_digit($id) === false) {
        return null;
    }

    $directory = realpath(rtrim($root, '/\\').'/sessione/log');
    if ($directory === false) {
        return null;
    }
    $prefix = rtrim($directory, '/\\').DIRECTORY_SEPARATOR;

    foreach (['.html', '.htm'] as $extension) {
        $real = realpath($prefix.$id.$extension);
        if ($real === false) {
            continue;
        }
        if (!str_starts_with($real, $prefix)) {
            return null;
        }
        if (is_file($real) && filesize($real) > 0) {
            return $real;
        }
    }
    return null;
}
