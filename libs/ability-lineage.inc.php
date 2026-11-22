<?php
declare(strict_types=1);
/** One authority for acquisition, inherited ownership and current-form use. */
function rpgLineagePolicy():array {static $v;return $v??=json_decode(file_get_contents(__DIR__.'/../config/ability-lineages.json'),true,64,JSON_THROW_ON_ERROR);}
function rpgAbilityOrigin(array $a):array {
 // Authored powers cannot be opened to a foreign lineage by widening race_ids.
 static $origins;if($origins===null){$origins=[];foreach(['rpg-seed.json','racial-abilities-20261026.json'] as $file){ foreach(json_decode(file_get_contents(__DIR__.'/../config/'.$file),true)['abilities'] as $entry){ $origins[$entry['key']]=$entry['race_ids']; } }}
 $key=$a['key']??$a['ability_key']??'';if(rpgHumanRule($key)){ return [alphanoHumanRaceId()]; }
 return array_map('intval',$origins[$key]??$a['race_ids']);
}
function rpgLineageId(array $identity):int {return (int)($identity['native_race_id']??$identity['lineage_race_id']??$identity['race_id']);}
function rpgLineageContains(int $race,array $origins):bool {
 if(in_array($race,$origins,true)){ return true; }$parent=(int)(rpgLineagePolicy()['parents'][$race]??0);return $parent>0&&in_array($parent,$origins,true);
}
function rpgHeritageReason(array $a,int $lineage):string {
 $r=rpgHumanRule($a['key']);if(!$r){ return 'Questa capacità non appartiene al patrimonio umano trasferibile.'; }
 if(!$r['initial']&&!in_array($r['number'],rpgLineagePolicy()['human_heritage_learnable'],true)){ return 'Capacità riservata agli Umani, esclusa dal patrimonio trasferibile.'; }
 if(in_array($r['number'],rpgLineagePolicy()['human_only'],true)){ return 'Resistenza propria della condizione umana: non trasferibile alle stirpi speciali.'; }
 if($lineage===6&&isset(rpgLineagePolicy()['ancestral_excluded'][$r['number']])){ return rpgLineagePolicy()['ancestral_excluded'][$r['number']]; }
 return '';
}
function rpgAbilityLineageReason(array $a,array $identity):string {
 $native=rpgLineageId($identity);if(str_starts_with($a['key'],'org_')){if(empty($a['organization'])){ return 'Disciplina organizzativa non pubblicata dall’Accademia.'; }if(!$a['organization']['enabled']){ return 'Organizzazione sospesa.'; }return rpgLineageContains($native,array_map('intval',$a['race_ids']))?'':'La razza effettiva non è compatibile con questa disciplina organizzativa.';}$origins=rpgAbilityOrigin($a);$human=alphanoHumanRaceId();$configured=array_map('intval',$a['race_ids']);
 if($origins===[$human]&&$native!==$human&&in_array($native,rpgLineagePolicy()['human_heritage_races'],true)){
  if(!in_array($human,$configured,true)){ return 'Il catalogo ha sospeso la competenza umana.'; }return rpgHeritageReason($a,$native);
 }
 if(!rpgLineageContains($native,$origins)){ return 'Riservata alla stirpe di origine e ai suoi ranghi superiori: la forma umana non cambia la stirpe.'; }
 if(!rpgLineageContains($native,$configured)){ return 'Questa capacità non è abilitata per la tua stirpe nel catalogo.'; }
 return '';
}
function rpgAbilityFormReason(array $a,array $identity):string {
 $reason=rpgAbilityLineageReason($a,$identity);if($reason!==''){ return $reason; }if(isset($a['organization'])){ return rpgOrgFormReason($a,$identity); }
 $native=rpgLineageId($identity);$visible=(int)$identity['race_id'];$human=alphanoHumanRaceId();$origins=rpgAbilityOrigin($a);$rule=rpgHumanRule($a['key']);
 if($origins!==[$human]&&in_array($native,[2,3,5,6],true)&&$visible!==$native){ return 'Richiede la forma speciale della tua stirpe; l’abilità resta posseduta.'; }
 if(!$rule||!in_array($visible,[3,6],true)){ return ''; }
 if($visible===3&&empty($identity['sentient'])){ return 'La forma lupina Pura non può eseguire tecniche umane deliberate; torna umano o acquisisci Senzienza.'; }
 if(in_array($rule['number'],rpgLineagePolicy()['human_speech'],true)){ return alphanoRaceLosesHumanLanguage($visible)?'La forma attuale non conserva il linguaggio umano.':'Questa tecnica sociale richiede la forma umana.'; }
 if(!in_array($rule['number'],rpgLineagePolicy()['lycan_controlled_body'],true)){ return 'Richiede mani, strumenti o postura della forma umana; la conoscenza è conservata.'; }
 return '';
}
function rpgAbilityAccess(array $a,array $identity):array {
 if(isset($a['organization'])){$o=$a['organization'];return ['origins'=>$a['race_ids'],'heritage'=>false,'inherited'=>false,'learning_race_id'=>rpgLineageId($identity),'label'=>'Disciplina · '.$o['name'],'note'=>'Riservata ai membri attuali, carica di livello almeno '.$o['minimum_rank'].'. Forma: '.['human'=>'umana','native'=>'speciale','both'=>'entrambe'][$o['form']].'. Revisione approvata '.$o['revision'].'.'];}
 $human=alphanoHumanRaceId();$native=rpgLineageId($identity);$origins=rpgAbilityOrigin($a);$heritage=$origins===[$human]&&in_array($native,rpgLineagePolicy()['human_heritage_races'],true);
 return ['origins'=>$origins,'heritage'=>$heritage,'inherited'=>!in_array($native,$origins,true)&&rpgLineageContains($native,$origins),'learning_race_id'=>$heritage?$human:$native,
 'label'=>$heritage?'Patrimonio umano':(in_array($native,$origins,true)?'Capacità della propria stirpe':(rpgLineageContains($native,$origins)?'Eredità della stirpe inferiore':'Capacità incompatibile con la stirpe')),
 'note'=>$heritage?'Si apprende con la base umana e i progressi personali; costo, attivazione e recupero restano quelli del catalogo.':'La stirpe determina l’accesso, la forma attuale determina l’uso.'];
}
function rpgLineageSheet(array $identity):array {
 $native=rpgLineageId($identity);$human=alphanoHumanRaceId();return ['version'=>rpgLineagePolicy()['version'],'native_race_id'=>$native,'native_name'=>rpg_race($native)['name'],'form_name'=>rpg_race((int)$identity['race_id'])['name'],'sentient'=>(bool)$identity['sentient'],'loses_human_language'=>alphanoRaceLosesHumanLanguage((int)$identity['race_id']),
 'heritage'=>in_array($native,rpgLineagePolicy()['human_heritage_races'],true),'parent_race_id'=>rpgLineagePolicy()['parents'][$native]??null,'balance'=>rpgLineagePolicy()['balance'],
 'summary'=>$native===$human?'Quattro innate iniziali e tecniche umane. I poteri delle stirpi speciali non sono apprendibili.':(in_array($native,rpgLineagePolicy()['human_heritage_races'],true)?'Conoscenze e PE restano del personaggio. Le capacità si apprendono soltanto dalla propria stirpe, dai suoi ranghi inferiori e dal patrimonio umano compatibile.':'Sono disponibili soltanto le capacità della propria razza, con i limiti della forma attuale.')];
}

/** Catalogue editing may tune mechanics, but cannot mix or reverse authored bloodlines. */
function rpgValidateLineageAssignment(string $key,array $races):void {
 $origins=rpgAbilityOrigin(['key'=>$key,'race_ids'=>$races]);$human=alphanoHumanRaceId();
 $families=[];foreach($origins as $race){ $families[]=match($race){2,5=>'vampire',3,6=>'lycan',default=>'race:'.$race}; }
 if(count(array_unique($families))>1){ throw new DomainException('Un’abilità non può unire stirpi diverse. Configura una competenza per ciascuna stirpe.',422); }
 foreach($races as $race){ if(!rpgLineageContains($race,$origins)){ throw new DomainException('Non puoi assegnare un potere a una stirpe estranea o a un rango inferiore. Il patrimonio umano viene condiviso automaticamente secondo le sue regole.',422); } }
}
/** The engine sees every owned skill. Prompt detail is selected by actual declared intent. */
function rpgAiSkillFacts(array $sheet,string $action=''):array {
 $roster=[];$details=[];$fold=mb_strtolower($action);$words=preg_split('/[^\p{L}\p{N}]+/u',$fold,-1,PREG_SPLIT_NO_EMPTY);
 foreach($sheet['abilities']??[] as $a){if(!$a['owned']){ continue; }
  $roster[]=['id'=>$a['id'],'name'=>$a['name'],'usable'=>$a['usable'],'automatic'=>(bool)($a['automatic']??false),'reason'=>$a['usable']?'':$a['reason']];
  $score=mb_stripos($action,$a['name'])!==false&&$action!==''?100:0;$text=mb_strtolower($a['name'].' '.$a['description']);foreach($words as $w){ if(mb_strlen($w)>4&&str_contains($text,$w)){ $score++; } }
  if(!$a['usable']){ continue; }$detail=['id'=>$a['id'],'name'=>$a['name'],'effect'=>$a['effect'],'cooldown'=>$a['cooldown'],'requirements'=>$a['requirements'],'description'=>$a['description']];
  if(isset($a['rules'])){ $detail['human_rules']=array_intersect_key($a['rules'],array_flip(['probability','activation','duration','bonus_text','requirement_text','mechanic'])); }
  $details[]=['score'=>$score,'detail'=>$detail];
 }
 usort($details,static fn($a,$b)=>$b['score']<=>$a['score']);
 return ['policy'=>rpgLineagePolicy()['version'],'lineage'=>$sheet['lineage']??rpgLineageSheet($sheet['identity']),'stats'=>$sheet['stats'],'conditions'=>array_intersect_key($sheet['malus']??[],array_flip(['state','command','corruption'])),'owned'=>$roster,'relevant'=>array_column(array_slice($details,0,$action===''?0:3),'detail'),
 'authority'=>'Solo capacità possedute e utilizzabili nella forma attuale. Il testo descrittivo non crea bonus tecnici: applica soltanto effetti, tiri e conseguenze convalidati dal backend. Le discipline narrative richiedono valutazione del Master; nessun danno o potere aggiuntivo inventato.'];
}
