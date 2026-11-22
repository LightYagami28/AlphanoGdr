<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/libs/player-runtime.inc.php';
require_once dirname(__DIR__).'/libs/experience.inc.php';
playerApi(function():array{
 $user=alphanoRequireLogin(true);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $experienceSettings=alphanoExperienceSettings($user);ob_start();require_once dirname(__DIR__).'/templates/partials/game-preferences.php';$form=ob_get_clean();
  return ['settings'=>$experienceSettings,'environment'=>alphanoMapEnvironment(),'confirmed'=>alphanoExperienceConfirmed($user),'form'=>$form];
 }
 if($_SERVER['REQUEST_METHOD']!=='POST'){ alphanoFail('Metodo non consentito.',405,true); }
 alphanoRequireCsrf(true);$input=alphanoRequestJson();
 if(!isset($input['settings'])||!is_array($input['settings'])||array_is_list($input['settings'])){ throw new DomainException('Preferenze non valide.',400); }
 $confirm=$input['confirm']??false;if(!is_bool($confirm)){ throw new DomainException('Conferma non valida.',400); }
 if($confirm&&array_diff(array_filter(array_diff(array_keys(alphanoExperienceDefaults()),['relationship_effects','chess_skin']),static fn($k)=>!str_starts_with($k,'email_')),array_keys($input['settings']))){ throw new DomainException('Completa tutte le preferenze prima di confermare.',400); }
 return ['settings'=>alphanoExperienceSave($user,$input['settings'],$confirm),'confirmed'=>alphanoExperienceConfirmed($user)];
});
