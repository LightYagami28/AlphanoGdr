<?php

namespace Onspli\Chess;

/**
* FEN is a standard notation for describing a particular board position of a chess game
*
* Forsyth–Edwards Notation (FEN) is a standard notation for describing a particular board
* position of a chess game. The purpose of FEN is to provide all the necessary information
* to restart a game from a particular position.
*
* Class provides intefrace for reading and setting all FEN fields, and also
* method for checking game state (check, mate, stalemate, fifty-move rule draw),
* getting all available moves in the position and changing the position by performing
* move according to chess rules.
*/
class FEN
{
    private $board;
    private $active = 'w';
    private $enPassant;
    private $halfmove = 0;
    private $fullmove = 1;

    private $kingsRookFile = 'h';
    private $queensRookFile = 'a';
    private $kingsFile = 'e';
    private $useShredderFen = false;
    private $castlingRights = [ "K" => true, "Q" => true, "k" => true, "q" => true];

    /**
    * Load FEN (or Shredder-FEN) or setup starting position.
    * @param string $fen
    * @return void
    */
    public function __construct(string $fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1')
    {
      $fen = trim($fen);
      $fen = preg_replace('/\s+/', ' ', $fen);
      $parts = explode(' ', $fen);
      if (sizeof($parts) < 4 || sizeof($parts) > 6) {
        throw new ParseException("FEN has " . sizeof($parts) . " fields. It must have at least 4 fields.");
      }

      $this->setBoard($parts[0]);
      $this->setActiveColor($parts[1]);
      $this->setCastling($parts[2]);
      $this->setEnPassant($parts[3]);
      $this->setHalfmove($parts[4] ?? 0);
      $this->setFullmove($parts[5] ?? 1);
    }
  use FenPositionTrait, FenStatusTrait, FenPseudoMovesTrait, FenCastlingMovesTrait, FenMoveTrait;

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
