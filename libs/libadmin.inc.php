<?php
declare(strict_types=1);

use Alphano\Database\PDOMySQLDriver;

require_once __DIR__ . '/../libs/kernel.inc.php';
require_once __DIR__ . '/../libs/libpg.inc.php';

/** Flag amministrativi: chiavi accettate dal pannello utenti. */
const LIBADMIN_FLAG_KEYS = [
    'fmaster', 'fguaritore', 'fresurrettore', 'fstatosociale', 'flock',
    'accademia', 'fadminorg', 'fadmincorp', 'fcatalogo', 'fordini',
    'fsindaco', 'fupload', 'fvacanza',
];

function optimizeAll(): string
{
    $db = connectDB();

    // recupera tutte le tabelle
    $rows = $db->query('SHOW TABLES', []);
    $tables = array_map(static fn(array $row): string => (string)reset($row), $rows);

    if (empty($tables)) {
        return 'Nessuna tabella trovata';
    }

    // comando OPTIMIZE
    $sql = 'OPTIMIZE TABLE ' . implode(', ', array_map(fn($t)=>"`$t`", $tables));
    $ok = $db->query($sql, []);
    return !empty($ok)
        ? 'Ottimizzazione completata'
        : 'Errore nell\'ottimizzazione';
}

/** @param array<string, int> $flags */
function setFlag(int $esecutore, string $destinatarioNome, array $flags): string
{
    $db = connectDB();
    $id = GetPGID($destinatarioNome);
    if (!$id) {
        return "L'utente non esiste";
    }

    $values = [];
    foreach (LIBADMIN_FLAG_KEYS as $key) {
        $values[$key] = (int)($flags[$key] ?? 0);
    }

    require_once __DIR__.'/admin-governance.inc.php';
    alphanoGovernanceApply($esecutore,(int)$id,[
        'fatepower'=>$values['fmaster'],'adminferite'=>$values['fguaritore'],'adminmorti'=>$values['fresurrettore'],
        'adminstatosociale'=>$values['fstatosociale'],'bloccato'=>$values['flock'],'adminaccademia'=>$values['accademia'],
        'adminorganizzazione'=>$values['fadminorg'],'admincorporazione'=>$values['fadmincorp'],
        'admincatalogo'=>$values['fcatalogo'],'admintrasfcorp'=>$values['fordini'],
        'sindaco'=>$values['fsindaco'],'upload'=>$values['fupload'],'vacanza'=>$values['fvacanza'],
    ],'Aggiornamento flag dal pannello utenti');

    // log
    $now = time();
    $sql = <<<SQL
INSERT INTO logs_flag
    (data, esecutore, destinatario,
     adminorg, admincorp, fatepower,
     adminferite, adminmorti, adminstatosociale,
     bloccato, accademia, catalogo,
     ordini, sindaco, upload, vacanza)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
    $logParams = [
        $now, $esecutore, $id,
        $values['fadminorg'], $values['fadmincorp'], $values['fmaster'],
        $values['fguaritore'], $values['fresurrettore'], $values['fstatosociale'],
        $values['flock'], $values['accademia'], $values['fcatalogo'],
        $values['fordini'], $values['fsindaco'], $values['fupload'], $values['fvacanza']
    ];
    $db->execute($sql, $logParams);

    return "FLAG correttamente impostati a $destinatarioNome";
}

/** Validazione dei campi profilo, estratta per contenere la complessita'. */
function assertProfileInputValid(PDOMySQLDriver $db, string $sesso, string $etnia, string $allineamento): void
{
    if (!in_array($sesso, ['Maschio', 'Femmina', ''], true)) {
        throw new InvalidArgumentException('Sesso non valido');
    }
    if (!in_array($allineamento, ['LB', 'NB', 'CB', 'LN', 'NN', 'CN', 'LM', 'NM', 'CM'], true)) {
        throw new InvalidArgumentException('Allineamento non valido');
    }
    if ((int)$etnia !== 0 && !$db->query('SELECT id FROM etnie WHERE id=?', [(int)$etnia])) {
        throw new InvalidArgumentException('Etnia non valida');
    }
}

/** @param array<string, mixed> $row
 *  @return array{0: array<string, mixed>, 1: string}
 */
function resolveProfileRace(array $row, string $razza, ?int $creatorId, int $esecutore): array
{
    if ($razza === '') {
        lineageEnsure($row);
        return [$row, ''];
    }
    if (!ctype_digit($razza)) {
        throw new InvalidArgumentException('Razza non valida');
    }
    $assignment = lineageAssignRace($row, (int)$razza, $creatorId, $esecutore);
    return [$assignment['user'], (string)$assignment['message']];
}

function setProfileNotice(string $name,array $row,string $notice):string {
    $msg = 'Profilo aggiornato con successo a ' . htmlspecialchars($name, ENT_QUOTES) . ' · ' . htmlspecialchars(GetRazza((int)$row['razza']), ENT_QUOTES);
    if (!empty($row['alternate_form'])) {
        $msg .= ' · Forma alternativa';
    }
    if ($notice !== '') {
        $msg .= ' · ' . htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $msg;
}
function setProfile(int $esecutore,string $destinatarioNome,string $sesso='',string $etnia='',string $allineamento='',string $razza='',?int $creatorId=null):string {
    require_once __DIR__.'/lineages.inc.php';
    $db = connectDB();
    $own = !$db->inTransaction();
    if ($own) {
        $db->beginTransaction();
    }
    try {
        if (in_array($razza, ['2', '5', '3', '6'], true)) {
            lineagePolicyLock(in_array($razza, ['2', '5'], true) ? 'vampire' : 'lycan');
        }
        $row = $db->query('SELECT * FROM utenti WHERE nome=? FOR UPDATE', [$destinatarioNome])[0] ?? null;
        if ($row === null) {
            if ($own) {
                $db->commit();
            }
            return "Il pg $destinatarioNome non esiste";
        }
        $id = (int)$row['id'];
        $sesso = $sesso ?: $row['sesso'];
        $etnia = $etnia !== '' ? $etnia : (string)$row['etnia'];
        $allineamento = $allineamento ?: $row['allineamento'];
        assertProfileInputValid($db, (string)$sesso, $etnia, (string)$allineamento);
        [$row, $notice] = resolveProfileRace($row, $razza, $creatorId, $esecutore);
        $db->execute('UPDATE utenti SET sesso=?,etnia=?,allineamento=? WHERE id=?', [$sesso, $etnia, $allineamento, $id]);
        $db->execute('INSERT INTO logs_profili(data,esecutore,destinatario,razza,sesso,etnia,allineamento,infetto,ancestrale) VALUES(?,?,?,?,?,?,?,?,?)', [time(), $esecutore, $id, $row['razza'], $sesso, (string)GetEtnia((int)$etnia), $allineamento, $row['infetto'], $row['ancestrale']]);
        if ($own) {
            $db->commit();
        }
        return setProfileNotice($destinatarioNome, $row, $notice);
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function adminSetNome(int $esecutore, string $destinatarioNome, string $nuovonome): string
{
    $db = connectDB();
    $id = GetPGID($destinatarioNome);
    if (!$id) {
        return "Impossibile cambiare nome a $destinatarioNome";
    }

    $nuovonome = trim($nuovonome);
    if ($nuovonome === '' || mb_strlen($nuovonome) > 50) {
        throw new InvalidArgumentException('Nome non valido');
    }
    $other = $db->query('SELECT id FROM utenti WHERE nome=? AND id<>?', [$nuovonome,$id]);
    if ($other) {
        throw new InvalidArgumentException('Nome già utilizzato');
    }
    $db->execute(
        'UPDATE utenti SET nome = ? WHERE id = ?',
        [$nuovonome, $id]
    );

    $now = time();
    $db->execute(
        'INSERT INTO logs_profili (data, esecutore, destinatario, nuovonome)
         VALUES (?, ?, ?, ?)',
        [$now, $esecutore, $id, $nuovonome]
    );

    return "Il pg $destinatarioNome ora si chiama $nuovonome";
}

function setPass(int $esecutore, string $destinatarioNome, string $pass): string
{
    $db = connectDB();
    $id = GetPGID($destinatarioNome);
    if (!$id) {
        return "Impossibile cambiare la password a $destinatarioNome";
    }

    if (trim($pass) === '' || strlen($pass) > 4096) {
        throw new InvalidArgumentException('Password non valida');
    }
    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    $db->execute(
        'UPDATE utenti SET password = ? WHERE id = ?',
        [$hashed, $id]
    );

    $now = time();
    $db->execute(
        'INSERT INTO logs_profili (data, esecutore, destinatario, nuovapass)
         VALUES (?, ?, ?, ?)',
        [$now, $esecutore, $id, '[password aggiornata]']
    );

    return "Password cambiata con successo a $destinatarioNome";
}

function hasActiveBoardGame(PDOMySQLDriver $db, int $id): bool
{
    if (!$db->query("SHOW TABLES LIKE 'board_games'")) {
        return false;
    }
    return (bool)$db->query(
        "SELECT id FROM board_games WHERE status='active' AND (a=? OR b=?) LIMIT 1",
        [$id, $id]
    );
}

function deleteUserData(PDOMySQLDriver $db, int $id, string $destinatarioNome, bool $profilo, bool $equip, bool $posta, bool $bacheca): string
{
    $msg = "All'utente $destinatarioNome è stato cancellato:<br>";

    if ($profilo) {
        $ok = $db->execute('DELETE FROM utenti WHERE id = ?', [$id]);
        $msg .= $ok !== false ? "Profilo cancellato<br>" : "Errore nel profilo<br>";
    }
    if ($equip) {
        $ok = $db->execute('DELETE FROM equippg WHERE idpg = ?', [$id]);
        $db->execute('DELETE FROM armadio WHERE idpg = ?', [$id]);
        $msg .= $ok !== false ? "Equipaggiamento cancellato<br>" : "Errore nell'equipaggiamento<br>";
    }
    if ($posta) {
        $ok = $db->execute('DELETE FROM mail WHERE idmittente = ? OR iddestinatario = ?', [$id, $id]);
        $msg .= $ok !== false ? "Posta cancellata<br>" : "Errore nella posta<br>";
    }
    if ($bacheca) {
        $ok = $db->execute('DELETE FROM bacheche WHERE idpg = ?', [$id]);
        $msg .= $ok !== false ? "Bacheche cancellate<br>" : "Errore nelle bacheche<br>";
    }

    return $msg;
}

function delUser(
    int    $esecutore,
    string $destinatarioNome,
    bool   $profilo = true,
    bool   $equip   = true,
    bool   $posta   = true,
    bool   $bacheca = false
): string {
    $db = connectDB();
    $id = GetPGID($destinatarioNome);
    if (!$id) {
        return "Utente inesistente";
    }

    require_once __DIR__.'/libeconomia.inc.php';
    return economiaAtomicaResiliente(static function() use ($db, $id, $esecutore, $destinatarioNome, $profilo, $equip, $posta, $bacheca): string {
        $db->query('SELECT id FROM utenti WHERE id=? FOR UPDATE', [$id]);
        if (($profilo || $equip) && hasActiveBoardGame($db, (int)$id)) {
            return 'Il personaggio ha una partita attiva. Annullala dal pannello Giochi, con rimborso delle poste, prima di cancellare profilo o equipaggiamento.';
        }

        $msg = deleteUserData($db, (int)$id, (string)$destinatarioNome, $profilo, $equip, $posta, $bacheca);
        $now = time();
        $db->execute(
            'INSERT INTO logs_deleteuser
             (data, esecutore, destinatario, profilo, equipaggiamento, posta, bacheca)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$now, $esecutore, $id, (int)$profilo, (int)$equip, (int)$posta, (int)$bacheca]
        );

        return $msg;
    });
}

function delPosta(
    int    $esecutore,
    string $destinatarioNome,
    string $invio,
    string $baule,
    string $letto
): string {
    $db = connectDB();
    $id = GetPGID($destinatarioNome);
    if (!$id) {
        return "Utente inesistente";
    }

    // costruisci WHERE dinamico
    $conds = [];
    switch ($invio) {
        case 'ric':
        case 'dest':  $conds[] = "iddestinatario = $id"; break;
        case 'both':  $conds[] = "(iddestinatario = $id OR idmittente = $id)"; break;
        default:      $conds[] = "idmittente = $id"; break;
    }
    if ($baule === 'si') {
        $conds[] = "baule = '1'";
    } elseif ($baule === 'no') {
        $conds[] = "baule = '0'";
    }
    if ($letto === 'si') {
        $conds[] = "letto = '1'";
    } elseif ($letto === 'no') {
        $conds[] = "letto = '0'";
    }

    $sql = 'DELETE FROM mail WHERE ' . implode(' AND ', $conds);
    $ok  = $db->execute($sql, []);

    if ($ok) {
        $now = time();
        $db->execute(
            'INSERT INTO logs_deletemail
             (data, esecutore, destinatario, invio, baule, letto)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$now, $esecutore, $id, $invio, $baule, $letto]
        );
        return "La posta di $destinatarioNome è stata cancellata";
    }

    return "Nessuna missiva corrisponde ai filtri per $destinatarioNome";
}

/** Letterale SQL senza perdita di NULL, apostrofi, backslash o stringhe vuote. */
function alphanoSQLValue(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if ((string)$value === '') {
        return "''";
    }
    return 'CONVERT(0x'.bin2hex((string)$value).' USING utf8mb4)';
}

/** Inserimento SQL autosufficiente per dump e backup recuperabili. */
function alphanoRowInsert(string $table,array $row): string
{
    if (!$row || !preg_match('/^\w+$/D',$table)) {
        throw new InvalidArgumentException('Tabella backup non valida');
    }
    $columns=implode(',',array_map(static fn($column) => '`'.str_replace('`','``',(string)$column).'`',array_keys($row)));
    return 'INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(',',array_map('alphanoSQLValue',$row)).')';
}

/** Esportazione SQL completa tramite il driver configurato, senza credenziali in comando. */
function alphanoDatabaseDump(): string
{
    $db = connectDB();
    $out = "-- Alphano: esportazione locale\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($db->query('SHOW TABLES') as $tableRow) {
        $table = (string)reset($tableRow);
        $safe = '`' . str_replace('`', '``', $table) . '`';
        $create = $db->query("SHOW CREATE TABLE $safe")[0];
        $out .= "DROP TABLE IF EXISTS $safe;\n" . $create['Create Table'] . ";\n";
        foreach ($db->query("SELECT * FROM $safe") as $row) {
            $out .= alphanoRowInsert($table,$row).";\n";
        }
    }
    return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
}
