<?php
declare(strict_types=1);

/**
 * Carica un file di configurazione che restituisce un array.
 * Il file registra il valore in ALPHANO_CONFIG_CACHE, quindi require_once
 * non puo' restituire `true` quando il file e' gia' stato incluso altrove.
 */
function alphanoLoadConfig(string $path): array
{
    $cacheKey = realpath($path) ?: $path;
    if (isset($GLOBALS['ALPHANO_CONFIG_CACHE'][$cacheKey])) {
        return $GLOBALS['ALPHANO_CONFIG_CACHE'][$cacheKey];
    }
    require_once $path;
    return $GLOBALS['ALPHANO_CONFIG_CACHE'][$cacheKey] ?? [];
}
