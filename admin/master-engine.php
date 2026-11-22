<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/ai.inc.php';alphanoRequireLogin();$u=alphanoUser();if(empty($u['admin'])){ alphanoFail('Configurazione IA riservata agli amministratori.',403); }
$admin=!empty($u['admin']);$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
require_once __DIR__.'/../templates/master/engine.php';
