<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/kernel.inc.php';
function alphanoLegacyChatKey(mixed $legacy): string
{
    $key = $_GET['chat'] ?? $legacy ?? '';
    if (!is_string($key) || $key === '') { alphanoFail('Seleziona un luogo dalla mappa.', 400); }
    return $key;
}
