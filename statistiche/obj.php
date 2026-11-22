<?php

    require_once "../libs/kernel.inc.php";
    require_once "../libs/libpg.inc.php";
    require_once "../libs/libgrafica.inc.php";
    alphano_require_login();

    $db=ConnectDB();
    $timer=GeneratePastTimestamp(30);
    $dati=["num"=>0, "nomeobj"=>"", "avgprz"=>0];
    $objId=(int)($_POST["obj"] ?? 0);
    $verso=(string)($_POST["verso"] ?? "");

    if ($verso === "1") {
        $row = $db->query("SELECT nomeobj FROM trasferimenti WHERE obj = ? AND verso = '1' LIMIT 1", [$objId])[0] ?? null;
        if ($row && !empty($row["nomeobj"])) {
            $res = $db->query(
                "SELECT nomeobj, COUNT(*) AS num, AVG(prezzo) AS avgprz FROM trasferimenti WHERE nomeobj = ? AND stato = '5' AND data > ? GROUP BY nomeobj",
                [$row["nomeobj"], $timer]
            );
            $dati = $res[0] ?? $dati;
        }
    } elseif ($verso === "2") {
        $res = $db->query(
            "SELECT nomeobj, COUNT(*) AS num, AVG(prezzo) AS avgprz FROM trasferimenti WHERE obj = ? AND (stato = '7' OR stato = '5') AND verso = '2' AND data > ? GROUP BY obj",
            [$objId, $timer]
        );
        $dati = $res[0] ?? $dati;
    }
?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <TITLE>Statistiche Oggetto <?=TitoloSito?></TITLE>

    </head>
    <body>
<?PHP
    if($dati["num"])
    {
?>
        <table class="column-width-100"><thead><tr><th scope="col" class="ui-visually-hidden">Dettaglio</th><th scope="col" class="ui-visually-hidden">Valore</th></tr></thead>
            <TR>
                <TD class="Descrizione align-center">Il prezzo indicato NON &egrave; vincolante</TD>
            </TR>
        </table>
        <table class="column-width-100"><thead><tr><th scope="col" class="ui-visually-hidden">Dettaglio</th><th scope="col" class="ui-visually-hidden">Valore</th></tr></thead>
            <TR>
                <TD class="Valore align-center">Oggetto</TD>
                <TD class="Valore align-center"><?=htmlspecialchars((string)$dati["nomeobj"], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></TD>
            </TR>
            <TR>
                <TD class="Valore align-center">Numero Transazioni</TD>
                <TD class="Valore align-center"><?=$dati["num"]?></TD>
            </TR>
            <TR>
                <TD class="Valore align-center">Prezzo Medio</TD>
                <TD class="Valore align-center"><?=round($dati["avgprz"])?> <?=alphano_currency_html()?></TD>
            </TR>
        </table>
<?PHP
    }
    else
    {
?>
        <table class="column-width-100"><thead><tr><th scope="col" class="ui-visually-hidden">Dettaglio</th><th scope="col" class="ui-visually-hidden">Valore</th></tr></thead>
            <TR>
                <TD class="Descrizione align-center">Nessun oggetto di questo tipo mai venduto</TD>
            </TR>
        </table>
<?PHP
    }
?>
    </body>
</html>
<?PHP
    ClosePage();
?>
