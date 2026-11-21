<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/libs/engine-settings.inc.php';
require_once dirname(__DIR__, 2) . '/libs/ai-quest.inc.php';

use PHPUnit\Framework\TestCase;

final class OutputEncodingTest extends TestCase
{
    public function testEscapeHtmlMetacaratteri(): void
    {
        self::assertSame(
            '&lt;script&gt;&amp;&quot;&#039;',
            alphano_escape('<script>&"\'')
        );
    }

    public function testEscapeValoriNonStringa(): void
    {
        self::assertSame('123', alphano_escape(123));
        self::assertSame('', alphano_escape(null));
    }

    public function testJsonCodificaUniCodeSenzaPerdereDati(): void
    {
        $json = ai_quest_json(['nome' => 'Città', 'testo' => 'a "b" <c>']);
        self::assertSame('{"nome":"Città","testo":"a \"b\" <c>"}', $json);
        self::assertSame(['nome' => 'Città', 'testo' => 'a "b" <c>'], json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
