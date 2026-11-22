<?php
declare(strict_types=1);
/**
 * Verifica un insieme di pattern semplici invece di una singola regex complessa.
 * Quando e' richiesto l'array di cattura, restituisce la corrispondenza che
 * inizia prima nel testo, come farebbe l'alternativa unica originale.
 */
function aiRegexAny(array $patterns, string $subject, ?array &$matches = null): bool
{
    if ($matches === null) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }
        return false;
    }
    $best = null;
    $bestOffset = PHP_INT_MAX;
    foreach ($patterns as $pattern) {
        $found = [];
        if (!preg_match($pattern, $subject, $found, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        $offset = $found[0][1];
        if ($best === null || $offset < $bestOffset) {
            $best = $found;
            $bestOffset = $offset;
        }
    }
    if ($best === null) {
        $matches = [];
        return false;
    }
    $matches = array_map(static fn($m) => $m[0], $best);
    return true;
}
