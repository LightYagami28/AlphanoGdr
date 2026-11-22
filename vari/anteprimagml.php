<?PHP
	require("../libs/kernel.inc.php");
	require("../libs/libgrafica.inc.php");
	require("../libs/libpg.inc.php");
	require("../libs/libtext.inc.php");
	alphano_require_login();
    if ($_SERVER["REQUEST_METHOD"] === "POST") { alphano_require_csrf(); }
	$statuto=(string)($_POST["testo"] ?? "");
?>
<!DOCTYPE html>
<HTML lang="it">
	<HEAD>
		<TITLE>Anteprima <?=htmlspecialchars(GetTitolo($statuto),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?> <?=TitoloSito?></TITLE>

	</HEAD>
	<BODY>
		<DIV class="Valore"><?=ProcessText($statuto)?></DIV>
	</BODY>
</HTML>