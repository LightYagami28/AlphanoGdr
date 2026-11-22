<?php
declare(strict_types=1);

if (!defined('S1192_API_CHARACTER_MARKERS_PHP_145B91_ELENCO_PERSONAGGI_NON_VALIDO')) { define('S1192_API_CHARACTER_MARKERS_PHP_145B91_ELENCO_PERSONAGGI_NON_VALIDO', 'Elenco personaggi non valido.'); }
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/ai-profile.inc.php';
require_once __DIR__.'/../libs/race-presentation.inc.php';
require_once __DIR__.'/../libs/player-labels.inc.php';

require_once __DIR__.'/../libs/online-presence.inc.php';
/** Public character labels only: no location, last activity or private NPC profile. */
playerApi(static function():array{
 alphanoRequireLogin(true);
 $parse=static function($raw):array{
  if(!is_string($raw)||strlen($raw)>1600||($raw!==''&&!preg_match('/^\d+(?:,\d+)*$/D',$raw))){ throw new DomainException(S1192_API_CHARACTER_MARKERS_PHP_145B91_ELENCO_PERSONAGGI_NON_VALIDO,422); }
  $ids=$raw===''?[]:array_values(array_unique(array_map('intval',explode(',',$raw))));
  if(count($ids)>100){ throw new DomainException(S1192_API_CHARACTER_MARKERS_PHP_145B91_ELENCO_PERSONAGGI_NON_VALIDO,422); }
  foreach($ids as $id){ if($id<1){ throw new DomainException(S1192_API_CHARACTER_MARKERS_PHP_145B91_ELENCO_PERSONAGGI_NON_VALIDO,422); } }
  return $ids;
 };
 $players=$parse($_GET['players']??'');$npcs=$parse($_GET['npcs']??'');$items=[];$db=connectDB();$races=array_column($db->query('SELECT id,nome FROM razze'),'nome','id');
 // Query letterali: gli identificatori gia' convalidati filtrano i risultati in
 // PHP, quindi nessun valore della richiesta raggiunge la costruzione della SQL.
 if($players){foreach($db->query('SELECT id,sesso,posizione,lastmove,bloccato,razza,vampire_human_form,dataregistrazione FROM utenti') as $u){if(!in_array((int)$u['id'],$players,true)){continue;}$race=alphanoVisibleRace($u);if($race===ALPHANO_RACE_LEGACY_SPIRIT){ $race=ALPHANO_RACE_SPIRIT; }$items['pg:'.$u['id']]=['sex'=>$u['sesso'],'online'=>alphanoPlayerOnline($u),'race_id'=>$race,'race'=>$races[$race]??'Razza sconosciuta',...alphanoRegistrationLabel((int)$u['dataregistrazione'])];}}
 if($npcs){foreach($db->query('SELECT id,active,profile FROM ai_npcs') as $n){if(!in_array((int)$n['id'],$npcs,true)){continue;}$p=aiNpcProfile($n);$items['npc:'.$n['id']]=['sex'=>$p['sex'],'online'=>(bool)$n['active'],'race_id'=>(int)$p['race_id'],'race'=>$races[(int)$p['race_id']]??'Razza sconosciuta'];}}
 return ['items'=>(object)$items];
});
