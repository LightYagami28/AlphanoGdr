<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/kernel.inc.php';
require_once __DIR__ . '/../libs/libgrafica.inc.php';
require_once __DIR__ . '/../libs/libpg.inc.php';
// Shared handbook links also work before login, using the existing guest policy.
if (GetIDPG(false) === null) {
    require_once __DIR__ . '/../manuali/index.php';
    return;
}
$idpg = GetIDPG(true);
$manuals = ['apiUrl'=>'/api/manuali.php','adminAccademia'=>(alphanoCan('adminaccademia')||alphanoCan('admin')),'csrfToken'=>alphanoCsrfToken()];
require_once __DIR__ . '/../templates/manuals/index.php';
