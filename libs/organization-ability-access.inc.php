<?php
declare(strict_types=1);
function rpgOrgReady():bool {static $v = null;return $v??=(bool)connectDB()->query("SHOW TABLES LIKE 'org_ability_publications'");}
function rpgOrgPublications():array {
 if(!rpgOrgReady()){ return []; }$out=[];
 foreach(connectDB()->query('SELECT p.*,o.nome,o.approvato FROM org_ability_publications p JOIN organizzazioni o ON o.id=p.org_id') as $r){$s=rpgDecode($r['spec']);$out[(int)$r['ability_id']]=['id'=>(int)$r['org_id'],'name'=>$r['nome'],'enabled'=>(bool)$r['approvato'],'minimum_rank'=>(int)$s['minimum_rank'],'form'=>$s['form'],'revision'=>(int)$r['revision']];}return $out;
}
function rpgOrgMembershipReason(array $a,array $identity):string {
 $org=$a['organization']??null;
 return match(true){
  $org===null => '',
  ($identity['kind']??'')!=='pg'||(int)($identity['organization_id']??0)!==$org['id'] => 'Richiede l’appartenenza attuale a '.$org['name'].'. La capacità appresa resta conservata.',
  (int)($identity['organization_rank_level']??0)<$org['minimum_rank'] => 'Richiede una carica di livello almeno '.$org['minimum_rank'].' in '.$org['name'].'.',
  default => ''
 };
}
function rpgOrgFormReason(array $a,array $identity):string {
 $org=$a['organization'];$visible=(int)$identity['race_id'];$human=alphanoHumanRaceId();
 return match(true){
  $org['form']==='human'&&$visible!==$human => 'La disciplina dell’organizzazione richiede la forma umana.',
  $org['form']==='native'&&$visible===$human => 'La disciplina richiede la forma speciale indicata nella proposta approvata.',
  $visible===3&&empty($identity['sentient']) => 'Il Lycan Puro non esegue discipline organizzative deliberate.',
  in_array($visible,[3,6],true)&&!empty($a['effect']['requires_weapon']) => 'La tecnica armata dell’organizzazione richiede mani e postura umane.',
  default => ''
 };
}
