<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/../kernel.inc.php';
require_once __DIR__ . '/../libgrafica.inc.php';
require_once __DIR__ . '/../libpg.inc.php';
require_once __DIR__ . '/../libposta.inc.php';
require_once __DIR__ . '/../libbacheca.inc.php';
require_once __DIR__ . '/../libtext.inc.php';
$idpg=alphanoRequireLogin();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { alphanoRequireCsrf(); }
foreach (array_merge($_GET,$_POST) as $value) { if (!is_scalar($value)) { alphanoFail('Parametri non validi.',400); } }

function bachecaLegacyBoard(): int {
    $board=(int)($_POST['bc'] ?? $_GET['bc'] ?? 0);
    if ($board<1) { alphanoFail('Bacheca non trovata.',404); }
    playerRequireBoard(getDB(),$board,alphanoUser());
    return $board;
}

function bachecaLegacyRender(int $board=0,int $thread=0,string $location='',string $message=''): void {
    $user=alphanoUser();
    $forum=['mode'=>'board','title'=>'Bacheche','apiBaseUrl'=>'/api/bacheche',
        'currentUserId'=>(int)$user['id'],'isAdmin'=>!empty($user['admin']),'csrfToken'=>alphanoCsrfToken(),
        'initialBoard'=>$board,'initialThread'=>$thread,'initialLocation'=>$location,'message'=>$message];
    require_once dirname(__DIR__,2).'/templates/forum/index.php';
}
