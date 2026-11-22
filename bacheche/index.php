<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/kernel.inc.php';
require_once __DIR__ . '/../libs/libpg.inc.php';
require_once __DIR__ . '/../libs/libgrafica.inc.php';
$idpg = GetIDPG(true);
$user = alphanoUser();
$forum = ['mode'=>'board', 'title'=>'Bacheche', 'apiBaseUrl'=>'/api/bacheche',
    'currentUserId'=>$idpg, 'isAdmin'=>!empty($user['admin']), 'csrfToken'=>alphanoCsrfToken()];
require_once __DIR__ . '/../templates/forum/index.php';
