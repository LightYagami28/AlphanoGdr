<?php
declare(strict_types=1);
require_once __DIR__.'/rpg.inc.php';
require_once __DIR__.'/profile-art.inc.php';
require_once __DIR__.'/manuals.inc.php';
function alphanoRaceMemberCounts():array {return array_column(connectDB()->query('SELECT '.alphanoRaceSql().' AS race_id,COUNT(*) AS total FROM utenti GROUP BY race_id'),'total','race_id');}
function alphanoRaceMembers(?int $raceId=null,int $offset=0,?int $limit=null):array {
 $groups=[];$where=$raceId===null?'':' WHERE '.alphanoRaceSql().'=?';$params=$raceId===null?[]:[$raceId];
 $pagination=$limit===null?'':' LIMIT '.max(1,min(20,$limit)).' OFFSET '.max(0,$offset);
 foreach(connectDB()->query('SELECT id,nome,razza,vampire_human_form,sesso,infetto,ancestrale,avatar,avatar2,'.alphanoRaceSql().' AS logical_race FROM utenti'.$where.' ORDER BY nome,id'.$pagination,$params) as $user){
  $race=(int)$user['logical_race'];$portrait=alphanoProfilePortrait($user);$visible=alphanoVisibleRace($user);$groups[$race][]=['id'=>(int)$user['id'],'name'=>$user['nome'],'portrait'=>$portrait['url'],'portrait_default'=>$portrait['default'],'human_form'=>$visible===alphanoHumanRaceId()&&$visible!==$race];
 }
 return $groups;
}
/** Public racial lore is read from the existing manuals, never copied into another catalogue. */
function alphanoRaceLore(array $race):array {
 return ['html'=>'<p>'.htmlspecialchars($race['description'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>','source'=>null];
}

function alphanoRaceView(?int $id):array {
 $catalog=rpgCatalog();$races=$catalog['races'];$selected=null;$counts=alphanoRaceMemberCounts();$members=$id===null?[]:alphanoRaceMembers($id,0,20);
 foreach($races as &$race){$race['banner']=alphanoProfileDefaultBanner();$race['icon']=alphanoRaceIcon();$race['members']=$members[$race['id']]??[];$race['members_count']=(int)($counts[$race['id']]??0);$race['counts']=['innate'=>0,'special'=>0,'purchasable'=>0];foreach($catalog['abilities'] as $a){ if($a['enabled']&&rpgAbilityRaceAllowed($a,(int)$race['id'])){ $race['counts'][$a['category']]++; } }if($id===$race['id']){ $selected=$race; }}unset($race);
 if($id!==null&&!$selected){ throw new DomainException('Razza non trovata.',404); }
 $groups=['innate'=>[],'purchasable'=>[]];$abilityNames=array_column($catalog['abilities'],'name','id');$statNames=array_column($catalog['stats'],'name','key');
 if($selected){ foreach($catalog['abilities'] as $a){ if($a['enabled']&&rpgAbilityRaceAllowed($a,(int)$selected['id'])){
  $a['requirements_text']=[];foreach($a['requirements']['stats']??[] as $key=>$value){ $a['requirements_text'][]=$statNames[$key].' ≥ '.$value; }
  foreach($a['requirements']['abilities']??[] as $required){ $a['requirements_text'][]=$abilityNames[$required]??'Abilità #'.$required; }
  if(($a['requirements']['sentient']??null)===true){ $a['requirements_text'][]='Senzienza'; }
  if(($a['requirements']['sentient']??null)===false){ $a['requirements_text'][]='Forma Pura'; }
  $groups[$a['category']==='purchasable'?'purchasable':'innate'][]=$a;
 }
 }
 }
 $groups['all']=array_merge($groups['innate'],$groups['purchasable']);
 $familyKind=null;if($selected){ if(in_array($selected['id'],[2,5],true)){ $familyKind='vampire'; }elseif(in_array($selected['id'],[3,6],true)){ $familyKind='lycan'; } }
 $families=[];
 if($familyKind){ foreach(connectDB()->query('SELECT u.id,u.nome,u.razza,u.vampire_human_form,u.sesso,u.avatar,u.avatar2 FROM lineage_members l JOIN utenti u ON u.id=l.player_id WHERE l.kind=? AND l.creator_id IS NULL ORDER BY u.dataregistrazione,u.id',[$familyKind]) as $u){$portrait=alphanoProfilePortrait($u);$families[]=['id'=>(int)$u['id'],'name'=>$u['nome'],'portrait'=>$portrait['url'],'portrait_default'=>$portrait['default']];} }
 $learnable=[];
 foreach(['attack'=>'Attacco','defence'=>'Difesa','heal'=>'Cura','mental'=>'Discipline mentali','perception'=>'Percezione','narrative'=>'Talenti narrativi','passive'=>'Talenti passivi'] as $kind=>$title){$abilities=array_values(array_filter($groups['purchasable'],static fn($a)=>$a['kind']===$kind));if($abilities){ $learnable[]=['title'=>$title,'abilities'=>$abilities]; }}
 return ['family_kind'=>$familyKind,'families'=>$families,'learnable_groups'=>$learnable,'races'=> $races,'selected'=>$selected,'stats'=>$catalog['stats'],'abilities'=>$groups,'lore'=>$selected?alphanoRaceLore($selected):null];
}
