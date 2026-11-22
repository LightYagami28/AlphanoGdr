<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
alphanoRequireLogin();
if(!alphanoCan('admin')){ alphanoFail('Accesso riservato agli amministratori.',403); }
require __DIR__.'/../templates/admin/rpg.php';
