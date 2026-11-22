<?php
declare(strict_types=1);
require dirname(__DIR__).'/libs/board-game-engine.inc.php';
function test(string $label,bool $ok):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$s=bgEngine('scacchi',[]);test('Chess starts with twenty legal moves',count($s['legal'])===20);$s=bgEngine('scacchi',['f3','e5','g4','Qh4']);test('Fools mate is resolved by PHP',$s['result']==='black');
$en=bgEngine('scacchi',['e4','a6','e5','d5']);test('En passant remains available',in_array('exd6',array_column($en['legal'],'id'),true));
$d=bgEngine('dama',[]);test('Draughts starts with 24 pieces',count((array)$d['board'])===24);test('Draughts starts with seven moves',count($d['legal'])===7);$move=$d['legal'][0]['id'];$next=bgEngine('dama',[$move]);test('Draughts advances turn',$next['turn']===1);
$board=[22=>'w',18=>'b'];$legal=adLegal($board,true);test('Capture is mandatory',count($legal)===1&&$legal[0]['path']===[22,15]);
$board=[22=>'w',18=>'B'];test('Man cannot take a king',count(array_filter(adLegal($board,true),fn($m)=>$m['taken']))===0);
$board=[22=>'W',18=>'B'];test('King can take a king',count(adLegal($board,true)[0]['taken'])===1);
$board=[22=>'w',18=>'b',10=>'b'];$legal=adLegal($board,true);test('Multiple capture is complete',max(array_map(fn($m)=>count($m['taken']),$legal))===2);
try{bgEngine('dama',['1-32']);throw new RuntimeException('Invalid move accepted');}catch(DomainException){echo "PASS Forged move rejected\n";}
