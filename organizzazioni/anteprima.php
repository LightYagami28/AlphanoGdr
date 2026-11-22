<?PHP
	require_once("../libs/kernel.inc.php");
	require_once("../libs/libgrafica.inc.php");
	require_once("../libs/libpg.inc.php");
	require_once("../libs/libtext.inc.php");
	GetIDPG(0);
	$statuto=stripslashes((string)($_POST["testo"] ?? ''));
?>
<!DOCTYPE html>
<html lang="it">
	<head>
		<title>Anteprima <?=htmlspecialchars(getTitolo($statuto),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?> <?=TITOLO_SITO?></title>

	</head>
	<body class="Valore"><?=processText($statuto)?></body>
</html>