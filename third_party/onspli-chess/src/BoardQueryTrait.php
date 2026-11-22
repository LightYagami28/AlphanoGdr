<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait BoardQueryTrait
{

  /**
  * Export piece placement string.
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
  public function export() : string
  {
    $rank_pieces_array = [];
    for ($rankIndex = 7; $rankIndex >= 0; $rankIndex --) {
      $rank_pieces_array[] = $this->exportRank($rankIndex);
    }
    return implode('/', $rank_pieces_array);
  }


  private function exportRank(int $rankIndex) : string
  {
    $rank_pieces = '';
    $space_count = 0;
    for ($fileIndex = 0; $fileIndex < 8; $fileIndex ++) {
      $piece = $this->getSquare(new Square($fileIndex, $rankIndex));
      if (!$piece)
      {
        $space_count++;
      }
      if ($piece || $fileIndex == 7)
      {
        $rank_pieces .= ($space_count > 0 ? (string)$space_count : '') . $piece;
        $space_count = 0;
      }
    }
    return $rank_pieces;
  }


  /**
  * Preview of the board in ASCII graphics.
  */
  public function preview() : string
  {
    $ranks = [];
    for ($rankIndex = 7; $rankIndex >= 0; $rankIndex --) {
      $ranks[] = $this->previewRank($rankIndex);
    }
    return implode(PHP_EOL, $ranks);
  }


  private function previewRank(int $rankIndex) : string
  {
    $previewRank = '';
    for ($fileIndex = 0; $fileIndex <= 7; $fileIndex ++) {
      $piece = $this->getSquare(new Square($fileIndex, $rankIndex));
      if (!$piece) {
        $piece = '.';
      }
      $previewRank .= $piece;
    }
    return $previewRank;
  }


  /**
  * Get piece on a particular square.
  *
  * @param string|Square square on the board.
  * @return string piece - one of PNBRQKpnbrqk or empty string for empty square
  */
  public function getSquare($square) : string
  {
    self::validateRegularSquare($square);
    return $this->board[$square->getRankIndex() * 8 + $square->getFileIndex()];
  }


  /**
  * Set piece on a particular square.
  *
  * @param string|Square square on the board.
  * @param string piece - one of PNBRQKpnbrqk or empty string for empty square
  */
  public function setSquare($square, string $piece) : void
  {
    self::validateRegularSquare($square);
    self::validatePiece($piece);
    $this->board[$square->getRankIndex() * 8 + $square->getFileIndex()] = $piece;
  }


  public function isSquareVacant($square) : bool
  {
    return $this->getSquare($square) == '';
  }


  /**
  * Returns array of squares containing piece.
  */
  public function findSquaresWithPiece(string $piece, bool $as_object = false) : array
  {
    self::validatePiece($piece);
    $arr = [];
    for ($rankIndex = 0; $rankIndex < 8; $rankIndex ++) {
      for ($fileIndex = 0; $fileIndex < 8; $fileIndex ++) {
        $square = new Square($fileIndex, $rankIndex);
        $p = $this->getSquare($square);
        if ($p == $piece) {
          $square->pushToArray($arr, $as_object);
        }
      }
    }
    return $arr;
  }


  /**
  * Returns square containing piece. If there are more pieces, throws.
  */
  public function findSquareWithPiece(string $piece, bool $as_object = false)
  {
    $squares = $this->findSquaresWithPiece($piece, $as_object);
    if (sizeof($squares) != 1) {
      throw new RulesException("There are " . sizeof($squares) . " pieces '" . $piece . "' on the board.");
    }
    return $squares[0];
  }
}
