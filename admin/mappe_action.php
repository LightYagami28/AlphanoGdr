<?php
require_once dirname(__DIR__).'/libs/kernel.inc.php';
alphanoRequireLogin();if(!alphanoCan('admin')){ alphanoFail('Accesso riservato.',403); }header('Location: /admin/world.php',true,303);exit;
