<?php

/**
 * Test del contesto HTTPS e della normalizzazione dell'origine.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica le funzioni pure di libs/http-context.inc.php.
 */
final class HttpContextTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Carica le funzioni sotto test prima di ogni test.
	 */
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2).'/libs/http-context.inc.php';
	}

	/**
	 * Il contesto HTTPS viene rilevato da URL, server o proxy noto.
	 */
	public function testRilevaContestoHttps(): void
	{
		$casi = [
			['https://game.example', [], true],
			['', ['HTTPS' => 'on'], true],
			['', ['SERVER_PORT' => 443], true],
			['', ['HTTP_HOST' => 'example.altervista.org', 'HTTP_X_FORWARDED_PROTO' => 'https'], true],
			['', ['HTTP_HOST' => 'game.example', 'HTTP_X_FORWARDED_PROTO' => 'https'], false],
			['http://localhost', [], false],
			['', ['HTTPS' => 'off'], false],
			['', ['HTTP_HOST' => 'example.altervista.org'], false],
		];
		foreach ($casi as [$url, $server, $atteso]) {
			self::assertSame($atteso, alphanoHttpsContext($url, $server));
		}
	}

	/**
	 * L'origine canonica rimuove le porte predefinite e normalizza host e schema.
	 */
	public function testNormalizzaOrigine(): void
	{
		self::assertSame('https://game.example', alphanoCanonicalOrigin('https://GAME.example:443/'));
		self::assertSame('http://game.example', alphanoCanonicalOrigin('http://GAME.example:80/'));
		self::assertSame('http://localhost:8000', alphanoCanonicalOrigin('http://localhost:8000/'));
	}

	/**
	 * Uno schema non HTTP viene rifiutato.
	 */
	public function testRifiutaSchemaNonValido(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		alphanoCanonicalOrigin('ftp://game.example');
	}
}

?>
