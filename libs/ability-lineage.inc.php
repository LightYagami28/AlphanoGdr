<?php
declare(strict_types=1);
/** One authority for acquisition, inherited ownership and current-form use. */
function rpgLineagePolicy():array {static $v = null;return $v??=json_decode(file_get_contents(__DIR__.'/../config/ability-lineages.json'),true,64,JSON_THROW_ON_ERROR);}
function rpgAbilityOrigin(array $a):array {
 static $origins = null;if($origins===null){$origins=[];foreach(['rpg-seed.json','racial-abilities-20261026.json'] as $file){ foreach(json_decode(file_get_contents(__DIR__.'/../config/'.$file),true)['abilities'] as $entry){ $origins[$entry['key']]=$entry['race_ids']; } }}
 $key=$a['key']??$a['ability_key']??'';if(rpgHumanRule($key)){ return [alphanoHumanRaceId()]; }
 return array_map('intval',$origins[$key]??$a['race_ids']);
}
function rpgLineageId(array $identity):int {return (int)($identity['native_race_id']??$identity['lineage_race_id']??$identity['race_id']);}
function rpgLineageContains(int $race,array $origins):bool {
 if(in_array($race,$origins,true)){ return true; }$parent=(int)(rpgLineagePolicy()['parents'][$race]??0);return $parent>0&&in_array($parent,$origins,true);
}
function rpgHeritageReason(array $a,int $lineage):string {
 $reason='';
 $r=rpgHumanRule($a['key']);
 if(!$r){ $reason='Questa capacità non appartiene al patrimonio umano trasferibile.'; }
 elseif(!$r['initial']&&!in_array($r['number'],rpgLineagePolicy()['human_heritage_learnable'],true)){ $reason='Capacità riservata agli Umani, esclusa dal patrimonio trasferibile.'; }
 elseif(in_array($r['number'],rpgLineagePolicy()['human_only'],true)){ $reason='Resistenza propria della condizione umana: non trasferibile alle stirpi speciali.'; }
 elseif($lineage===6&&isset(rpgLineagePolicy()['ancestral_excluded'][$r['number']])){ $reason=rpgLineagePolicy()['ancestral_excluded'][$r['number']]; }
 return $reason;
}
function rpgAbilityLineageReason(array $a,array $identity):string {
 $reason='';
 $native=rpgLineageId($identity);
 if(str_starts_with($a['key'],'org_')){
  if(empty($a['organization'])){ $reason='Disciplina organizzativa non pubblicata dall’Accademia.'; }
  elseif(!$a['organization']['enabled']){ $reason='Organizzazione sospesa.'; }
  elseif(!rpgLineageContains($native,array_map('intval',$a['race_ids']))){ $reason='La razza effettiva non è compatibile con questa disciplina organizzativa.'; }
  return $reason;
 }
 $origins=rpgAbilityOrigin($a);$human=alphanoHumanRaceId();$configured=array_map('intval',$a['race_ids']);
 if($origins===[$human]&&$native!==$human&&in_array($native,rpgLineagePolicy()['human_heritage_races'],true)){
  if(!in_array($human,$configured,true)){ $reason='Il catalogo ha sospeso la competenza umana.'; }
  else{ $reason=rpgHeritageReason($a,$native); }
 }
 elseif(!rpgLineageContains($native,$origins)){ $reason='Riservata alla stirpe di origine e ai suoi ranghi superiori: la forma umana non cambia la stirpe.'; }
 elseif(!rpgLineageContains($native,$configured)){ $reason='Questa capacità non è abilitata per la tua stirpe nel catalogo.'; }
 return $reason;
}
function rpgAbilityFormReason(array $a,array $identity):string {
 $reason=rpgAbilityLineageReason($a,$identity);
 if($reason!==''||isset($a['organization'])){
  if($reason===''){ $reason=rpgOrgFormReason($a,$identity); }
  return $reason;
 }
 $native=rpgLineageId($identity);$visible=(int)$identity['race_id'];$human=alphanoHumanRaceId();$origins=rpgAbilityOrigin($a);$rule=rpgHumanRule($a['key']);
 if($origins!==[$human]&&in_array($native,[2,3,5,6],true)&&$visible!==$native){ $reason='Richiede la forma speciale della tua stirpe; l’abilità resta posseduta.'; }
 elseif(!$rule||!in_array($visible,[3,6],true)){ $reason=''; }
 elseif($visible===3&&empty($identity['sentient'])){ $reason='La forma lupina Pura non può eseguire tecniche umane deliberate; torna umano o acquisisci Senzienza.'; }
 elseif(in_array($rule['number'],rpgLineagePolicy()['human_speech'],true)){ $reason=alphanoRaceLosesHumanLanguage($visible)?'La forma attuale non conserva il linguaggio umano.':'Questa tecnica sociale richiede la forma umana.'; }
 elseif(!in_array($rule['number'],rpgLineagePolicy()['lycan_controlled_body'],true)){ $reason='Richiede mani, strumenti o postura della forma umana; la conoscenza è conservata.'; }
 return $reason;
}
function rpgAbilityAccess(array $a,array $identity):array {
 if(isset($a['organization'])){
  $o=$a['organization'];
  return ['origins'=>$a['race_ids'],'heritage'=>false,'inherited'=>false,'learning_race_id'=>rpgLineageId($identity),'label'=>'Disciplina · '.$o['name'],'note'=>'Riservata ai membri attuali, carica di livello almeno '.$o['minimum_rank'].'. Forma: '.['human'=>'umana','native'=>'speciale','both'=>'entrambe'][$o['form']].'. Revisione approvata '.$o['revision'].'.'];
 }
 $human=alphanoHumanRaceId();$native=rpgLineageId($identity);$origins=rpgAbilityOrigin($a);$heritage=$origins===[$human]&&in_array($native,rpgLineagePolicy()['human_heritage_races'],true);
 if($heritage){ $label='Patrimonio umano'; }
 elseif(in_array($native,$origins,true)){ $label='Capacità della propria stirpe'; }
 elseif(rpgLineageContains($native,$origins)){ $label='Eredità della stirpe inferiore'; }
 else{ $label='Capacità incompatibile con la stirpe'; }
 $note=$heritage?'Si apprende con la base umana e i progressi personali; costo, attivazione e recupero restano quelli del catalogo.':'La stirpe determina l’accesso, la forma attuale determina l’uso.';
 return ['origins'=>$origins,'heritage'=>$heritage,'inherited'=>!in_array($native,$origins,true)&&rpgLineageContains($native,$origins),'learning_race_id'=>$heritage?$human:$native,'label'=>$label,'note'=>$note];
}
function rpgLineageSheet(array $identity):array {
 $native=rpgLineageId($identity);$human=alphanoHumanRaceId();
 if($native===$human){ $summary='Quattro innate iniziali e tecniche umane. I poteri delle stirpi speciali non sono apprendibili.'; }
 elseif(in_array($native,rpgLineagePolicy()['human_heritage_races'],true)){ $summary='Conoscenze e PE restano del personaggio. Le capacità si apprendono soltanto dalla propria stirpe, dai suoi ranghi inferiori e dal patrimonio umano compatibile.'; }
 else{ $summary='Sono disponibili soltanto le capacità della propria razza, con i limiti della forma attuale.'; }
 return ['version'=>rpgLineagePolicy()['version'],'native_race_id'=>$native,'native_name'=>rpg_race($native)['name'],'form_name'=>rpg_race((int)$identity['race_id'])['name'],'sentient'=>(bool)$identity['sentient'],'loses_human_language'=>alphanoRaceLosesHumanLanguage((int)$identity['race_id']),'heritage'=>in_array($native,rpgLineagePolicy()['human_heritage_races'],true),'parent_race_id'=>rpgLineagePolicy()['parents'][$native]??null,'balance'=>rpgLineagePolicy()['balance'],'summary'=>$summary];
}

/** Catalogue editing may tune mechanics, but cannot mix or reverse authored bloodlines. */
function rpgValidateLineageAssignment(string $key,array $races):void {
 $origins=rpgAbilityOrigin(['key'=>$key,'race_ids'=>$races]);
 $families=[];foreach($origins as $race){ $families[]=match($race){2,5=>'vampire',3,6=>'lycan',default=>'race:'.$race}; }
 if(count(array_unique($families))>1){ throw new DomainException('Un’abilità non può unire stirpi diverse. Configura una competenza per ciascuna stirpe.',422); }
 foreach($races as $race){ if(!rpgLineageContains($race,$origins)){ throw new DomainException('Non puoi assegnare un potere a una stirpe estranea o a un rango inferiore. Il patrimonio umano viene condiviso automaticamente secondo le sue regole.',422); } }
}
function rpgAiAbilityScore(array $a,string $action,array $words):int {
 $score=mb_stripos($action,$a['name'])!==false&&$action!==''?100:0;
 $text=mb_strtolower($a['name'].' '.$a['description']);
 foreach($words as $w){ if(mb_strlen($w)>4&&str_contains($text,$w)){ $score++; } }
 return $score;
}
function rpgAiAbilityDetail(array $a):array {
 $detail=['id'=>$a['id'],'name'=>$a['name'],'effect'=>$a['effect'],'cooldown'=>$a['cooldown'],'requirements'=>$a['requirements'],'description'=>$a['description']];
 if(isset($a['rules'])){ $detail['human_rules']=array_intersect_key($a['rules'],array_flip(['probability','activation','duration','bonus_text','requirement_text','mechanic'])); }
 return $detail;
}
/** The engine sees every owned skill. Prompt detail is selected by actual declared intent. */
function rpgAiSkillFacts(array $sheet,string $action=''):array {
 $roster=[];$details=[];$fold=mb_strtolower($action);$words=preg_split('/[^\p{L}\p{N}]+/u',$fold,-1,PREG_SPLIT_NO_EMPTY);
 foreach($sheet['abilities']??[] as $a){
  if(!$a['owned']){ continue; }
  $roster[]=['id'=>$a['id'],'name'=>$a['name'],'usable'=>$a['usable'],'automatic'=>(bool)($a['automatic']??false),'reason'=>$a['usable']?'':$a['reason']];
  $score=rpgAiAbilityScore($a,$action,$words);
  if(!$a['usable']){ continue; }
  $details[]=['score'=>$score,'detail'=>rpgAiAbilityDetail($a)];
 }
 usort($details,static fn($a,$b)=>$b['score']<=>$a['score']);
 return ['policy'=>rpgLineagePolicy()['version'],'lineage'=>$sheet['lineage']??rpgLineageSheet($sheet['identity']),'stats'=>$sheet['stats'],'conditions'=>array_intersect_key($sheet['malus']??[],array_flip(['state','command','corruption'])),'owned'=>$roster,'relevant'=>array_column(array_slice($details,0,$action===''?0:3),'detail'),'authority'=>'Solo capacità possedute e utilizzabili nella forma attuale. Il testo descrittivo non crea bonus tecnici: applica soltanto effetti, tiri e conseguenze convalidati dal backend. Le discipline narrative richiedono valutazione del Master; nessun danno o potere aggiuntivo inventato.'];
}
