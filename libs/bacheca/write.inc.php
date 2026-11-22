<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.inc.php';
$board=bachecaLegacyBoard();
$thread=(int)($_POST['replyID'] ?? $_GET['replyID'] ?? 0);
$parent=$thread ? getDB()->query('SELECT * FROM bacheche WHERE id=? AND bacheca=? AND replyid=0',[$thread,$board])[0] ?? null : null;
if ($thread && !$parent) { alphanoFail('Discussione non trovata.',404); }
if ($parent && !empty($parent['chiusa'])) { alphanoFail('Discussione chiusa.',409); }
$subject=trim((string)($_POST['oggetto'] ?? ''));
$message=trim((string)($_POST['messaggio'] ?? ''));
if ($subject!=='' || $message!=='') {
    if (!empty($_POST['firma'])) { $message.=FirmaPG($idpg); }
    $notify=$parent ? !empty($parent['segnalazione']) : !empty($_POST['segnalazione']);
    $saved=$thread ? inserisciPost((string)$board,$thread,$idpg,$subject,$message,$notify) : inserisciThread((string)$board,$idpg,$subject,$message,$notify);
    if (!$saved) { alphanoFail('Invio non riuscito.',500); }
    bachecaLegacyRender($board,$thread,message:'Messaggio pubblicato.');
} else {
    $quoteId=(int)($_POST['quota'] ?? $_GET['quota'] ?? 0);$quote='';
    if ($quoteId) {
        $original=bachecaRequireMessage($quoteId);
        if ((int)$original['bacheca']!==$board) { alphanoFail('Messaggio da citare non trovato.',404); }
        $quote='[q '.GetPGFromID((int)$original['idpg']).']'.$original['messaggio'].'[/q]';
    }
    $editor=['title'=>$thread?'Rispondi':'Nuova discussione','board'=>$board,'thread'=>$thread,'id'=>0,
        'subject'=>'','message'=>$quote,'modify'=>false,'notify'=>!empty($parent['segnalazione'])];
    require_once dirname(__DIR__,2).'/templates/forum/editor.php';
}
