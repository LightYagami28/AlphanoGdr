<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.inc.php';
$board=bachecaLegacyBoard();
$id=(int)($_POST['id'] ?? $_GET['id'] ?? 0);
$original=bachecaRequireMessage($id);
if ((int)$original['bacheca']!==$board) { alphanoFail('Messaggio non trovato.',404); }
if ((int)$original['idpg']!==$idpg && !alphanoCan('admin')) { alphanoFail('Permesso negato.',403); }
$thread=(int)($original['replyid'] ?: $original['id']);
if (($_POST['modifica'] ?? '')==='1') {
    if (!aggiornaMessaggio($id,(string)($_POST['oggetto'] ?? ''),(string)($_POST['messaggio'] ?? ''))) { alphanoFail('Modifica non riuscita.',500); }
    bachecaLegacyRender($board,$thread,message:'Messaggio modificato.');
} else {
    $editor=['title'=>'Modifica messaggio','board'=>$board,'thread'=>$thread,'id'=>$id,
        'subject'=>$original['oggetto'],'message'=>$original['messaggio'],'modify'=>true,'notify'=>false];
    require_once dirname(__DIR__,2).'/templates/forum/editor.php';
}
