<?php
declare(strict_types=1);

if (!defined('S1192_API_RPG_PHP_5A543A_PERMESSI_AMMINISTRATIVI_RICHIESTI')) { define('S1192_API_RPG_PHP_5A543A_PERMESSI_AMMINISTRATIVI_RICHIESTI', 'Permessi amministrativi richiesti.'); }
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/rpg.inc.php';
playerApi(function():array {
 $actor=alphanoRequireLogin(true);$user=alphanoUser();$admin=!empty($user['admin']);$in=playerInput();$action=playerText($in,'action',40)?:'profile';$kind=in_array($action,['save_race','save_stat','save_ability','save_settings'],true)?'pg':rpgKind(playerText($in,'kind',8)?:'pg');$id=in_array($action,['save_race','save_stat','save_ability','save_settings'],true)?$actor:rpgInt($in['id']??$actor,1,PHP_INT_MAX,'Personaggio');$edit=$admin||($kind==='pg'&&$id===$actor);
 $own=$kind==='pg'&&$id===$actor;
 // Public profile presentation is distinct from administrative edit rights.
 $profileView=static function(array $sheet)use($own,$edit,$admin,$user):array{
  if(!$own){ $sheet['abilities']=array_values(array_filter($sheet['abilities'],static fn($ability)=>!empty($ability['owned']))); }
  $sheet['can_manage_malus']=$admin||!empty($user['fatepower']);$sheet['is_owner']=$own;$sheet['can_edit']=$edit;$sheet['is_admin']=$admin;return $sheet;
 };
 if($_SERVER['REQUEST_METHOD']==='GET'){
  if($action==='admin'){if(!$admin){ throw new DomainException(S1192_API_RPG_PHP_5A543A_PERMESSI_AMMINISTRATIVI_RICHIESTI,403); }return rpgAdminData();}
  if($action==='malus_targets'){if(!$admin&&empty($user['fatepower'])){ throw new DomainException('Permessi Master richiesti.',403); }return ['players'=>getDB()->query('SELECT id,nome AS name FROM utenti ORDER BY nome LIMIT 1000'),'npcs'=>getDB()->query('SELECT id,name FROM ai_npcs ORDER BY name LIMIT 1000')];}
  if($action==='players'){if(!$admin){ throw new DomainException(S1192_API_RPG_PHP_5A543A_PERMESSI_AMMINISTRATIVI_RICHIESTI,403); }$q=playerText($in,'q',50);return ['players'=>getDB()->query('SELECT id,nome AS name,razza AS race_id FROM utenti WHERE nome LIKE ? ORDER BY nome LIMIT 50',[$q.'%'])];}
  if($action==='creation_catalog'){ return rpgCreationCatalog(); }
  if($action==='catalog'){ return rpgCatalog()+['settings'=>['stat_cap'=>100,'initial_points'=>(int)alphanoConfig('initial_points'),'xp_per_stat'=>1]]; }
  if($action==='history'){if(!$edit){ throw new DomainException('Questo registro è personale.',403); }return rpgHistory($kind,$id,rpgInt($in['before']??0,0,PHP_INT_MAX,'Pagina'));}
  if($action!=='profile'){ throw new DomainException('Azione non riconosciuta.',422); }return $profileView(rpgSheet($kind,$id,$edit));
 }
 playerMutation();
 if($action==='save_malus'){if(!$admin&&empty($user['fatepower'])){ throw new DomainException('Permessi Master richiesti.',403); }return $profileView(rpgSaveMalus($kind,$id,$in['conditions']??null,playerText($in,'reason',500,true),$actor));}
 if($action==='upload_ability_image'){
  if(!$admin){ throw new DomainException(S1192_API_RPG_PHP_5A543A_PERMESSI_AMMINISTRATIVI_RICHIESTI,403); }
  require_once __DIR__.'/../libs/media-assets.inc.php';
  $path=alphanoMediaUpload($_FILES['image']??[],'rpg/abilities/uploads',null,[1024,1024]);
  rpgAdminAudit($actor,'upload_ability_image',$path,null,['image'=>$path]);return ['image'=>$path];
 }
 if(in_array($action,['save_race','save_stat','save_ability','save_settings','grant_xp'],true)){
  if(!$admin){ throw new DomainException(S1192_API_RPG_PHP_5A543A_PERMESSI_AMMINISTRATIVI_RICHIESTI,403); }
  $result=match($action){'save_race'=>rpgAdminSaveRace($in,$actor),'save_stat'=>rpgAdminSaveStat($in,$actor),'save_ability'=>rpgAdminSaveAbility($in,$actor),'save_settings'=>rpgAdminSaveSettings($in['settings']??null,$actor),'grant_xp'=>rpgGrantXp($kind,$id,rpgInt($in['amount']??0,1,1000000,'Esperienza'),playerText($in,'reason',500,true),'admin:'.rpgRequestKey($in['request_id']??''),$actor)};return ['success'=>true,'result'=>$result];
 }
 if(!$edit){ throw new DomainException('Puoi gestire soltanto il tuo personaggio.',403); }
 if($action==='allocation_preview'){ return ['allocation'=>rpgDecode(rpgProgress($kind,$id)['initial_stats'],array_fill_keys(RPG_STATS,0)),'sheet'=>rpgSheet($kind,$id),'claimed'=>(bool)rpgProgress($kind,$id)['initial_claimed'],'points'=>(int)alphanoConfig('initial_points')]; }
 $request=rpgRequestKey($in['request_id']??'');$result=match($action){'creation_complete'=>rpgCompleteCreation($kind,$id,$in['allocation']??null,$in['innates']??null,$request,$actor),'allocation_claim'=>rpgClaimInitial($kind,$id,$request,$actor,$in['allocation']??null),'stat_increase'=>rpgIncreaseStat($kind,$id,playerText($in,'stat',32,true),rpgInt($in['points']??0,1,100,'Punti'),$request,$actor),'buy_ability'=>rpgBuyAbility($kind,$id,rpgInt($in['ability_id']??0,1,PHP_INT_MAX,'Abilità'),$request,$actor),default=>throw new DomainException('Azione non riconosciuta.',422)};return $profileView($result);
});
