<?php
declare(strict_types=1);
/** Manual v2 racial weaknesses. Conditions are recorded by staff or verified scene resolution. */
function rpgRacialFamily(int $race):string {
 if(in_array($race,[2,5],true)){ return 'vampire'; }
 if(in_array($race,[3,6],true)){ return 'lycan'; }
 return $race===alphanoHumanRaceId()?'human':'other';
}
function rpgRacialCatalog(int $race):array {return array_values(array_filter(rpgHumanManual()['malus'],static fn($m)=>$m['family']===rpgRacialFamily($race)&&!($race===5&&$m['number']===8)));}
function rpgMalusFields():array {return ['corruption_offer'=>['Offerta corruttiva credibile accertata',0,1],'decisive_desire'=>['Offerta che soddisfa un desiderio documentato',0,1],'wound'=>['Ferita fisica (0 nessuna, 1 lieve, 2 grave)',0,2],'fatigue'=>['Turni di sforzo intenso senza riposo',0,5],'sunlight'=>['Esposizione diretta consecutiva (turni)',0,4],'indirect_light'=>['Luce indiretta intensa',0,1],'sacred'=>['Sacro (0 nessuno, 1 croce, 2 reliquia, 3 acqua santa)',0,3],'silver_contact'=>['Argento a contatto',0,1],'silver_wound'=>['Ferita d’argento (0 nessuna, 1 lieve, 2 grave)',0,2],'silver_embedded'=>['Argento trattenuto nella ferita',0,1],'dismembered'=>['Smembramento completo',0,1],'destroyed'=>['Combustione totale accertata',0,1]];}
function rpgMalusState(string $kind,int $id):array {if(!rpgCreationReady()){ return []; }return rpgDecode(connectDB()->query('SELECT conditions FROM rpg_malus_state WHERE owner_kind=? AND owner_id=?',[$kind,$id])[0]['conditions']??'{}');}
function rpgMalusHumanRules(array $state,string $test,callable $add):void {
 if($test!=='physical'){ return; }
 $add('wound',match((int)($state['wound']??0)){2=>25,1=>10,default=>0},1);
 $fatigue=(int)($state['fatigue']??0);
 if($fatigue>=5){ $penalty=20; }elseif($fatigue>=3){ $penalty=10; }else{ $penalty=0; }
 $add('fatigue',$penalty,4);
}
function rpgMalusVampireRules(array $state,string $test,callable $add):array {
 $sun=(int)($state['sunlight']??0);$dead=$sun>=4||!empty($state['destroyed']);
 $incapacitated=$sun>=3||!empty($state['dismembered'])||$dead;
 if($sun>=2){ $penalty=35; }elseif($sun===1){ $penalty=20; }else{ $penalty=!empty($state['indirect_light'])?10:0; }
 $add('sunlight',$penalty,5);
 if($test==='mental'){ $add('sacred',match((int)($state['sacred']??0)){3=>25,2=>20,1=>15,default=>0},7); }
 return [$incapacitated,$dead];
}
function rpgMalusLycanRules(array $state,string $test,callable $add):bool {
 if($test==='physical'){ $add('wound',match((int)($state['silver_wound']??0)){2=>30,1=>15,default=>0},9); }
 if(in_array($test,['physical','mental'],true)){ $add('silver_contact',!empty($state['silver_contact'])||!empty($state['silver_after'])?15:0,10); }
 $blocked=!empty($state['silver_embedded'])&&!empty($state['silver_wound']);
 if($blocked&&$test==='physical'){ $add('silver_embedded',10,11); }
 return $blocked;
}
function rpgMalusEvaluate(int $race,array $state,string $test='physical'):array {
 $family=rpgRacialFamily($race);$groups=[];$active=[];$incapacitated=false;$dead=false;$blocked=false;
 $add=static function(string $group,int $penalty,int $number)use(&$groups,&$active){if($penalty>0){$groups[$group]=max($groups[$group]??0,$penalty);$active[]=$number;}};
 if($family==='human'){ rpgMalusHumanRules($state,$test,$add); }
 elseif($family==='vampire'){ [$incapacitated,$dead]=rpgMalusVampireRules($state,$test,$add); }
 elseif($family==='lycan'){ $blocked=rpgMalusLycanRules($state,$test,$add); }
 return ['penalty'=>array_sum($groups),'groups'=>$groups,'active'=>array_values(array_unique($active)),'incapacitated'=>$incapacitated,'dead'=>$dead,'regeneration_blocked'=>$blocked];
}
function rpgRacialSheet(string $kind,int $id,array $identity):array {
 $race=(int)($identity['native_race_id']??$identity['race_id']);$state=rpgMalusState($kind,$id);$command=null;$corruption=null;$source='Condizioni registrate dallo staff';
 if(rpgCreationReady()&&connectDB()->query("SHOW TABLES LIKE 'ai_quest_cast'")){[$state,$source,$command,$corruption]=rpgMalusSceneState($kind,$id,$state);}
 $state=rpgMalusWounds($identity,$state);$result=rpgMalusEvaluate($race,$state);return ['race_id'=>$race,'family'=>rpgRacialFamily($race),'catalog'=>rpgRacialCatalog($race),'state'=>$state,'command'=>$command,'corruption'=>$corruption,'source'=>$source,'physical'=>$result,'mental'=>rpgMalusEvaluate($race,$state,'mental'),'fields'=>rpgMalusFields()];
}
function rpgMalusSceneState(string $kind,int $id,array $state):array {
 $row=connectDB()->query("SELECT c.state,g.round_number FROM ai_quest_cast c JOIN ai_quests q ON q.session_id=c.session_id LEFT JOIN ai_session_guidance g ON g.session_id=c.session_id WHERE c.kind=? AND c.actor_id=? AND c.active=1 AND q.status='active' ORDER BY q.created_at DESC LIMIT 1",[$kind,$id])[0]??[];
 $scene=rpgDecode($row['state']??'{}');$round=(int)($row['round_number']??1);
 $corruption=$scene['corruption']??null;if($corruption&&(int)$corruption['until']<$round){ $corruption=null; }
 $command=$scene['dominated']??null;if($command&&(int)$command['until']<$round){ $command=null; }
 if(isset($scene['racial_malus'])){ $state=array_replace($state,$scene['racial_malus']);$source='Condizioni dell’ultima quest attiva'; }else{ $source='Condizioni registrate dallo staff'; }
 return [$state,$source,$command,$corruption];
}
function rpgSaveMalus(string $kind,int $id,mixed $input,string $reason,int $actor):array {
 if(!is_array($input)||array_diff(array_keys($input),array_keys(rpgMalusFields()))){ throw new DomainException('Condizioni razziali non valide.',422); }$values=[];foreach(rpgMalusFields() as $key=>[$label,$min,$max]){ $values[$key]=rpgInt($input[$key]??0,$min,$max,$label); }$reason=rpgText($reason,500,true);
 return rpgAtomic(function()use($kind,$id,$values,$reason,$actor){$before=rpgMalusState($kind,$id);if(!empty($before['silver_contact'])&&empty($values['silver_contact'])){ $values['silver_after']=1; }connectDB()->execute('INSERT INTO rpg_malus_state(owner_kind,owner_id,conditions,reason,actor_id,updated_at) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE conditions=VALUES(conditions),reason=VALUES(reason),actor_id=VALUES(actor_id),updated_at=VALUES(updated_at)',[$kind,$id,rpgJson($values),$reason,$actor,time()]);
  foreach(connectDB()->query("SELECT c.session_id,c.state FROM ai_quest_cast c JOIN ai_quests q ON q.session_id=c.session_id WHERE c.kind=? AND c.actor_id=? AND q.status='active' FOR UPDATE",[$kind,$id]) as $row){$s=rpgDecode($row['state']);$s['racial_malus']=$values;connectDB()->execute('UPDATE ai_quest_cast SET state=? WHERE session_id=? AND kind=? AND actor_id=?',[rpgJson($s),$row['session_id'],$kind,$id]);}
  rpgAdminAudit($actor,'save_malus',$kind.':'.$id,$before,['conditions'=>$values,'reason'=>$reason]);return rpgSheet($kind,$id);
  });
}
/** A single resistance roll, persisted in the verdict. Only the relevant acquired skill applies. */
function rpgDominationResolution(int $sourceRace,int $targetRace,array $owned,int $roll,array $chances=[]):array {
 $duration=$sourceRace===5?5:3;$skill=$sourceRace===5?'human_v2_52':'human_v2_51';$eligible=$targetRace===alphanoHumanRaceId()||($sourceRace===5&&$targetRace===2);
 $chance=0;
 if($targetRace===alphanoHumanRaceId()&&in_array($skill,$owned,true)){ $chance=(int)($chances[$skill]??($sourceRace===5?40:60)); }
 return ['eligible'=>$eligible,'resistance'=>$chance,'roll'=>$chance?$roll:null,'resisted'=>$eligible&&$chance>0&&$roll<=$chance,'duration'=>$duration];
}
/** Wound severity follows recorded injuries/HP; an ordinary lycan wound is never relabelled silver. */
function rpgMalusWounds(array $actor,array $state):array {
 $race=(int)($actor['native_race_id']??$actor['razza']??$actor['race_id']??1);$wound=(string)($actor['ferite']??$actor['wounds']??'');$hp=(int)($actor['puntif']??$actor['hit_points']??1000);$severe=$hp<=500||preg_match('/grave|gravissima|profonda/iu',$wound);$present=trim($wound)!=='';
 if(rpgRacialFamily($race)==='human'&&$present){ $state['wound']=max((int)($state['wound']??0),$severe?2:1); }
 if(rpgRacialFamily($race)==='lycan'&&$present&&preg_match('/argent/iu',$wound)){ $state['silver_wound']=max((int)($state['silver_wound']??0),$severe?2:1); }
 return $state;
}
function rpgMalusScene(array $actor,array $state,int $round,string $intent):array {
 $m=rpgMalusWounds($actor,array_replace(rpgMalusState($actor['kind'],(int)$actor['id']),$state['racial_malus']??[]));
 if(($m['_round']??0)>=$round){ return $m; }
 if(!empty($m['sunlight'])&&isset($m['_round'])){ $m['sunlight']=min(4,(int)$m['sunlight']+1); }
 if(!empty($m['sacred'])&&$m['sacred']>=2){$m['_sacred_until']??=$round+1;if($round>$m['_sacred_until']){ $m['sacred']=0; }}
 if(!empty($m['silver_after'])){$m['_silver_until']??=$round;if($round>$m['_silver_until']){ $m['silver_after']=0; }}
 if(in_array($intent,['assalto','attacco','arresto','fuga','ritirata','allenamento'],true)){ $m['fatigue']=min(5,(int)($m['fatigue']??0)+1); }
 $m['_round']=$round;return $m;
}
function rpgMalusContext(array $sheet):string {
 $m=$sheet['malus']??[];if(!$m){ return ''; }$lines=[];foreach($m['catalog'] as $rule){ $lines[]=$rule['name'].': '.$rule['effect']; }return "\nREGOLE RAZZIALI V2, PREVALENTI SUI VECCHI ESEMPI: ".implode(' ',$lines).' Condizioni accertate: '.rpgJson($m['state']).'. Comando soprannaturale attualmente registrato (da tentare in gioco, senza esito automatico): '.rpgJson($m['command']??[]).'. Valutazione di un’offerta corruttiva accertata: '.rpgJson($m['corruption']??[]).'. Penalità attive fisiche '.$m['physical']['penalty'].' p.p., mentali '.$m['mental']['penalty'].' p.p. Il modello narra soltanto: sole, argento e condizioni permanenti devono essere verificati dal backend o dal Master; non inventarli.';
}
