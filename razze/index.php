<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/race-presentation.inc.php';
alphanoRequireLogin();
$raw=$_GET['id']??null;
if($raw!==null&&(!is_scalar($raw)||filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)){ alphanoFail('Razza non valida.',400); }
try{$races=alphanoRaceView($raw===null?null:(int)$raw,(string)(alphanoUser()['sesso']??'Maschio'));}catch(DomainException $e){alphanoFail($e->getMessage(),$e->getCode());}
require __DIR__.'/../templates/races/index.php';
