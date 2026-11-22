<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/rankings.inc.php';
$viewer=alphanoRequireLogin();$metric=$_GET['categoria']??'caratteristiche';$page=filter_var($_GET['pagina']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
if(!is_string($metric)||$page===false){ alphanoFail('Classifica non valida.',400); }
if($metric==='giochi'){require_once __DIR__.'/../libs/board-games.inc.php';if(!bgReady()){ alphanoFail('Giochi in preparazione.',503); }require __DIR__.'/../templates/board-games/ranking.php';exit;}
try{$ranking=alphanoRankingView($metric,$page,$viewer);}catch(DomainException $e){alphanoFail($e->getMessage(),$e->getCode());}
require __DIR__.'/../templates/rankings/index.php';
