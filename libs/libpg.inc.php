<?php
declare(strict_types=1);
const ALPHANO_REGEX_JS_VAR = '/^[a-zA-Z_$][a-zA-Z0-9_$]*(?:\[\d+\])?$/D';

use Alphano\Database\PDOMySQLDriver;
const ALPHANO_MSG_INVALID_CARICA = 'Dati della carica non validi';
const ALPHANO_MSG_BADGE_SAVE = 'Impossibile salvare immagine';
const ALPHANO_MSG_CARICA_SAVE = 'Impossibile salvare la carica';
const ALPHANO_MSG_CARICA_NOT_FOUND = 'Carica non trovata';
const ALPHANO_BADGE_PATH_ORG = '/uploads/badges/organizzazioni/';
const ALPHANO_BADGE_PATH_CORP = '/uploads/badges/corporazioni/';

/** Errore durante il salvataggio di uno stemma di carica. */
class BadgeSaveException extends RuntimeException {}

require_once __DIR__ . '/kernel.inc.php';
require_once __DIR__ . '/astro.inc.php';
require_once __DIR__.'/alignments.inc.php';
alphanoSession();
/**
 * Inizializza la sessione PHP (una sola volta).
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        alphanoSession();
    }
}

/**
 * Restituisce l’istanza PDO riutilizzando kernel.inc.php.
 */
function getDB(): PDOMySQLDriver
{
    static $db;
    if (!$db) {
        $db = connectDB();
    }
    return $db;
}

/**
 * ---------- AUTENTICAZIONE ----------
 */

/**
 * Esegue il login: verifica credenziali, imposta sessione.
 *
 * @param string $username
 * @param string $password
 * @return bool
 */
function doLogin(string $username, string $password): bool
{
    initSession();
    $db = getDB();
    $rows = $db->query('SELECT id, nome, password FROM utenti WHERE nome = ? LIMIT 1', [$username]);
    if (count($rows) !== 1 || !alphanoPasswordVerify($password, $rows[0]['password'])) {return false;}
    // Migrazione progressiva: gli account esistenti continuano a funzionare.
    if (password_needs_rehash($rows[0]['password'], PASSWORD_DEFAULT)) {
        $column = $db->query("SHOW COLUMNS FROM utenti LIKE 'password'")[0]['Type'] ?? '';
        if (preg_match('/(?:varchar|char)\((\d+)\)/i', $column, $match) && (int)$match[1] >= 255) {
            $db->execute('UPDATE utenti SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $rows[0]['id']]);
        }
    }
    if (!headers_sent()) {session_regenerate_id(true);}
    $_SESSION['user_id']   = (int)$rows[0]['id'];
    $_SESSION['user_name'] = $rows[0]['nome'];
    return true;
}

/**
 * Svuota la sessione e disconnette.
 */
function doLogout(): void
{
    initSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

/**
 * Controlla se l’utente è loggato e restituisce l’ID.
 * Se $requireLogin=true blocca l’esecuzione con 403.
 *
 * @param bool $requireLogin
 * @return int|null
 */
function getIDPG($requireLogin = true): ?int
{
    // forziamo il cast: se arrivano 0/1 da int → li convertiamo
    $requireLogin = (bool)$requireLogin;
    initSession();
    if (empty($_SESSION['user_id'])) {
        if ($requireLogin) {
            header('HTTP/1.1 403 Forbidden');
            die('Devi essere loggato per accedere');
        }
        return null;
    }
    $id = (int)$_SESSION['user_id'];
    if ($id < 1 || !getDB()->query('SELECT 1 FROM utenti WHERE id = ?', [$id])) {
        unset($_SESSION['user_id'], $_SESSION['user_name']);
        if ($requireLogin) {alphanoFail('Sessione scaduta. Effettua nuovamente il login.', 403);}
        return null;
    }
    return $id;
}

/**
 * Restituisce il nome del PG a partire dall’ID (o null).
 */
function getPGFromID(?int $idpg): ?string
{
    if ($idpg === null) {
        return null;
    }
    $db = getDB();
    $rows = $db->query('SELECT nome FROM utenti WHERE id = ? LIMIT 1', [$idpg]);
    return $rows[0]['nome'] ?? null;
}

/**
 * ---------- UTILITÀ TEMPO & LUCE/LUNA ----------
 */





/**
 * ---------- POSIZIONE & STATISTICHE ----------
 */

/**
 * Aggiorna la posizione corrente del PG e tiene traccia dei PF e degli off‐line.
 */
function setPosizione(string $pos, int $idpg, string $chat = ''): void
{
    if(!empty($_SESSION['support_session_id'])){return;}
    $db  = getDB();
    $now = time();

    // 1) Mark offline tutti gli utenti fermi da >1h, **escludendo** l'utente corrente
    $cut = $now - 3600;
    $db->execute(
        "UPDATE utenti
            SET posizione = 'off', chat = ''
          WHERE lastmove <= ?
            AND posizione <> 'off'
            AND id <> ?",
        [$cut, $idpg]
    );

    // 2) aggiorno posizione+lastmove per l'utente corrente
    $db->execute(
        'UPDATE utenti
            SET posizione = ?, chat = ?, lastmove = ?
          WHERE id = ?',
        [$pos, $chat, $now, $idpg]
    );

    // 3) Inserisco statistica oraria (una volta al giorno per PG/ora)
    $h = (int)date('H', $now);
    $exists = $db->query(
        'SELECT 1
           FROM logs_statistica
          WHERE pg = ?
            AND ora = ?',
        [$idpg, $h]
    );
    if (count($exists) === 0) {
        $db->execute(
            'INSERT INTO logs_statistica (data, pg, ora)
             VALUES (?, ?, ?)',
            [$now, $idpg, $h]
        );
    }
    require_once __DIR__.'/lycan.inc.php';
    alphanoLycanSync($idpg);
}


/**
 * Formatta la posizione in testo leggibile.
 */
function getPosizione(string $pos): string
{
    return match($pos) {
        'off'    => 'Non Connesso',
        'online' => 'Parcheggiato',
        default  => htmlentities($pos, ENT_QUOTES, 'UTF-8'),
    };
}

/**
 * ---------- SEMPLICI LOOKUP ----------
 */
function getPGID(string $nomepg): ?int
{
    if ($nomepg === '') {
        return null;
    }
    $db = getDB();
    $rows = $db->query('SELECT id FROM utenti WHERE nome = ? LIMIT 1', [$nomepg]);
    return isset($rows[0]['id']) ? (int)$rows[0]['id'] : null;
}

function getAllineamento(int $idpg): ?string
{
    $db = getDB();
    $rows = $db->query('SELECT allineamento FROM utenti WHERE id = ? LIMIT 1', [$idpg]);
    return $rows[0]['allineamento'] ?? null;
}

function processAl(string $al): string
{
    return alphanoAlignment($al)['name']??$al;
}

function getRazza(int $r): string
{
    if ($r === 0) {return 'Non assegnata';}
    $db = getDB();
    $rows = $db->query('SELECT nome FROM razze WHERE id = ? LIMIT 1', [$r]);
    return $rows[0]['nome'] ?? 'Razza non configurata';
}

function getForzaRazza(int $r): ?int
{
    $db = getDB();
    $rows = $db->query('SELECT forza FROM razze WHERE id = ? LIMIT 1', [$r]);
    return isset($rows[0]['forza']) ? (int)$rows[0]['forza'] : null;
}

/**
 * Restituisce un array di [id, nome] per tutte le razze.
 */
function listaRazze(bool $visibili = false): array
{
    $db = getDB();
    if ($visibili) {
        return $db->query('SELECT * FROM razze ORDER BY nome ASC');
    }
    return $db->query(
        'SELECT razze.*
         FROM razze
         JOIN utenti ON razze.id=utenti.razza
         GROUP BY utenti.razza
         ORDER BY razze.nome ASC'
    );
}


/**
 * ---------- PROTEZIONE IP ----------
 */
function isLock(): bool
{
    // No real network addresses are preconfigured in the distributed engine.
    return false;
}

/* =========================================================
 *  UTILITÀ BREVETI BASE64
 * =======================================================*/
function criptaNome(string $plain): string   { return base64_encode($plain); }
function decriptaNome(string $crypt): string { return base64_decode($crypt); }

/* =========================================================
 *  SHORTCUT NOME PG & CREDENZIALI ADMIN
 * =======================================================*/
/**
 * Ritorna il nome del PG loggato (o null se guest).
 */
function getPGName(bool $requireLogin = true): ?string
{
    return getPGFromID(getIDPG($requireLogin));
}

/**
 * Con il nuovo sistema salviamo la chiave admin in sessione, non più in cookie criptati.
 */
function getAdminKey(): ?string
{
    initSession();
    return $_SESSION['admin_key'] ?? null;
}

function getPGPasswd(): ?string   // hashed o plain, dipende da come la salvi al login
{
    initSession();
    return $_SESSION['user_pass'] ?? null;
}

/* =========================================================
 *  RAZZE • ETNIE • ICONCINE
 * =======================================================*/
/**
 * Produci HTML dell’icona razza + eventuale etnia.
 */
function alphanoRaceIcon(): string
{
    return '/assets/avatar.svg';
}

function processRazza(int $razzaId, string|int $etniaId = 0): string
{
    if ($razzaId === ALPHANO_RACE_LEGACY_SPIRIT) {$razzaId = ALPHANO_RACE_SPIRIT;}
    $db     = getDB();
    $razza  = $db->query('SELECT nome FROM razze WHERE id=?', [$razzaId])[0]['nome'] ?? '';
    $etniaT = is_numeric($etniaId) && (int)$etniaId > 0 ? ' ' . htmlspecialchars(getEtnia((int)$etniaId) ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';

    $file = alphanoRaceIcon();

    return sprintf(
        '<img src="%s" class="race-emblem" width="26" height="26" alt="%s%s" title="%s%s" />',
        $file,
        htmlentities($razza),
        $etniaT,
        htmlentities($razza),
        $etniaT
    );
}

function getEtnia(int $id): ?string
{
    $db = getDB();
    return $db->query('SELECT nome FROM etnie WHERE id=?', [$id])[0]['nome'] ?? null;
}

function listaEtnie(): array
{
    $db = getDB();
    return $db->query('SELECT * FROM etnie ORDER BY nome');
}

/* =========================================================
 *  FIRMA PG & ARALDICHE
 * =======================================================*/
function firmaPG(int $idpg): string
{
    $db  = getDB();
    $row = $db->query(
        'SELECT nome, firma,
                caricaorganizzazione, caricacorporazione,
                araldica, organizzazione, corporazione
           FROM utenti WHERE id=?',
        [$idpg]
    )[0] ?? [];

    if (!empty($row['firma'])) {
        return "\n\n".$row['firma'];
    }

    $stamp   = [];
    $stamp[] = $row['nome'] ?? '';

    /* Organizzazione */
    if ($row['organizzazione']) {
        $orgName = $db->query('SELECT nome FROM organizzazioni WHERE id=?', [$row['organizzazione']])[0]['nome'] ?? '';
        $carName = $db->query('SELECT nome FROM caricheorganizzazioni WHERE id=?', [$row['caricaorganizzazione']])[0]['nome'] ?? '';
        $stamp[] = sprintf('[i]%s[/i]%s dell’Organizzazione %s',alphanoBadgeUrl('organizzazioni',(int)$row['caricaorganizzazione'],(int)$row['organizzazione']),$carName,$orgName);
    }

    /* Corporazione */
    if ($row['corporazione']) {
        $corpName = $db->query('SELECT nome FROM corporazioni WHERE id=?', [$row['corporazione']])[0]['nome'] ?? '';
        $carName  = $db->query('SELECT nome FROM carichecorporazioni WHERE id=?', [$row['caricacorporazione']])[0]['nome'] ?? '';
        $stamp[]  = sprintf('[i]%s[/i]%s della Corporazione %s',alphanoBadgeUrl('corporazioni',(int)$row['caricacorporazione'],(int)$row['corporazione']),$carName,$corpName);
    }

    /* Araldica */
    $nomeAr = getAraldica($row['araldica'] ?? 0) ?? '';
    $stamp[] = sprintf('[i]%s[/i]%s',alphanoBadgeUrl('cariche',(int)$row['araldica']),$nomeAr);

    return "\n\n".implode("\n", array_filter($stamp));
}

function getAraldica(int $id): ?string
{
    $db = getDB();
    return $db->query('SELECT nome FROM araldiche WHERE id=?', [$id])[0]['nome'] ?? null;
}

/* =========================================================
 *  FIRMA PG & ARALDICHE  ─ processAraldica()
 * =======================================================*/

/**
 * Ritorna l’elemento <img> per lo stemma araldico del PG.
 *
 * ✔️ Compatibile PHP 8 (type-hint)
 * ✔️ Se $jsVar è passato, attacca gli handler onmouseover / onmouseout.
 * ✔️ Se non c’è descrizione, usa alt/title con il nome nello stemma.
 *
 * @param int    $id     id della voce in tabella «araldiche»
 * @param string $jsVar  testo JS da mostrare nel tooltip ('' = alt/title)
 * @return string        markup HTML completo
 */
/** Percorso pubblico di uno stemma, con fallback per gli asset storici assenti. */
function alphanoBadgeUrl(string $type, int $id, int $scope = 0): string
{
    if (!in_array($type, ['cariche','organizzazioni','corporazioni'], true)) {throw new InvalidArgumentException('Tipo stemma non valido');}
    $path = '/uploads/badges/' . $type . '/' . ($scope > 0 ? $scope . '/' : '') . max(0,$id) . '.gif';
    foreach ([$path] as $candidate) {
        foreach ([htmlroot . $candidate, dirname(__DIR__) . $candidate] as $file) {
            if (!is_file($file)) {continue;}
            // Rank insignia remain editable through the existing GIF upload.
            // Refresh cached art after either a release or an administrator upload.
            return $candidate . '?v=' . filemtime($file);
        }
    }
    return '/assets/placeholder.svg';
}

function processAraldica(int $id, string $jsVar = ''): string
{
    // Recupero nome araldica (title/alt) – ritorno stringa vuota se non esiste
    $nome = getAraldica($id);
    if ($nome === null) {
        return '';
    }

    // Path immagine (la tua struttura è /uploads/badges/cariche/<id>.gif)
    $src = alphanoBadgeUrl('cariche', $id);

    // Attributi accessibilità + eventuale tooltip JS
    if (preg_match(ALPHANO_REGEX_JS_VAR, $jsVar)) {
        $attr = "onmouseover=\"MostraDescrizione('hint',{$jsVar})\" " .
                "onmouseout=\"NascondiDescrizione('hint')\"";
    } else {
        $safe = htmlentities($nome, ENT_QUOTES | ENT_SUBSTITUTE);
        $attr = "alt=\"{$safe}\" title=\"{$safe}\"";
    }

    // Sempre 25×25 come nel codice originario
    return "<img src=\"{$src}\" width=\"25\" height=\"25\" {$attr} />";
}


function listaAraldiche(): array
{
    return getDB()->query('SELECT id, nome, city FROM araldiche ORDER BY nome');
}

/* ----------  Add & Modify Araldica  ------------------------------------ */
/** Validazione comune dei dati di una carica: null se tutto e' valido. */
function alphanoValidateCarica(string $type, int $group, int $ramo, string $nome, int $livello, array $file): ?string
{
    if (!alphanoBadgeScope($type, $group, $ramo) || trim($nome) === '' || $livello < 0) {
        return ALPHANO_MSG_INVALID_CARICA;
    }
    $valid = validaUpload($file);
    return $valid === true ? null : $valid;
}

/** Verifica che l'ammissione a un gruppo sia consentita. */
function alphanoCanAdmit(?array $user, ?int $current, ?array $rank, bool $admit, int $groupId): bool
{
    if ($user === null) {
        return false;
    }
    if ($admit) {
        return $rank !== null && ($current === 0 || $current === $groupId);
    }
    return $current === $groupId;
}

/**
 * @param string $nome
 * @param bool $prima
 * @param int $city
 * @param string $descrizione
 * @param int $ramo
 * @param int $livello
 * @param array $file
 */
function addAraldica(...$args): string
{
    [$nome, $prima, $city, $descrizione, $ramo, $livello, $file] = $args;
    $error = alphanoValidateCarica('araldiche', $city, $ramo, $nome, $livello, $file);
    if ($error !== null) {
        return $error;
    }
    $db = getDB();
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $db->execute('INSERT INTO araldiche (nome,prima,city,ramo,livello,descrizione) VALUES (?,?,?,?,?,?)', [$nome, $prima ? 1 : 0, $city, $ramo, $livello, $descrizione]);
        $id = (int)$db->lastInsertId();
        if (!alphanoSaveBadge($file, '/uploads/badges/cariche/' . $id . '.gif')) {
            throw new BadgeSaveException(ALPHANO_MSG_BADGE_SAVE);
        }
        if ($owned) {
            $db->commit();
        }
        return 'Carica inserita con successo';
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        return ALPHANO_MSG_CARICA_SAVE;
    }
}

/**
 * @param int $id
 * @param string $nome
 * @param bool $prima
 * @param int $city
 * @param string $descrizione
 * @param int $ramo
 * @param int $livello
 * @param array $file
 */
function modifyAraldica(...$args): string
{
    [$id, $nome, $prima, $city, $descrizione, $ramo, $livello, $file] = $args;
    $error = alphanoValidateCarica('araldiche', $city, $ramo, $nome, $livello, $file);
    if ($error !== null) {
        return $error;
    }
    $db = getDB();
    if (!($db->query('SELECT id FROM araldiche WHERE id=? AND city=?', [$id, $city]))) {
        return ALPHANO_MSG_CARICA_NOT_FOUND;
    }
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $db->execute('UPDATE araldiche SET nome=?,prima=?,city=?,ramo=?,livello=?,descrizione=? WHERE id=? AND city=?', [$nome, $prima ? 1 : 0, $city, $ramo, $livello, $descrizione, $id, $city]);
        if (!alphanoSaveBadge($file, '/uploads/badges/cariche/' . $id . '.gif')) {
            throw new BadgeSaveException(ALPHANO_MSG_BADGE_SAVE);
        }
        if ($owned) {
            $db->commit();
        }
        $result = 'Carica aggiornata';
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        $result = ALPHANO_MSG_CARICA_SAVE;
    }
    return $result;
}

/* =========================================================
 *  CITY – CITTADINANZA
 * =======================================================*/
function getListaCity(bool $elezioni = false): array
{
    $sql = 'SELECT * FROM city'.($elezioni ? ' WHERE elezioni=1' : '').' ORDER BY city';
    return getDB()->query($sql);
}

function getListaRamiCity(bool $all, int $city): array
{
    $db  = getDB();
    $sql = $all
        ? 'SELECT * FROM ramiaraldiche ORDER BY livello DESC'
        : 'SELECT * FROM ramiaraldiche WHERE city=? ORDER BY livello DESC';

    $rows = $db->query($sql, $all ? [] : [$city]);
    $out  = [];
    foreach ($rows as $r) {
        $out[$r['city']][$r['id']] = $r;
    }
    return $out;
}

function getGerarchiaCity(int $city): array
{
    return getDB()->query(
        'SELECT a.*, r.nome AS nomeramo, r.livello
           FROM araldiche a
           JOIN ramiaraldiche r ON r.id=a.ramo
          WHERE a.city=?
          ORDER BY r.livello, a.livello',
        [$city]
    );
}

function makeRichiestaCity(int $city, int $idpg): void
{
    $db = getDB();
    $db->execute('INSERT INTO richieste (idpg, data, city) VALUES (?,?,?)', [$idpg, time(), $city]);

    $admins = $db->query(
        'SELECT u.id
           FROM utenti u
           JOIN araldiche a ON a.id=u.araldica
          WHERE a.city=? AND u.sindaco=1',
        [$city]
    );
    foreach ($admins as $ad) {
        inviaMissiva(
            'Notifica Richiesta di Cittadinanza',
            'Hai ricevuto una nuova richiesta di ammissione',
            (int)$ad['id']
        );
    }
}

function getRichiesteCity(int $city): array
{
    return getDB()->query(
        'SELECT idpg, data FROM richieste WHERE city=? ORDER BY data',
        [$city]
    );
}

function getRichiesteCityPG(int $pg): int
{
    return (int)getDB()->query(
        'SELECT COUNT(*) AS num FROM richieste WHERE idpg=? AND city<>0',
        [$pg]
    )[0]['num'];
}

function getLastCaricaCity(int $city): ?int
{
    $row = getDB()->query(
        'SELECT id FROM araldiche WHERE city=? AND prima=1 LIMIT 1',
        [$city]
    );
    return $row[0]['id'] ?? null;
}

function getPGCity(int $idpg): ?int
{
    $row = getDB()->query(
        'SELECT a.city
           FROM utenti u
           JOIN araldiche a ON a.id=u.araldica
          WHERE u.id=?',
        [$idpg]
    );
    return $row[0]['city'] ?? null;
}

/* =========================================================
 *  RICHIESTE (contatori rapidi)
 * =======================================================*/
function getRichiesteOrgPG(int $pg): int
{
    return (int)getDB()->query(
        'SELECT COUNT(*) AS num FROM richieste WHERE idpg=? AND organizzazione<>0',
        [$pg]
    )[0]['num'];
}

function getRichiesteCorpPG(int $pg): int
{
    return (int)getDB()->query(
        'SELECT COUNT(*) AS num FROM richieste WHERE idpg=? AND corporazione<>0',
        [$pg]
    )[0]['num'];
}

/* =========================================================
 *  ORGANIZZAZIONI
 * =======================================================*/
function getOrg(int $id, bool $nessuna = true): string
{
    $row = getDB()->query('SELECT nome FROM organizzazioni WHERE id=?', [$id])[0]['nome'] ?? '';
    if ($row !== '') {
        return $row;
    }
    return $nessuna ? 'Nessuna' : '';
}

function getCaricaOrg(int $org, int $carica, bool $appendName = true, string $jsVar = ''): string
{
    $row = getDB()->query(
        'SELECT nome, descrizione FROM caricheorganizzazioni WHERE organizzazione=? AND id=?',
        [$org, $carica]
    )[0] ?? null;
    if (!$row) { return 'Nessuna'; }

    $jsVar = preg_match(ALPHANO_REGEX_JS_VAR, $jsVar) ? $jsVar : '';
    $attr = $jsVar && $row['descrizione']
        ? "onmouseover=\"MostraDescrizione('hint',$jsVar)\" onmouseout=\"NascondiDescrizione('hint')\""
        : 'alt="'.htmlentities($row['nome']).'" title="'.htmlentities($row['nome']).'"';

    $img = sprintf('<img src="%s" width="25" height="25" %s />',
                   alphanoBadgeUrl('organizzazioni', $carica, $org), $attr);

    return $appendName ? $img.'&nbsp;'.htmlspecialchars($row['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $img;
}

function getOrgMasters(int $org): array
{
    return getDB()->query(
        'SELECT id, nome FROM utenti WHERE organizzazione=? AND adminorganizzazione=1 ORDER BY nome',
        [$org]
    );
}

function listaOrg(): array
{
    return getDB()->query('SELECT id, nome FROM organizzazioni WHERE approvato=1 ORDER BY nome');
}

function getListaRamiOrg(int $org): array
{
    $rows = getDB()->query(
        'SELECT id, nome FROM ramiorganizzazioni WHERE organizzazione=? ORDER BY livello DESC',
        [$org]
    );
    $out = [];
    foreach ($rows as $r) { $out[$r['id']] = $r['nome']; }
    return $out;
}

function getListaCaricheOrg(int $org): array
{
    return getDB()->query(
        'SELECT * FROM caricheorganizzazioni WHERE organizzazione=? ORDER BY nome',
        [$org]
    );
}

function getGerarchiaOrg(int $org): array
{
    return getDB()->query(
        'SELECT o.id      AS idorg,
                o.nome    AS nomeorg,
                c.id      AS idcarica,
                c.nome    AS nomecarica,
                c.prima,
                c.descrizione,
                c.ramo,
                r.nome    AS nomeramo,
                r.livello
           FROM organizzazioni o
           JOIN caricheorganizzazioni c ON c.organizzazione=o.id
           JOIN ramiorganizzazioni r     ON r.id=c.ramo
          WHERE o.id=?
          ORDER BY r.livello, c.ramo, c.livello',
        [$org]
    );
}

function makeRichiestaOrg(int $org, int $pg): bool
{
    $db=getDB();
    $db->beginTransaction();
    try {
        $user=$db->query('SELECT id, organizzazione FROM utenti WHERE id=? FOR UPDATE',[$pg])[0]??null;
        $group=$db->query('SELECT id FROM organizzazioni WHERE id=? AND approvato=1',[$org]);
        if (!$user || !$group || (int)$user['organizzazione']!==0 || $db->query('SELECT 1 FROM richieste WHERE idpg=? AND organizzazione=?',[$pg,$org])) { $db->rollBack(); return false; }
        $db->execute('INSERT INTO richieste(idpg,data,organizzazione) VALUES(?,?,?)',[$pg,time(),$org]);
        $admins=$db->query('SELECT id FROM utenti WHERE organizzazione=? AND adminorganizzazione=1',[$org]);
        require_once __DIR__.'/libposta.inc.php';
        foreach($admins as $admin) {inviaMissiva('Richiesta di Ammissione','Hai ricevuto una nuova richiesta di ammissione.',(int)$admin['id']);}
        $db->commit();return true;
    } catch(Throwable $e) { if($db->inTransaction()){$db->rollBack();} throw $e; }
}

function getRichiesteOrg(int $org): array
{
    return getDB()->query(
        'SELECT idpg, data FROM richieste WHERE organizzazione=? ORDER BY data',
        [$org]
    );
}

function admitPGOrg(int $pg, int $org, int $carica, bool $admit): bool
{
    $db = getDB();
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $user = $db->query('SELECT id,organizzazione FROM utenti WHERE id=? FOR UPDATE', [$pg])[0] ?? null;
        $rank = $admit ? ($db->query('SELECT id,ramo FROM caricheorganizzazioni WHERE id=? AND organizzazione=?', [$carica, $org])[0] ?? null) : null;
        $current = $user === null ? null : (int)$user['organizzazione'];
        if (!alphanoCanAdmit($user, $current, $rank, $admit, $org)) {
            if ($owned) {
                $db->rollBack();
            }
            return false;
        }
        $db->execute('DELETE FROM richieste WHERE organizzazione=? AND idpg=?', [$org, $pg]);
        $db->execute('UPDATE utenti SET organizzazione=?,caricaorganizzazione=?,ramoorganizzazione=? WHERE id=?', [$admit ? $org : 0, $admit ? $carica : 0, $admit ? (int)$rank['ramo'] : 0, $pg]);
        if (!$admit) {
            $db->execute('UPDATE utenti SET adminorganizzazione=0 WHERE id=?', [$pg]);
        }
        if ($owned) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function getLastCaricaOrg(int $org): ?int
{
    $row = getDB()->query(
        'SELECT id FROM caricheorganizzazioni WHERE organizzazione=? AND prima=1 LIMIT 1',
        [$org]
    );
    return $row[0]['id'] ?? null;
}

/* ----------  Add / Modify / Drop CARICHE ORG  --------------------------- */
/**
 * @param int $org
 * @param string $nome
 * @param bool $prima
 * @param int $ramo
 * @param int $livello
 * @param string $descrizione
 * @param array $file
 */
function addCaricaOrg(...$args): string
{
    [$org, $nome, $prima, $ramo, $livello, $descrizione, $file] = $args;
    $error = alphanoValidateCarica('organizzazioni', $org, $ramo, $nome, $livello, $file);
    if ($error !== null) {
        return $error;
    }
    $db = getDB();
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $db->execute('INSERT INTO caricheorganizzazioni (nome,prima,organizzazione,ramo,livello,descrizione) VALUES (?,?,?,?,?,?)', [$nome, $prima ? 1 : 0, $org, $ramo, $livello, $descrizione]);
        $id = (int)$db->lastInsertId();
        if (!alphanoSaveBadge($file, ALPHANO_BADGE_PATH_ORG . $org . '/' . $id . '.gif')) {
            throw new BadgeSaveException(ALPHANO_MSG_BADGE_SAVE);
        }
        if ($owned) {
            $db->commit();
        }
        return 'Carica organizzazione creata';
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        return ALPHANO_MSG_CARICA_SAVE;
    }
}

/**
 * @param int $org
 * @param int $id
 * @param string $nome
 * @param bool $prima
 * @param int $ramo
 * @param int $livello
 * @param string $descrizione
 * @param array $file
 */
function modifyCaricaOrg(...$args): string
{
    [$org, $id, $nome, $prima, $ramo, $livello, $descrizione, $file] = $args;
    $error = alphanoValidateCarica('organizzazioni', $org, $ramo, $nome, $livello, $file);
    if ($error !== null) {
        return $error;
    }
    $db = getDB();
    if (!($db->query('SELECT id FROM caricheorganizzazioni WHERE id=? AND organizzazione=?', [$id, $org]))) {
        return ALPHANO_MSG_CARICA_NOT_FOUND;
    }
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $db->execute('UPDATE caricheorganizzazioni SET nome=?,prima=?,organizzazione=?,ramo=?,livello=?,descrizione=? WHERE id=? AND organizzazione=?', [$nome, $prima ? 1 : 0, $org, $ramo, $livello, $descrizione, $id, $org]);
        if (!alphanoSaveBadge($file, ALPHANO_BADGE_PATH_ORG . $org . '/' . $id . '.gif')) {
            throw new BadgeSaveException(ALPHANO_MSG_BADGE_SAVE);
        }
        if ($owned) {
            $db->commit();
        }
        $result = 'Carica organizzazione aggiornata';
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        $result = ALPHANO_MSG_CARICA_SAVE;
    }
    return $result;
}

function dropRamoOrg(int $org, int $ramo): bool
{
    $db = getDB();
    $used = $db->query(
        'SELECT COUNT(*) AS num FROM caricheorganizzazioni WHERE organizzazione=? AND ramo=?',
        [$org, $ramo]
    )[0]['num'];
    if ($used) { return false; }

    $db->execute('DELETE FROM ramiorganizzazioni WHERE organizzazione=? AND id=?', [$org, $ramo]);
    return true;
}

function dropCaricaOrg(int $org, int $id): bool
{
    $db=getDB();
    if($db->query('SELECT 1 FROM utenti WHERE organizzazione=? AND caricaorganizzazione=? LIMIT 1',[$org,$id])){return false;}
    $deleted=$db->execute('DELETE FROM caricheorganizzazioni WHERE organizzazione=? AND id=?',[$org,$id]);
    $file=htmlroot.ALPHANO_BADGE_PATH_ORG.$org.'/'.$id.'.gif';
    if($deleted && is_file($file)){unlink($file);}
    return $deleted>0;
}

function getPGInOrg(int $org): array
{
    return getDB()->query(
        'SELECT id, nome FROM utenti WHERE organizzazione=? ORDER BY nome',
        [$org]
    );
}

/* =========================================================
 *  CORPORAZIONI  (stessa logica delle organizzazioni)
 * =======================================================*/
function getCorporazione(int $id): string
{
    $row = getDB()->query('SELECT nome FROM corporazioni WHERE id=?', [$id])[0]['nome'] ?? '';
    return $row ?: 'Nessuna';
}

function getCaricaCorp(int $corp, int $carica, bool $appendName = true, string $jsVar = ''): string
{
    $row = getDB()->query(
        'SELECT nome, descrizione FROM carichecorporazioni WHERE corporazione=? AND id=?',
        [$corp, $carica]
    )[0] ?? null;
    if (!$row) { return 'Nessuna'; }

    $jsVar = preg_match(ALPHANO_REGEX_JS_VAR, $jsVar) ? $jsVar : '';
    $attr = $jsVar && $row['descrizione']
        ? "onmouseover=\"MostraDescrizione('hint',$jsVar)\" onmouseout=\"NascondiDescrizione('hint')\""
        : 'alt="'.htmlentities($row['nome']).'" title="'.htmlentities($row['nome']).'"';

    $img = sprintf('<img src="%s" width="25" height="25" %s />',
                   alphanoBadgeUrl('corporazioni', $carica, $corp), $attr);
    return $appendName ? $img.'&nbsp;'.htmlspecialchars($row['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $img;
}

function getCorpMasters(int $corp): array
{
    return getDB()->query(
        'SELECT id, nome FROM utenti WHERE corporazione=? AND admincorporazione=1 ORDER BY nome',
        [$corp]
    );
}

function listaCorporazioni(): array
{
    return getDB()->query('SELECT id, nome FROM corporazioni WHERE approvato=1 ORDER BY nome');
}

function getListaRamiCorp(int $corp): array
{
    $rows = getDB()->query(
        'SELECT id, nome FROM ramicorporazioni WHERE corporazione=? ORDER BY livello DESC',
        [$corp]
    );
    $out = [];
    foreach ($rows as $r) { $out[$r['id']] = $r['nome']; }
    return $out;
}

function getListaCaricheCorp(int $corp): array
{
    return getDB()->query(
        'SELECT * FROM carichecorporazioni WHERE corporazione=? ORDER BY nome',
        [$corp]
    );
}

function getGerarchiaCorp(int $corpId)
{
    return getDB()->query(
        <<<SQL
        SELECT
          c.id           AS idcarica,
          c.corporazione AS corporazione,   -- il vero nome del campo
          c.livello,
          c.prima, c.stipendio, c.provvigione,
          c.ramo,
          r.nome         AS nomeramo,
          c.nome         AS nomecarica
        FROM carichecorporazioni AS c
        LEFT JOIN ramicorporazioni AS r
          ON r.id = c.ramo
        WHERE c.corporazione = ?
        ORDER BY c.livello, c.ramo, c.id
        SQL
    , [$corpId]);
}


function makeRichiestaCorp(int $corp, int $pg): bool
{
    $db=getDB();
    $db->beginTransaction();
    try {
        $user=$db->query('SELECT id, corporazione FROM utenti WHERE id=? FOR UPDATE',[$pg])[0]??null;
        $group=$db->query('SELECT id FROM corporazioni WHERE id=? AND approvato=1',[$corp]);
        if (!$user || !$group || (int)$user['corporazione']!==0 || $db->query('SELECT 1 FROM richieste WHERE idpg=? AND corporazione=?',[$pg,$corp])) { $db->rollBack(); return false; }
        $db->execute('INSERT INTO richieste(idpg,data,corporazione) VALUES(?,?,?)',[$pg,time(),$corp]);
        $admins=$db->query('SELECT id FROM utenti WHERE corporazione=? AND admincorporazione=1',[$corp]);
        require_once __DIR__.'/libposta.inc.php';
        foreach($admins as $admin) {inviaMissiva('Richiesta di Ammissione','Hai ricevuto una nuova richiesta di ammissione.',(int)$admin['id']);}
        $db->commit();return true;
    } catch(Throwable $e) { if($db->inTransaction()){$db->rollBack();} throw $e; }
}

function getRichiesteCorp(int $corp): array
{
    return getDB()->query(
        'SELECT idpg, data FROM richieste WHERE corporazione=? ORDER BY data',
        [$corp]
    );
}

function admitPGCorp(int $pg, int $corp, int $carica, bool $admit): bool
{
    $db = getDB();
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $user = $db->query('SELECT id,corporazione FROM utenti WHERE id=? FOR UPDATE', [$pg])[0] ?? null;
        $rank = $admit ? ($db->query('SELECT id,ramo FROM carichecorporazioni WHERE id=? AND corporazione=?', [$carica, $corp])[0] ?? null) : null;
        $current = $user === null ? null : (int)$user['corporazione'];
        if (!alphanoCanAdmit($user, $current, $rank, $admit, $corp)) {
            if ($owned) {
                $db->rollBack();
            }
            return false;
        }
        $db->execute('DELETE FROM richieste WHERE corporazione=? AND idpg=?', [$corp, $pg]);
        $db->execute('UPDATE utenti SET corporazione=?,caricacorporazione=?,ramocorporazione=? WHERE id=?', [$admit ? $corp : 0, $admit ? $carica : 0, $admit ? (int)$rank['ramo'] : 0, $pg]);
        if (!$admit) {
            $db->execute('UPDATE utenti SET admincorporazione=0 WHERE id=?', [$pg]);
        }
        if ($owned) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function getLastCaricaCorp(int $corp): ?int
{
    $row = getDB()->query(
        'SELECT id FROM carichecorporazioni WHERE corporazione=? AND prima=1 LIMIT 1',
        [$corp]
    );
    return $row[0]['id'] ?? null;
}

/* ----------  Add / Modify / Drop CARICHE CORP  --------------------------- */
/**
 * @param int $corp
 * @param string $nome
 * @param bool $prima
 * @param int $ramo
 * @param int $livello
 * @param string $descrizione
 * @param float $stipendio
 * @param float $provvigione
 * @param array $file
 */
function addCaricaCorp(...$args): string
{
    [$corp, $nome, $prima, $ramo, $livello, $descrizione, $stipendio, $provvigione, $file] = $args;
    $error = alphanoValidateCarica('corporazioni', $corp, $ramo, $nome, $livello, $file);
    if ($error !== null) {
        return $error;
    }
    $db = getDB();
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $db->execute('INSERT INTO carichecorporazioni (nome,prima,corporazione,ramo,livello,descrizione,stipendio,provvigione) VALUES (?,?,?,?,?,?,?,?)', [$nome, $prima ? 1 : 0, $corp, $ramo, $livello, $descrizione, $stipendio, $provvigione]);
        $id = (int)$db->lastInsertId();
        if (!alphanoSaveBadge($file, ALPHANO_BADGE_PATH_CORP . $corp . '/' . $id . '.gif')) {
            throw new BadgeSaveException(ALPHANO_MSG_BADGE_SAVE);
        }
        if ($owned) {
            $db->commit();
        }
        return 'Carica corporazione creata';
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        return ALPHANO_MSG_CARICA_SAVE;
    }
}

/**
 * @param int $corp
 * @param int $id
 * @param string $nome
 * @param bool $prima
 * @param int $ramo
 * @param int $livello
 * @param string $descrizione
 * @param float $stipendio
 * @param float $provvigione
 * @param array $file
 */
function modifyCaricaCorp(...$args): string
{
    [$corp, $id, $nome, $prima, $ramo, $livello, $descrizione, $stipendio, $provvigione, $file] = $args;
    $error = alphanoValidateCarica('corporazioni', $corp, $ramo, $nome, $livello, $file);
    if ($error !== null) {
        return $error;
    }
    $db = getDB();
    if (!($db->query('SELECT id FROM carichecorporazioni WHERE id=? AND corporazione=?', [$id, $corp]))) {
        return ALPHANO_MSG_CARICA_NOT_FOUND;
    }
    $owned = !$db->inTransaction();
    if ($owned) {
        $db->beginTransaction();
    }
    try {
        $db->execute('UPDATE carichecorporazioni SET nome=?,prima=?,corporazione=?,ramo=?,livello=?,descrizione=?,stipendio=?,provvigione=? WHERE id=? AND corporazione=?', [$nome, $prima ? 1 : 0, $corp, $ramo, $livello, $descrizione, $stipendio, $provvigione, $id, $corp]);
        if (!alphanoSaveBadge($file, ALPHANO_BADGE_PATH_CORP . $corp . '/' . $id . '.gif')) {
            throw new BadgeSaveException(ALPHANO_MSG_BADGE_SAVE);
        }
        if ($owned) {
            $db->commit();
        }
        $result = 'Carica corporazione aggiornata';
    } catch (Throwable $e) {
        if ($owned && $db->inTransaction()) {
            $db->rollBack();
        }
        $result = ALPHANO_MSG_CARICA_SAVE;
    }
    return $result;
}

function dropRamoCorp(int $corp, int $ramo): bool
{
    $db = getDB();
    $used = $db->query(
        'SELECT COUNT(*) AS num FROM carichecorporazioni WHERE corporazione=? AND ramo=?',
        [$corp, $ramo]
    )[0]['num'];
    if ($used) { return false; }

    $db->execute('DELETE FROM ramicorporazioni WHERE corporazione=? AND id=?', [$corp, $ramo]);
    return true;
}

function dropCaricaCorp(int $corp, int $id): bool
{
    $db=getDB();
    if($db->query('SELECT 1 FROM utenti WHERE corporazione=? AND caricacorporazione=? LIMIT 1',[$corp,$id])){return false;}
    $deleted=$db->execute('DELETE FROM carichecorporazioni WHERE corporazione=? AND id=?',[$corp,$id]);
    $file=htmlroot.ALPHANO_BADGE_PATH_CORP.$corp.'/'.$id.'.gif';
    if($deleted && is_file($file)){unlink($file);}
    return $deleted>0;
}

function getPGInCorp(int $corp): array
{
    return getDB()->query(
        'SELECT id, nome FROM utenti WHERE corporazione=? ORDER BY nome',
        [$corp]
    );
}

/* =========================================================
 *  UMORI
 * =======================================================*/
function getListaUmori(): array
{
    return getDB()->query('SELECT id, nome FROM umori ORDER BY nome');
}

function getUmore(int $id): string
{
    $row = getDB()->query('SELECT nome FROM umori WHERE id=?', [$id])[0] ?? null;
    return $row
        ? sprintf('<img src="/assets/placeholder.svg" alt="%s" title="%s" />',
                  htmlentities($row['nome']), htmlentities($row['nome']))
        : '';
}

/* =========================================================
 *  ADMIN & SICUREZZA
 * =======================================================*/
function ensureAdmin(): void
{
    // 1) Prendi l’ID del personaggio (o utente) già autenticato
    $idpg = getIDPG(false);
    if ($idpg === null) {
        // non loggato
        header('Location: /login.php');
        exit;
    }

    // 2) Recupera il driver PDO dal singleton
    /** @var PDOMySQLDriver $db */
    $db = getDB();

    // 3) Leggi il campo `admin` dalla tabella `utenti`
    $row = $db->query(
        'SELECT admin FROM utenti WHERE id = ? LIMIT 1',
        [$idpg]
    )[0] ?? null;

    $isAdmin = $row && ((int)$row['admin'] === 1);

    if (! $isAdmin) {
        // non sei admin, neghi l’accesso
        http_response_code(403);
        exit('Accesso negato: privilegi insufficienti.');
    }
}

function isAdmin(int $pg): bool
{
    return (bool)(getDB()->query('SELECT admin FROM utenti WHERE id=?', [$pg])[0]['admin'] ?? false);
}

/* =========================================================
 *  ANALISI ACCOUNT (DOPPI) – LOGS
 * =======================================================*/
function trovaDoppi(
    int  $pg,
    bool $trasferimenti = false,
    bool $ricompense    = false,
    bool $concatena     = false
): array {
    $db  = getDB();

    /* 1) Tutti gli IP usati dal PG */
    $ips = array_column(
        $db->query('SELECT DISTINCT ip FROM logs_ingressi WHERE pg=?', [$pg]),
        'ip'
    );
    if (!$ips) { return []; }

    /* 2) Tutti i PG che sono passati per quegli IP */
    $place = implode(',', array_fill(0, count($ips), '?'));
    $idSet = array_column(
        $db->query("SELECT DISTINCT pg FROM logs_ingressi WHERE ip IN ($place)", $ips),
        'pg'
    );

    $idsTrasf = $idsRic = [];
    $idPlaceholders = $idSet ? implode(',', array_fill(0, count($idSet), '?')) : 'NULL';
    $idParams = array_merge($idSet, $idSet);
    if ($trasferimenti) {
        $idsTrasf = array_unique(array_merge(...$db->query(
            "SELECT esecutore, destinatario
               FROM logs_adminpg
              WHERE azione='trasferimento'
                AND esecutore IN ($idPlaceholders)
                AND destinatario IN ($idPlaceholders)",
            $idParams
        )));
    }
    if ($ricompense) {
        $idsRic = array_unique(array_merge(...$db->query(
            "SELECT esecutore, destinatario
               FROM logs_adminpg
              WHERE azione LIKE 'ricompensa(%'
                AND esecutore IN ($idPlaceholders)
                AND destinatario IN ($idPlaceholders)",
            $idParams
        )));
    }

    if ($concatena) {
        $transferIds = $trasferimenti ? $idsTrasf : $idSet;
        $rewardIds = $ricompense ? $idsRic : $idSet;
        return array_values(array_intersect($idSet, $transferIds, $rewardIds));
    }
    return array_values(array_unique(array_merge($idSet, $idsTrasf, $idsRic)));
}

/* =========================================================
 *  CONTATORE VISITE PROFILO
 * =======================================================*/
function incrementaVisite(int $visitatore, int $visitato): bool
{
    $db   = getDB();
    $when = strtotime('-1 day');

    $exists = $db->query(
        'SELECT 1 FROM logs_visite WHERE esecutore=? AND destinatario=? AND data>=?',
        [$visitatore, $visitato, $when]
    );
    if ($exists) { return false; }

    $now = time();
    $db->execute(
        'INSERT INTO logs_visite (data, esecutore, destinatario) VALUES (?,?,?)',
        [$now, $visitatore, $visitato]
    );
    $db->execute('UPDATE utenti SET visite = visite + 1 WHERE id=?', [$visitato]);
    return true;
}

/* =========================================================
 *  VALIDATORE GENERICO UPLOAD GIF/PNG/JPG <= 25×25 | <= 50 KB
 * =======================================================*/
function validaUpload(array $file)
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $result = true;
    if ($error === UPLOAD_ERR_NO_FILE) {
        $result = true;
    } elseif ($error !== UPLOAD_ERR_OK) {
        $result = 'Errore upload';
    } else {
        $path = $file['tmp_name'] ?? '';
        if (!is_string($path) || !is_uploaded_file($path) || filesize($path) > 50000) {
            $result = 'Upload non valido o oltre 50 KB';
        } else {
            $image = @getimagesize($path);
            if (!$image || $image[0] < 1 || $image[1] < 1 || $image[0] > 25 || $image[1] > 25) {
                $result = 'Immagine non valida (max 25×25)';
            } elseif (!in_array($image[2], [IMAGETYPE_GIF, IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                $result = 'Formato non valido (gif/png/jpg)';
            }
        }
    }
    return $result;
}




function alphanoBadgeScope(string $type, int $group, int $branch): bool
{
    $map=['araldiche'=>['city','ramiaraldiche','city'],'organizzazioni'=>['organizzazioni','ramiorganizzazioni','organizzazione'],'corporazioni'=>['corporazioni','ramicorporazioni','corporazione']];
    if(!isset($map[$type])){return false;}
    [$table,$branches,$column]=$map[$type];
    if(!($type==='araldiche' && $group===0) && !getDB()->query("SELECT id FROM `$table` WHERE id=?",[$group])){return false;}
    return $branch===0 || (bool)getDB()->query("SELECT id FROM `$branches` WHERE id=? AND `$column`=?",[$branch,$group]);
}

function alphanoSaveBadge(array $file, string $relative): bool
{
    $path = htmlroot . $relative;
    $dir = dirname($path);
    $result = is_dir($dir) || mkdir($dir, 0755, true);
    if ($result && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $image = getimagesize($file['tmp_name']);
        if ($image[2] === IMAGETYPE_GIF) {
            $result = move_uploaded_file($file['tmp_name'], $path);
        } else {
            $resource = imagecreatefromstring(file_get_contents($file['tmp_name']));
            if ($resource) {
                $result = imagegif($resource, $path);
                imagedestroy($resource);
            } else {
                $result = false;
            }
        }
    }
    return $result;
}
