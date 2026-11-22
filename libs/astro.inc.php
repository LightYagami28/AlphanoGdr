<?php declare(strict_types=1);

/**
 * Calcola alba e tramonto (timestamp) basati su coordinate RO.
 * @return array{int,int} [alba, tramonto]
 */
function sorgeCala(): array
{
    $giorno      = (int)date("z");
    $latitudine  = 45.0 * M_PI/180;
    $longitudine = 25.0 / 15.0; // in ore
    $hour           = -50/60 * M_PI/180;
    $zone        = (int)date("I") + 1;

    // declinazione solare
    $dk = 0.40954 * sin(0.0172 * ($giorno - 79.35));
    // fuso e correzione
    $corr = -(-0.1752*sin(0.03343*$giorno+0.5474) - 0.1340*sin(0.018234*$giorno-0.1939));
    // ore di alba e tramonto
    $acosArg = (sin($hour) - sin($latitudine)*sin($dk)) / (cos($latitudine)*cos($dk));
    $omega   = acos(max(-1, min(1, $acosArg))) / M_PI * 12;
    $albaH   = 12 - $omega + $corr - $longitudine + $zone;
    $traH    = 12 + $omega + $corr - $longitudine + $zone;

    // ───────── helper per creare un timestamp ─────────
    $make = fn(float $hour): int => mktime(
        (int) floor($hour),                               // ora intera
        (int) floor(($hour - floor($hour)) * 60),            // minuti
        0,                                             // secondi
        (int) date('n'),
        (int) date('j'),
        (int) date('Y')
    );

    return [$make($albaH), $make($traH)];
}

/**
 * Restituisce TRUE se è notte (prima alba o dopo tramonto).
 */
function licanTime(): bool
{
    [$alba, $tra] = sorgeCala();
    $now = time();
    return ($now < $alba) || ($now >= $tra);
}

/**
 * Calcola fase lunare (1–30).
 */
function getFaseLunare(?int $at = null): int
{
    $at ??= time();
    $year  = (int)date('Y', $at);
    $century  = (int)($year / 100);
    $go = ($year % 19) + 1;
    $e  = 30 - ((((int)(($century-15)*3/4 - (($century-15)/25) /3)) % 30) - ((11*$go-10)%30));

    $lunarMonth = match((int)date('n', $at)) {
        1 => 0,
        2 => 1,
        3 => date('L', $at) ? 1 : 0,
        4 => date('L', $at) ? 2 : 1,
        5 => date('L', $at) ? 3 : 2,
        6 => date('L', $at) ? 4 : 3,
        7 => date('L', $at) ? 5 : 4,
        8 => date('L', $at) ? 6 : 5,
        9 => date('L', $at) ? 8 : 7,
       10 => date('L', $at) ? 8 : 7,
       11 => date('L', $at) ? 10: 9,
       12 => date('L', $at) ? 10: 9,
        default => 0,
    };

    $fase = $e + $lunarMonth + (int)date('j', $at);
    while ($fase > 30) {
        $fase -= 30;
    }
    return max(1, min(30, (int)$fase));
}

/**
 * Descrizione testuale della fase lunare.
 */
function faseLunare(?int $at = null): string
{
    $f = getFaseLunare($at);
    return match(true) {
        $f < 7   => 'Luna Nuova',
        $f < 14  => 'Luna Crescente',
        $f < 22  => 'Luna Piena',
        default  => 'Luna Calante',
    };
}

/**
 * Giorni mancanti alla Luna Piena.
 */
function daysToFullMoon(): int
{
    $f = getFaseLunare();
    return $f >= 14 && $f < 22 ? 0 : (14 - $f + 30) % 30;
}

/** Conto alla rovescia del calendario di gioco e durata della notte. */
function timeToFullMoon(): array
{
    [$rise, $set] = sorgeCala();
    $night = 86400 - ($set - $rise);
    $days = daysToFullMoon();
    $delta = $days === 0 ? 0 : max(0, strtotime('+' . $days . ' days', $set) - time());
    return [(int)floor($delta / 86400), (int)floor(($delta % 86400) / 3600),
        (int)floor(($delta % 3600) / 60), (int)floor($night / 3600), (int)floor(($night % 3600) / 60)];
}
