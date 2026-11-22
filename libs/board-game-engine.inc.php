<?php
declare(strict_types=1);
spl_autoload_register(static function($class){$prefix='Onspli\\Chess\\';if(str_starts_with($class,$prefix)){ $file=dirname(__DIR__).'/third_party/onspli-chess/src/'.substr($class,strlen($prefix)).'.php';if(is_file($file)){ require_once $file; } }});
function bg_dead(array $board):bool {
 $pieces=array_filter($board,static fn($p)=>$p!==''&&strtolower($p)!=='k');if(!$pieces){ return true; }
 if(count($pieces)===1&&in_array(strtolower(array_values($pieces)[0]),['b','n'],true)){ return true; }
 $colors=[];foreach($pieces as $sq=>$p){if(strtolower($p)!=='b'){ return false; }$colors[]=(ord($sq[0])+intval($sq[1]))%2;}return count(array_unique($colors))===1;
}
function bg_engine(string $game,array $moves):array {
 if(count($moves)>512){ throw new DomainException('Limite di mosse raggiunto.',409); }
 if($game==='dama'){require_once __DIR__.'/draughts-engine.inc.php';return ad_engine($moves);}
 if($game!=='scacchi'){ throw new DomainException('Gioco non valido.',422); }
 $fen=new Onspli\Chess\FEN();$history=[$fen->exportShort()];foreach($moves as $san){if(!in_array($san,$fen->getLegalMoves(),true)){ throw new DomainException('Mossa non consentita.',422); }$fen->move($san);$history[]=$fen->exportShort();}
 $board=[];for($r=1;$r<=8;$r++){ foreach(range('a','h') as $f){ $board[$f.$r]=$fen->getSquare($f.$r); } }
 $legal=[];foreach($fen->getLegalMoves() as $san){$after=clone $fen;$after->move($san);$move=new Onspli\Chess\Move($san);$to=$move->getTarget();$from='';if($move->getCastling()){$from=$fen->getActiveColor()==='w'?'e1':'e8';$to=($move->getCastling()==='O-O'?'g':'c').($fen->getActiveColor()==='w'?'1':'8');}else{foreach($board as $sq=>$piece){ if($piece!==''&&strtolower($piece)===strtolower($move->getPieceType())&&($fen->getActiveColor()==='w'?ctype_upper($piece):ctype_lower($piece))&&$after->getSquare($sq)!==$piece&&$sq!==$to){$from=$sq;break;} }}$legal[]=['id'=>$san,'from'=>$from,'to'=>$to,'promotion'=>$move->getPromotion(),'path'=>[$from,$to]];}
 $result=$fen->isMate()?($fen->getActiveColor()==='w'?'black':'white'):null;
 if(!$result&&($fen->isStalemate()||$fen->isFiftyMove()||bg_dead($board)||count(array_filter($history,static fn($p)=>$p===$fen->exportShort()))>=3)){ $result='draw'; }
 return ['fen'=>$fen->export(),'board'=>$board,'turn'=>$fen->getActiveColor()==='w'?0:1,'check'=>$fen->isCheck(),'legal'=>$legal,'result'=>$result];
}
