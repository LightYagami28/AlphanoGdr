<?php
declare(strict_types=1);
require_once __DIR__.'/player-runtime.inc.php';
require_once __DIR__.'/profile-art.inc.php';
require_once __DIR__.'/city-directory.inc.php';
function alphanoGroupPeople(array $g):array {
 $db=connectDB();$people=[];$id=(int)$g['idmaster'];if($id>0){ $people[$id]=['id'=>$id,'roles'=>['Fondatore']]; }
 foreach($g['masters'] as $m){$mid=(int)$m['id'];$people[$mid]??=['id'=>$mid,'roles'=>[]];$people[$mid]['roles'][]='Admin';}
 foreach($people as &$p){$u=$db->query('SELECT id,nome,avatar,avatar2,razza,alternate_form,sesso FROM utenti WHERE id=?',[$p['id']])[0]??null;if(!$u){$p['name']='Non assegnato';$p['avatar']='/assets/placeholder.svg';continue;}$art=alphanoProfilePortrait($u);$p['default_avatar']=$art['default'];$p['name']=$u['nome'];$p['avatar']=$art['url'];}unset($p);
 return array_values($people);
}
function alphanoGroupCity(array $g,string $kind):array {
 $db=connectDB();$table=$kind==='corporazioni'?'corporazioni':'organizzazioni';
 $stendardo=(string)($db->query("SELECT stendardo FROM $table WHERE id=?",[$g['id']])[0]['stendardo']??'');
 $city=$db->query("SELECT c.id,(SELECT a.id FROM araldiche a WHERE a.city=c.id ORDER BY a.prima DESC,a.livello DESC,a.id LIMIT 1) AS badge FROM {$table} g JOIN city c ON c.id=g.city WHERE g.id=?",[$g['id']])[0]??null;
 $icon=$city?alphanoCityArtwork((string)$g['city']):'';
 if($city&&(int)$city['badge']!==1&&$icon==='/assets/placeholder.svg'){ $icon=alphanoCityArtwork((string)$g['city']); }
 return ['stendardo'=>$stendardo,'city_id'=>(int)($city['id']??0),'city_icon'=>$icon];
}
function alphanoGroupListing(array $groups,string $kind):array {
 foreach($groups as &$g){
  $g['people']=alphanoGroupPeople($g);
  $city=alphanoGroupCity($g,$kind);$g['stendardo']=$city['stendardo'];$g['city_id']=$city['city_id'];$g['city_icon']=$city['city_icon'];
 }unset($g);return $groups;
}
