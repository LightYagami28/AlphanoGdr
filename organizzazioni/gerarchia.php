<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/group-hierarchy.inc.php';
alphanoRequireLogin();
$rawId = $_GET['org'] ?? '';
if (!is_string($rawId) || filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) { alphanoFail('Organizzazione non specificata.', 400); }
require_once __DIR__.'/../libs/group-media.inc.php';
$hierarchy = alphanoGroupHierarchy('organizzazioni', (int)$rawId, alphanoUser());
$hierarchy['header_image']=groupHeaderImage('organizzazioni',(int)$rawId);
require_once __DIR__ . '/../templates/groups/hierarchy.php';
closePage();
