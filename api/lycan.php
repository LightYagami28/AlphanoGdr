<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/transformations.inc.php';
playerApi(function():array {
    $id=alphano_require_login(true);
    header('Cache-Control: private, no-store');
    if ($_SERVER['REQUEST_METHOD']==='GET') { return ['transformation'=>alphano_transformation_sync($id)]; }
    playerMutation();$input=playerInput();
    return ['success'=>true,'transformation'=>alphano_transformation_sync($id,playerText($input,'form',10,true))];
});
