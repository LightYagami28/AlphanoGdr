<?php
declare(strict_types=1);
/** Character creation v2: one server authority shared by registration and existing characters. */
function rpgHumanManual():array {static $v = null;return $v??=json_decode(file_get_contents(__DIR__.'/../config/human-rules-v2.json'),true,64,JSON_THROW_ON_ERROR);}
function rpgCreationReady():bool {static $v = null;return $v??=(bool)connectDB()->query("SHOW TABLES LIKE 'rpg_creation'");}
function rpgHumanRule(string $key):?array {foreach(rpgHumanManual()['abilities'] as $a){ if($a['key']===$key){ return $a; } }return null;}
/** Initial human talents stay innate in nature, but extra choices can be learned for PE. */
function rpgAbilityLearnable(array $a):bool {
 return alphanoConfig('abilities_enabled') && ($a['category']==='purchasable'||($a['category']==='innate'&&!empty(rpgHumanRule($a['key']??$a['ability_key']??'')['initial'])));
}
function rpgCreationStatus(string $kind,int $id,?array $identity=null):array {
 $identity??=rpgIdentity($kind,$id);$state=rpgCreationReady()?(connectDB()->query('SELECT * FROM rpg_creation WHERE owner_kind=? AND owner_id=?',[$kind,$id])[0]??[]):[];
 $eligible=(int)alphanoConfig('initial_innates')>0;
 return ['manual_points'=>(bool)($state['manual_points']??false),'innates_chosen'=>(bool)($state['innates_chosen']??false),'needs_points'=>$kind==='pg'&&empty($state['manual_points']),'needs_innates'=>$eligible&&empty($state['innates_chosen']),'eligible'=>$eligible,'choices'=>(int)alphanoConfig('initial_innates')];
}
function rpgCreationCatalog():array {
 $human=rpgRace(alphanoHumanRaceId());$skills=[];foreach(rpgCatalog()['abilities'] as $a){ if($a['enabled']&&$a['category']==='innate'&&in_array(alphanoHumanRaceId(),$a['race_ids'],true)&&($rule=rpgHumanRule($a['key']))&&$rule['initial']){ $skills[]=$a+['discipline'=>$rule['discipline'],'rules'=>$a['rules']??$rule]; } }
 return ['points'=>(int)alphanoConfig('initial_points'),'choices'=>(int)alphanoConfig('initial_innates'),'stats'=>array_map(static fn($s)=>$s+['base'=>$human['stats'][$s['key']],'max'=>100],rpgCatalog()['stats']),'abilities'=>$skills,'malus'=>rpgRacialCatalog(alphanoHumanRaceId())];
}
function rpgValidateAllocation(mixed $input,array $identity,array $p):array {
 $points=(int)alphanoConfig('initial_points');if(!is_array($input)||array_diff(array_keys($input),RPG_STATS)||count($input)!==8){ throw new DomainException('Distribuisci i punti fra tutte le otto caratteristiche.',422); }
 $allocation=[];foreach(RPG_STATS as $k){ $allocation[$k]=rpgInt($input[$k]??null,0,$points,rpgStatName($k)); }
 if(array_sum($allocation)!==$points){ throw new DomainException('Devi assegnare esattamente '.$points.' punti iniziali.',422); }
 $candidate=$p;$candidate['initial_stats']='{}';foreach(rpgStatForms($identity,$candidate) as $form){ foreach($allocation as $k=>$v){ if($form['stats'][$k]+$v>100){ throw new DomainException(rpgStatName($k).' supererebbe 100 in '.$form['name'].'.',422); } } }
 return $allocation;
}
function rpgValidateInnates(mixed $input):array {
 if(!is_array($input)||!array_is_list($input)||count($input)!==(int)alphanoConfig('initial_innates')){ throw new DomainException('Scegli esattamente quattro abilità innate.',422); }
 $ids=array_map(static fn($v)=>rpgInt($v,1,PHP_INT_MAX,'Abilità innata'),$input);if(count(array_unique($ids))!==(int)alphanoConfig('initial_innates')){ throw new DomainException('Le quattro abilità devono essere differenti.',422); }
 $allowed=array_column(rpgCreationCatalog()['abilities'],'id');if(array_diff($ids,$allowed)){ throw new DomainException('Scegli soltanto abilità innate del nuovo catalogo umano.',422); }sort($ids);return $ids;
}
function rpgCreationReceiptSheet(string $kind,int $id,array $payload,string $request):?array {
 $receipt=rpgReceipt($kind,$id,$request,'creation');
 if(!$receipt){ return null; }
 if(rpgDecode($receipt['payload'])!=$payload){ throw new DomainException('Questa conferma contiene scelte diverse.',409); }
 return rpgSheet($kind,$id);
}
function rpgCompleteCreation(string $kind,int $id,mixed $allocation,mixed $innates,string $request,int $actor):array {
 if(!rpgCreationReady()){ throw new DomainException('Creazione personaggio in aggiornamento.',503); }
 return rpgAtomic(function()use($kind,$id,$allocation,$innates,$request,$actor){
  $identity=rpgIdentity($kind,$id,true);$p=rpgProgress($kind,$id,true);$db=connectDB();$status=rpgCreationStatus($kind,$id,$identity);
  $payload=['allocation'=>$allocation,'innates'=>$innates];
  $sheet=rpgCreationReceiptSheet($kind,$id,$payload,$request);if($sheet!==null){ return $sheet; }
  if(!$status['needs_points']&&!$status['needs_innates']){ throw new DomainException('Le scelte iniziali sono già state confermate.',409); }
  $points=$status['needs_points']?rpgValidateAllocation($allocation,$identity,$p):null;$selected=$status['needs_innates']?rpgValidateInnates($innates):[];
  $db->execute('INSERT IGNORE INTO rpg_creation(owner_kind,owner_id) VALUES(?,?)',[$kind,$id]);
  if($points!==null){$db->execute('UPDATE rpg_progress SET initial_claimed=1,initial_stats=?,version=version+1 WHERE owner_kind=? AND owner_id=?',[rpgJson($points),$kind,$id]);$db->execute('UPDATE rpg_creation SET manual_points=1 WHERE owner_kind=? AND owner_id=?',[$kind,$id]);}
  foreach($selected as $ability){ $db->execute('INSERT INTO rpg_learned(owner_kind,owner_id,ability_id,cost,learned_at) VALUES(?,?,?,0,?)',[$kind,$id,$ability,time()]); }
  if($selected){ $db->execute('UPDATE rpg_creation SET innates_chosen=1 WHERE owner_kind=? AND owner_id=?',[$kind,$id]); }
  $db->execute('UPDATE rpg_creation SET updated_at=? WHERE owner_kind=? AND owner_id=?',[time(),$kind,$id]);
  rpgLedger($kind,$id,$request,'creation',0,'Scelte iniziali del personaggio.',$payload,$actor);return rpgSheet($kind,$id);
 });
}

function rpgValidateHumanRuleOverrides(mixed $input):array {
 if(!is_array($input)||array_diff(array_keys($input),['probability','activation','duration','bonus_text','requirement_text','mechanic'])){ throw new DomainException('Regole umane non valide.',422); }$out=[];
 foreach(['probability'=>[1,95],'activation'=>[1,5],'duration'=>[1,10]] as $k=>[$min,$max]){ if(isset($input[$k])){ $out[$k]=rpgInt($input[$k],$min,$max,$k); } }
 foreach(['bonus_text'=>500,'requirement_text'=>500,'mechanic'=>1500] as $k=>$max){ if(isset($input[$k])){ $out[$k]=rpgText($input[$k],$max,true); } }return $out;
}
