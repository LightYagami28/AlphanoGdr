<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/ai-profile.inc.php';
require_once __DIR__.'/../libs/race-presentation.inc.php';
require_once __DIR__.'/../libs/player-labels.inc.php';

require_once __DIR__.'/../libs/online-presence.inc.php';
function alphanoParseCharacterIds($raw):array {
 if(!is_string($raw)||strlen($raw)>1600||($raw!==''&&!preg_match('/^\d+(?:,\d+)*$/D',$raw))){ throw new DomainException('Elenco personaggi non valido.',422); }
 $ids=$raw===''?[]:array_values(array_unique(array_map('intval',explode(',',$raw))));
 if(count($ids)>100){ throw new DomainException('Elenco personaggi non valido.',422); }
 foreach($ids as $id){ if($id<1){ throw new DomainException('Elenco personaggi non valido.',422); } }
 return $ids;
}
// Query letterali: gli identificatori gia' convalidati filtrano i risultati in
// PHP, quindi nessun valore della richiesta raggiunge la costruzione della SQL.
function alphanoCharacterMarkerPlayers($db,array $players,array $races):array {
 $items=[];
 if(!$players){ return $items; }
 foreach($db->query('SELECT id,sesso,posizione,lastmove,bloccato,razza,vampire_human_form,dataregistrazione FROM utenti') as $u){
  if(!in_array((int)$u['id'],$players,true)){ continue; }
  $race=alphanoVisibleRace($u);
  if($race===ALPHANO_RACE_LEGACY_SPIRIT){ $race=ALPHANO_RACE_SPIRIT; }
  $items['pg:'.$u['id']]=['sex'=>$u['sesso'],'online'=>alphanoPlayerOnline($u),'race_id'=>$race,'race'=>$races[$race]??'Razza sconosciuta',...alphanoRegistrationLabel((int)$u['dataregistrazione'])];
 }
 return $items;
}
function alphanoCharacterMarkerNpcs($db,array $npcs,array $races):array {
 $items=[];
 if(!$npcs){ return $items; }
 foreach($db->query('SELECT id,active,profile FROM ai_npcs') as $n){
  if(!in_array((int)$n['id'],$npcs,true)){ continue; }
  $p=aiNpcProfile($n);
  $items['npc:'.$n['id']]=['sex'=>$p['sex'],'online'=>(bool)$n['active'],'race_id'=>(int)$p['race_id'],'race'=>$races[(int)$p['race_id']]??'Razza sconosciuta'];
 }
 return $items;
}
/** Public character labels only: no location, last activity or private NPC profile. */
playerApi(static function():array{
 alphanoRequireLogin(true);
 $players=alphanoParseCharacterIds($_GET['players']??'');$npcs=alphanoParseCharacterIds($_GET['npcs']??'');
 $db=connectDB();$races=array_column($db->query('SELECT id,nome FROM razze'),'nome','id');
 $items=alphanoCharacterMarkerPlayers($db,$players,$races)+alphanoCharacterMarkerNpcs($db,$npcs,$races);
 return ['items'=>(object)$items];
});
