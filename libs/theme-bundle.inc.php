<?php
declare(strict_types=1);
/** Resolve the checked-in CSS manifest. No build tools are required on hosting. */
function alphanoThemeFiles(): array {
    $root=dirname(__DIR__);$manifest=file_get_contents($root.'/css/theme/theme.css');
    preg_match_all('~@import url\("([a-z-]+\.css)"\);~',$manifest,$matches);
    return [...array_map(static fn($name)=>$root.'/css/theme/'.$name,$matches[1]),$root.'/assets/engine.css',$root.'/assets/appearance.css',$root.'/assets/portal.css',$root.'/assets/credits.css',$root.'/assets/management.css'];
}
function alphanoThemeVersion(): string {
    static $version = null;
    return $version??=substr(hash('sha256',implode('',array_map(static fn($file)=>hash_file('sha256',$file),alphanoThemeFiles()))),0,20);
}
