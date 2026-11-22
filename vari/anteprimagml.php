<?PHP
    require("../libs/kernel.inc.php");
    require("../libs/libgrafica.inc.php");
    require("../libs/libpg.inc.php");
    require("../libs/libtext.inc.php");
    alphanoRequireLogin();
    if ($_SERVER["REQUEST_METHOD"] === "POST") { alphanoRequireCsrf(); }
    $statuto=(string)($_POST["testo"] ?? "");
?>
<!DOCTYPE html>
<HTML lang="it">
    <HEAD>
        <TITLE>Anteprima <?=htmlspecialchars(getTitolo($statuto),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?> <?=TITOLO_SITO?></TITLE>

    </HEAD>
    <BODY>
        <DIV class="Valore"><?=processText($statuto)?></DIV>
    </BODY>
</HTML>
