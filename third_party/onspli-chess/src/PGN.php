<?php

namespace Onspli\Chess;


/**
* Portable Game Notation (PGN) is a standard plain text format for recording chess games.
*
* Portable Game Notation (PGN) is a standard plain text format for recording
* chess games (both the moves and related data), which can be read by humans
* and is also supported by most chess software.
*/
class PGN
{
  protected $tags = [];
  protected $halfmoves = [];
  protected $fens = [];
  protected $initialFen;
  protected $initialHalfmoveNumber = 1;

  /**
  * Initialize object from PGN string.
  */
  public function __construct(string $pgn = '')
  {
    $this->unsetInitialFen();
    $this->parseTagSection($pgn);
    $movetext_section = $this->getMovetextSection($pgn);
    if ($movetext_section) {
      $halfmoves = explode(' ', $movetext_section);
      array_walk($halfmoves, [self::class, 'move']);
    }
  }
  use PgnMetadataTrait, PgnPositionTrait, PgnMovetextTrait;

  /**
   * Compatibilita' con la API storica in snake_case della libreria vendored.
   */
  public function __call(string $name, array $arguments): mixed
  {
    $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    if (method_exists($this, $camel)) {
      return $this->$camel(...$arguments);
    }
    throw new \BadMethodCallException('Metodo sconosciuto: ' . $name);
  }
}
