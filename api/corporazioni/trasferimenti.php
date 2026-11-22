<?php
declare(strict_types=1);

foreach (array_merge($_GET,$_POST) as $value) {
    if (is_array($value) || is_object($value)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'error'=>true,'message'=>'Parametri non validi']);
        exit;
    }
}

// ─── disabilito ogni warning/output accidentale ─────────────────────────────
ini_set('display_errors','0');
error_reporting(E_ALL);

// ─── header JSON ────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

// ─── librerie ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../libs/kernel.inc.php';
require_once __DIR__ . '/../../libs/libpg.inc.php';
require_once __DIR__ . '/../../libs/libgrafica.inc.php';
require_once __DIR__ . '/../../libs/libeconomia.inc.php';
require_once __DIR__ . '/../../libs/libposta.inc.php';
require_once __DIR__ . '/../../libs/libequip.inc.php';
require_once __DIR__.'/../../libs/corporation-commerce.inc.php';

// ─── helper per rispondere e terminare ──────────────────────────────────────
function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ─── autenticazione ─────────────────────────────────────────────────────
    $idpg = alphanoRequireLogin(true);
    if ($idpg === null) {
        respond(['success'=>false,'error'=>true,'message'=>'Non autenticato']);
    }

    $db   = getDB();
    $user = $db->query(
        'SELECT admin,corporazione,nome,admintrasfcorp,admincorporazione,razza
           FROM utenti WHERE id = ?',
        [$idpg]
    )[0] ?? null;

    if (!$user || (!$user['corporazione'] && empty($user['admin']))) {
        respond(['success'=>false,'error'=>true,'message'=>'Nessuna corporazione selezionata']);
    }

    try{$corp=alphanoCorporationContext($user,$_POST['corp']??$_GET['corp']??null);}catch(DomainException $e){http_response_code($e->getCode());respond(['success'=>false,'error'=>true,'message'=>$e->getMessage()]);}
    $pgname        = $user['nome'];
    $isAdminTransf = !empty($user['admin']) || (bool)$user['admintrasfcorp'] || (bool)$user['admincorporazione'];
    if (in_array((int)$user['razza'],[ALPHANO_RACE_SPIRIT,ALPHANO_RACE_DEAD_VAMPIRE,ALPHANO_RACE_LEGACY_SPIRIT],true)) { http_response_code(403); respond(['success'=>false,'error'=>true,'message'=>'Non puoi gestire il commercio quando sei morto']); }
    $isAdminCorp   = !empty($user['admin']) || (bool)$user['admincorporazione'];

    // ─── leggi azione ────────────────────────────────────────────────────────
    $azione = (string)($_REQUEST['azione'] ?? 'list');
    if ($azione!=='list' && ($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='POST') {
        http_response_code(405); header('Allow: GET, POST'); respond(['success'=>false,'error'=>true,'message'=>'Usare POST per modificare dati']);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') { alphanoRequireCsrf(true); }


    if (in_array($azione,['adminorder','admintransf','consegna','prendiincarico'],true)) {
        foreach (['id','prezzo','qta'] as $field) {
            if (isset($_POST[$field]) && commercioIntero($_POST[$field],1,$field==='qta' ? 10000 : 2147483647)===null) {
                http_response_code(400); respond(['success'=>false,'error'=>true,'message'=>alphanoCurrencyText('Inserisci quantità e Corone intere nei limiti consentiti')]);
            }
        }
    }

    switch ($azione) {

      // ─ Prendi in carico un ordine cliente ───────────────────────────────────
      case 'prendiincarico':
        if (!$isAdminTransf) {
            respond(['success'=>false,'error'=>true,'message'=>'Permessi insufficienti']);
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            respond(['success'=>false,'error'=>true,'message'=>'Parametri non esaurienti']);
        }
        $ok  = prendiInCaricoOrdine($id, $idpg);
        $msg = $ok
             ? 'Ordine preso in carico correttamente'
             : 'Errore durante la presa in carico dell\'ordine';
        respond(['success'=>$ok,'message'=>$msg]);

      // ─ Amministrazione trasferimenti ────────────────────────────────────────
// api/corporazioni/trasferimenti.php

case 'admintransf':
    if (!$isAdminTransf) {
        respond(['success'=>false,'error'=>true,'message'=>'Permessi insufficienti']);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        respond(['success'=>false,'error'=>true,'message'=>'Parametri non esaurienti']);
    }

    if (isset($_POST['accetta'])) {
        $msg = finalizeTransf($id, $idpg);
        respond(['success'=>in_array($msg,['Trasferimento avvenuto correttamente','Ordine accettato con successo'],true),'message'=>$msg]);
    }

    if (isset($_POST['elimina'])) {
        $ok  = rejectTransf($id, $idpg);
        $msg = $ok
             ? 'Trasferimento eliminato con successo'
             : 'Errore nell\'eliminazione del trasferimento';
        respond(['success'=>$ok,'message'=>$msg]);
    }

    if (
        isset($_POST['prezzo'], $_POST['qta'])
        && is_numeric($_POST['prezzo']) && (float)$_POST['prezzo'] > 0
        && is_numeric($_POST['qta'])     && (int)$_POST['qta']     > 0
    ) {
        // quando a rilanciare è la corp, lo stato deve diventare 4
        $newState = 4;
        $ok = updateTransf(
            $id,
            $idpg,
            (float)$_POST['prezzo'],
            $newState,
            (int)$_POST['qta']
        );
        $msg = $ok
            ? 'Proposta aggiornata correttamente'
            : 'Errore durante l\'operazione';
        respond(['success'=>$ok,'message'=>$msg]);
    }

    respond(['success'=>false,'error'=>true,'message'=>'Parametri non esaurienti']);
    break;

      // ─ Amministrazione ordini ───────────────────────────────────────────────
      case 'adminorder':
        if (!$isAdminTransf) {
            respond(['success'=>false,'error'=>true,'message'=>'Permessi insufficienti']);
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            respond(['success'=>false,'error'=>true,'message'=>'Parametri non esaurienti']);
        }
        if (isset($_POST['accetta'])) {
            $msg = finalizeOrdine($id, $idpg);
            respond(['success'=>in_array($msg,['Trasferimento avvenuto correttamente','Ordine accettato con successo'],true),'message'=>$msg]);
        }
        if (isset($_POST['elimina'])) {
            $ok  = rejectOrdine($id, $idpg);
            $msg = $ok
                 ? 'Ordine eliminato con successo'
                 : 'Errore nell\'eliminazione dell\'ordine';
            respond(['success'=>$ok,'message'=>$msg]);
        }
        if (
            isset($_POST['prezzo'], $_POST['qta'])
            && is_numeric($_POST['prezzo']) && (float)$_POST['prezzo'] > 0
            && is_numeric($_POST['qta'])     && (int)$_POST['qta']     > 0
        ) {
            $ok  = updateOrdine(
                $id,
                $idpg,
                (float)$_POST['prezzo'],
                (int)$_POST['qta'],
                3
            );
            $msg = $ok
                 ? 'Ordine aggiornato correttamente'
                 : 'Errore durante l\'operazione';
            respond(['success'=>$ok,'message'=>$msg]);
        }
        respond(['success'=>false,'error'=>true,'message'=>'Parametri non esaurienti']);

      // ─ Esegui una consegna ───────────────────────────────────────────────────
        case 'consegna':
            if (!$isAdminTransf) {
                respond([
                    'success' => false,
                    'error'   => true,
                    'message' => 'Permessi insufficienti'
                ]);
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                respond([
                    'success' => false,
                    'error'   => true,
                    'message' => 'Parametri non esaurienti'
                ]);
            }
            $msg     = consegna($id, $idpg, $corp);
            $success = ($msg === 'Consegna andata a buon fine');
            respond([
                'success' => $success,
                'error'   => !$success,
                'message' => $msg
            ]);
            break;


      // ─ Cancella storico cronologico ───────────────────────────────────────────
      case 'clearcrono':
        if (!$isAdminCorp) {
            respond(['success'=>false,'error'=>true,'message'=>'Permessi insufficienti']);
        }
        $tras = $db->query("SELECT id FROM trasferimenti WHERE corp = ?", [$corp]);
        $ids  = [];
        foreach ($tras as $r) {
            if (isset($_POST[$r['id']]) && $_POST[$r['id']] === 's') {
                $ids[] = (int)$r['id'];
            }
        }
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $db->execute(
                "UPDATE trasferimenti SET nascostocorp = 1 WHERE corp = ? AND id IN ($ph)",
                array_merge([$corp], $ids)
            );
        }
        respond(['success'=>true,'message'=>'Storico nascosto']);

      // ─ Legislazione DEFAULT: lista completa ──────────────────────────────────
      case 'list':
      default:
        clearTrasferimenti($corp);

        // 1) Contrattazioni
        $transazioni = [];
        foreach (getTransazioniCorp($corp, $idpg) as $r) {
            $transazioni[] = [
                'id'           => (int)$r['id'],
                'data'         => formatTimestamp($r['data']),
                'pg'           => (int)$r['pg'],
                'interlocutore'=> $r['interlocutore'],
                'nomeobj'      => $r['nomeobj'],
                'obj'          => (int)($r['obj'] ?? 0),
                'qta'          => (int)$r['qta'],           // ← Aggiunta quantità
                'prezzo'       => (float)$r['prezzo'],
                'stato'        => (int)$r['stato'],
                'verso'        => (int)$r['verso'],
                'statoText'    => statoTransf((int)$r['stato'], (int)$r['verso']),
            ];
        }

        // 2) Storico Acquisti
        $storicoAcq = [];
        foreach (getStoricoAcqCorp($corp) as $r) {
            $storicoAcq[] = [
                'id'           => (int)$r['id'],
                'data'         => formatTimestamp($r['data']),
                'pg'           => (int)$r['pg'],
                'pgcorp'       => (int)$r['pgcorp'],
                'pgcorpName'   => GetPGFromID((int)$r['pgcorp']),
                'interlocutore'=> $r['interlocutore'],
                'nomeobj'      => $r['nomeobj'],
                'qta'          => (int)$r['qta'],           // ← Aggiunta quantità
                'prezzo'       => (float)$r['prezzo'],
                'stato'        => (int)$r['stato'],
                'verso'        => (int)$r['verso'],
                'statoText'    => statoTransf((int)$r['stato'], (int)$r['verso']),
            ];
        }

        $storicoVen=[];
        foreach (getStoricoVenCorp($corp) as $r) {
            $storicoVen[]=[
                'id'=>(int)$r['id'],'data'=>formatTimestamp($r['data']),
                'pg'=>(int)$r['pg'],'pgcorp'=>(int)$r['pgcorp'],
                'pgcorpName'=>GetPGFromID((int)$r['pgcorp']),
                'interlocutore'=>$r['interlocutore'],'nomeobj'=>$r['nomeobj'],
                'qta'=>(int)$r['qta'],'prezzo'=>(int)$r['prezzo'],
                'stato'=>(int)$r['stato'],'verso'=>2,
                'statoText'=>statoTransf((int)$r['stato'],2),
            ];
        }

        // 3) Ordini Clienti
        $ordini = [];
        foreach (getOrdiniCorp($corp) as $r) {
            $ordini[] = [
                'id'           => (int)$r['id'],
                'data'         => formatTimestamp($r['data']),
                'pg'           => (int)$r['pg'],
                'pgcorp'       => $r['pgcorp'] ? (int)$r['pgcorp'] : 0,
                'pgcorpName'   => $r['pgcorp'] ? GetPGFromID((int)$r['pgcorp']) : null,
                'interlocutore'=> $r['interlocutore'],
                'nomeobj'      => $r['nomeobj'],
                'qta'          => (int)$r['qta'],
                'prezzo'       => (float)$r['prezzo'],
                'stato'        => (int)$r['stato'],
                'verso'        => (int)$r['verso'],
                'statoText'    => statoTransf((int)$r['stato'], (int)$r['verso']),
            ];
        }

        // 4) Ordini Presi in Carico
        $ordiniPresi = [];
        foreach (getOrdiniPGCorp($corp, $idpg) as $r) {
            $ordiniPresi[] = [
                'id'           => (int)$r['id'],
                'data'         => formatTimestamp($r['data']),
                'pg'           => (int)$r['pg'],
                'interlocutore'=> $r['interlocutore'],
                'nomeobj'      => $r['nomeobj'],
                'qta'          => (int)$r['qta'],
                'prezzo'       => (float)$r['prezzo'],
                'stato'        => (int)$r['stato'],
                'verso'        => (int)$r['verso'],
                'statoText'    => statoTransf((int)$r['stato'], (int)$r['verso']),
            ];
        }

        // 5) Magazzino
        $rawMag   = getMagazzino($corp);
        $magazzino = [];
        $materialArt=[];
        foreach($db->query("SELECT e.materiaprima,e.shortDesc,e.immagine FROM equip e JOIN tipo t ON t.id=e.tipo WHERE e.corp=0 AND e.approvato='1' AND t.tipoG='risorsa' ORDER BY e.id") as $art){ $materialArt[$art['materiaprima']]??=$art; }
        foreach ($rawMag as $materia => $qta) {
            $magazzino[] = [
                'materia' => $materia,
                'name' => $materialArt[$materia]['shortDesc']??ucfirst($materia),
                'image' => alphanoEquipmentImage(rawurldecode($materialArt[$materia]['immagine']??'')),
                'qta'     => $qta,
            ];
        }

        // 6) Consegne
        $consegne = [];
        foreach (getConsegne($corp, $idpg) as $r) {
            $consegne[] = [
                'image' => alphanoEquipmentImage(rawurldecode($r['immagine']??'')),
                'id'           => (int)$r['id'],
                'data'         => formatTimestamp($r['data']),
                'pg'           => (int)$r['pg'],
                'interlocutore'=> $r['interlocutore'],
                'nomeobj'      => $r['nomeobj'],
                'qta'          => (int)$r['qta'],
                'prezzo'       => (float)$r['prezzo'],
            ];
        }

        respond([
            'success'       => true,
            'transazioni'   => $transazioni,
            'storicoAcq'    => $storicoAcq,
            'storicoVen'    => $storicoVen,
            'budget'        => (int)($db->query('SELECT budget FROM corporazioni WHERE id=?',[$corp])[0]['budget'] ?? 0),
            'ordini'        => $ordini,
            'ordiniPresi'   => $ordiniPresi,
            'magazzino'     => $magazzino,
            'consegne'      => $consegne,
        ]);
    }
} catch (\Throwable $e) {
    // qualunque eccezione produce JSON di errore
    error_log('[trasferimenti] '.$e->getMessage());
    http_response_code(500); respond(['success'=>false,'error'=>true,'message'=>'Impossibile completare l’operazione. Riprova.']);
}
