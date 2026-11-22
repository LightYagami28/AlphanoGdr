<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/rpg.inc.php';

function alphanoRpgProfileView(array $sheet,bool $own,bool $edit,bool $admin,array $user):array {
 if(!$own){ $sheet['abilities']=array_values(array_filter($sheet['abilities'],static fn($ability)=>!empty($ability['owned']))); }
 $sheet['can_manage_malus']=$admin||!empty($user['fatepower']);$sheet['is_owner']=$own;$sheet['can_edit']=$edit;$sheet['is_admin']=$admin;
 return $sheet;
}
function alphanoRpgAdminRead(string $action,bool $admin,array $user,array $in):array {
 if($action==='admin'){
  if(!$admin){ throw new DomainException('Permessi amministrativi richiesti.',403); }
  return rpg_admin_data();
 }
 if($action==='malus_targets'){
  if(!$admin&&empty($user['fatepower'])){ throw new DomainException('Permessi Master richiesti.',403); }
  return ['players'=>getDB()->query('SELECT id,nome AS name FROM utenti ORDER BY nome LIMIT 1000'),'npcs'=>getDB()->query('SELECT id,name FROM ai_npcs ORDER BY name LIMIT 1000')];
 }
 if(!$admin){ throw new DomainException('Permessi amministrativi richiesti.',403); }
 $q=playerText($in,'q',50);
 return ['players'=>getDB()->query('SELECT id,nome AS name,razza AS race_id FROM utenti WHERE nome LIKE ? ORDER BY nome LIMIT 50',[$q.'%'])];
}
function alphanoRpgCatalogRead(string $action,string $kind,int $id,bool $edit,array $in):array {
 if($action==='creation_catalog'){ return rpgCreationCatalog(); }
 if($action==='catalog'){ return rpg_catalog()+['settings'=>['stat_cap'=>100,'initial_points'=>(int)alphanoConfig('initial_points'),'xp_per_stat'=>1]]; }
 if(!$edit){ throw new DomainException('Questo registro è personale.',403); }
 return rpg_history($kind,$id,rpg_int($in['before']??0,0,PHP_INT_MAX,'Pagina'));
}
function alphanoRpgGet(string $action,bool $admin,bool $edit,array $user,string $kind,int $id,array $in,bool $own):array {
 if(in_array($action,['admin','malus_targets','players'],true)){ return alphanoRpgAdminRead($action,$admin,$user,$in); }
 if(in_array($action,['creation_catalog','catalog','history'],true)){ return alphanoRpgCatalogRead($action,$kind,$id,$edit,$in); }
 if($action!=='profile'){ throw new DomainException('Azione non riconosciuta.',422); }
 return alphanoRpgProfileView(rpg_sheet($kind,$id,$edit),$own,$edit,$admin,$user);
}
function alphanoRpgMalus(int $actor,bool $admin,array $user,string $kind,int $id,array $in,bool $own,bool $edit):array {
 if(!$admin&&empty($user['fatepower'])){ throw new DomainException('Permessi Master richiesti.',403); }
 return alphanoRpgProfileView(rpgSaveMalus($kind,$id,$in['conditions']??null,playerText($in,'reason',500,true),$actor),$own,$edit,$admin,$user);
}
function alphanoRpgAdminWrite(int $actor,bool $admin,string $action,string $kind,int $id,array $in):array {
 if(!$admin){ throw new DomainException('Permessi amministrativi richiesti.',403); }
 if($action==='upload_ability_image'){
  require_once __DIR__.'/../libs/media-assets.inc.php';
  $path=alphanoMediaUpload($_FILES['image']??[],'rpg/abilities/uploads',null,[1024,1024]);
  rpg_admin_audit($actor,'upload_ability_image',$path,null,['image'=>$path]);
  return ['image'=>$path];
 }
 $result=match($action){
  'save_race'=>rpg_admin_save_race($in,$actor),
  'save_stat'=>rpg_admin_save_stat($in,$actor),
  'save_ability'=>rpg_admin_save_ability($in,$actor),
  'save_settings'=>rpg_admin_save_settings($in['settings']??null,$actor),
  'grant_xp'=>rpg_grant_xp($kind,$id,rpg_int($in['amount']??0,1,1000000,'Esperienza'),playerText($in,'reason',500,true),'admin:'.rpg_request_key($in['request_id']??''),$actor)
 };
 return ['success'=>true,'result'=>$result];
}
function alphanoRpgPlayerWrite(string $action,string $kind,int $id,array $in,int $actor,bool $own,bool $edit,bool $admin,array $user):array {
 if($action==='allocation_preview'){
  return ['allocation'=>rpg_decode(rpg_progress($kind,$id)['initial_stats'],array_fill_keys(RPG_STATS,0)),'sheet'=>rpg_sheet($kind,$id),'claimed'=>(bool)rpg_progress($kind,$id)['initial_claimed'],'points'=>(int)alphanoConfig('initial_points')];
 }
 $request=rpg_request_key($in['request_id']??'');
 $result=match($action){
  'creation_complete'=>rpgCompleteCreation($kind,$id,$in['allocation']??null,$in['innates']??null,$request,$actor),
  'allocation_claim'=>rpg_claim_initial($kind,$id,$request,$actor,$in['allocation']??null),
  'stat_increase'=>rpg_increase_stat($kind,$id,playerText($in,'stat',32,true),rpg_int($in['points']??0,1,100,'Punti'),$request,$actor),
  'buy_ability'=>rpg_buy_ability($kind,$id,rpg_int($in['ability_id']??0,1,PHP_INT_MAX,'Abilità'),$request,$actor),
  default=>throw new DomainException('Azione non riconosciuta.',422)
 };
 return alphanoRpgProfileView($result,$own,$edit,$admin,$user);
}
function alphanoRpgPost(int $actor,bool $admin,array $user,int $id,array $in,string $action,string $kind,bool $edit,bool $own):array {
 playerMutation();
 if($action==='save_malus'){ return alphanoRpgMalus($actor,$admin,$user,$kind,$id,$in,$own,$edit); }
 if(in_array($action,['save_race','save_stat','save_ability','save_settings','grant_xp','upload_ability_image'],true)){ return alphanoRpgAdminWrite($actor,$admin,$action,$kind,$id,$in); }
 if(!$edit){ throw new DomainException('Puoi gestire soltanto il tuo personaggio.',403); }
 return alphanoRpgPlayerWrite($action,$kind,$id,$in,$actor,$own,$edit,$admin,$user);
}
playerApi(function():array {
 $actor=alphanoRequireLogin(true);$user=alphanoUser();$admin=!empty($user['admin']);$in=playerInput();$action=playerText($in,'action',40)?:'profile';
 $self=in_array($action,['save_race','save_stat','save_ability','save_settings'],true);
 $kindHint=playerText($in,'kind',8)?:'pg';
 $kind=$self?'pg':rpg_kind($kindHint);
 $id=$self?$actor:rpg_int($in['id']??$actor,1,PHP_INT_MAX,'Personaggio');
 $edit=$admin||($kind==='pg'&&$id===$actor);
 $own=$kind==='pg'&&$id===$actor;
 return $_SERVER['REQUEST_METHOD']==='GET'
  ? alphanoRpgGet($action,$admin,$edit,$user,$kind,$id,$in,$own)
  : alphanoRpgPost($actor,$admin,$user,$id,$in,$action,$kind,$edit,$own);
});
