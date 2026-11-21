<?php

/**
 * Test dell'escape dei parametri di richiesta stampati nelle pagine HTML.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica lo standard di escape usato da showdetails.php e showstatuto.php.
 */
final class OutputSafetyTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Carica le funzioni sotto test prima di ogni test.
     */
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/libs/output-safety.inc.php';
    }

    /**
     * Ogni metacarattere HTML viene neutralizzato con le virgolette.
     */
    public function testEscapeMetacaratteri(): void
    {
        self::assertSame('&lt;script&gt;&amp;&quot;&#039;', alphanoHtmlEscape('<script>&"\''));
        self::assertSame('123', alphanoHtmlEscape(123));
        self::assertSame('', alphanoHtmlEscape(null));
        self::assertSame('città', alphanoHtmlEscape('città'));
    }

    /**
     * L'escape condiviso equivale alla chiamata inline usata nei template.
     */
    public function testEquivalenzaConTemplate(): void
    {
        foreach (['<img src=x onerror=1>', 'a" onfocus="b', "x'y", 'città & dintorni'] as $value) {
            self::assertSame(
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                alphanoHtmlEscape($value)
            );
        }
    }

    /**
     * I valori non scalari non possono raggiungere un attributo HTML.
     */
    public function testParametriNonScalari(): void
    {
        self::assertSame('', alphanoRequestParameter([['pgName' => ['x']]], 'pgName'));
        self::assertSame('', alphanoRequestParameter([['pgName' => new stdClass()]], 'pgName'));
        self::assertSame('', alphanoRequestParameter([], 'pgName'));
        self::assertSame('', alphanoRequestParameter([['nome' => 'x']], 'pgName'));
    }

    /**
     * La sorgente con priorita' piu' alta vince e il fallback e' determinato.
     */
    public function testPrioritaSorgenti(): void
    {
        $post = ['pgName' => 'Post', 'org' => 12];
        $get  = ['pgName' => 'Get', 'nome' => 'Mario'];
        self::assertSame('Post', alphanoRequestParameter([$post, $get], 'pgName'));
        self::assertSame('12', alphanoRequestParameter([$post, $get], 'org'));
        self::assertSame('Mario', alphanoRequestParameter([$post, $get], 'nome'));
        self::assertSame('Get', alphanoRequestParameter([[], $get], 'pgName'));
    }
}
