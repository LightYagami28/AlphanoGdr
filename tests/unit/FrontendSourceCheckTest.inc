<?php

/**
 * Test di regressione per dichiarazioni JS esplicite e valori CSS validi.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica i sorgenti frontend corretti e i rilevatori delle vecchie forme.
 */
final class FrontendSourceCheckTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Carica le funzioni sotto test prima di ogni test.
     */
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/libs/frontend-source-check.inc.php';
    }

    /**
     * I file JS corretti non contengono assegnazioni implicite.
     */
    public function testSorgentiJsCorretti(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['js/chats.js', 'js/gml.js', 'js/password.js'] as $file) {
            self::assertTrue(
                alphano_js_explicit_declarations((string)file_get_contents($root.'/'.$file)),
                $file.' contiene dichiarazioni implicite'
            );
        }
    }

    /**
     * Il rilevatore riconosce le vecchie forme prive di parola chiave.
     */
    public function testRilevamentoGlobaliImpliciti(): void
    {
        self::assertFalse(alphano_js_explicit_declarations('riga = righe.pop();'));
        self::assertFalse(alphano_js_explicit_declarations('tmp = obj.scrollTop;'));
        self::assertFalse(alphano_js_explicit_declarations('sel = document.selection.createRange();'));
        self::assertFalse(alphano_js_explicit_declarations('for (c in punctuations) {}'));
        self::assertFalse(alphano_js_explicit_declarations('for (i in membership) {}'));

        self::assertTrue(alphano_js_explicit_declarations('var riga = righe.pop();'));
        self::assertTrue(alphano_js_explicit_declarations('var tmp = obj.scrollTop;'));
        self::assertTrue(alphano_js_explicit_declarations('var sel = document.selection.createRange();'));
        self::assertTrue(alphano_js_explicit_declarations('for (var c in punctuations) {}'));
        self::assertTrue(alphano_js_explicit_declarations('for (var i in membership) {}'));
    }

    /**
     * Il CSS della mappa usa il raggio con unita' di misura.
     */
    public function testUnitaCss(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__, 2).'/css/theme/maps.css');
        self::assertTrue(alphano_css_radius_has_unit($css));
        self::assertFalse(alphano_css_radius_has_unit('body .map-dot { r: 6; }'));
        self::assertTrue(alphano_css_radius_has_unit('body .map-dot { r: 6px; }'));
    }
}
