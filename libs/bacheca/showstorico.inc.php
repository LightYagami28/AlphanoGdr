<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.inc.php';
$board=bachecaLegacyBoard();
$id=(int)($_POST['oldid'] ?? $_GET['oldid'] ?? 0);
$original=bachecaRequireMessage($id);
if ((int)$original['bacheca']!==$board) { alphanoFail('Messaggio non trovato.',404); }
$thread=(int)($original['replyid'] ?: $original['id']);
require_once dirname(__DIR__).'/profile-art.inc.php';
$history=getStorico($id);
foreach($history as &$entry) { $entry['portrait']=alphanoProfilePortrait(array_merge($entry,['id'=>(int)$entry['idpg']])); }
unset($entry);
require dirname(__DIR__,2).'/templates/forum/history.php';
