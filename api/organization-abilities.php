<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/organization-abilities.inc.php';
playerApi(function():array{
 alphanoRequireLogin(true);$u=alphanoUser();
 if($_SERVER['REQUEST_METHOD']==='GET'){$mode=($_GET['mode']??'organization')==='academy'?'academy':'organization';return orgAbilitiesData($u,$mode,rpgInt($_GET['org_id']??0,0,PHP_INT_MAX,'Organizzazione'));}
 playerMutation();return orgAbilityMutate($u,playerInput());
});
