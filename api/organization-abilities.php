<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/organization-abilities.inc.php';
playerApi(function():array{
 alphano_require_login(true);$u=alphano_user();
 if($_SERVER['REQUEST_METHOD']==='GET'){$mode=($_GET['mode']??'organization')==='academy'?'academy':'organization';return org_abilities_data($u,$mode,rpg_int($_GET['org_id']??0,0,PHP_INT_MAX,'Organizzazione'));}
 playerMutation();return org_ability_mutate($u,playerInput());
});
