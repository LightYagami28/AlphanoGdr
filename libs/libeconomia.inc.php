<?php
declare(strict_types=1);

const ECON_DEBIT_SQL = 'UPDATE utenti SET denari=denari-? WHERE id=?';
const ECON_CREDIT_SQL = 'UPDATE utenti SET denari=denari+? WHERE id=?';
const ECON_INVALID_MANDATE = 'Mandato non valido';
const ECON_CORP_BUDGET_SQL = 'SELECT budget FROM corporazioni WHERE id=? FOR UPDATE';
const ECON_CURRENCY_SUFFIX = ' Corone.';
const ECON_CANCEL_TRANSFER_SQL = 'UPDATE trasferimenti SET stato=6 WHERE id=?';

final class EconomiaDomainException extends DomainException {}

require_once __DIR__ . '/kernel.inc.php';
require_once __DIR__ . '/libposta.inc.php';
require_once __DIR__ . '/libequip.inc.php';
require_once __DIR__ . '/operations.inc.php';
require_once __DIR__ . '/activity.inc.php';

use Alphano\Database\PDOMySQLDriver;

/** Tutte le scritture di un movimento condividono la stessa transazione. */
function economiaAtomica(callable $operation): mixed
{
    $pdo = getDB()->getConnection();
    $own = !$pdo->inTransaction();
    if ($own) {$pdo->beginTransaction();}
    try {
        $result = $operation();
        if ($own) {$pdo->commit();}
        return $result;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {$pdo->rollBack();}
        throw $e;
    }
}
/** DB-only operations may retry a deadlock only when owning the entire transaction. */
function economiaAtomicaResiliente(callable $operation): mixed
{
    $db=getDB();$own=!$db->inTransaction();$attempt=0;
    do {
        $savepoint='economia_'.bin2hex(random_bytes(6));
        if($own){$db->beginTransaction();}else {$db->execute('SAVEPOINT '.$savepoint);}
        try {
            $result=$operation();
            if($own){$db->commit();}else {$db->execute('RELEASE SAVEPOINT '.$savepoint);}
            return $result;
        } catch(Throwable $e) {
            if($db->inTransaction()) {
                if($own){$db->rollBack();}
                else {$db->execute('ROLLBACK TO SAVEPOINT '.$savepoint);$db->execute('RELEASE SAVEPOINT '.$savepoint);}
            }
            $deadlock=$e instanceof PDOException&&((string)$e->getCode()==='40001'||in_array((int)($e->errorInfo[1]??0),[1205,1213],true));
            if(!$own||!$deadlock||++$attempt>=3){throw $e;}
        }
    }while(true);
}
function economiaBool(callable $operation): bool
{
    try { return (bool)economiaAtomica($operation); }
    catch (DomainException $e) { return false; }
    catch (RuntimeException $e) {
        if (in_array($e->getCode(),[400,409],true)) {throw $e;}
        error_log('[economia] '.$e->getMessage()); return false;
    }
    catch (Throwable $e) { error_log('[economia] '.$e->getMessage()); return false; }
}
function economiaVerifica(bool $condition, string $message): void
{
    if (!$condition) {throw new EconomiaDomainException($message);}
}
function economiaImpiegato(int $pg, int $corp): bool
{
    return (bool)getDB()->query('SELECT 1 FROM utenti WHERE id=? AND (admin=1 OR (corporazione=? AND (admintrasfcorp=1 OR admincorporazione=1)))', [$pg,$corp]);
}


/**
 * Trasferisce denaro da un PG a un altro, registra log e invia notifiche.
 */
function trasferisciSoldi(int $mittente, int $destinatario, int $soldi, string $motivo = '', ?string $requestKey = null): bool
{
    if ($soldi<=0 || $mittente===$destinatario) {return false;}
    return economiaBool(static function() use($mittente,$destinatario,$soldi,$motivo,$requestKey): bool {
        if (!alphanoOperationOnce('money-transfer',$mittente,$requestKey,[$destinatario,$soldi,$motivo])) {return true;}
        $db=getDB();
        $rows=$db->query('SELECT id,nome,denari FROM utenti WHERE id IN (?,?) ORDER BY id FOR UPDATE',[$mittente,$destinatario]);
        $users=array_column($rows,null,'id');
        economiaVerifica(isset($users[$mittente],$users[$destinatario]) && (int)$users[$mittente]['denari'] >= $soldi && (int)$users[$destinatario]['denari']<=PHP_INT_MAX-$soldi,'Fondi insufficienti o destinatario assente');
        $db->execute(ECON_DEBIT_SQL,[$soldi,$mittente]);
        $db->execute(ECON_CREDIT_SQL,[$soldi,$destinatario]);
        $db->execute('INSERT INTO logs_adminpg(azione,esecutore,destinatario,qta,data,descr,chat) VALUES(?,?,?,?,?,?,?)',['trasferimento',$mittente,$destinatario,$soldi,time(),$motivo,'']);
        inviaMissiva('Notifica di Trasferimento',$users[$mittente]['nome'].alphanoCurrencyText(" ti ha dato {$soldi} Corone").($motivo!=='' ? ", motivazione: {$motivo}" : '.'),$destinatario);
        alphanoRecordActivity('money-transferred',$mittente,[
            ['scope'=>'personale','id'=>$mittente,'section'=>'trasferimenti','role'=>'member'],
            ['scope'=>'personale','id'=>$destinatario,'section'=>'trasferimenti','role'=>'member'],
        ],alphanoCurrencyText('Trasferimento di Corone'),$users[$mittente]['nome'].alphanoCurrencyText(" ha trasferito {$soldi} Corone a ").$users[$destinatario]['nome'].($motivo!=='' ? ". Motivazione: {$motivo}" : '.'),['mittente'=>$mittente,'destinatario'=>$destinatario,'importo'=>$soldi,'motivo'=>$motivo]);
        return true;
    });
}

/**
 * Restituisce la descrizione di uno stato prestito/trasferimento.
 */
function processStato(int $stato): string
{
    return match($stato) {
        0 => 'Non ancora preso in carico',
        1 => 'Preso in carico',
        2 => 'Controproposta della corporazione',
        3 => 'Controproposta dell’organizzazione',
        4 => 'Controproposta della corporazione',
        5 => 'Accettato',
        6 => 'Estinto',
        default => 'Stato sconosciuto',
    };
}

/**
 * Elenco prestiti per un'organizzazione.
 */
function listaPrestitiOrg(int $org): array
{
    return getDB()->query(
        'SELECT * FROM prestiti WHERE org = ? ORDER BY data ASC',
        [$org]
    );
}

/**
 * Elenco prestiti a carico del PG nell'organizzazione.
 */
function listaPrestitiPGOrg(int $pg, int $org): array
{
    return getDB()->query(
        'SELECT * FROM prestiti
         WHERE org = ?
           AND (pgorg = ? OR (pgorg>0 AND NOT EXISTS(SELECT 1 FROM utenti u WHERE u.id=prestiti.pgorg AND u.organizzazione=prestiti.org AND u.adminorganizzazione=1)))
           AND stato < 6
         ORDER BY data ASC',
        [$org, $pg]
    );
}

/**
 * Elenco prestiti per una corporazione.
 */
function listaPrestitiCorp(int $corp): array
{
    return getDB()->query(
        'SELECT * FROM prestiti WHERE corp = ? ORDER BY data ASC',
        [$corp]
    );
}

/**
 * Elenco prestiti a carico del PG nella corporazione.
 */
function listaPrestitiPGCorp(int $pg, int $corp): array
{
    return getDB()->query(
        'SELECT * FROM prestiti WHERE corp = ? AND (pgcorp = ? OR NOT EXISTS(SELECT 1 FROM utenti u WHERE u.id=prestiti.pgcorp AND u.corporazione=prestiti.corp AND u.admincorporazione=1)) ORDER BY data ASC',
        [$corp, $pg]
    );
}

/** Importi di modulo: mai troncare decimali, esponenti o valori fuori intervallo. */
function economiaIntero(mixed $value, int $min = 0, int $max = PHP_INT_MAX): ?int
{
    if (!is_scalar($value) || !preg_match('/^\d+$/D', (string)$value)) {return null;}
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>$min,'max_range'=>$max]]);
    return $number === false ? null : $number;
}

function economiaPercentuale(mixed $value): ?float
{
    if (!is_scalar($value)) {return null;}
    $number=str_replace(',','.',(string)$value);
    if (!preg_match('/^\d+(?:\.\d{1,4})?$/D',$number)) {return null;}
    $rate=(float)$number;
    return is_finite($rate) && $rate>=0 && $rate<=100 ? $rate : null;
}

/** I moduli HTML conservano il pannello anche dopo un conflitto di reinvio. */
function economiaAzioneModulo(callable $operation): bool
{
    try { return (bool)$operation(); }
    catch (RuntimeException $e) {
        if (!in_array($e->getCode(),[400,409],true)) {throw $e;}
        http_response_code($e->getCode());
        return false;
    }
}

/** Il mandato deve essere ancora valido, anche per chi gestiva già il prestito. */
function prestitoResponsabileValido(array $loan, string $side): bool
{
    $field=$side==='org'?'organizzazione':'corporazione';
    $flag=$side==='org'?'adminorganizzazione':'admincorporazione';
    return (bool)getDB()->query("SELECT id FROM utenti WHERE id=? AND {$field}=? AND {$flag}=1",[(int)$loan['pg'.$side],(int)$loan[$side]]);
}

function prestitoInterlocutore(array &$loan, int $pg): string
{
    $db=getDB();
    $user=$db->query('SELECT organizzazione,corporazione,adminorganizzazione,admincorporazione FROM utenti WHERE id=? FOR UPDATE',[$pg])[0] ?? null;
    if (!$user) {return '';}
    foreach (['org'=>['organizzazione','adminorganizzazione'],'corp'=>['corporazione','admincorporazione']] as $side=>[$field,$flag]) {
        if ((int)$user[$field]!==(int)$loan[$side] || empty($user[$flag])) {continue;}
        if ((int)$loan['pg'.$side]===$pg) {return $side;}
        // A new mandate can recover an orphaned assignment, never take a valid one.
        if ((int)$loan['pg'.$side]>0 && !prestitoResponsabileValido($loan,$side)) {
            $db->execute('UPDATE prestiti SET pg'.$side.'=? WHERE id=?',[$pg,$loan['id']]);
            $loan['pg'.$side]=$pg;
            return $side;
        }
    }
    return '';
}

/** Non consegna dettagli riservati a responsabili che hanno perso il mandato. */
function prestitoMissiva(array $loan, string $side, string $title, string $detail): void
{
    $field=$side==='org'?'organizzazione':'corporazione';
    $flag=$side==='org'?'adminorganizzazione':'admincorporazione';
    $owner=(int)$loan['pg'.$side];
    $recipients=prestitoResponsabileValido($loan,$side)?[['id'=>$owner]]:getDB()->query("SELECT id FROM utenti WHERE {$field}=? AND {$flag}=1",[(int)$loan[$side]]);
    foreach ($recipients as $recipient) {inviaMissiva($title,$detail,(int)$recipient['id']);}
}

function prestitoMessaggio(array $loan, string $event): string
{
    $db=getDB();
    $org=$db->query('SELECT nome FROM organizzazioni WHERE id=?',[$loan['org']])[0]['nome'] ?? 'Organizzazione';
    $corp=$db->query('SELECT nome FROM corporazioni WHERE id=?',[$loan['corp']])[0]['nome'] ?? 'Corporazione';
    return alphanoCurrencyText("Prestito #{$loan['id']}: {$corp} / {$org}. {$event}\nImporto: {$loan['quantita']} Corone. Interesse mensile semplice: {$loan['interessi']}%.");
}

/** Storico condiviso, scritto nella stessa transazione del movimento. */
function prestitoEvento(array $loan, int $actor, string $event, string $title, string $detail, array $data = []): void
{
    alphanoRecordActivity('loan-'.$event,$actor,[
        ['scope'=>'organizzazioni','id'=>(int)$loan['org'],'section'=>'finanze','role'=>'admin'],
        ['scope'=>'corporazioni','id'=>(int)$loan['corp'],'section'=>'finanze','role'=>'admin'],
    ],$title,prestitoMessaggio($loan,$detail),['loan_id'=>(int)$loan['id'],'quantita'=>(int)$loan['quantita'],'interessi'=>(int)$loan['interessi'],'stato'=>(int)($loan['stato'] ?? 0),'restituiti'=>(int)($loan['restituiti'] ?? 0),...$data]);
}

/** Crea la proposta della corporazione, prima del finanziamento. */
function createPrestito(int $pgcorp, int $corp, int $org, int $quantita, int $interessi, ?string $requestKey = null): bool
{
    if ($quantita<=0 || $quantita>2147483647 || $interessi<0 || $interessi>100) {return false;}
    return economiaBool(static function() use($pgcorp,$corp,$org,$quantita,$interessi,$requestKey): bool {
        if (!alphanoOperationOnce('loan-create',$pgcorp,$requestKey,[$corp,$org,$quantita,$interessi])) {return true;}
        $db=getDB();
        $user=$db->query('SELECT organizzazione FROM utenti WHERE id=? AND corporazione=? AND admincorporazione=1 FOR UPDATE',[$pgcorp,$corp])[0] ?? null;
        economiaVerifica($user && (int)$user['organizzazione']!==$org && (bool)$db->query('SELECT id FROM corporazioni WHERE id=?',[$corp]) && (bool)$db->query('SELECT id FROM organizzazioni WHERE id=? AND approvato=1',[$org]),'Organizzazione o mandato non valido');
        $db->execute('INSERT INTO prestiti(data,pgcorp,corp,org,quantita,interessi) VALUES(?,?,?,?,?,?)',[time(),$pgcorp,$corp,$org,$quantita,$interessi]);
        $loan=['id'=>(int)$db->lastInsertId(),'org'=>$org,'corp'=>$corp,'quantita'=>$quantita,'interessi'=>$interessi];
        prestitoEvento($loan,$pgcorp,'requested','Nuova richiesta di prestito','La corporazione richiede un finanziamento.',['stato'=>0]);
        return true;
    });
}

/** Ogni controproposta passa il turno all'altro interlocutore. */
function updatePrestito(int $id, int $pg, int $quantita, int $interessi, int $stato, ?string $requestKey = null): bool
{
    if ($quantita<=0 || $quantita>2147483647 || $interessi<0 || $interessi>100 || !in_array($stato,[2,3,4,5],true)) {return false;}
    if ($stato===5) {return acceptPrestito($id,$pg,$requestKey);}
    return economiaBool(static function() use($id,$pg,$quantita,$interessi,$stato,$requestKey): bool {
        if (!alphanoOperationOnce('loan-proposal',$pg,$requestKey,[$id,$quantita,$interessi,$stato])) {return true;}
        $db=getDB();
        $loan=$db->query('SELECT * FROM prestiti WHERE id=? AND stato BETWEEN 1 AND 4 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica((bool)$loan,'Prestito non negoziabile');
        $side=prestitoInterlocutore($loan,$pg);
        $current=(int)$loan['stato'];
        economiaVerifica(($side==='org' && in_array($current,[1,2,4],true) && $stato===3) || ($side==='corp' && $current===3 && in_array($stato,[2,4],true)),'Non è il tuo turno');
        economiaVerifica($quantita!==(int)$loan['quantita'] || $interessi!==(int)$loan['interessi'],'La proposta è invariata');
        $db->execute('UPDATE prestiti SET quantita=?,interessi=?,stato=? WHERE id=?',[$quantita,$interessi,$stato,$id]);
        $oldQuantity=(int)$loan['quantita'];$oldInterest=(int)$loan['interessi'];
        $loan['quantita']=$quantita;$loan['interessi']=$interessi;
        prestitoEvento($loan,$pg,'proposal','Controproposta di prestito','Nuove condizioni da valutare.',['stato'=>$stato,'stato_precedente'=>$current,'quantita_precedente'=>$oldQuantity,'interessi_precedenti'=>$oldInterest]);
        prestitoMissiva($loan,$side==='org'?'corp':'org','Controproposta di prestito',prestitoMessaggio($loan,'Nuova controproposta: verifica e accetta oppure modifica le condizioni.'));
        return true;
    });
}

/** Prende in carico una nuova richiesta, senza muovere fondi. */
function prendiInCarico(int $id, int $pg, ?string $requestKey = null): bool
{
    return economiaBool(static function() use($id,$pg, $requestKey): bool {
        if (!alphanoOperationOnce('loan-claim',$pg,$requestKey,[$id])) {return true;}
        $db=getDB();
        $loan=$db->query('SELECT * FROM prestiti WHERE id=? AND stato=0 AND pgorg=0 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica((bool)$loan,'Richiesta già presa in carico');
        $user=$db->query('SELECT id FROM utenti WHERE id=? AND organizzazione=? AND adminorganizzazione=1 AND corporazione<>? FOR UPDATE',[$pg,$loan['org'],$loan['corp']]);
        economiaVerifica((bool)$user,ECON_INVALID_MANDATE);
        $db->execute('UPDATE prestiti SET pgorg=?,stato=1 WHERE id=?',[$pg,$id]);
        prestitoEvento($loan,$pg,'claimed','Prestito preso in carico','Un amministratore dell’organizzazione ha preso in carico la richiesta.',['stato'=>1]);
        prestitoMissiva($loan,'corp','Prestito preso in carico',prestitoMessaggio($loan,'La richiesta è stata presa in carico dall’organizzazione.'));
        return true;
    });
}

/** Accetta le condizioni dell'altra parte e accredita una sola volta. */
function acceptPrestito(int $id, int $pg, ?string $requestKey = null): bool
{
    return economiaBool(static function() use($id,$pg, $requestKey): bool {
        if (!alphanoOperationOnce('loan-accept',$pg,$requestKey,[$id])) {return true;}
        $db=getDB();
        $loan=$db->query('SELECT * FROM prestiti WHERE id=? AND stato BETWEEN 1 AND 4 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica($loan && (int)$loan['quantita']>0 && (int)$loan['quantita']<=2147483647 && (int)$loan['interessi']>=0 && (int)$loan['interessi']<=100,'Prestito non valido o già accettato');
        $side=prestitoInterlocutore($loan,$pg);$state=(int)$loan['stato'];
        economiaVerifica(($side==='org' && in_array($state,[1,2,4],true)) || ($side==='corp' && $state===3),'Le condizioni devono essere accettate dall’altra parte');
        $org=$db->query('SELECT budget FROM organizzazioni WHERE id=? FOR UPDATE',[$loan['org']])[0] ?? null;
        $corp=$db->query(ECON_CORP_BUDGET_SQL,[$loan['corp']])[0] ?? null;
        $amount=(int)$loan['quantita'];
        economiaVerifica($org && $corp && (int)$org['budget']>=$amount && (int)$corp['budget']<=PHP_INT_MAX-$amount,'Fondi insufficienti o saldo fuori limite');
        $db->execute('UPDATE organizzazioni SET budget=budget-? WHERE id=?',[$amount,$loan['org']]);
        $db->execute('UPDATE corporazioni SET budget=budget+? WHERE id=?',[$amount,$loan['corp']]);
        $db->execute('UPDATE prestiti SET stato=5,data=? WHERE id=?',[time(),$id]);
        prestitoEvento($loan,$pg,'accepted','Prestito accettato','Finanziamento accreditato al budget corporativo.',['stato'=>5,'org_budget_before'=>(int)$org['budget'],'org_budget_after'=>(int)$org['budget']-$amount,'corp_budget_before'=>(int)$corp['budget'],'corp_budget_after'=>(int)$corp['budget']+$amount]);
        prestitoMissiva($loan,$side==='org'?'corp':'org','Prestito accettato e accreditato',prestitoMessaggio($loan,alphanoCurrencyText('Accreditate le Corone al budget della corporazione. Gli interessi decorrono da oggi.')));
        return true;
    });
}

/** Rifiuto atomico: un reinvio non produce altre missive. */
function deletePrestito(int $id, int $pg, ?string $requestKey = null): bool
{
    return economiaBool(static function() use($id,$pg, $requestKey): bool {
        if (!alphanoOperationOnce('loan-reject',$pg,$requestKey,[$id])) {return true;}
        $db=getDB();
        $loan=$db->query('SELECT * FROM prestiti WHERE id=? AND stato<5 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica((bool)$loan,'Prestito non eliminabile');
        $side=prestitoInterlocutore($loan,$pg);
        economiaVerifica($side!=='',ECON_INVALID_MANDATE);
        $other=$side==='org'?(int)$loan['pgcorp']:(int)$loan['pgorg'];
        if ($other>0) {prestitoMissiva($loan,$side==='org'?'corp':'org','Richiesta di prestito rifiutata',prestitoMessaggio($loan,'La richiesta è stata rifiutata.'));}
        prestitoEvento($loan,$pg,'rejected','Prestito rifiutato','La richiesta è stata rifiutata.',['stato_precedente'=>(int)$loan['stato']]);
        $db->execute('DELETE FROM prestiti WHERE id=?',[$id]);
        return true;
    });
}

/** Interessi semplici, maturati soltanto per mesi interi. */
function calcolaInteressi(int $num, int $intPercent, int $timestamp): int
{
    $start=(new DateTimeImmutable())->setTimestamp(max(0,min(time(),$timestamp)));
    $elapsed=$start->diff(new DateTimeImmutable());
    $months=$elapsed->y*12+$elapsed->m;
    $principal=max(0,$num);$rate=max(0,$intPercent);
    // Le quantità dei prestiti sono limitate a INT32: il prodotto resta intero.
    return $principal+intdiv($principal*$rate*$months,100);
}

/** Rimborso parziale/totale dal budget corporativo, con saldo corrente sotto lock. */
function pagaPrestito(int $id, int $pgcorp, float $pagamento, ?string $requestKey = null): bool
{
    if (!is_finite($pagamento) || $pagamento<1 || $pagamento>=PHP_INT_MAX || floor($pagamento)!==$pagamento) {return false;}
    return economiaBool(static function() use($id,$pgcorp,$pagamento, $requestKey): bool {
        if (!alphanoOperationOnce('loan-repay',$pgcorp,$requestKey,[$id,$pagamento])) {return true;}
        $db=getDB();
        $loan=$db->query('SELECT * FROM prestiti WHERE id=? AND stato=5 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica($loan && prestitoInterlocutore($loan,$pgcorp)==='corp','Prestito o mandato non valido');
        $remaining=calcolaInteressi((int)$loan['quantita'],(int)$loan['interessi'],(int)$loan['data'])-(int)$loan['restituiti'];
        $amount=min($remaining,(int)$pagamento);
        economiaVerifica($amount>0,'Importo non valido');
        $org=$db->query('SELECT budget FROM organizzazioni WHERE id=? FOR UPDATE',[$loan['org']])[0] ?? null;
        $corp=$db->query(ECON_CORP_BUDGET_SQL,[$loan['corp']])[0] ?? null;
        economiaVerifica($org && $corp && (int)$corp['budget']>=$amount && (int)$org['budget']<=PHP_INT_MAX-$amount,'Fondi insufficienti o saldo fuori limite');
        $db->execute('UPDATE prestiti SET restituiti=restituiti+?,stato=? WHERE id=?',[$amount,$remaining===$amount?6:5,$id]);
        $db->execute('UPDATE organizzazioni SET budget=budget+? WHERE id=?',[$amount,$loan['org']]);
        $db->execute('UPDATE corporazioni SET budget=budget-? WHERE id=?',[$amount,$loan['corp']]);
        prestitoEvento($loan,$pgcorp,'repaid',$remaining===$amount?'Prestito estinto':'Rimborso parziale del prestito',alphanoCurrencyText("Ricevute {$amount} Corone. Residuo: ").($remaining-$amount).alphanoCurrencyText(ECON_CURRENCY_SUFFIX),['stato'=>$remaining===$amount?6:5,'importo'=>$amount,'residuo'=>$remaining-$amount,'restituiti'=>(int)$loan['restituiti']+$amount,'org_budget_before'=>(int)$org['budget'],'org_budget_after'=>(int)$org['budget']+$amount,'corp_budget_before'=>(int)$corp['budget'],'corp_budget_after'=>(int)$corp['budget']-$amount]);
        prestitoMissiva($loan,'org','Rimborso prestito',prestitoMessaggio($loan,alphanoCurrencyText("Rimborsate {$amount} Corone. Debito residuo: ").($remaining-$amount).alphanoCurrencyText(ECON_CURRENCY_SUFFIX).($remaining===$amount?' Prestito estinto.':'')));
        return true;
    });
}

/** Importi/quantità arrivano dalle form come stringhe: mai troncare decimali o esponenti. */
function commercioIntero(mixed $value, int $min = 1, int $max = 2147483647): ?int
{
    if (!is_int($value) && !is_string($value)) {return null;}
    $value=(string)$value;
    if (!preg_match('/^\d+$/D',$value)) {return null;}
    $trimmed=ltrim($value,'0');
    if (strlen($trimmed)>10) {return null;}
    $number=(int)$value;
    return $number >= $min && $number <= $max ? $number : null;
}

/** I prezzi sono Corone intere, come i saldi persistiti. */
function commercioPrezzoValido(float $price, int $quantity = 1): bool
{
    return is_finite($price) && $price >= 1 && floor($price)===$price
        && $quantity >= 1 && $quantity <= 10000 && $price <= intdiv(2147483647,$quantity);
}

/** Ricevuta immutabile, nella stessa transazione dei beni e del denaro. */
function commercioAttivita(string $type,int $actor,array $trade,string $title,string $detail,array $changes): void
{
    $before=$changes['before']??[]; $after=$changes['after']??[]; $extra=$changes['extra']??[]; $eventKey=$changes['eventKey']??null;
    $audiences=[['scope'=>'corporazioni','id'=>(int)$trade['corp'],'section'=>'trasferimenti','role'=>'trade']];
    if ((int)($trade['pg'] ?? 0)>0) {$audiences[]=['scope'=>'personale','id'=>(int)$trade['pg'],'section'=>'trasferimenti','role'=>'member'];}
    $event=alphanoRecordActivity($type,$actor,$audiences,$title,$detail,array_merge([
        'order_id'=>(int)$trade['id'],'stato'=>(int)$after['stato'],'verso'=>(int)$trade['verso'],
        'qta'=>(int)($after['qta'] ?? $trade['qta']),'prezzo'=>(int)($after['prezzo'] ?? $trade['prezzo']),
        'oggetto'=>(string)$trade['nomeobj'],'prima'=>$before,'dopo'=>$after,
    ],$extra),$eventKey);
    $db=connectDB();$current=$db->query('SELECT * FROM trasferimenti WHERE id=?',[(int)$trade['id']])[0]??$trade;
    $recipients=[(int)($current['pg']??0),(int)($current['pgcorp']??0)];
    if(empty($current['pgcorp'])){foreach($db->query('SELECT id FROM utenti WHERE corporazione=? AND (admintrasfcorp=1 OR admincorporazione=1) AND bloccato=0',[(int)$trade['corp']]) as $member){$recipients[]=(int)$member['id'];}}
    $marker='[alphano-trade:'.(int)$trade['id'].':'.$event.']';
    foreach(array_unique($recipients) as $recipient){
        if($recipient<1||$recipient===$actor){continue;}
        if($db->query('SELECT id FROM mail WHERE iddestinatario=? AND idmittente=0 AND messaggio LIKE ? LIMIT 1',[$recipient,'%'.$marker.'%'])){continue;}
        inviaMissiva($title.' · #'.(int)$trade['id'],$detail."\n\nApri questa missiva per consultare lo stato aggiornato e rispondere alla trattativa.\n".$marker,$recipient);
    }
}

/**
 * Catalogo generale (visibile/equip approvati) per una corporazione.
 */
function getCatalogo(int $corp, bool $all = false): array
{
    $db     = getDB();
    $allSql = $all ? '' : 'AND equip.approvato = 1';

    $sql = <<<SQL
SELECT
  equip.*,
  equip.tipo AS tipo_id,
  tipo.subtipo AS production_id,
  tipo.nome AS tipo
FROM equip
JOIN tipo
  ON tipo.id = equip.tipo
WHERE equip.corp          = ?
  $allSql
  AND equip.destinatario  = 0
  AND equip.destorg       = 0
  AND equip.visibile      = 1
ORDER BY equip.shortDesc
SQL;

    return $db->query($sql, [$corp]);
}
/**
 * Catalogo personale per PG (oggetti destinati).
 */
function getCatalogoPers(int $corp, bool $all = false, ?int $pg = null): array
{
    $db  = getDB();
    if ($all) {
        $destSql = "AND equip.destinatario <> 0";
        // qui servono due volte $corp: uno per equip.corp e uno per p.corporazione
        $params = [$corp, $corp];
    } else {
        $destSql = "AND equip.approvato = 1 AND equip.destinatario = ?";
        // in ordine: equip.corp = ?, destinatario = ?, p.corporazione = ?
        $params = [$corp, $pg, $corp];
    }
    return $db->query(
        "SELECT equip.*,equip.tipo AS tipo_id, tipo.subtipo AS production_id,
  tipo.nome AS tipo
           FROM equip
           JOIN tipo ON tipo.id = equip.tipo
           JOIN produzionicorporazioni p ON p.produzione = tipo.subtipo
          WHERE equip.corp = ?
            $destSql
            AND equip.destorg = 0
            AND equip.visibile = 1
            AND p.corporazione = ?
          ORDER BY equip.shortDesc",
        $params
    );
}

/**
 * Catalogo personale per Organizzazione (oggetti destinati a org).
 */
function getCatalogoPersOrg(int $corp, bool $all = false, ?int $org = null): array
{
    $db = getDB();
    if ($org !== null || $all) {
        if ($all) {
            $destSql = "AND equip.destorg <> 0";
            $params  = [$corp, $corp];
        } else {
            $destSql = "AND equip.approvato = 1 AND equip.destorg = ?";
            $params  = [$corp, $org, $corp];
        }
        return $db->query(
            "SELECT equip.*,equip.tipo AS tipo_id, tipo.subtipo AS production_id,
  tipo.nome AS tipo
               FROM equip
               JOIN tipo ON tipo.id = equip.tipo
               JOIN produzionicorporazioni p ON p.produzione = tipo.subtipo
              WHERE equip.corp = ?
                $destSql
                AND equip.destinatario = 0
                AND equip.visibile = 1
                AND p.corporazione = ?
              ORDER BY equip.shortDesc",
            $params
        );
    }
    return [];
}

/**
 * Catalogo oggetti “danneggiati” in possesso del PG.
 */
function getCatalogoDamaged(int $idpg, int $corp): array
{
    return getDB()->query(
        "SELECT equip.*,equip.tipo AS tipo_id, tipo.subtipo AS production_id,
  tipo.nome AS tipo, equippg.id AS id, equippg.consumo AS consumo
           FROM equip
           JOIN tipo ON tipo.id = equip.tipo
           JOIN equippg ON equippg.uid = equip.id
           JOIN produzionicorporazioni p ON p.produzione = tipo.subtipo
          WHERE equip.corp = ?
            AND equippg.idpg = ?
            AND equippg.consumo > 0
            AND equip.visibile = 1
            AND p.corporazione = ?
          ORDER BY equip.shortDesc",
        [$corp, $idpg, $corp]
    );
}

/**
 * Stato di un trasferimento (offerta/compravendita).
 */
function statoTransf(int $stato, int $verso): string
{
    return match($stato) {
        0 => 'Non ancora preso in carico',
        1 => 'Preso in carico',
        2 => $verso === 1 ? 'In attesa del prezzo del venditore' : 'Proposta del cliente: risponde la corporazione',
        3 => $verso === 1 ? 'Proposta del venditore: risponde la corporazione' : 'Proposta della corporazione: risponde il cliente',
        4 => $verso === 1 ? 'Proposta della corporazione: risponde il venditore' : 'In contrattazione',
        5 => 'Accettato',
        6 => 'Respinto',
        7 => 'Consegnato',
        default => 'Stato sconosciuto',
    };
}


/**
 * Avvia un’offerta di acquisto di un oggetto, con quantità variabile.
 *
 * @param int   $equippgId  L’ID dell’istanza in zaino (equippg.id)
 * @param int   $dest       L’ID del player destinatario (venditore)
 * @param int   $pgcorp     L’ID del tuo PG (acquirente, la corporazione)
 * @param int   $corp       L’ID della tua corporazione
 * @param int   $qta        La quantità di oggetti da acquistare
 * @return bool             True se l’inserimento è andato a buon fine
 */
function buyObject(int $equippgId, int $dest, int $pgcorp, int $corp, int $qta = 1, ?string $requestKey = null): bool
{
    if ($pgcorp===$dest || $qta<1 || $qta>10000 || !economiaImpiegato($pgcorp,$corp)) {return false;}
    return economiaBool(static function() use($equippgId,$dest,$pgcorp,$corp,$qta,$requestKey): bool {
        $db=getDB();
        if (!alphanoOperationOnce('purchase-create',$pgcorp,$requestKey,['instance'=>$equippgId,'seller'=>$dest,'corp'=>$corp,'qta'=>$qta])) {return true;}
        $row=$db->query('SELECT e.uid,p.shortDesc FROM equippg e JOIN equip p ON p.id=e.uid WHERE e.id=? AND e.idpg=? FOR UPDATE',[$equippgId,$dest])[0] ?? null;
        economiaVerifica((bool)$row,'Oggetto assente');
        economiaVerifica(!$db->query('SELECT 1 FROM trasferimenti t JOIN equippg e ON e.id=t.obj WHERE t.pgcorp=? AND t.pg=? AND e.uid=? AND t.verso=1 AND t.stato<5',[$pgcorp,$dest,$row['uid']]),'Offerta già presente');
        $count=(int)$db->query('SELECT COUNT(*) AS n FROM equippg WHERE uid=? AND idpg=?',[$row['uid'],$dest])[0]['n'];
        economiaVerifica($count >= $qta,'Quantità non disponibile');
        $db->execute('INSERT INTO trasferimenti(data,corp,pgcorp,pg,obj,nomeobj,verso,stato,qta,prezzo) VALUES(?,?,?,?,?,?,1,2,?,0)',[time(),$corp,$pgcorp,$dest,$equippgId,$row['shortDesc'],$qta]);
        $trade=['id'=>(int)$db->lastInsertId(),'corp'=>$corp,'pg'=>$dest,'verso'=>1,'nomeobj'=>$row['shortDesc'],'qta'=>$qta,'prezzo'=>0];
        commercioAttivita('purchase-created',$pgcorp,$trade,'Richiesta di acquisto',"La corporazione richiede {$qta}× {$row['shortDesc']}. Il venditore deve proporre un prezzo totale.",['before'=>[],'after'=>['stato'=>2,'qta'=>$qta,'prezzo'=>0]]);
        return true;
    });
}



/**
 * Elenco offerte in corso per un PG.
 */
function getTransazioniPG(int $idpg): array
{
    return getDB()->query(
        'SELECT  t.*,
                 u.nome AS nomecorp,         -- nome dell’impiegato (se c’è)
                 c.nome AS interlocutore     -- nome della corporazione
           FROM  trasferimenti   AS t
           JOIN  corporazioni    AS c  ON c.id = t.corp
           LEFT JOIN utenti      AS u  ON u.id = t.pgcorp
          WHERE  t.pg    = ?
            AND  t.stato < 5
            AND  t.verso = 1
          ORDER BY t.obj',
        [$idpg]
    );
}



/**
 * Cronologia offerte concluse per un PG.
 */
function getCronologiaPG(int $idpg): array
{
    return getDB()->query(
        'SELECT t.*, c.nome AS nomecorp, u.nome AS interlocutore
           FROM trasferimenti t
           JOIN corporazioni c ON c.id = t.corp
           LEFT JOIN utenti u ON u.id = t.pgcorp
          WHERE t.pg = ?
            AND t.stato >= 5
            AND t.nascostopg = 0
          ORDER BY t.data ASC',
        [$idpg]
    );
}

/**
 * Aggiorna lo stato di un trasferimento offerta.
 */
// libs/libeconomia.inc.php

/**
 * Aggiorna un trasferimento (offerta) includendo prezzo, quantità e stato.
 */
function updateTransf(int $id, int $pg, float $prezzo, int $stato, ?int $qta = null): bool
{
    // Per gli acquisti di istanze il prezzo è il totale dell'intera offerta.
    if (!commercioPrezzoValido($prezzo) || ($qta!==null && ($qta<1 || $qta>10000))) {return false;}
    return economiaBool(static function() use($id,$pg,$prezzo,$stato,$qta): bool {
        $db=getDB();
        $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND verso=1 AND stato<5 AND (pg=? OR pgcorp=?) FOR UPDATE',[$id,$pg,$pg])[0] ?? null;
        economiaVerifica($d && economiaImpiegato((int)$d['pgcorp'],(int)$d['corp']),'Trattativa non modificabile');
        $owner=$pg===(int)$d['pg'];
        economiaVerifica($owner ? in_array((int)$d['stato'],[2,4],true) && $stato===3 : (int)$d['stato']===3 && $stato===4,'Attendi la risposta della controparte');
        $quantity=$qta ?? (int)$d['qta'];
        $item=$db->query('SELECT uid FROM equippg WHERE id=? AND idpg=? FOR UPDATE',[$d['obj'],$d['pg']])[0] ?? null;
        economiaVerifica((bool)$item,'Oggetto non disponibile');
        $available=$db->query('SELECT id FROM equippg WHERE uid=? AND idpg=? FOR UPDATE',[$item['uid'],$d['pg']]);
        economiaVerifica(count($available)>=$quantity,'Quantità non disponibile');
        $db->execute('UPDATE trasferimenti SET prezzo=?,qta=?,stato=? WHERE id=?',[(int)$prezzo,$quantity,$stato,$id]);
        commercioAttivita('purchase-proposal',$pg,$d,'Nuova proposta di acquisto',"{$d['nomeobj']}: {$quantity} pezzi per ".(int)$prezzo.alphanoCurrencyText(' Corone totali.'),['before'=>['stato'=>(int)$d['stato'],'qta'=>(int)$d['qta'],'prezzo'=>(int)$d['prezzo']],'after'=>['stato'=>$stato,'qta'=>$quantity,'prezzo'=>(int)$prezzo],'extra'=>['totale'=>(int)$prezzo]]);
        return true;
    });
}

/**
 * Offerte in corso per la corporazione.
 */
function getTransazioniCorp(int $corp, int $idpg): array
{
    return getDB()->query(
        'SELECT t.*, u.nome AS interlocutore
           FROM trasferimenti t
           JOIN utenti u ON u.id = t.pg
          WHERE t.pgcorp = ?
            AND t.corp = ?
            AND t.stato < 5
            AND t.verso = 1',
        [$idpg, $corp]
    );
}

/**
 * Storico acquisti per la corporazione.
 */
function getStoricoAcqCorp(int $corp): array
{
    return getDB()->query(
        'SELECT t.*, u.nome AS interlocutore
           FROM trasferimenti t
           JOIN utenti u ON u.id = t.pg
          WHERE t.corp = ?
            AND t.stato >= 5
            AND t.verso = 1
            AND t.nascostocorp = 0
          ORDER BY t.data ASC',
        [$corp]
    );
}

/**
 * Storico vendite per la corporazione.
 */
function getStoricoVenCorp(int $corp): array
{
    return getDB()->query(
        'SELECT t.*, u.nome AS interlocutore
           FROM trasferimenti t
           JOIN utenti u ON u.id = t.pg
          WHERE t.corp = ?
            AND t.stato >= 5
            AND t.verso = 2
            AND t.nascostocorp = 0
          ORDER BY t.data ASC',
        [$corp]
    );
}

/**
 * Elenco ordini in corso (verso=2) per la corporazione.
 */
function getOrdiniCorp(int $corp): array
{
    return getDB()->query(
        'SELECT t.*, u1.nome AS interlocutore, u2.nome AS nomepgcorp
           FROM trasferimenti t
           JOIN utenti u1 ON u1.id = t.pg
           LEFT JOIN utenti u2 ON u2.id = t.pgcorp
          WHERE t.corp = ?
            AND (t.stato < 5 OR (t.stato=5 AND t.pgcorp=0))
            AND t.verso = 2
          GROUP BY t.id',
        [$corp]
    );
}

/**
 * Elenco ordini PG della corporazione.
 */
function getOrdiniPGCorp(int $corp, int $idpg): array
{
    return getDB()->query(
        'SELECT t.*, u.nome AS interlocutore
           FROM trasferimenti t
           JOIN utenti u ON u.id = t.pg
          WHERE t.corp = ?
            AND t.verso = 2
            AND t.pgcorp = ?
            AND t.stato <= 5',
        [$corp, $idpg]
    );
}

/**
 * PG prende in carico un ordine (verso=2).
 */
function prendiInCaricoOrdine(int $id, int $pg): bool
{
    return economiaBool(static function() use($id,$pg): bool {
        $db=getDB();
        $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND verso=2 AND stato IN (0,5) AND pgcorp=0 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica($d && economiaImpiegato($pg,(int)$d['corp']) && (int)$d['pg']!==$pg,'Ordine non assegnabile');
        $newState=(int)$d['stato']===5 ? 5 : 1;
        $db->execute('UPDATE trasferimenti SET pgcorp=?,stato=? WHERE id=?',[$pg,$newState,$id]);
        commercioAttivita('order-assigned',$pg,$d,'Ordine preso in carico',"Un responsabile segue l’ordine di {$d['qta']}× {$d['nomeobj']}.".($newState===5 ? ' Prezzo e quantità già concordati: pronto per la consegna.' : ' In attesa della sua proposta di prezzo.'),['before'=>['stato'=>(int)$d['stato'],'responsabile'=>0],'after'=>['stato'=>$newState,'responsabile'=>$pg]]);
        return true;
    });
}

/**
 * Finalizza un trasferimento (verso = 1), gestendo sia materie prime che manufatti,
 * rispettando la quantità richiesta.
 *
 * @param int $id  ID del trasferimento (trasferimenti.id)
 * @param int $pg  ID dell'impiegato che sta accettando
 * @return string Messaggio di esito (o “Exception: …” in caso di errore)
 */
/**
 * Conclude una compravendita “verso = 1”.
 * - scala il budget della corporazione
 * - accredita il venditore
 * - inserisce materie prime / aggiorna magazzino
 * - elimina esattamente $qtaRich istanze dall’inventario del venditore
 * - riallinea o respinge le altre offerte aperte sullo stesso prototipo
 *
 * Ritorna una stringa d’esito o “Exception: …” in caso di errore.
 */
function finalizeTransf(int $id, int $pg): string
{
    try {
        return economiaAtomica(static function() use($id,$pg): string {
            $db=getDB();
            $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND verso=1 AND stato BETWEEN 1 AND 4 AND (pgcorp=? OR pg=?) FOR UPDATE',[$id,$pg,$pg])[0] ?? null;
            economiaVerifica((bool)$d,'Il trasferimento non esiste o non è a tuo carico');
            $corp=(int)$d['corp']; $seller=(int)$d['pg']; $qta=(int)$d['qta']; $price=(int)$d['prezzo'];
            economiaVerifica($qta>0 && $qta<=10000 && commercioPrezzoValido((float)$price) && economiaImpiegato((int)$d['pgcorp'],$corp),'Trasferimento non valido');
            economiaVerifica($pg===$seller ? (int)$d['stato']===4 : (int)$d['stato']===3,'Puoi accettare soltanto una proposta della controparte');
            $item=$db->query('SELECT e.uid,p.* FROM equippg e JOIN equip p ON p.id=e.uid WHERE e.id=? AND e.idpg=? FOR UPDATE',[(int)$d['obj'],$seller])[0] ?? null;
            economiaVerifica((bool)$item,'Istanza oggetto non trovata');
            $uid=(int)$item['uid'];
            $stock=$db->query('SELECT id FROM equippg WHERE uid=? AND idpg=? ORDER BY id FOR UPDATE',[$uid,$seller]);
            economiaVerifica(count($stock)>=$qta,'Il venditore non possiede abbastanza pezzi');
            $budget=$db->query(ECON_CORP_BUDGET_SQL,[$corp])[0] ?? null;
            economiaVerifica($budget && (int)$budget['budget']>=$price,'Fondi insufficienti per la corporazione');
            $db->query('SELECT id FROM utenti WHERE id=? FOR UPDATE',[$seller]);
            $db->execute('UPDATE corporazioni SET budget=budget-? WHERE id=?',[$price,$corp]);
            $db->execute(ECON_CREDIT_SQL,[$price,$seller]);
            $materia=(string)($item['materiaprima'] ?? '');
            if ($materia!=='') {
                economiaVerifica(inserisciMateria($materia,$corp,$qta),'Materia prima non valida');
            } else {
                foreach (getMateriePrime($item) ?: [] as $mat=>$units) {
                    economiaVerifica((int)$units>=0,'Materie prime non valide');
                    if ((int)$units>0) {
                        economiaVerifica(inserisciMateria($mat,$corp,(int)$units*$qta),'Magazzino non aggiornabile');
                    }
                }
            }
            $others=$db->query('SELECT t.* FROM trasferimenti t JOIN equippg e ON e.id=t.obj WHERE t.verso=1 AND t.stato<5 AND t.pg=? AND e.uid=? AND t.id<>? FOR UPDATE',[$seller,$uid,$id]);
            $deleted=array_slice(array_column($stock,'id'),0,$qta);
            $ph=implode(',',array_fill(0,count($deleted),'?'));
            $db->execute("UPDATE equippg SET idpadre=0,slot='' WHERE idpg=? AND idpadre IN ($ph)",array_merge([$seller],$deleted));
            $db->execute("DELETE FROM equippg WHERE idpg=? AND id IN ($ph)",array_merge([$seller],$deleted));
            $db->execute('UPDATE trasferimenti SET stato=5 WHERE id=?',[$id]);
            $materie=getMateriePrime($item) ?: [];
            commercioAttivita('purchase-completed',$pg,$d,'Acquisto completato',alphanoCurrencyText("La corporazione ha acquistato {$qta}× {$d['nomeobj']} per {$price} Corone totali. Materiali recuperati in magazzino."),['before'=>['stato'=>(int)$d['stato']],'after'=>['stato'=>5],'extra'=>['totale'=>$price,'venditore'=>(int)$d['pg'],'materie_recuperate'=>$materia!=='' ? [$materia=>$qta] : array_map(static fn($n)=>(int)$n*$qta,$materie)]]);
            $remaining=array_slice(array_column($stock,'id'),$qta); $left=count($remaining);
            foreach ($others as $other) {
                if (!$left) {$db->execute(ECON_CANCEL_TRANSFER_SQL,[$other['id']]);}
                else {$db->execute('UPDATE trasferimenti SET obj=?,qta=? WHERE id=?',[in_array($other['obj'],$remaining) ? $other['obj'] : $remaining[0],min((int)$other['qta'],$left),$other['id']]);}
                commercioAttivita('purchase-availability-changed',$pg,$other,'Disponibilità aggiornata','La disponibilità degli oggetti è cambiata: controlla la quantità e lo stato aggiornati della trattativa.',['before'=>['stato'=>(int)$other['stato'],'qta'=>(int)$other['qta']],'after'=>['stato'=>$left?(int)$other['stato']:6,'qta'=>$left?min((int)$other['qta'],$left):(int)$other['qta']]]);
            }
            return 'Trasferimento avvenuto correttamente';
        });
    } catch (DomainException $e) { return 'Exception: '.$e->getMessage(); }
    catch (Throwable $e) { error_log('[FinalizeTransf] '.$e->getMessage()); return 'Exception: Impossibile completare il trasferimento'; }
}





/**
 * Rifiuta un trasferimento (verso=1).
 */
function rejectTransf(int $id, int $pg): bool
{
    return economiaBool(static function() use($id,$pg): bool {
        $db=getDB();
        $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND (pg=? OR pgcorp=?) AND verso=1 AND stato<5 FOR UPDATE',[$id,$pg,$pg])[0] ?? null;
        economiaVerifica((bool)$d,'Trasferimento non annullabile');
        $db->execute(ECON_CANCEL_TRANSFER_SQL,[$id]);
        commercioAttivita('purchase-cancelled',$pg,$d,'Acquisto annullato',"La trattativa per {$d['qta']}× {$d['nomeobj']} è stata annullata. Nessun pagamento.",['before'=>['stato'=>(int)$d['stato']],'after'=>['stato'=>6]]);
        return true;
    });
}

/**
 * Crea un ordine (verso=2).
 */
function makeOrdine(int $obj, int $pg, int $corp, int $qta = 1, ?string $requestKey = null, ?int &$orderId = null): bool
{
    if ($qta<1 || $qta>10000) {return false;}
    return economiaBool(static function() use($obj,$pg,$corp,$qta,$requestKey,&$orderId): bool {
        $db=getDB();
        $eventKey=$requestKey!==null && $requestKey!=='' ? 'commerce-order:'.$pg.':'.$requestKey : null;
        if (!alphanoOperationOnce('order-create',$pg,$requestKey,['obj'=>$obj,'corp'=>$corp,'qta'=>$qta])) {
            $receipt=$db->query('SELECT data_json FROM activity_events WHERE event_key=?',[$eventKey])[0] ?? null;
            $orderId=(int)(json_decode($receipt['data_json'] ?? '{}',true)['order_id'] ?? 0);
            economiaVerifica($orderId>0,'Ricevuta ordine non disponibile');
            return true;
        }
        $user=$db->query('SELECT organizzazione FROM utenti WHERE id=? FOR UPDATE',[$pg])[0] ?? null;
        $item=$db->query('SELECT e.shortDesc,e.destinatario,e.destorg FROM equip e JOIN tipo t ON t.id=e.tipo JOIN produzionicorporazioni p ON p.produzione=t.subtipo AND p.corporazione=e.corp WHERE e.id=? AND e.corp=? AND e.approvato=1 AND e.visibile=1',[$obj,$corp])[0] ?? null;
        economiaVerifica($user && $item && ((int)$item['destinatario']===0 || (int)$item['destinatario']===$pg) && ((int)$item['destorg']===0 || (int)$item['destorg']===(int)$user['organizzazione']),'Oggetto non ordinabile');
        economiaVerifica(!$db->query('SELECT 1 FROM trasferimenti WHERE pg=? AND obj=? AND corp=? AND verso=2 AND stato<5',[$pg,$obj,$corp]),'Ordine già presente');
        $db->execute('INSERT INTO trasferimenti(data,corp,pg,obj,qta,nomeobj,verso,stato) VALUES(?,?,?,?,?,?,2,0)',[time(),$corp,$pg,$obj,$qta,$item['shortDesc']]);
        $orderId=(int)$db->lastInsertId();
        commercioAttivita('order-created',$pg,['id'=>$orderId,'corp'=>$corp,'pg'=>$pg,'verso'=>2,'qta'=>$qta,'prezzo'=>0,'nomeobj'=>$item['shortDesc']],'Nuovo ordine cliente',"Richiesti {$qta}× {$item['shortDesc']}. La corporazione deve prendere in carico e formulare il prezzo.",['before'=>[],'after'=>['stato'=>0,'qta'=>$qta,'prezzo'=>0],'extra'=>[],'eventKey'=>$eventKey]);
        return true;
    });
}

/**
 * Elenco ordini PG (verso=2).
 */
function getOrdiniPG(int $idpg): array
{
    return getDB()->query(
        'SELECT t.*, c.nome AS nomecorp, u.nome AS interlocutore
           FROM trasferimenti t
           JOIN corporazioni c ON c.id = t.corp
           LEFT JOIN utenti u ON u.id = t.pgcorp
          WHERE t.pg = ?
            AND t.stato < 5
            AND t.verso = 2
          GROUP BY t.id',
        [$idpg]
    );
}

/**
 * Finalizza un ordine (verso=2).
 */
function finalizeOrdine(int $id, int $pg): string
{
    $ok=economiaBool(static function() use($id,$pg): bool {
        $db=getDB();
        $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND (pg=? OR pgcorp=?) AND verso=2 AND stato BETWEEN 1 AND 4 FOR UPDATE',[$id,$pg,$pg])[0] ?? null;
        economiaVerifica($d && commercioPrezzoValido((float)$d['prezzo'],(int)$d['qta']) && economiaImpiegato((int)$d['pgcorp'],(int)$d['corp']),"L'ordine selezionato non esiste o non è a tuo carico");
        economiaVerifica($pg===(int)$d['pg'] ? (int)$d['stato']===3 : (int)$d['stato']===2,'Puoi accettare soltanto una proposta della controparte');
        $db->execute('UPDATE trasferimenti SET stato=5 WHERE id=?',[$id]);
        commercioAttivita('order-agreed',$pg,$d,'Ordine concordato',alphanoCurrencyText("Concordati {$d['qta']}× {$d['nomeobj']} a {$d['prezzo']} Corone ciascuno. Pagamento alla consegna."),['before'=>['stato'=>(int)$d['stato']],'after'=>['stato'=>5],'extra'=>['totale'=>(int)$d['qta']*(int)$d['prezzo']]]);
        return true;
    });
    return $ok ? 'Ordine accettato con successo' : "L'ordine selezionato non è accettabile o non è a tuo carico";
}

/** Un ordine concordato può essere annullato fino alla consegna: nessuna risorsa è ancora spesa. */
function rejectOrdine(int $id, int $pg): bool
{
    return economiaBool(static function() use($id,$pg): bool {
        $db=getDB();
        $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND verso=2 AND stato BETWEEN 0 AND 5 FOR UPDATE',[$id])[0] ?? null;
        economiaVerifica((bool)$d,'Ordine non annullabile');
        $owner=$pg===(int)$d['pg'];
        $employee=economiaImpiegato($pg,(int)$d['corp']) && ((int)$d['pgcorp']===$pg || (int)$d['pgcorp']===0);
        economiaVerifica($owner || $employee,'Ordine non a tuo carico');
        $db->execute(ECON_CANCEL_TRANSFER_SQL,[$id]);
        commercioAttivita('order-cancelled',$pg,$d,'Ordine annullato',"Annullato l’ordine di {$d['qta']}× {$d['nomeobj']} prima della consegna. Nessun addebito né consumo di materie.",['before'=>['stato'=>(int)$d['stato']],'after'=>['stato'=>6]]);
        return true;
    });
}

/** La corporazione formula il primo prezzo; poi le proposte alternano le parti. */
function updateOrdine(int $id, int $pg, float $prezzo, int $qta, int $stato): bool
{
    if (!commercioPrezzoValido($prezzo,$qta)) {return false;}
    return economiaBool(static function() use($id,$pg,$prezzo,$qta,$stato): bool {
        $db=getDB();
        $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND verso=2 AND stato BETWEEN 1 AND 4 AND (pg=? OR pgcorp=?) FOR UPDATE',[$id,$pg,$pg])[0] ?? null;
        economiaVerifica($d && economiaImpiegato((int)$d['pgcorp'],(int)$d['corp']),'Ordine non modificabile');
        $owner=$pg===(int)$d['pg'];
        economiaVerifica($owner ? (int)$d['stato']===3 && $stato===2 : in_array((int)$d['stato'],[1,2],true) && $stato===3,'Attendi la risposta della controparte');
        $db->execute('UPDATE trasferimenti SET prezzo=?,qta=?,stato=? WHERE id=?',[(int)$prezzo,$qta,$stato,$id]);
        commercioAttivita('order-proposal',$pg,$d,'Nuova proposta per l’ordine',"{$d['nomeobj']}: {$qta} pezzi a ".(int)$prezzo.alphanoCurrencyText(' Corone ciascuno.'),['before'=>['stato'=>(int)$d['stato'],'qta'=>(int)$d['qta'],'prezzo'=>(int)$d['prezzo']],'after'=>['stato'=>$stato,'qta'=>$qta,'prezzo'=>(int)$prezzo],'extra'=>['totale'=>$qta*(int)$prezzo]]);
        return true;
    });
}

/**
 * Magazzino di materie prime di una corporazione.
 */
function getMagazzino(int $corp): array
{
    global $dbequipar;
    $db   = getDB();
    $rows = $db->query('SELECT materia, qta FROM equipcorp WHERE corp = ?', [$corp]);

    // se $dbequipar non è un array, uso solo le materie presenti
    $keys = MATERIE_PRIME;
    $materie = array_fill_keys($keys, 0);

    foreach ($rows as $r) {
        $materia = (string)$r['materia'];
        if ($keys === [] || in_array($materia, $keys, true)) {
            $materie[$materia] = (int)$r['qta'];
        }
    }

    return $materie;
}


/**
 * Elenco consegne (verso=2, stato=5) per PG/corp.
 */
function getConsegne(int $corp, int $pg): array
{
    return getDB()->query(
        'SELECT t.*, u.nome AS interlocutore, COALESCE(NULLIF(e.immagine,\'\'),tipo.immagine) AS immagine
           FROM trasferimenti t
           JOIN utenti u ON u.id = t.pg
           LEFT JOIN equip e ON e.id=t.obj
           LEFT JOIN tipo ON tipo.id=e.tipo
          WHERE t.corp = ?
            AND t.pgcorp = ?
            AND t.verso = 2
            AND t.stato = 5',
        [$corp, $pg]
    );
}

/**
 * Verifica se si può consegnare (materie prime sufficienti).
 */
function canConsegnare(array $req, array $mag): bool
{
    foreach ($req as $mat => $qta) {
        if (($mag[$mat] ?? 0) < $qta) {
            return false;
        }
    }
    return true;
}


/**
 * Consegna merce per un ordine (verso=2), scalando prima
 * le materie prime dal magazzino della corporazione.
 *
 * @param int $id    ID del trasferimento (ordine)
 * @param int $pg    ID del giocatore che consegna
 * @param int $corp  ID della corporazione
 * @return string    Messaggio di esito (successo o errore)
 */
function consegna(int $id, int $pg, int $corp): string
{
    try {
        return economiaAtomica(static function() use($id,$pg,$corp): string {
            $db=getDB();
            $d=$db->query('SELECT * FROM trasferimenti WHERE id=? AND stato=5 AND verso=2 AND corp=? AND pgcorp=? FOR UPDATE',[$id,$corp,$pg])[0] ?? null;
            economiaVerifica($d && economiaImpiegato($pg,$corp),"L'oggetto non è consegnabile");
            $qty=(int)$d['qta']; $price=(int)$d['prezzo']; $total=$qty*$price;
            economiaVerifica(commercioPrezzoValido((float)$price,$qty),'Quantità o prezzo non validi');
            $item=$db->query('SELECT e.* FROM equip e JOIN tipo t ON t.id=e.tipo JOIN produzionicorporazioni p ON p.produzione=t.subtipo AND p.corporazione=e.corp WHERE e.id=? AND e.corp=? AND e.approvato=1',[$d['obj'],$corp])[0] ?? null;
            economiaVerifica((bool)$item,'Oggetto non disponibile');
            $buyer=$db->query('SELECT denari FROM utenti WHERE id=? FOR UPDATE',[$d['pg']])[0] ?? null;
            economiaVerifica($buyer && (int)$buyer['denari']>=$total,"L'acquirente non ha fondi sufficienti");
            $info=$db->query('SELECT budget,provvigione,stipendio FROM corporazioni WHERE id=? FOR UPDATE',[$corp])[0] ?? null;
            economiaVerifica((bool)$info,'Corporazione assente');
            $commission=0;
            if (!empty($info['provvigione'])) {
                $role=$db->query('SELECT c.provvigione FROM utenti u JOIN carichecorporazioni c ON c.id=u.caricacorporazione AND c.corporazione=u.corporazione WHERE u.id=? AND u.corporazione=?',[$pg,$corp])[0] ?? null;
                economiaVerifica($role && (float)$role['provvigione']>=0 && (float)$role['provvigione']<=100,'Provvigioni non impostate correttamente');
                $commission=(int)floor((float)$role['provvigione']*$total/100);
            } else {economiaVerifica(!empty($info['stipendio']),'Provvigioni non impostate correttamente');}
            require_once __DIR__.'/item-engine.inc.php';itemStockDelivery($item,$qty);
            $mag=array_column($db->query('SELECT materia,qta FROM equipcorp WHERE corp=? FOR UPDATE',[$corp]),'qta','materia');
            foreach (getMateriePrime($item) ?: [] as $mat=>$unit) {
                $need=(int)$unit*$qty;
                economiaVerifica($need>=0 && ($mag[$mat] ?? 0)>=$need,'Non disponi delle materie prime');
                if ($need>0) {$db->execute('UPDATE equipcorp SET qta=qta-? WHERE corp=? AND materia=?',[$need,$corp,$mat]);}
            }
            $db->execute(ECON_DEBIT_SQL,[$total,$d['pg']]);
            if ($commission>0) {$db->execute(ECON_CREDIT_SQL,[$commission,$pg]);}
            $db->execute('UPDATE corporazioni SET budget=budget+? WHERE id=?',[$total-$commission,$corp]);
            for ($i=0;$i<$qty;$i++) {$db->execute('INSERT INTO equippg(uid,idpg) VALUES(?,?)',[$d['obj'],$d['pg']]);}
            $db->execute('UPDATE trasferimenti SET stato=7 WHERE id=?',[$id]);
            commercioAttivita('order-delivered',$pg,$d,'Ordine consegnato e pagato',alphanoCurrencyText("Consegnati {$qty}× {$d['nomeobj']}. Addebitate {$total} Corone al cliente; provvigione {$commission} Corone."),['before'=>['stato'=>5],'after'=>['stato'=>7],'extra'=>['totale'=>$total,'provvigione'=>$commission,'entrata_corporazione'=>$total-$commission,'materie_consumate'=>array_map(static fn($n)=>(int)$n*$qty,getMateriePrime($item) ?: [])]]);
            return 'Consegna andata a buon fine';
        });
    } catch (DomainException $e) { return $e->getMessage(); }
    catch (Throwable $e) { error_log('[Consegna] '.$e->getMessage()); return 'Errore nella consegna'; }
}


/** Produzioni e visibilità del catalogo cambiano insieme, oppure restano intatte. */
function impostaProduzioniCorp(int $corp, int $actor, array $selection, ?string $requestKey = null): bool
{
    return economiaBool(static function() use($corp,$actor,$selection,$requestKey): bool {
        if (!alphanoOperationOnce('corporation-production',$actor,$requestKey,[$corp,$selection])) {return true;}
        $db=getDB();
        economiaVerifica((bool)$db->query('SELECT id FROM utenti WHERE id=? AND (admin=1 OR (corporazione=? AND admincorporazione=1)) FOR UPDATE',[$actor,$corp]),ECON_INVALID_MANDATE);
        economiaVerifica((bool)$db->query('SELECT id FROM corporazioni WHERE id=? FOR UPDATE',[$corp]),'Corporazione non valida');
        $allowed=array_column($db->query('SELECT id FROM subtipi'),'id');$points=0;
        foreach ($selection as $subtype=>$quality) {
            economiaVerifica(in_array((int)$subtype,array_map('intval',$allowed),true) && is_int($quality) && isset(QUALITY[$quality]),'Produzione o qualità non valida');
            $points+=(int)QUALITY[$quality]['costo'];
        }
        economiaVerifica($points>0 && $points<=MAXPOINTVALUE,'Punti produzione non validi');
        $before=$db->query('SELECT produzione,quality FROM produzionicorporazioni WHERE corporazione=? ORDER BY produzione FOR UPDATE',[$corp]);
        $db->query('SELECT id FROM equip WHERE corp=? ORDER BY id FOR UPDATE',[$corp]);
        $db->execute('DELETE FROM produzionicorporazioni WHERE corporazione=?',[$corp]);
        foreach ($selection as $subtype=>$quality) {if ($quality>0) {$db->execute('INSERT INTO produzionicorporazioni(corporazione,produzione,quality) VALUES(?,?,?)',[$corp,(int)$subtype,$quality]);}}
        $items=$db->query('SELECT e.id,e.durata,p.quality FROM equip e JOIN tipo t ON t.id=e.tipo JOIN produzionicorporazioni p ON p.produzione=t.subtipo AND p.corporazione=e.corp WHERE e.corp=? AND e.visibile=1',[$corp]);
        foreach ($items as $item) {
            $limit=(int)QUALITY[(int)$item['quality']]['durata'];
            if ((int)$item['durata']>$limit) {$db->execute('UPDATE equip SET durata=? WHERE id=?',[$limit,$item['id']]);}
        }
        $hidden=$db->execute('UPDATE equip e JOIN tipo t ON t.id=e.tipo LEFT JOIN produzionicorporazioni p ON p.produzione=t.subtipo AND p.corporazione=e.corp SET e.visibile=0 WHERE e.corp=? AND e.visibile=1 AND p.produzione IS NULL',[$corp]);
        $after=$db->query('SELECT produzione,quality FROM produzionicorporazioni WHERE corporazione=? ORDER BY produzione',[$corp]);
        if ($before!==$after || $hidden>0) {alphanoRecordActivity('corporation-production-updated',$actor,[['scope'=>'corporazioni','id'=>$corp,'section'=>'production','role'=>'admin']],
            'Produzioni corporative aggiornate',"Assegnati {$points} punti produzione. Oggetti nascosti perché non più producibili: {$hidden}.",['corporazione'=>$corp,'prima'=>$before,'dopo'=>$after,'punti'=>$points,'oggetti_nascosti'=>$hidden]);}
        return true;
    });
}

/**
 * Calcola il totale stipendi dovuti per la corporazione.
 */
function getStipendi(int $corp): int
{
    $rows = getDB()->query(
        'SELECT SUM(c.stipendio) AS tot
           FROM carichecorporazioni c
           JOIN utenti u ON u.corporazione = c.corporazione
                          AND u.caricacorporazione = c.id
          WHERE c.corporazione = ?',
        [$corp]
    );
    return (int)($rows[0]['tot'] ?? 0);
}

/**
 * Eroga stipendi e invia notifiche.
 */
function consegnastipendi(int $corp, ?string $requestKey = null): bool
{
    require_once __DIR__.'/treasury.inc.php';
    if(alphanoTreasuryReady()){return economiaBool(static fn():bool=>alphanoTreasuryPayroll('corporazioni',$corp,(int)($_SESSION['user_id']??0),$requestKey?:bin2hex(random_bytes(16)))['status']==='applied');}
    $actor=(int)($_SESSION['user_id'] ?? 0);
    return economiaBool(static function() use($corp,$requestKey,$actor): bool {
        if (!alphanoOperationOnce('corporation-payroll',$actor,$requestKey,[$corp])) {return true;}
        $db=getDB();
        economiaVerifica((bool)$db->query('SELECT id FROM utenti WHERE id=? AND (admin=1 OR (corporazione=? AND admincorporazione=1)) FOR UPDATE',[$actor,$corp]),ECON_INVALID_MANDATE);
        $organization=$db->query('SELECT budget,stipendio,nome FROM corporazioni WHERE id=? FOR UPDATE',[$corp])[0] ?? null;
        $members=$db->query('SELECT u.id,u.denari,c.stipendio FROM utenti u JOIN carichecorporazioni c ON c.id=u.caricacorporazione AND c.corporazione=u.corporazione WHERE u.corporazione=? ORDER BY u.id FOR UPDATE',[$corp]);
        $total=0;$paid=0;
        foreach ($members as $m) {
            $amount=(int)$m['stipendio'];
            economiaVerifica($amount>=0 && $amount<=PHP_INT_MAX-$total && (int)$m['denari']<=PHP_INT_MAX-$amount,'Stipendio o saldo non valido');
            $total+=$amount;if($amount>0){$paid++;}
        }
        economiaVerifica($organization && !empty($organization['stipendio']) && $total>0 && (int)$organization['budget']>=$total,'Fondi insufficienti o stipendi non impostati');
        $db->execute('UPDATE corporazioni SET budget=budget-?,ultimostipendio=? WHERE id=?',[$total,time(),$corp]);
        foreach ($members as $m) {
            $amount=(int)$m['stipendio'];if($amount<1){continue;}
            $db->execute(ECON_CREDIT_SQL,[$amount,$m['id']]);
            inviaMissiva('Stipendio corporativo ricevuto',alphanoCurrencyText("Hai ricevuto {$amount} Corone di stipendio da {$organization['nome']}."),(int)$m['id']);
            alphanoRecordActivity('corporation-salary-received',$actor,[['scope'=>'personale','id'=>(int)$m['id'],'section'=>'trasferimenti','role'=>'member']],
                'Stipendio corporativo ricevuto',alphanoCurrencyText("{$organization['nome']} ha accreditato {$amount} Corone di stipendio."),['corporazione'=>$corp,'importo'=>$amount,'denari_prima'=>(int)$m['denari'],'denari_dopo'=>(int)$m['denari']+$amount]);
        }
        alphanoRecordActivity('corporation-payroll',$actor,[['scope'=>'corporazioni','id'=>$corp,'section'=>'finanze','role'=>'admin']],
            'Stipendi corporativi consegnati',alphanoCurrencyText("{$organization['nome']}: erogate {$total} Corone a {$paid} personaggi."),['corporazione'=>$corp,'totale'=>$total,'destinatari'=>$paid,'budget_prima'=>(int)$organization['budget'],'budget_dopo'=>(int)$organization['budget']-$total]);
        return true;
    });
}

/**
 * Pulisce trasferimenti orfani o obsoleti per una corporazione.
 */
function clearTrasferimenti(int $corp): bool
{
    if ($corp<=0) {return true;}
    return economiaBool(static function() use($corp): bool {
        $db=getDB();
        $rows=$db->query('SELECT t.* FROM trasferimenti t WHERE t.corp=? AND t.stato BETWEEN 0 AND 5 ORDER BY t.id FOR UPDATE',[$corp]);
        foreach ($rows as $trade) {
            $customer=(bool)$db->query('SELECT id FROM utenti WHERE id=?',[$trade['pg']]);
            $assigned=(int)$trade['pgcorp'];
            $validEmployee=$assigned>0 && economiaImpiegato($assigned,$corp);
            if ((int)$trade['verso']===2) {
                if (!$customer) {
                    $db->execute(ECON_CANCEL_TRANSFER_SQL,[$trade['id']]);
                    commercioAttivita('order-cancelled',0,$trade,'Ordine non più disponibile','Il cliente non è più disponibile. Nessun addebito.',['before'=>['stato'=>(int)$trade['stato']],'after'=>['stato'=>6]]);
                } elseif ($assigned>0 && !$validEmployee) {
                    // Preserve an agreement already accepted by the customer.
                    $state=(int)$trade['stato']===5 ? 5 : 0;
                    $price=$state===5 ? (int)$trade['prezzo'] : 0;
                    $db->execute('UPDATE trasferimenti SET pgcorp=0,stato=?,prezzo=? WHERE id=?',[$state,$price,$trade['id']]);
                    commercioAttivita('order-unassigned',0,$trade,'Ordine da riassegnare','Il precedente responsabile non ha più il mandato. Un nuovo responsabile deve prendere in carico l’ordine.'.($state===5 ? ' Il prezzo concordato è conservato.' : ''),['before'=>['stato'=>(int)$trade['stato'],'responsabile'=>$assigned,'prezzo'=>(int)$trade['prezzo']],'after'=>['stato'=>$state,'responsabile'=>0,'prezzo'=>$price]]);
                }
            } elseif ((int)$trade['stato']<5) {
                $item=(bool)$db->query('SELECT id FROM equippg WHERE id=? AND idpg=?',[$trade['obj'],$trade['pg']]);
                if (!$customer || !$validEmployee || !$item) {
                    $db->execute(ECON_CANCEL_TRANSFER_SQL,[$trade['id']]);
                    commercioAttivita('purchase-cancelled',0,$trade,'Trattativa non più disponibile','L’oggetto o il mandato del responsabile non è più disponibile. Nessun pagamento.',['before'=>['stato'=>(int)$trade['stato']],'after'=>['stato'=>6]]);
                }
            }
        }
        return true;
    });
}


/**
 * Eroga stipendi per una città intera.
 */
function consegnaStipendiCity(int $city, ?string $requestKey = null): string
{
    require_once __DIR__.'/treasury.inc.php';
    if(alphanoTreasuryReady()){
        $ok=economiaBool(static fn():bool=>alphanoTreasuryPayroll('citta',$city,(int)($_SESSION['user_id']??0),$requestKey?:bin2hex(random_bytes(16)))['status']==='applied');
        return $ok?'Stipendi consegnati correttamente':'Fondi insufficienti o mandato non valido';
    }
    $actor=(int)($_SESSION['user_id'] ?? 0);
    $ok=economiaBool(static function() use($city,$requestKey,$actor): bool {
        if (!alphanoOperationOnce('city-payroll',$actor,$requestKey,[$city])) {return true;}
        $db=getDB();
        economiaVerifica((bool)$db->query('SELECT u.id FROM utenti u JOIN araldiche a ON a.id=u.araldica WHERE u.id=? AND a.city=? AND u.sindaco=1 FOR UPDATE',[$actor,$city]),ECON_INVALID_MANDATE);
        $row=$db->query('SELECT budget,city FROM city WHERE id=? FOR UPDATE',[$city])[0] ?? null;
        $members=$db->query('SELECT u.id,u.denari,a.stipendio FROM utenti u JOIN araldiche a ON a.id=u.araldica WHERE a.city=? ORDER BY u.id FOR UPDATE',[$city]);
        $total=0;$paid=0;
        foreach ($members as $m) {
            $amount=(int)$m['stipendio'];
            economiaVerifica($amount>=0 && $amount<=PHP_INT_MAX-$total && (int)$m['denari']<=PHP_INT_MAX-$amount,'Stipendio o saldo non valido');
            $total+=$amount;if($amount>0){$paid++;}
        }
        economiaVerifica($row && $total>0 && (int)$row['budget']>=$total,'Fondi insufficienti o stipendi non impostati');
        $db->execute('UPDATE city SET budget=budget-?,ultimostipendio=? WHERE id=?',[$total,time(),$city]);
        foreach ($members as $m) {
            $amount=(int)$m['stipendio'];if($amount<1){continue;}
            $db->execute(ECON_CREDIT_SQL,[$amount,$m['id']]);
            inviaMissiva('Stipendio cittadino ricevuto',alphanoCurrencyText("Hai ricevuto {$amount} Corone di stipendio da {$row['city']}."),(int)$m['id']);
            alphanoRecordActivity('city-salary-received',$actor,[['scope'=>'personale','id'=>(int)$m['id'],'section'=>'trasferimenti','role'=>'member']],
                'Stipendio cittadino ricevuto',alphanoCurrencyText("{$row['city']} ha accreditato {$amount} Corone di stipendio."),['city'=>$city,'importo'=>$amount,'denari_prima'=>(int)$m['denari'],'denari_dopo'=>(int)$m['denari']+$amount]);
        }
        alphanoRecordActivity('city_consegnastipendi',$actor,[['scope'=>'citta','id'=>$city,'section'=>'budget-stipendi','role'=>'admin']],
            'Stipendi cittadini consegnati',alphanoCurrencyText("{$row['city']}: erogate {$total} Corone a {$paid} cittadini."),['city'=>$city,'totale'=>$total,'destinatari'=>$paid,'budget_prima'=>(int)$row['budget'],'budget_dopo'=>(int)$row['budget']-$total]);
        return true;
    });
    return $ok ? 'Stipendi consegnati correttamente' : 'Fondi insufficienti o stipendi non validi';
}

/**
 * Crea un prestito PG da parte di un'organizzazione.
 */
function creaPrestitoPG(int $org, int $pg, int $denari, ?string $requestKey = null): bool
{
    if ($denari<=0 || $denari>2147483647) {return false;}
    $actor=(int)($_SESSION['user_id'] ?? 0);
    return economiaBool(static function() use($org,$pg,$denari,$requestKey,$actor): bool {
        if (!alphanoOperationOnce('loan-pg',$actor,$requestKey,[$org,$pg,$denari])) {return true;}
        $db=getDB();
        $budget=$db->query('SELECT budget,nome FROM organizzazioni WHERE id=? FOR UPDATE',[$org])[0] ?? null;
        economiaVerifica((bool)$db->query('SELECT id FROM utenti WHERE id=? AND organizzazione=? AND adminorganizzazione=1 AND bloccato=0 FOR UPDATE',[$actor,$org]),ECON_INVALID_MANDATE);
        $user=$db->query('SELECT id,nome,denari FROM utenti WHERE id=? AND organizzazione=? FOR UPDATE',[$pg,$org])[0] ?? null;
        economiaVerifica($budget && $user && (int)$budget['budget']>=$denari && (int)$user['denari']<=PHP_INT_MAX-$denari,'Fondi insufficienti o membro assente');
        $db->execute('INSERT INTO prestitipg(data,org,pg,qta) VALUES(?,?,?,?)',[time(),$org,$pg,$denari]);
        $loanId=(int)$db->lastInsertId();
        $db->execute('UPDATE organizzazioni SET budget=budget-? WHERE id=?',[$denari,$org]);
        $db->execute(ECON_CREDIT_SQL,[$denari,$pg]);
        $detail=alphanoCurrencyText("Hai ricevuto {$denari} Corone di prestito da {$budget['nome']}. Puoi rimborsarle dalla sezione Trasferimenti, donando alla stessa organizzazione.");
        inviaMissiva('Prestito ricevuto',$detail,$pg);
        alphanoRecordActivity('loan-pg-created',$actor,[
            ['scope'=>'organizzazioni','id'=>$org,'section'=>'prestiti','role'=>'admin'],
            ['scope'=>'personale','id'=>$pg,'section'=>'trasferimenti','role'=>'member'],
        ],'Prestito a personaggio erogato',alphanoCurrencyText("{$budget['nome']} ha concesso un prestito di {$denari} Corone a {$user['nome']}. Il rimborso avviene tramite un versamento alla stessa organizzazione dalla sezione Trasferimenti."),['pg_loan_id'=>$loanId,'destinatario'=>$pg,'importo'=>$denari,'org_budget_before'=>(int)$budget['budget'],'org_budget_after'=>(int)$budget['budget']-$denari,'pg_denari_before'=>(int)$user['denari'],'pg_denari_after'=>(int)$user['denari']+$denari]);
        return true;
    });
}

/** Le donazioni rimborsano prima i prestiti più vecchi, poi accrescono il budget. */
function creaDonazione(int $idpg, int $org, int $soldi, string $motivo = '', ?string $requestKey = null): bool
{
    if ($soldi<=0) {return false;}
    return economiaBool(static function() use($idpg,$org,$soldi,$motivo,$requestKey): bool {
        if (!alphanoOperationOnce('donation',$idpg,$requestKey,[$org,$soldi,$motivo])) {return true;}
        $db=getDB();
        $organization=$db->query('SELECT budget,nome FROM organizzazioni WHERE id=? FOR UPDATE',[$org])[0] ?? null;
        $user=$db->query('SELECT denari,nome FROM utenti WHERE id=? FOR UPDATE',[$idpg])[0] ?? null;
        economiaVerifica($organization && $user && (int)$user['denari']>=$soldi && (int)$organization['budget']<=PHP_INT_MAX-$soldi,'Fondi insufficienti o organizzazione assente');
        $remaining=$soldi;$debito=0;$receipts=[];
        foreach ($db->query('SELECT id,qta FROM prestitipg WHERE org=? AND pg=? ORDER BY data,id FOR UPDATE',[$org,$idpg]) as $loan) {
            $amount=(int)$loan['qta'];$debito+=$amount;
            if ($remaining<=0) {continue;}
            $receipts[]=['pg_loan_id'=>(int)$loan['id'],'debito_precedente'=>$amount,'rimborso'=>min($amount,$remaining),'debito_residuo'=>max(0,$amount-$remaining)];
            if ($amount<=$remaining) {$db->execute('DELETE FROM prestitipg WHERE id=?',[$loan['id']]);}
            else {$db->execute('UPDATE prestitipg SET qta=qta-? WHERE id=?',[$remaining,$loan['id']]);}
            $remaining-=min($remaining,$amount);
        }
        $repaid=min($debito,$soldi);
        $db->execute('UPDATE organizzazioni SET budget=budget+? WHERE id=?',[$soldi,$org]);
        $db->execute(ECON_DEBIT_SQL,[$soldi,$idpg]);
        $db->execute('INSERT INTO logs_adminpg(azione,esecutore,destinatario,qta,data,descr,chat) VALUES(?,?,?,?,?,?,?)',['donazione',$idpg,$org,$soldi,time(),$motivo,'']);
        $detail=alphanoCurrencyText("{$user['nome']} ha versato {$soldi} Corone a {$organization['nome']}. Rimborso prestiti: {$repaid} Corone. Donazione: {$remaining} Corone. Debito residuo: ").max(0,$debito-$soldi).alphanoCurrencyText(ECON_CURRENCY_SUFFIX).($motivo!=='' ? " Motivazione: {$motivo}" : '');
        foreach ($db->query('SELECT id FROM utenti WHERE organizzazione=? AND adminorganizzazione=1',[$org]) as $admin) {inviaMissiva($repaid>0?'Rimborso prestito PG':'Donazione ricevuta',$detail,(int)$admin['id']);}
        alphanoRecordActivity('organization-payment',$idpg,[
            ['scope'=>'organizzazioni','id'=>$org,'section'=>$repaid>0?'prestiti':'finanze','role'=>'admin'],
            ['scope'=>'personale','id'=>$idpg,'section'=>'trasferimenti','role'=>'member'],
        ],$repaid>0?'Rimborso prestito a personaggio':'Donazione all’organizzazione',$detail,['importo'=>$soldi,'rimborso'=>$repaid,'donazione'=>$remaining,'debito_residuo'=>max(0,$debito-$soldi),'motivo'=>$motivo,'prestiti'=>$receipts,'org_budget_before'=>(int)$organization['budget'],'org_budget_after'=>(int)$organization['budget']+$soldi,'pg_denari_before'=>(int)$user['denari'],'pg_denari_after'=>(int)$user['denari']-$soldi]);
        return true;
    });
}


/**
 * Aggiunge materie prime al magazzino corporativo.
 */
function inserisciMateria(string $materia, int $corp, int $qta = 1): bool
{
    global $dbequipar;
    // se $dbequipar non è un array, lo consideriamo vuoto
    $haystack = MATERIE_PRIME;

    // se materia vuota o non consentita, esci
    if ($qta<1 || $qta>2147483647 || $materia === '' || !in_array($materia, $haystack, true)) {
        return false;
    }

    $db = getDB();
    // Verifica esistenza record
    $rows = $db->query(
        'SELECT 1 FROM equipcorp WHERE corp = ? AND materia = ?',
        [$corp, $materia]
    );
    if (!empty($rows)) {
        // Update
        return $db->execute(
            'UPDATE equipcorp
                SET qta = qta + ?
              WHERE corp = ? AND materia = ?',
            [$qta, $corp, $materia]
        ) > 0;
    } else {
        // Insert
        return $db->execute(
            'INSERT INTO equipcorp (corp, materia, qta)
             VALUES (?, ?, ?)',
            [$corp, $materia, $qta]
        ) > 0;
    }
}


/** Ripristina un oggetto proprio usando le materie della propria corporazione. */
function riparaOggetto(int $instance, int $pg, int $corp): string
{
    try {
        return economiaAtomica(static function() use($instance,$pg,$corp): string {
            $db=getDB();
            economiaVerifica(economiaImpiegato($pg,$corp),'La riparazione richiede un responsabile della tua corporazione');
            $item=$db->query('SELECT e.*,p.consumo,p.id AS instance FROM equippg p JOIN equip e ON e.id=p.uid JOIN tipo t ON t.id=e.tipo JOIN produzionicorporazioni production ON production.produzione=t.subtipo AND production.corporazione=e.corp WHERE p.id=? AND p.idpg=? AND e.corp=? AND e.approvato=1 AND p.consumo>0 FOR UPDATE',[$instance,$pg,$corp])[0] ?? null;
            economiaVerifica((bool)$item,'Oggetto non riparabile');
            $ratio=min((int)$item['consumo'],(int)$item['durata'])/max(1,(int)$item['durata']);
            $mag=array_column($db->query('SELECT materia,qta FROM equipcorp WHERE corp=? FOR UPDATE',[$corp]),'qta','materia');
            foreach (getMateriePrime($item) ?: [] as $mat=>$units) {
                $need=(int)ceil((int)$units*$ratio);
                economiaVerifica($need>=0 && ($mag[$mat] ?? 0)>=$need,'Non disponi delle materie prime');
                if ($need>0) {$db->execute('UPDATE equipcorp SET qta=qta-? WHERE corp=? AND materia=?',[$need,$corp,$mat]);}
            }
            $db->execute('UPDATE equippg SET consumo=0 WHERE id=? AND idpg=?',[$instance,$pg]);
            $db->execute("INSERT INTO logs_equip(data,esecutore,origine,obj,azione) VALUES(?,?,?,?,'RIPARA')",[time(),$pg,$pg,$item['id']]);
            alphanoRecordActivity('equipment-repaired',$pg,[['scope'=>'corporazioni','id'=>$corp,'section'=>'catalogo','role'=>'trade'],['scope'=>'personale','id'=>$pg,'section'=>'trasferimenti','role'=>'member']],'Oggetto riparato',"Riparato {$item['shortDesc']}: consumo {$item['consumo']} → 0. Materie prelevate dal magazzino.",['instance_id'=>$instance,'oggetto'=>$item['shortDesc'],'prima'=>['consumo'=>(int)$item['consumo']],'dopo'=>['consumo'=>0],'materie_consumate'=>array_map(static fn($n)=>(int)ceil((int)$n*$ratio),getMateriePrime($item) ?: [])]);
            return 'Oggetto riparato correttamente';
        });
    } catch (DomainException $e) { return $e->getMessage(); }
    catch (Throwable $e) { error_log('[riparazione] '.$e->getMessage()); return 'Errore nella riparazione'; }
}
