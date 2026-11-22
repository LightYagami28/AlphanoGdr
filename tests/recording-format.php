<?php
declare(strict_types=1);
require __DIR__.'/../libs/libxml.inc.php';
$messages=['<p>Un messaggio di prova.</p>','Unicode: àèìòù'];
$xml=getXMLFromArray($messages,'giocata');
assert(getArrayFromBZXML($xml,'giocata')===$messages);
assert(getArrayFromBZXML($xml,'altra')===false);
assert(getArrayFromBZXML('<!DOCTYPE FIELDSET [<!ENTITY x SYSTEM "file:///etc/passwd">]><FIELDSET area="giocata" count="0"/>','giocata')===false);
assert(getArrayFromBZXML('BZh9invalid','giocata')===false);
echo "PASS portable recordings and invalid/legacy input guards\n";
