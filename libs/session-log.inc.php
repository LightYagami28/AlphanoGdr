<?php
declare(strict_types=1);

/**
 * Estensioni dei log storici, in ordine di preferenza.
 *
 * @return array<int,string>
 */
function alphanoSessionLogExtensions(): array
{
    return ['.html', '.htm'];
}

/**
 * Verifica che un percorso canonico resti dentro la directory attesa.
 */
function alphanoSessionLogInside(string $directory, string $path): bool
{
    $prefix = rtrim($directory, '/\\').DIRECTORY_SEPARATOR;
    return str_starts_with($path, $prefix);
}

/**
 * Risolve un log di sessione nella directory attesa, con controllo di contenimento.
 *
 * @return string|null Percorso canonico del file, oppure null se non valido
 */
function alphanoSessionLogPath(string $root, string $id): ?string
{
    $result = null;
    $directory = ($id !== '' && ctype_digit($id))
        ? realpath(rtrim($root, '/\\').'/sessione/log')
        : false;
    if ($directory !== false) {
        foreach (alphanoSessionLogExtensions() as $extension) {
            $real = realpath($directory.DIRECTORY_SEPARATOR.$id.$extension);
            if ($real !== false && alphanoSessionLogInside($directory, $real) && is_file($real) && filesize($real) > 0) {
                $result = $real;
                break;
            }
        }
    }
    return $result;
}
