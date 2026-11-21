<?php
declare(strict_types=1);

/**
 * Controlli sul sorgente frontend: dichiarazioni esplicite e valori CSS validi.
 *
 * Usati dai test di regressione per evitare la reintroduzione di globali
 * impliciti e di valori geometrici senza unita'.
 */

/** @return array<int,string> */
function alphanoFrontendJsFiles(): array
{
    return ['js/chats.js', 'js/gml.js', 'js/password.js'];
}

/**
 * Restituisce i nomi di variabile ancora assegnati senza dichiarazione.
 *
 * @return array<int,string>
 */
function alphanoJsImplicitAssignments(string $source): array
{
    $body = (string)preg_replace(
        '/\b(?:var|let|const)\s+(?:riga|tmp|sel|ci|cu|cl|cn|i)\b/',
        '',
        $source
    );
    $found = [];
    foreach (['riga', 'tmp', 'sel', 'ci', 'cu', 'cl', 'cn', 'i'] as $name) {
        if (preg_match('/(?:^|[;{}\s])'.$name.'\s*=/m', $body) === 1) {
            $found[] = $name;
        }
    }
    if (preg_match('/\bfor\s*\(\s*(ci|cu|cl|cn|i)\s*(?:in|=)/', $body, $match) === 1
        && !in_array($match[1], $found, true)) {
        $found[] = $match[1];
    }
    return $found;
}

/**
 * True quando non resta alcuna assegnazione implicita nota.
 */
function alphanoJsExplicitDeclarations(string $source): bool
{
    return alphanoJsImplicitAssignments($source) === [];
}

/**
 * Estrae il valore della proprieta' r dal CSS, se presente.
 */
function alphanoCssRadiusValue(string $css): ?string
{
    if (preg_match('/\br\s*:\s*(\d+(?:px|em|rem|%)?)\s*;/i', $css, $matches) !== 1) {
        return null;
    }
    return $matches[1];
}

/**
 * True quando il raggio CSS dichiara un'unita' di misura.
 */
function alphanoCssRadiusHasUnit(string $css): bool
{
    $value = alphanoCssRadiusValue($css);
    return $value !== null && preg_match('/\d$/D', $value) !== 1;
}

/**
 * Rapporto sui sorgenti frontend, usato dai test di regressione.
 *
 * @return array<string,mixed>
 */
function alphanoFrontendSourceReport(string $root): array
{
    $report = [];
    foreach (alphanoFrontendJsFiles() as $file) {
        $report[$file] = alphanoJsImplicitAssignments((string)file_get_contents($root.'/'.$file));
    }
    $report['css/theme/maps.css'] = alphanoCssRadiusValue(
        (string)file_get_contents($root.'/css/theme/maps.css')
    );
    return $report;
}
