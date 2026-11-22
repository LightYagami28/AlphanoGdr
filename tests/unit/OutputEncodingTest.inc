<?php

/**
 * Test della codifica degli output HTML e JSON.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica escape HTML e serializzazione JSON delle risposte quest.
 */
final class OutputEncodingTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Carica le funzioni sotto test prima di ogni test.
	 */
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2).'/libs/engine-settings.inc.php';
		require_once dirname(__DIR__, 2).'/libs/ai-quest.inc.php';
	}

	/**
	 * I metacaratteri HTML vengono convertiti in entita'.
	 */
	public function testEscapeHtml(): void
	{
		self::assertSame('&lt;script&gt;&amp;&quot;&#039;', alphano_escape('<script>&"'."'"));
		self::assertSame('123', alphano_escape(123));
		self::assertSame('', alphano_escape(null));
	}

	/**
	 * Il JSON mantiene Unicode e supporta il round-trip.
	 */
	public function testJsonQuest(): void
	{
		$json = aiQuestJson(['nome' => 'Citta', 'testo' => 'a "b" <c>']);
		self::assertSame('{"nome":"Citta","testo":"a \u0022b\u0022 \u003Cc\u003E"}', $json);
		self::assertSame(false, strpbrk($json, "<>&'"));
		self::assertSame(
			['nome' => 'Citta', 'testo' => 'a "b" <c>'],
			json_decode($json, true, 512, JSON_THROW_ON_ERROR)
		);
	}
}

?>
