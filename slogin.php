<?php
declare(strict_types=1);

require_once __DIR__ . '/libs/kernel.inc.php';
require_once __DIR__ . '/libs/libpg.inc.php';

$idpg = GetIDPG(false);
if ($idpg === null || !IsAdmin($idpg)) {
    http_response_code(403);
    exit('Accesso negato: privilegi insufficienti.');
}

// Procedura deprecata: l’amministrazione è legata alla sessione autenticata.
header('Location: /main.php', true, 303);
exit;
