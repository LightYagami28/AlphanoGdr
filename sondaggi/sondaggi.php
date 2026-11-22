<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/kernel.inc.php';
require_once __DIR__ . '/../libs/libsondaggi.inc.php';
require_once __DIR__ . '/../libs/libgrafica.inc.php';
require_once __DIR__ . '/../libs/libpg.inc.php';
$pgid=alphanoRequireLogin();
$admin=IsAdmin($pgid);
$db=connectDB();
$message='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
    alphanoRequireCsrf();
    if (isset($_POST['vota']) || isset($_POST['id'])) {
        $answer=$_POST['vota'] ?? '';$pollId=$_POST['id'] ?? '';
        if (!is_scalar($answer) || !is_scalar($pollId) || !preg_match('/^[1-9]\d*$/D',(string)$answer) || !preg_match('/^[1-9]\d*$/D',(string)$pollId)) {
            alphanoFail('Scegli una risposta valida.',400);
        }
        try { vote((int)$answer,(int)$pollId,$pgid);$message='Il tuo voto è registrato.'; }
        catch (RuntimeException $error) { alphanoFail($error->getMessage(),400); }
    }
}
$counts=[];
foreach ($db->query('SELECT idsondaggio,idrisposta,COUNT(*) AS votes FROM risondaggi GROUP BY idsondaggio,idrisposta') as $row) {
    $poll=(int)$row['idsondaggio'];$answer=(int)$row['idrisposta'];
    $counts[$poll]['total']=($counts[$poll]['total'] ?? 0)+(int)$row['votes'];
    $counts[$poll]['answers'][$answer]=(int)$row['votes'];
}
$ownVotes=[];
foreach ($db->query('SELECT idsondaggio,idrisposta FROM risondaggi WHERE idpg=?',[$pgid]) as $vote) {
    $ownVotes[(int)$vote['idsondaggio']]=(int)$vote['idrisposta'];
}
$pollGroups=['active'=>[],'archive'=>[]];
foreach ($db->query('SELECT id,tema,risposte,attivo FROM sondaggi ORDER BY id DESC') as $row) {
    $id=(int)$row['id'];$active=!empty($row['attivo']);$total=$counts[$id]['total'] ?? 0;$answers=[];
    foreach (explode(';',(string)$row['risposte']) as $index=>$answer) {
        $answerId=$index+1;$votes=$counts[$id]['answers'][$answerId] ?? 0;
        $answers[]=['id'=>$answerId,'label'=>$answer,'votes'=>$votes,'percent'=>$total?round($votes*100/$total,2):0];
    }
    $pollGroups[$active?'active':'archive'][]=['id'=>$id,'title'=>stripslashes((string)$row['tema']),
        'active'=>$active,'canVote'=>$active && !isset($ownVotes[$id]),'ownVote'=>$ownVotes[$id] ?? null,
        'total'=>$total,'answers'=>$answers];
}
require_once __DIR__ . '/../templates/polls/index.php';
