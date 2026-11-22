<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/kernel.inc.php';
require_once __DIR__ . '/../libs/libpg.inc.php';
require_once __DIR__ . '/../libs/libgrafica.inc.php';
$idpg=GetIDPG(true);
$user=alphanoUser();
if (empty($user['admin']) && empty($user['sindaco'])) { alphanoFail('Accesso negato.',403); }
$mayorCity=null;
if (empty($user['admin'])) {
    $mayorCity=getDB()->query('SELECT c.city FROM city c JOIN araldiche a ON a.city=c.id WHERE a.id=?',[(int)$user['araldica']])[0]['city'] ?? null;
    if (!$mayorCity) { alphanoFail('Città non trovata.',404); }
}
$boardAdmin=['apiUrl'=>'/api/admin/bacheche.php','csrfToken'=>alphanoCsrfToken(),'canManageAll'=>!empty($user['admin']),'mayorCity'=>$mayorCity];
require_once __DIR__ . '/../templates/forum/admin.php';
