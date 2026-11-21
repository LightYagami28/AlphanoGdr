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
 * Elenca le assegnazioni alle variabili storiche, con numero di riga.
 *
 * @return array<int,array{line:int,name:string,value:string}>
 */
function alphanoJsAssignments(string $source): array
{
    $assignments = [];
    $lines = preg_split('/\R/u', $source) ?: [];
    foreach ($lines as $number => $line) {
        if (preg_match('/(?:^|[;{}\s])(riga|tmp|sel|ci|cu|cl|cn|i)\s*=\s*([^;]+)/', $line, $match) !== 1) {
            continue;
        }
        $assignments[] = [
            'line'  => $number + 1,
            'name'  => $match[1],
            'value' => trim($match[2]),
        ];
    }
    return $assignments;
}

/**
 * Elenca i nomi dichiarati con var, let o const.
 *
 * @return array<int,string>
 */
function alphanoJsDeclaredNames(string $source): array
{
    $names = [];
    if (preg_match_all('/\b(?:var|let|const)\s+(riga|tmp|sel|ci|cu|cl|cn|i)\b/', $source, $matches) > 0) {
        foreach ($matches[1] as $name) {
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
    }
    return $names;
}

/**
 * Restituisce i nomi di variabile ancora assegnati senza dichiarazione.
 *
 * @return array<int,string>
 */
function alphanoJsImplicitAssignments(string $source): array
{
    $declared = alphanoJsDeclaredNames($source);
    $found = [];
    foreach (alphanoJsAssignments($source) as $assignment) {
        if (!in_array($assignment['name'], $declared, true) && !in_array($assignment['name'], $found, true)) {
            $found[] = $assignment['name'];
        }
    }
    foreach (['ci', 'cu', 'cl', 'cn', 'i'] as $name) {
        $inLoop = preg_match('/\bfor\s*\(\s*'.$name.'\s*(?:in|=)/', $source) === 1;
        if ($inLoop && !in_array($name, $declared, true) && !in_array($name, $found, true)) {
            $found[] = $name;
        }
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
 * Estrae le dichiarazioni CSS come mappa proprieta' => valore.
 *
 * @return array<string,string>
 */
function alphanoCssDeclarations(string $css): array
{
    $declarations = [];
    if (preg_match_all('/\b([a-z-]+)\s*:\s*([^;]+)\s*;/i', $css, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $match) {
            $declarations[strtolower($match[1])] = trim($match[2]);
        }
    }
    return $declarations;
}

/**
 * Estrae il valore della proprieta' r dal CSS, se presente.
 */
function alphanoCssRadiusValue(string $css): ?string
{
    $declarations = alphanoCssDeclarations($css);
    return $declarations['r'] ?? null;
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
