<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/transformations.inc.php';
playerApi(function():array {
    $id=alphanoRequireLogin(true);
    header('Cache-Control: private, no-store');
    if ($_SERVER['REQUEST_METHOD']==='GET') { return ['transformation'=>alphanoTransformationSync($id)]; }
    playerMutation();$input=playerInput();
    return ['success'=>true,'transformation'=>alphanoTransformationSync($id,playerText($input,'form',10,true))];
});
