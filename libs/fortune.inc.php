<?php
declare(strict_types=1);
require_once __DIR__.'/libequip.inc.php';

function alphanoFortuneRarity(int $weight): string {
 if($weight<=3){ return 'Rara'; }
 if($weight<=8){ return 'Non comune'; }
 return 'Comune';
}
function alphanoFortuneCatalog(): array
{
 $settings=require_once __DIR__.'/../config/fortune.php';$items=[];$available=[];
 foreach(db()->query("SELECT e.id,e.materiaprima,e.shortDesc,e.immagine FROM equip e JOIN tipo t ON t.id=e.tipo WHERE e.corp=0 AND e.approvato='1' AND e.visibile='1' AND t.tipoG='risorsa' ORDER BY e.id") as $row){ $available[$row['materiaprima']]??=$row; }
 foreach($settings['resources'] as $key=>$weight){
  $r=$available[$key]??null;
  if(!$r){ continue; }
  $items[]=['id'=>(int)$r['id'],'key'=>$key,'name'=>$r['shortDesc'],'image'=>alphanoEquipmentImage(rawurldecode($r['immagine'])),'weight'=>$weight,'rarity'=>alphanoFortuneRarity($weight)];
 }
 $sum=array_sum(array_column($items,'weight'));
 foreach($items as &$r){ $r['chance']=round(100*$r['weight']/$sum,2); }
 return $items;
}

function alphanoFortuneDraw(array $weights): int|string
{
 $roll=random_int(1,array_sum($weights));
 foreach($weights as $key=>$weight){$roll-=$weight;if($roll<=0){ return $key; }}
 throw new LogicException('Pesi non validi.');
}

function alphanoFortuneRound(array $r): array
{
 return ['id'=>(int)$r['id'],'resource_id'=>(int)$r['resource_id'],'key'=>$r['resource_key'],'name'=>$r['resource_name'],'image'=>$r['image'],'multiplier'=>(int)$r['multiplier'],'started_at'=>(int)$r['started_at'],'awarded_at'=>(int)$r['awarded_at'],'available_at'=>(int)$r['available_at']];
}

function alphanoFortunePhase(?array $r,int $now): string {
 if($r&&!$r['awarded_at']){ return 'multiplier'; }
 if($r&&$r['available_at']>$now){ return 'cooldown'; }
 return 'resource';
}
function alphanoFortuneState(int $player): array
{
 $history=array_map('alphanoFortuneRound',db()->query('SELECT * FROM fortune_rounds WHERE player_id=? ORDER BY id DESC LIMIT 10',[$player]));
 $r=$history[0]??null;$now=time();
 return ['server_time'=>$now,'round'=>$r,'phase'=>alphanoFortunePhase($r,$now),'history'=>$history];
}

function alphanoFortuneStart(int $player,string $request,int $round,?array $latest):void {
 if(!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D',$request)){ throw new DomainException('Identificativo giro non valido.',400); }
 if(db()->query('SELECT id FROM fortune_rounds WHERE player_id=? AND request_key=?',[$player,$request])){ return; }
 if($latest&&!(int)$latest['awarded_at']){ return; }
 if($latest&&(int)$latest['available_at']>time()){ throw new DomainException('La ruota riposa: attendi la fine delle 6 ore.',409); }
 if(($latest?(int)$latest['id']:0)!==$round){ throw new DomainException('Il giro è cambiato. Aggiorna la ruota.',409); }
 $catalog=alphanoFortuneCatalog();if(!$catalog){ throw new DomainException('La ruota non ha risorse disponibili.',409); }
 $r=$catalog[alphanoFortuneDraw(array_column($catalog,'weight'))];
 db()->execute('INSERT INTO fortune_rounds(player_id,request_key,resource_id,resource_key,resource_name,image,started_at,inventory_ids) VALUES(?,?,?,?,?,?,?,?)',[$player,$request,$r['id'],$r['key'],$r['name'],$r['image'],time(),'[]']);
}
function alphanoFortuneFinish(int $player,int $round):void {
 $r=db()->query('SELECT * FROM fortune_rounds WHERE id=? AND player_id=? FOR UPDATE',[$round,$player])[0]??null;
 if(!$r){ throw new DomainException('Giro non trovato.',404); }
 if((int)$r['awarded_at']){ return; }
 if(!db()->query('SELECT id FROM equip WHERE id=?',[$r['resource_id']])){ throw new DomainException('Risorsa non disponibile: contatta la gestione.',409); }
 $settings=require_once __DIR__.'/../config/fortune.php';$qty=(int)alphanoFortuneDraw($settings['multipliers']);$ids=[];
 for($i=0;$i<$qty;$i++){db()->execute('INSERT INTO equippg(uid,idpg) VALUES(?,?)',[$r['resource_id'],$player]);$ids[]=(int)db()->lastInsertId();}
 $now=time();db()->execute('UPDATE fortune_rounds SET multiplier=?,awarded_at=?,available_at=?,inventory_ids=? WHERE id=?',[$qty,$now,$now+$settings['cooldown'],json_encode($ids),$round]);
}
/** Player row serializes all tabs/devices; prize and receipt commit together. */
function alphanoFortunePlay(int $player,string $action,string $request,int $round): array
{
 return equipAtomico(static function()use($player,$action,$request,$round):array{
  if(!db()->query('SELECT id FROM utenti WHERE id=? FOR UPDATE',[$player])){ throw new DomainException('Personaggio non trovato.',404); }
  $latest=db()->query('SELECT * FROM fortune_rounds WHERE player_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$player])[0]??null;
  if($action==='start'){ alphanoFortuneStart($player,$request,$round,$latest); }
  elseif($action==='finish'){ alphanoFortuneFinish($player,$round); }
  else { throw new DomainException('Operazione non valida.',400); }
  return alphanoFortuneState($player);
 });
}
