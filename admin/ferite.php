<?php
declare(strict_types=1);
const ALPHANO_MSG_PARAMETRI_NON_ESAURIENTI_B98C = 'Parametri non esaurienti';
const ALPHANO_MSG_QUESTA_AZIONE_RISERVATA_AI_MASTE_66EF = 'Questa azione è riservata ai Master';

/********************************************************
 * admin/ferite.php
 * Amministrazione Ferite & Anime – PHP 8 + PDO
 ********************************************************/

/* ─────────────────── 1) Bootstrap ───────────────────────── */
require_once __DIR__ . '/../libs/kernel.inc.php';         // connectDB()
require_once __DIR__ . '/../libs/libpg.inc.php';          // GetIDPG()
require_once __DIR__ . '/../libs/libgrafica.inc.php';
require_once __DIR__ . '/../libs/session-control.inc.php';
require_once __DIR__ . '/../libs/libferite.inc.php';      // setFerite(), uccidi(), …
require_once __DIR__ . '/../libs/libequip.inc.php';       // consuma(), daiOggetto(), …
require_once __DIR__ . '/../libs/libeconomia.inc.php';   // validazione interi dei moduli
require_once __DIR__ . '/../chat/libs/chat-core.php';     // db(), insertIntoChat(), …



alphanoRequireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { alphanoRequireCsrf(); }
/* sessione + login */
initSession();
$idpg = GetIDPG(false);
if (!$idpg) {
    header('Location: /login.php');
    exit;
}

/* PDO singleton da chat-core */
$db = connectDB();

/* ─────────────────── 2) Permessi correnti ───────────────── */
$user = $db->query(
    'SELECT nome,
            posizione,
            chat,
            fatepower    AS master,
            adminmorti   AS resurrettore,
            adminferite  AS guaritore,
            admin        AS administrator
       FROM utenti
      WHERE id = ?',
    [$idpg]
)[0] ?? null;

if (!$user) { http_response_code(403); exit('Utente non trovato'); }

$chatRoom       = $user['chat'];
$posizione      = str_replace(' (Spettatore)', '', $user['posizione']);
$canMaster      = (bool) ($user['master'] || $user['administrator']);
$canResurrect   = (bool) ($user['resurrettore'] || $user['administrator']);
$canHurt        = (bool) ($user['guaritore'] || $user['administrator']);
if (!$canMaster && !$canResurrect && !$canHurt) { http_response_code(403); exit('Accesso negato'); }
$sessionRunning = false;
$partecipanti   = [];

/* sessione Master in corso? */
if ($canMaster && $chatRoom !== '') {
    $row = $db->query(
        'SELECT run, partecipanti
           FROM sessioni
          WHERE chat = ? AND run = 1',
        [$chatRoom]
    )[0] ?? null;

    if ($row) {
        $sessionRunning = true;
        $partecipanti   = array_filter(
            explode(';', trim($row['partecipanti'], ';'))
        );
    }
}

/* ─────────────────── 3) Gestione POST ───────────────────── */
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $replayed=false;
    try {
    $guard=alphanoSessionGuard($chatRoom);
    if(!(int)($db->query('SELECT GET_LOCK(?,5) locked',[$guard])[0]['locked']??0)){ throw new DomainException('La regia sta aggiornando la scena. Riprova.',409); }
    register_shutdown_function(static function()use($db,$guard){$db->query('SELECT RELEASE_LOCK(?)',[$guard]);});
    foreach ($_POST as $value) { if (!is_scalar($value)) { throw new InvalidArgumentException('Parametri non validi'); } }
    $action = (string)($_POST['azione'] ?? '');
    if (!in_array($action,['uccidi','ferisci','resuscita','ricompensa','oggetto','consuma','addnote'],true)) { throw new InvalidArgumentException('Azione non valida'); }
    $target = economiaIntero($_POST['pg'] ?? null,1);
    if ($target===null) { throw new InvalidArgumentException('Personaggio non valido'); }
    $requestKey=isset($_POST['_request_key']) ? (string)$_POST['_request_key'] : null;
    if(!$user['administrator']&&$canMaster&&(!in_array($action,['ferisci','uccidi'],true)||!$canHurt)){ alphanoSessionRequireManager($idpg,$chatRoom); }
    if ($chatRoom === '' || !$db->query('SELECT id FROM utenti WHERE id=? AND chat=?', [$target,$chatRoom])) {
        http_response_code(400); exit('Il personaggio deve essere presente nella stessa chat');
    }

    switch ($action) {

        /* ― Kill / Ferite ― */
        case 'uccidi':
            if ($target && ($canMaster || $canHurt)) {
                $modo = economiaIntero($_POST['modo'] ?? '0',0,1);
                if ($modo===null) { throw new InvalidArgumentException('Modo non valido'); }
                $msg  = uccidi($target, $idpg, $modo, $chatRoom)
                      ? 'Personaggio ucciso con successo'
                      : 'Impossibile uccidere il personaggio';
            } else { $msg = ALPHANO_MSG_PARAMETRI_NON_ESAURIENTI_B98C; }
            break;

        case 'ferisci':
            $pf   = economiaIntero($_POST['pf'] ?? null,1,maxpuntif);
            if ($pf===null) { throw new InvalidArgumentException('I PF devono essere un numero intero valido'); }
            $tipo = trim($_POST['tipo']   ?? '');
            if ($target && $pf > 0 && ($canMaster || $canHurt)) {
                $msg = setFerite($target, $pf, $tipo, $idpg, $chatRoom)
                     ? 'Personaggio ferito con successo'
                     : 'Impossibile ferire il personaggio';
            } else { $msg = ALPHANO_MSG_PARAMETRI_NON_ESAURIENTI_B98C; }
            break;

        /* ― Resurrezione ― */
        case 'resuscita':
            if ($target && $canResurrect) {
                $msg = resuscita($target, $idpg, $chatRoom)
                     ? 'Personaggio resuscitato con successo'
                     : 'Impossibile resuscitare il personaggio';
            } else { $msg = ALPHANO_MSG_PARAMETRI_NON_ESAURIENTI_B98C; }
            break;

        /* ― Ricompense Master (solo se sessione attiva) ― */
        case 'ricompensa':
            $soldi = economiaIntero($_POST['soldi'] ?? null,1,1000000000);
            if ($soldi===null) { throw new InvalidArgumentException(alphanoCurrencyText('Le Corone devono essere un numero intero da 1 a 1000000000')); }
            if (!$canMaster) { throw new DomainException(ALPHANO_MSG_QUESTA_AZIONE_RISERVATA_AI_MASTE_66EF,403); }
            if ($target && $target !== $idpg && $canMaster && $sessionRunning
                && in_array((string)$target, $partecipanti, true) && $soldi > 0) {
                $msg = ricompensa($target, $soldi, $idpg, $chatRoom,$requestKey,$replayed)
                     ? alphanoCurrencyText("ricompensa di $soldi Corone inviata")
                     : 'Impossibile ricompensare il personaggio';
            } else { throw new DomainException('Il premio richiede una sessione attiva e un partecipante diverso dal Master',400); }
            break;

        case 'oggetto':
            $objId = economiaIntero($_POST['oggetto'] ?? null,1);
            if ($objId===null) { throw new InvalidArgumentException('Oggetto non valido'); }
            if (!$canMaster) { throw new DomainException(ALPHANO_MSG_QUESTA_AZIONE_RISERVATA_AI_MASTE_66EF,403); }
            if ($target && $target !== $idpg && $canMaster && $sessionRunning
                && in_array((string)$target, $partecipanti, true) && $objId > 0) {
                $msg = daiOggetto($target, $objId, $idpg, $chatRoom,$requestKey,$replayed)
                     ? 'Oggetto consegnato con successo'
                     : 'Impossibile consegnare l’oggetto';
            } else { throw new DomainException('Il premio richiede una sessione attiva e un partecipante diverso dal Master',400); }
            break;

        /* ― consuma item ― */
        case 'consuma':
            $objId = economiaIntero($_POST['oggetto'] ?? null,1);
            $qty   = economiaIntero($_POST['quantita'] ?? null,1,2147483647);
            if ($objId===null || $qty===null) { throw new InvalidArgumentException('Oggetto e quantità devono essere numeri interi validi'); }
            if (!$canMaster) { throw new DomainException(ALPHANO_MSG_QUESTA_AZIONE_RISERVATA_AI_MASTE_66EF,403); }
            if ($target && $objId > 0 && $qty > 0 && $canMaster) {
                $msg = consumaMaster($target,$objId,$qty,$idpg,$chatRoom,$requestKey,$replayed)
                     ? 'Oggetto consumato con successo'
                     : 'Impossibile consumare l’oggetto';
            } else { $msg = ALPHANO_MSG_PARAMETRI_NON_ESAURIENTI_B98C; }
            break;

        /* ― Note Master ― */
        case 'addnote':
            $nota = trim($_POST['nota'] ?? '');
            if ($target && $nota !== '' && $canMaster) {
                $msg = notaMaster($target,$idpg,$chatRoom,$nota,$requestKey,$replayed,'Nota inserita dal pannello del Master')
                     ? 'Nota inserita con successo'
                     : 'Impossibile inserire la nota';
            } else { $msg = ALPHANO_MSG_PARAMETRI_NON_ESAURIENTI_B98C; }
            break;
        default: break;
    }
    } catch (Throwable $error) {
        $status=$error instanceof InvalidArgumentException ? 400 : (int)$error->getCode();
        if (!in_array($status,[400,403,409],true)) {
            $status=500;
            error_log('[admin ferite] '.$error->getMessage());
        }
        http_response_code($status);
        $msg=$status===500 ? 'Operazione non completata. Riprova.' : $error->getMessage();
    }

    /* messaggio di esito in chat */
    if ($msg !== '' && !$replayed && http_response_code()<400) {
        insertIntoChat(
            $chatRoom,
            '<DIV class="MasterKey">' . htmlspecialchars($msg) . '</DIV>'
        );
    }
}

/* ─────────────────── 4) Dati per i form ─────────────────── */
$inChat = ($posizione !== 'online' && $chatRoom !== '')
        ? $db->query('SELECT id, nome FROM utenti WHERE chat = ? ORDER BY nome', [$chatRoom])
        : [];

$deadList = $canResurrect
          ? $db->query(
                'SELECT id, nome FROM utenti
                  WHERE chat = ? AND razza IN (4,7,8)
               ORDER BY nome',
                [$chatRoom]
            )
          : [];

$equipList = $db->query(
    'SELECT id, shortDesc
       FROM equip
      WHERE approvato = 1 AND corp = 0
   ORDER BY shortDesc'
);

/* preload equip per “consuma” */
$equipData = [];
foreach ($inChat as $pg) {
    $equipData[$pg['id']] = $db->query(
        'SELECT ep.id, e.shortDesc
           FROM equip e
           JOIN equippg ep ON ep.uid = e.id
          WHERE ep.idpg = ?',
        [$pg['id']]
    );
}

/* ─────────────────── 5) HTML ────────────────────────────── */
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Ferite &amp; Anime – <?=htmlspecialchars($user['nome'])?></title>

</head>
<body>

<h1>Amministrazione Ferite &amp; Anime</h1>
<?php if ($msg): ?>
  <div class="message"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<div class="container">
<?php /* ---------------- pannelli ---------------- */ ?>

<?php if ($canResurrect): ?>
<form class="panel" method="post" id="resuscita"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>resuscita</header>
  <label for="fer-resuscita-pg">Personaggio</label>
  <select id="fer-resuscita-pg" name="pg" required>
    <option value="">– seleziona –</option>
    <?php foreach ($deadList as $p): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endforeach; ?>
  </select>
  <input type="hidden" name="azione" value="resuscita">
  <button>resuscita</button>
</form>
<?php endif; ?>

<?php if ($canMaster || $canHurt): ?>
<form class="panel" method="post" id="uccidi"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>Morte e stato spirituale</header>
  <label for="fer-uccidi-pg">Personaggio</label>
  <select id="fer-uccidi-pg" name="pg" required>
    <option value="">– seleziona –</option>
    <?php foreach ($inChat as $p): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endforeach; ?>
  </select>
  <label for="fer-modo">Modo</label>
  <select id="fer-modo" name="modo">
    <option value="0">Non-Morto</option>
    <option value="1">Spirito</option>
  </select>
  <input type="hidden" name="azione" value="uccidi">
  <button>uccidi</button>
</form>

<form class="panel" method="post" id="ferisci"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>Ferisci</header>
  <label for="fer-ferisci-pg">Personaggio</label>
  <select id="fer-ferisci-pg" name="pg" required>
    <option value="">– seleziona –</option>
    <?php foreach ($inChat as $p): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endforeach; ?>
  </select>
  <label for="fer-pf">Punti Ferita</label>
  <input id="fer-pf" type="number" name="pf" min="1" max="<?=maxpuntif?>" step="1" required>
  <label for="fer-tipo">Tipo Ferite</label>
  <input id="fer-tipo" type="text" name="tipo" required>
  <input type="hidden" name="azione" value="ferisci">
  <button>Ferisci</button>
</form>
<?php endif; ?>

<?php if ($canMaster && $sessionRunning): ?>
<form class="panel" method="post" id="ricompensa"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>ricompensa</header>
  <label for="fer-ricompensa-pg">Personaggio</label>
  <select id="fer-ricompensa-pg" name="pg" required>
    <option value="">– seleziona –</option>
    <?php foreach ($inChat as $p): if ($p['id'] != $idpg): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endif; endforeach; ?>
  </select>
  <label for="fer-soldi"><?=alphanoCurrencyHtml()?></label>
  <input id="fer-soldi" type="number" name="soldi" min="1" max="1000000000" step="1" required>
  <input type="hidden" name="azione" value="ricompensa">
  <input type="hidden" name="_request_key" value="<?=bin2hex(random_bytes(16))?>">
  <button>Consegna</button>
</form>

<form class="panel" method="post" id="oggetto"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>Oggetto</header>
  <label for="fer-oggetto-pg">Personaggio</label>
  <select id="fer-oggetto-pg" name="pg" required>
    <option value="">– seleziona –</option>
    <?php foreach ($inChat as $p): if ($p['id'] != $idpg): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endif; endforeach; ?>
  </select>
  <label for="fer-oggetto">Oggetto</label>
  <select id="fer-oggetto" name="oggetto" required>
    <option value="">– seleziona –</option>
    <?php foreach ($equipList as $e): ?>
      <option value="<?=$e['id']?>"><?=htmlspecialchars($e['shortDesc'])?></option>
    <?php endforeach; ?>
  </select>
  <input type="hidden" name="azione" value="oggetto">
  <input type="hidden" name="_request_key" value="<?=bin2hex(random_bytes(16))?>">
  <button>Consegna</button>
</form>
<?php endif; ?>

<?php if ($canMaster): ?>
<form class="panel" method="post" id="consuma"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>consuma</header>
  <label for="pg-cons">Personaggio</label>
  <select id="pg-cons" name="pg" onchange="onPGChange()" required>
    <option value="">– seleziona –</option>
    <?php foreach ($inChat as $p): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endforeach; ?>
  </select>
  <label for="obj-cons">Oggetto</label>
  <select id="obj-cons" name="oggetto" required>
    <option value="">– seleziona prima PG –</option>
  </select>
  <label for="fer-quantita">Quantità</label>
  <input id="fer-quantita" type="number" name="quantita" min="1" max="2147483647" step="1" required>
  <input type="hidden" name="azione" value="consuma">
  <input type="hidden" name="_request_key" value="<?=bin2hex(random_bytes(16))?>">
  <button>consuma</button>
</form>

<form class="panel" method="post" id="addnote"><?= '<input type="hidden" name="_csrf" value="' . htmlspecialchars(alphanoCsrfToken(), ENT_QUOTES) . '">' ?>
  <header>Inserisci Nota</header>
  <label for="fer-nota-pg">Personaggio</label>
  <select id="fer-nota-pg" name="pg" required>
    <option value="">– seleziona –</option>
    <?php foreach ($inChat as $p): ?>
      <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?></option>
    <?php endforeach; ?>
  </select>
  <label for="fer-nota">Nota</label>
  <textarea id="fer-nota" name="nota" rows="4" required></textarea>
  <input type="hidden" name="azione" value="addnote">
  <button>Inserisci Nota</button>
</form>
<?php endif; ?>
</div>

<script>
const equipData = <?=json_encode($equipData, JSON_UNESCAPED_UNICODE)?>;
function onPGChange() {
    const pg  = document.getElementById('pg-cons').value;
    const sel = document.getElementById('obj-cons');
    sel.innerHTML = '<option value="">– seleziona –</option>';
    if (equipData[pg]) {
        equipData[pg].forEach(o => sel.add(new Option(o.shortDesc, o.id)));
    }
}
</script>
</body>
</html>

