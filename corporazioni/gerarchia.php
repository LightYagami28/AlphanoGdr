<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/group-hierarchy.inc.php';
alphanoRequireLogin();
$rawId = $_GET['corp'] ?? '';
if (!is_string($rawId) || filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) { alphanoFail('Corporazione non specificata.', 400); }
require_once __DIR__.'/../libs/group-media.inc.php';
$hierarchy = alphanoGroupHierarchy('corporazioni', (int)$rawId, alphanoUser());
$hierarchy['header_image']=groupHeaderImage('corporazioni',(int)$rawId);
require __DIR__ . '/../templates/groups/hierarchy.php';
closePage();
