<?php
namespace Onspli\Chess;

class Board
{
  private $board = [];

  /**
  * Load piece placement or setup initial position.
  *
  * Piece placement (from White's perspective). Each rank is described,
  * starting with rank 8 and ending with rank 1; within each rank,
  * the contents of each square are described from file "a" through file "h".
  * Following the Standard Algebraic Notation (SAN), each piece is identified
  * by a single letter taken from the standard English names (pawn = "P",
  * knight = "N", bishop = "B", rook = "R", queen = "Q" and king = "K").
  * White pieces are designated using upper-case letters ("PNBRQK") while
  * black pieces use lowercase ("pnbrqk"). Empty squares are noted using
  * digits 1 through 8 (the number of empty squares), and "/" separates ranks.
  */
  public function __construct(string $pieces = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR')
  {
    $this->board = array_fill(0, 64, '');
    $ranks_array = $this->splitToRanks($pieces);
    foreach ($ranks_array as $index => $rank_pieces)
    {
      $this->fillRank($rank_pieces, 7 - $index);
    }
  }

  private function splitToRanks(string $pieces) : array
  {
    $pieces = preg_replace('/\s+/', '', $pieces);
    $ranks_array = explode('/', $pieces);
    if (sizeof($ranks_array) != 8) {
      throw new ParseException("Wrong number of ranks " . sizeof($ranks_array) . ".");
    }
    return $ranks_array;
  }

  private function fillRank(string $rank_pieces, int $rankIndex) : void
  {
    $fileIndex = 0;
    $rank_pieces_array = str_split($rank_pieces);
    foreach ($rank_pieces_array as $piece)
    {
      if (is_numeric($piece))
      {
        $fileIndex += intval($piece);
      }
      else
      {
        $this->setSquare(new Square($fileIndex, $rankIndex), $piece);
        $fileIndex += 1;
      }
      if ($fileIndex > 8) {
        throw new ParseException("Too many pieces on rank.");
      }
    }
  }

  public function __toString() : string
  {
    return $this->export();
  }
  use BoardQueryTrait, BoardDefenseTrait, BoardReachabilityTrait, BoardValidationTrait;

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

  /**
   * Compatibilita' con la API statica storica in snake_case.
   */
  public static function __callStatic(string $name, array $arguments): mixed
  {
    $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    if (is_callable([static::class, $camel])) {
      return static::{$camel}(...$arguments);
    }
    throw new \BadMethodCallException('Metodo statico sconosciuto: ' . $name);
  }
}
