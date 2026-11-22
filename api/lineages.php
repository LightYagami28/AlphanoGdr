<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/lineages.inc.php';
playerApi(function():array {
 $id=alphano_require_login(true);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $kind=playerText($_GET,'kind',12)?:'vampire';$root=filter_var($_GET['root']??$id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$root){ throw new DomainException('Personaggio non valido.',422); }
  return ['lineage'=>lineage_tree((int)$root,$kind)];
 }
 playerMutation();$input=playerInput();$target=filter_var($input['target']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$target){ throw new DomainException('Destinatario non valido.',422); }
 return ['success'=>true,'infection'=>lineage_infect($id,(int)$target,playerText($input,'request_key',100,true))];
});
