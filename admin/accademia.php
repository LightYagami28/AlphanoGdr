<?php
declare(strict_types=1);

foreach (array_merge($_GET,$_POST) as $value) {
    if (is_array($value) || is_object($value)) {
        http_response_code(400);
        echo 'Parametri non validi';
        exit;
    }
}

require_once __DIR__ . '/../libs/kernel.inc.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { alphanoRequireCsrf(); }
require_once __DIR__ . '/../libs/libaccademia.inc.php';
require_once __DIR__ . '/../libs/libgrafica.inc.php';
require_once __DIR__ . '/../libs/libpg.inc.php';
require_once __DIR__ . '/../libs/libequip.inc.php';
require_once __DIR__ . '/../libs/libtext.inc.php';
require_once __DIR__ . '/../libs/libxml.inc.php';
require_once __DIR__ . '/../libs/libposta.inc.php';
require_once __DIR__ . '/../libs/libbacheca.inc.php';
require_once __DIR__ . '/../libs/activity.inc.php';



if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- AUTENTICAZIONE & PERMESSI ---
$pgId = GetIDPG(false, true);
if ($pgId === null) {
    header('Location: /login.php');
    exit;
}
$user = getDB()->query('SELECT admin, adminaccademia FROM utenti WHERE id = ?', [$pgId])[0] ?? [];
$admin = (bool)($user['admin'] ?? false);
if (!$admin && !($user['adminaccademia'] ?? false)) {
    http_response_code(403);
    exit('Non puoi accedere');
}

// --- AZIONI POST ---
$azione    = $_POST['azione'] ?? '';
$org       = $_POST['org']      ?? '';
$corp      = $_POST['corp']     ?? '';
$obj       = $_POST['obj']      ?? '';
$giocata   = $_POST['giocata']  ?? '';
$voto      = $_POST['voto']     ?? '';
$motivo    = trim($_POST['motivo'] ?? '');
$resurgo   = isset($_POST['resurgo']);
$cerimonia = isset($_POST['cerimonia']);
$ferite    = isset($_POST['ferite']);

if (in_array($azione, ['approva','cancella','approvamodif','cancellamodif','setflagorg'], true) && !$admin) {
    http_response_code(403);
    exit('Solo gli amministratori possono decidere sulle proposte');
}
if (in_array($azione, ['vota','modifyvoto'], true) && !in_array($voto, ['0','1'], true)) {
    http_response_code(400);
    exit('Seleziona un voto positivo o negativo');
}
$academyActions=['showvoti','vota','modifyvoto','approva','cancella','approvamodif','cancellamodif','setflagorg'];
$academyTargets=array_filter(['org'=>(int)$org,'corp'=>(int)$corp,'obj'=>(int)$obj,'giocata'=>(int)$giocata],static fn($target)=>$target>0);
if ($azione!=='' && (!in_array($azione,$academyActions,true) || count($academyTargets)!==1 || ($azione==='setflagorg' && !$org))) {
    http_response_code(400); exit('Seleziona una sola proposta valida');
}
$academyKind=$academyTargets ? array_key_first($academyTargets) : '';
$academyTarget=$academyTargets ? (int)reset($academyTargets) : 0;
$academyTable=['org'=>'organizzazioni','corp'=>'corporazioni','obj'=>'equip','giocata'=>'giocate'][$academyKind] ?? '';
$academyState=static function() use($academyTable,$academyTarget,$academyKind): array {
    if ($academyTable==='') { return []; }
    $row=getDB()->query("SELECT * FROM {$academyTable} WHERE id=?",[$academyTarget])[0] ?? null;
    $state=['exists'=>$row!==null,'content_hash'=>$row ? hash('sha256',json_encode($row)) : null];
    if ($academyKind==='obj') { $state['draft_exists']=(bool)getDB()->query('SELECT id FROM newequip WHERE id=?',[$academyTarget]); }
    $votes=getDB()->query("SELECT idpg,voto,motivo FROM accademia WHERE {$academyKind}=? ORDER BY idpg",[$academyTarget]);
    $state['votes_count']=count($votes);
    $state['votes_hash']=hash('sha256',json_encode($votes));
    return $state;
};
$academyBefore=$academyState();
$academyProposal=$academyTable!=='' ? (getDB()->query("SELECT * FROM {$academyTable} WHERE id=?",[$academyTarget])[0] ?? []) : [];

// --- caso “showvoti”: esci qui con la tabella dei voti e motivi ---
if ($azione === 'showvoti') {
    if ($org) {
        $rows = getDB()->query(
            'SELECT a.*, u.nome
             FROM accademia a
             JOIN utenti u ON u.id = a.idpg
             WHERE a.org = ?',
            [(int)$org]
        );
        $titolo = GetOrg((int)$org);
    } elseif ($corp) {
        $rows = getDB()->query(
            'SELECT a.*, u.nome
             FROM accademia a
             JOIN utenti u ON u.id = a.idpg
             WHERE a.corp = ?',
            [(int)$corp]
        );
        $titolo = GetCorporazione((int)$corp);
    } elseif ($obj) {
        $rows = getDB()->query(
            'SELECT a.*, u.nome
             FROM accademia a
             JOIN utenti u ON u.id = a.idpg
             WHERE a.obj = ?',
            [(int)$obj]
        );
        $titolo = getEquipDesc((int)$obj);
    } else {
        $rows = getDB()->query(
            'SELECT a.*, u.nome
             FROM accademia a
             JOIN utenti u ON u.id = a.idpg
             WHERE a.giocata = ?',
            [(int)$giocata]
        );
        $titolo = getNomeGiocata((int)$giocata);
    }
    $academyView = 'votes';
    require_once __DIR__ . '/../templates/academy/votes.php';
    exit;
}

// --- SWITCH principale ---
$msg = '';
switch ($azione) {
    case 'modifyvoto':
        $column=$org ? 'org' : ($corp ? 'corp' : ($obj ? 'obj' : 'giocata'));
        $target=(int)($org ?: ($corp ?: ($obj ?: $giocata)));
        if ($target>0 && in_array($voto,['0','1'],true) && ($voto==='1' || $motivo!=='')) {
            getDB()->execute("UPDATE accademia SET voto=?,motivo=? WHERE idpg=? AND {$column}=?",[$voto==='1' ? 'vpos' : 'vneg',$motivo,$pgId,$target]);
            $msg='Voto aggiornato';
        } else { $msg='Voto non valido'; }
        break;
    case 'vota':
        if ($org)     { $msg = votaOrg((int)$org,    $pgId, $voto==='1', $motivo) ? 'OK' : 'KO'; }
        if ($corp)    { $msg = votaCorp((int)$corp,  $pgId, $voto==='1', $motivo) ? 'OK' : 'KO'; }
        if ($obj)     { $msg = votaObj((int)$obj,    $pgId, $voto==='1', $motivo) ? 'OK' : 'KO'; }
        if ($giocata) { $msg = votaGiocata((int)$giocata,$pgId, $voto==='1', $motivo) ? 'OK' : 'KO'; }
        break;
    case 'approva':
        if ($admin) {
            if ($org)     { $msg = approvaOrg((int)$org)      ? 'OK' : 'KO'; }
            if ($corp)    { $msg = approvaCorp((int)$corp)    ? 'OK' : 'KO'; }
            if ($obj)     { $msg = approvaObj((int)$obj)      ? 'OK' : 'KO'; }
            if ($giocata) { $msg = approvaGiocata((int)$giocata)? 'OK' : 'KO'; }
        }
        break;
    case 'cancella':
        if ($admin) {
            if ($org)     { $msg = cancellaOrg((int)$org)      ? 'OK' : 'KO'; }
            if ($corp)    { $msg = cancellaCorp((int)$corp)    ? 'OK' : 'KO'; }
            if ($obj)     { $msg = cancellaObj((int)$obj)      ? 'OK' : 'KO'; }
            if ($giocata) { $msg = cancellaGiocata((int)$giocata)? 'OK' : 'KO'; }
        }
        break;
    case 'approvamodif':
        if ($admin) {
            if ($org)  { $msg = approvaModifOrg((int)$org)  ? 'OK' : 'KO'; }
            if ($corp) { $msg = approvaModifCorp((int)$corp)? 'OK' : 'KO'; }
            if ($obj)  { $msg = approvaModifObj((int)$obj)  ? 'OK' : 'KO'; }
        }
        break;
    case 'cancellamodif':
        if ($admin) {
            if ($org)  { $msg = cancellaModifOrg((int)$org)  ? 'OK' : 'KO'; }
            if ($corp) { $msg = cancellaModifCorp((int)$corp)? 'OK' : 'KO'; }
            if ($obj)  { $msg = cancellaModifObj((int)$obj)  ? 'OK' : 'KO'; }
        }
        break;
    case 'setflagorg':
        if ($admin && $org) {
            $ok  = setFlagOrg((int)$org, $resurgo, $cerimonia, $ferite);
            $msg = $ok ? 'OK' : 'KO';
        }
        break;
    default: break;
}

$academyAfter=$academyState();
if ($academyBefore!==$academyAfter && in_array($azione,['vota','modifyvoto','approva','cancella','approvamodif','cancellamodif','setflagorg'],true)) {
    $section=$azione==='setflagorg' ? 'funzioni-organizzazioni' : (in_array($azione,['approvamodif','cancellamodif'],true)?'modifiche':'proposte');
    $audiences=[['scope'=>'accademia','id'=>0,'section'=>$section,'role'=>'admin']];
    $owner=(int)($academyProposal['idmaster'] ?? $academyProposal['idcreatore'] ?? $academyProposal['idpg'] ?? 0);
    if ($owner>0 && !in_array($azione,['vota','modifyvoto'],true)) { $audiences[]=['scope'=>'personale','id'=>$owner,'section'=>'accademia','role'=>'member']; }
    if(!in_array($azione,['vota','modifyvoto'],true)){
        if(in_array($academyKind,['org','corp'],true)){ $audiences[]=['scope'=>$academyKind==='org'?'organizzazioni':'corporazioni','id'=>(int)$academyTarget,'section'=>$azione==='setflagorg'?'flag':'statuto','role'=>'admin']; }
        elseif($academyKind==='obj'&&(int)($academyProposal['corp']??0)>0){ $audiences[]=['scope'=>'corporazioni','id'=>(int)$academyProposal['corp'],'section'=>'catalogo','role'=>'catalogue']; }
    }
    $titles=['vota'=>'Voto dell’Accademia registrato','modifyvoto'=>'Voto dell’Accademia aggiornato','approva'=>'Proposta approvata','cancella'=>'Proposta respinta','approvamodif'=>'Modifica approvata','cancellamodif'=>'Modifica respinta','setflagorg'=>'Funzioni dell’organizzazione aggiornate'];
    $name=(string)($academyProposal['nome'] ?? $academyProposal['shortDesc'] ?? 'Proposta');
    alphanoRecordActivity('academy_'.$azione,$pgId,$audiences,$titles[$azione],$name,
        ['kind'=>$academyKind,'target'=>$academyTarget,'before'=>$academyBefore,'after'=>$academyAfter]);
}

// --- RACCOLTA DATI e PULIZIA VECCHIE VOTAZIONI ---
$numVotanti = getNumVotanti();
purgeAccademia();

$orgNew  = getOrgNApprovati();
$corpNew = getCorpNApprovate();
$objNew  = getObjNApprovati();
$plays   = getGiocate();

$orgMod  = getOrgModificati();
$corpMod = getCorpModificati();
$objMod  = getObjModificati();


$proposals = [];
$collections = [
 ['rows'=>$orgNew, 'parameter'=>'org', 'type'=>'Organizzazione', 'modified'=>false, 'votes'=>'getVotiOrg', 'canVote'=>'canVoteOrg'],
 ['rows'=>$corpNew, 'parameter'=>'corp', 'type'=>'Corporazione', 'modified'=>false, 'votes'=>'getVotiCorp', 'canVote'=>'canVoteCorp'],
 ['rows'=>$objNew, 'parameter'=>'obj', 'type'=>'Oggetto', 'modified'=>false, 'votes'=>'getVotiObj', 'canVote'=>'canVoteObj'],
 ['rows'=>$plays, 'parameter'=>'giocata', 'type'=>'Giocata', 'modified'=>false, 'votes'=>'getVotiGiocata', 'canVote'=>'canVoteGiocata'],
 ['rows'=>$orgMod, 'parameter'=>'org', 'type'=>'Organizzazione', 'modified'=>true, 'votes'=>'getVotiOrg', 'canVote'=>'canVoteOrg'],
 ['rows'=>$corpMod, 'parameter'=>'corp', 'type'=>'Corporazione', 'modified'=>true, 'votes'=>'getVotiCorp', 'canVote'=>'canVoteCorp'],
 ['rows'=>$objMod, 'parameter'=>'obj', 'type'=>'Oggetto', 'modified'=>true, 'votes'=>'getVotiObj', 'canVote'=>'canVoteObj'],
];
foreach ($collections as $collection) {
    foreach ($collection['rows'] as $item) {
        $id=(int)$item['id']; $kind=$collection['parameter']; $modified=$collection['modified'];
        $title=$kind==='obj' ? $item['shortDesc'] : ($kind==='giocata' ? getNomeGiocata($id,$item['nome']) : $item['nome']);
        $owner=(int)($kind==='giocata' ? $item['idpg'] : ($kind==='obj' ? $item['idcreatore'] : ($modified ? $item['newidmaster'] : $item['idmaster'])));
        $url = match($kind) {
            'org'=>'/organizzazioni/'.($modified?'shownewstatuto.php':'showstatuto.php').'?org='.$id,
            'corp'=>'/corporazioni/'.($modified?'shownewstatuto.php':'showstatuto.php').'?corp='.$id,
            'obj'=>'/corporazioni/showobj.php?obj='.$id,
            default=>'/admin/giocata.php?giocata='.$id,
        };
        $proposals[]=['id'=>$id,'kind'=>$kind,'type'=>$collection['type'],'modified'=>$modified,
            'title'=>$title,'owner'=>$owner,'ownerName'=>GetPGFromID($owner),'url'=>$url,
            'votes'=>$collection['votes']($id),'canVote'=>$collection['canVote']($pgId,$id)];
    }
}
$approvedOrganizations = $admin ? getOrgApprovati() : [];
require_once __DIR__ . '/../templates/academy/index.php';
