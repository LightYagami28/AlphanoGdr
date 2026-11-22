<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.inc.php';
$board=bachecaLegacyBoard();
if (!empty($_POST['delThread'])) {
    $message=bachecaRequireMessage((int)($_POST['delID'] ?? 0));
    if ((int)$message['bacheca']!==$board) { alphanoFail('Discussione non trovata.',404); }
    eliminaThread((int)$message['id']);
}
bachecaLegacyRender($board);
