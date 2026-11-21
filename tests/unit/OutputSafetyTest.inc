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
        self::assertSame('&lt;script&gt;&amp;&quot;&#039;', alphano_html_escape('<script>&"\''));
        self::assertSame('123', alphano_html_escape(123));
        self::assertSame('', alphano_html_escape(null));
        self::assertSame('città', alphano_html_escape('città'));
    }

    /**
     * L'escape condiviso equivale alla chiamata inline usata nei template.
     */
    public function testEquivalenzaConTemplate(): void
    {
        foreach (['<img src=x onerror=1>', 'a" onfocus="b', "x'y", 'città & dintorni'] as $value) {
            self::assertSame(
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                alphano_html_escape($value)
            );
        }
    }

    /**
     * I valori non scalari non possono raggiungere un attributo HTML.
     */
    public function testParametriNonScalari(): void
    {
        self::assertSame('', alphano_request_parameter([['pgName' => ['x']]], 'pgName'));
        self::assertSame('', alphano_request_parameter([['pgName' => new stdClass()]], 'pgName'));
        self::assertSame('', alphano_request_parameter([], 'pgName'));
        self::assertSame('', alphano_request_parameter([['nome' => 'x']], 'pgName'));
    }

    /**
     * La sorgente con priorita' piu' alta vince e il fallback e' determinato.
     */
    public function testPrioritaSorgenti(): void
    {
        $post = ['pgName' => 'Post', 'org' => 12];
        $get  = ['pgName' => 'Get', 'nome' => 'Mario'];
        self::assertSame('Post', alphano_request_parameter([$post, $get], 'pgName'));
        self::assertSame('12', alphano_request_parameter([$post, $get], 'org'));
        self::assertSame('Mario', alphano_request_parameter([$post, $get], 'nome'));
        self::assertSame('Get', alphano_request_parameter([[], $get], 'pgName'));
    }
}
