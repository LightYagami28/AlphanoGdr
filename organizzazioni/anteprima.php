<?PHP
	require("../libs/kernel.inc.php");
	require("../libs/libgrafica.inc.php");
	require("../libs/libpg.inc.php");
	require("../libs/libtext.inc.php");
	GetIDPG(0);
	$statuto=stripslashes((string)($_POST["testo"] ?? ''));
?>
<html>
	<head>
		<title>Anteprima <?=htmlspecialchars(GetTitolo($statuto),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?> <?=TitoloSito?></title>

	</head>
	<body class="Valore"><?=ProcessText($statuto)?></body>
</html>