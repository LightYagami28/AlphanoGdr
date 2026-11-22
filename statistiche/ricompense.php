<?PHP
    require_once "../libs/kernel.inc.php";
    require_once "../libs/libpg.inc.php";
    require_once "../libs/libequip.inc.php";
    alphanoRequireLogin();
    $user = alphanoUser();
    if (!$user || (empty($user['admin']) && empty($user['fatepower']))) {
        alphanoFail('Accesso riservato ai Master e agli Amministratori.', 403);
    }
    $db=connectDB();

    $master = isset($_GET["master"]) ? (int)$_GET["master"] : null;
    $sessione = isset($_GET["sessione"]) ? (int)$_GET["sessione"] : null;
    $condition = "";
    $params = [];

    if ($master !== null && $master > 0) {
        $condition = "logs_adminpg.esecutore = ? AND logs_adminpg.data BETWEEN sessioni.datain AND sessioni.dataout";
        $params = [$master];
    } elseif ($sessione !== null && $sessione > 0) {
        $dati = $db->query("SELECT id, datain, dataout FROM sessioni WHERE id = ?", [$sessione])[0] ?? null;
        if (!$dati) { http_response_code(404); exit('Sessione non trovata'); }
        $condition = "sessioni.id = ? AND logs_adminpg.data BETWEEN ? AND ?";
        $params = [(int)$dati["id"], (int)$dati["datain"], (int)$dati["dataout"]];
    } else {
        alphanoFail("Nessun tipo di indagine selezionato.", 400);
    }

    $query="SELECT sessioni.id,sessioni.nome,logs_adminpg.obj,logs_adminpg.azione,logs_adminpg.qta,logs_adminpg.esecutore AS master,master.nome AS nomemaster,destinatario.nome AS nomedestinatario,destinatario.id AS destinatario FROM `logs_adminpg`,sessioni,utenti AS destinatario,utenti AS master WHERE logs_adminpg.azione LIKE 'ricompensa%' AND destinatario.id=logs_adminpg.destinatario AND master.id=logs_adminpg.esecutore AND logs_adminpg.chat=sessioni.chat AND $condition";
    $res=$db->query($query, $params);
    $sessioni=array();
    while($dati=legacyFetchRow($res)) {
        $sessioni[$dati["id"]]["nome"]=$dati["nome"];
        $sessioni[$dati["id"]]["master"][$dati["master"]]["nome"]=$dati["nomemaster"];
        $sessioni[$dati["id"]]["master"][$dati["master"]]["ricompense"][$dati["destinatario"]]["nome"]=$dati["nomedestinatario"];
        if($dati["azione"]=="ricompensa(oggetto)") {
            $sessioni[$dati["id"]]["master"][$dati["master"]]["ricompense"][$dati["destinatario"]]["obj"][$dati["obj"]] = ($sessioni[$dati["id"]]["master"][$dati["master"]]["ricompense"][$dati["destinatario"]]["obj"][$dati["obj"]] ?? 0) + 1;
        } else {
            $sessioni[$dati["id"]]["master"][$dati["master"]]["ricompense"][$dati["destinatario"]]["soldi"] = ($sessioni[$dati["id"]]["master"][$dati["master"]]["ricompense"][$dati["destinatario"]]["soldi"] ?? 0) + (int)$dati["qta"];
        }
    }
?>
<!DOCTYPE html>
<HTML lang="it">
    <HEAD>
        <TITLE>Statistiche Ricompense <?=TITOLO_SITO?></TITLE>

        <SCRIPT language="JavaScript" src="/js/global.js"></SCRIPT>
    </HEAD>
    <BODY>
        <DIV class="Valore">
            <SPAN class="Descrizione">ATTENZIONE:</SPAN> <b>Le ricompense sono associate alla sessione tramite luogo e intervallo di apertura.</b><br>
<?PHP
    if(count($sessioni)) {
?>
            <UL>
<?PHP
        foreach($sessioni as $key => $dati1) {
?>
                <LI>
                    Sessione: <?=htmlentities($dati1["nome"])?><br>
<?PHP
            $dati = $db->query('SELECT * FROM sessioni WHERE id = ?', [(int)$key])[0] ?? [];
?>
                    Aperta da: <A href="javascript:openProfileId('<?=(int)($dati["masterin"] ?? 0)?>')"><?=htmlentities(GetPGFromID($dati["masterin"] ?? 0))?></A> in data <?=formatTimestamp((int)($dati["datain"] ?? 0))?><br>
                    Chiusa da: <A href="javascript:openProfileId('<?=(int)($dati["masterout"] ?? 0)?>')"><?=htmlentities(GetPGFromID($dati["masterout"] ?? 0))?></A> in data <?=formatTimestamp((int)($dati["dataout"] ?? 0))?><br>
<?PHP
            $rawIds = array_filter(array_map('intval', explode(';', trim((string)($dati['partecipanti'] ?? ''), ';'))));
            $partecipanti = [];
            if ($rawIds) {
                $ph = implode(',', array_fill(0, count($rawIds), '?'));
                $res = $db->query("SELECT id,nome FROM utenti WHERE id IN ($ph) ORDER BY nome", array_values($rawIds));
                foreach ($res as $partRow) {
                    $partecipanti[] = "<A href=\"javascript:openProfileId('" . (int)$partRow['id'] . "')\">" . htmlentities($partRow['nome']) . "</A>";
                }
            }
?>
                    Partecipanti: <?=implode(", ",$partecipanti)?><br>
                    Ricompense:<br>
                    <UL>
<?PHP
            foreach($dati1["master"] as $id2 => $dati2) {
?>
                        <LI>
                            <A href="javascript:openProfileId('<?=(int)$id2?>')"><?=htmlentities($dati2["nome"])?></A><br>
                            <UL>
<?PHP
                foreach($dati2["ricompense"] as $id3 => $dati3) {
?>
                                <LI>
                                    <A href="javascript:openProfileId('<?=(int)$id3?>')"><?=htmlentities($dati3["nome"])?></A>
<?PHP
                    if(($dati3["soldi"] ?? 0)) {
?>
                                    (<?=($dati3["soldi"] ?? 0)?> <?=alphanoCurrencyHtml()?>)
<?PHP
                    }
                    foreach(($dati3["obj"] ?? []) as $key => $qta) {
?>
                                    (<?=$qta?> <?=htmlentities(getEquipDesc($key))?>)
<?PHP
                    }
?>
                                </LI>
<?PHP
                }
?>
                            </UL>
                        </LI>
<?PHP
            }
?>
                    </UL>
                </LI>
<?PHP
        }
?>
            </UL>
<?PHP
    }
    else {
?>
            <DIV class="align-center">
<?PHP
        if (isset($_GET["master"])) {
?>
                Nessuna ricompensa trovata per il master selezionato.
<?PHP
        } elseif (isset($_GET["sessione"])) {
?>
                Nessuna ricompensa trovata per la sessione selezionata.
<?PHP
        }
?>
            </DIV>
<?PHP
    }
?>
        </DIV>
        <DIV class="align-center">
<?PHP
    if (isset($_GET["master"])) {
?>
            <INPUT type="button" value="Torna alle statistiche Master" onclick="javascript:window.location.href = 'master.php'">
<?PHP
    } elseif (isset($_GET["sessione"])) {
?>
            <INPUT type="button" value="Torna alla lista Sessioni" onclick="javascript:window.location.href = '../sessione/viewlog.php'">
<?PHP
    }
?>
        </DIV>
    </BODY>
</HTML>
