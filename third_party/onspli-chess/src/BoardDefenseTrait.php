<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait BoardDefenseTrait
{

  private function pushSquaresInDirectionToArray(array &$arr, Square $origin_square, int $north, int $east, bool $as_object, string $excluded_color = '') : void
  {
      $square = $origin_square;
      while ($square = $square->getRelativeSquare($north, $east)) {
        if (!$this->pushSquareToArray($arr, $square, $as_object, $excluded_color)) {
          break;
        }
      }
  }


  /**
  * Push square to the end of the array.
  *
  * Only pushes regular squares, non-regular squares are ignored.
  * @param string $excluded_color If there is a piece of $excluded_color on the square, the square is NOT pushed to the array.
  * @return bool Returns false if the square is not regular or if it contains a piece.
  */
  private function pushSquareToArray(array &$arr, Square $square, bool $as_object, string $excluded_color = '') : bool
  {
    if (!$square->isRegular()) {
      return false;
    }
    $target_piece = $this->getSquare($square);
    if ($target_piece && $excluded_color && self::getColorOfPiece($target_piece) == $excluded_color) {
      return false;
    }
    $square->pushToArray($arr, $as_object);
    return $target_piece === '';
  }


  private function pushSquaresDefendedByKingToArray(array &$arr, Square $king_square, bool $as_object, string $excluded_color = '') : void
  {
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(0,1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(-1,1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(-1,0), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(-1,-1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(0,-1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(1,-1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(1,0), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $king_square->getRelativeSquare(1,1), $as_object, $excluded_color);
  }


  private function pushSquaresDefendedByKnightToArray(array &$arr, Square $knight_square, bool $as_object, string $excluded_color = '') : void
  {
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(2, 1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(2, -1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(-2, 1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(-2, -1), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(1, 2), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(1, -2), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(-1, 2), $as_object, $excluded_color);
    $this->pushSquareToArray($arr, $knight_square->getRelativeSquare(-1, -2), $as_object, $excluded_color);
  }


  private function pushSquaresDefendedByBishopToArray(array &$arr, Square $bishop_square, bool $as_object, string $excluded_color = '') : void
  {
    $this->pushSquaresInDirectionToArray($arr, $bishop_square, 1, 1, $as_object, $excluded_color);
    $this->pushSquaresInDirectionToArray($arr, $bishop_square, -1, 1, $as_object, $excluded_color);
    $this->pushSquaresInDirectionToArray($arr, $bishop_square, 1, -1, $as_object, $excluded_color);
    $this->pushSquaresInDirectionToArray($arr, $bishop_square, -1, -1, $as_object, $excluded_color);
  }


  private function pushSquaresDefendedByRookToArray(array &$arr, Square $rook_square, bool $as_object, string $excluded_color = '') : void
  {
    $this->pushSquaresInDirectionToArray($arr, $rook_square, 1, 0, $as_object, $excluded_color);
    $this->pushSquaresInDirectionToArray($arr, $rook_square, -1, 0, $as_object, $excluded_color);
    $this->pushSquaresInDirectionToArray($arr, $rook_square, 0, 1, $as_object, $excluded_color);
    $this->pushSquaresInDirectionToArray($arr, $rook_square, 0, -1, $as_object, $excluded_color);
  }


  private function pushSquaresDefendedByQueenToArray(array &$arr, Square $queen_square, bool $as_object, string $excluded_color = '') : void
  {
    $this->pushSquaresDefendedByRookToArray($arr, $queen_square, $as_object, $excluded_color);
    $this->pushSquaresDefendedByBishopToArray($arr, $queen_square, $as_object, $excluded_color);
  }


  private function pushSquaresDefendedByNotAPawnToArray(array &$arr, string $piece, Square $piece_square, bool $as_object, string $excluded_color = '') : void
  {
    switch ($piece) {
      case 'K':
      case 'k':
        $this->pushSquaresDefendedByKingToArray($arr, $piece_square, $as_object, $excluded_color);
        break;
      case 'N':
      case 'n':
        $this->pushSquaresDefendedByKnightToArray($arr, $piece_square, $as_object, $excluded_color);
        break;
      case 'B':
      case 'b':
        $this->pushSquaresDefendedByBishopToArray($arr, $piece_square, $as_object, $excluded_color);
        break;
      case 'R':
      case 'r':
        $this->pushSquaresDefendedByRookToArray($arr, $piece_square, $as_object, $excluded_color);
        break;
      case 'Q':
      case 'q':
        $this->pushSquaresDefendedByQueenToArray($arr, $piece_square, $as_object, $excluded_color);
        break;
      default:
        break;
    }
  }


  /**
  * Get array of all squares defended (or attacked) by $defender being on $defender_square.
  */
  public function getDefendedSquares($defender_square, string $defender, bool $as_object = false) : array
  {
    self::validateRegularSquare($defender_square);
    self::validateRegularPiece($defender);
    $arr = [];

    switch ($defender) {
      case 'P':
        $this->pushSquareToArray($arr, $defender_square->getRelativeSquare(-1, 1), $as_object);
        $this->pushSquareToArray($arr, $defender_square->getRelativeSquare(1, 1), $as_object);
        break;
      case 'p':
        $this->pushSquareToArray($arr, $defender_square->getRelativeSquare(-1, -1), $as_object);
        $this->pushSquareToArray($arr, $defender_square->getRelativeSquare(1, -1), $as_object);
        break;
      default:
        $this->pushSquaresDefendedByNotAPawnToArray($arr, $defender, $defender_square, $as_object);
    }
    return $arr;
  }


  /**
  * Get list of pieces on squares (including multiplicities, excluding blank squares).
  */
  private function getPiecesOnSquares(array $squares) : array
  {
    $arr = [];
    foreach ($squares as $square) {
      $piece = $this->getSquare($square);
      if ($piece) {
        $arr[] = $piece;
      }
    }
    return $arr;
  }
}
