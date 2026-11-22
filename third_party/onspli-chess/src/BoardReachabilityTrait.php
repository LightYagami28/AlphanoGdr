<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait BoardReachabilityTrait
{

  private function pushSquareWithPawnCaptureToArray(array &$arr, Square $target_square, Square $en_passant_square, bool $as_object, string $excluded_color) : void
  {
    if (!$target_square->isRegular()) {
      return;
    }

    if ($target_square->export() == $en_passant_square->export()) {
      $target_square->pushToArray($arr, $as_object);
      return;
    }

    $target_piece = $this->getSquare($target_square);
    if ($target_piece && (!$excluded_color || self::getColorOfPiece($target_piece) != $excluded_color)) {
      $target_square->pushToArray($arr, $as_object);
    }
  }


  private function pushSquaresReachableByWhitePawnToArray(array &$arr, Square $pawn_square, Square $en_passant_square, bool $as_object) : void
  {
    if ($pawn_square->getRankIndex() == 1 && $this->getSquare($pawn_square->getRelativeSquare(0,1)) == '') {
      $this->pushSquareToArray($arr, $pawn_square->getRelativeSquare(0, 2), $as_object, 'w');
    }
    $this->pushSquareToArray($arr, $pawn_square->getRelativeSquare(0, 1), $as_object, 'w');
    $this->pushSquareWithPawnCaptureToArray($arr, $pawn_square->getRelativeSquare(-1, 1), $en_passant_square, $as_object, 'w');
    $this->pushSquareWithPawnCaptureToArray($arr, $pawn_square->getRelativeSquare(1, 1), $en_passant_square, $as_object, 'w');
  }


  private function pushSquaresReachableByBlackPawnToArray(array &$arr, Square $pawn_square, Square $en_passant_square, bool $as_object) : void
  {
    if ($pawn_square->getRankIndex() == 6 && $this->getSquare($pawn_square->getRelativeSquare(0,-1)) == '') {
      $this->pushSquareToArray($arr, $pawn_square->getRelativeSquare(0, -2), $as_object, 'b');
    }
    $this->pushSquareToArray($arr, $pawn_square->getRelativeSquare(0, -1), $as_object, 'b');
    $this->pushSquareWithPawnCaptureToArray($arr, $pawn_square->getRelativeSquare(-1, -1), $en_passant_square, $as_object, 'b');
    $this->pushSquareWithPawnCaptureToArray($arr, $pawn_square->getRelativeSquare(1, -1), $en_passant_square, $as_object, 'b');
  }


  /**
  * Get array of all squares reachable from $origin_square by $moving_piece.
  */
  public function getReachableSquares($origin_square, $moving_piece, $en_passant_square = '-', bool $as_object = false) : array
  {
    self::validateRegularSquare($origin_square);
    self::validateRegularPiece($moving_piece);
    self::validateSquare($en_passant_square);
    $arr = [];

    switch ($moving_piece) {
      case 'P':
        $this->pushSquaresReachableByWhitePawnToArray($arr, $origin_square, $en_passant_square, $as_object);
        break;
      case 'p':
        $this->pushSquaresReachableByBlackPawnToArray($arr, $origin_square, $en_passant_square, $as_object);
        break;
      default:
        $this->pushSquaresDefendedByNotAPawnToArray($arr, $moving_piece, $origin_square, $as_object, self::getColorOfPiece($moving_piece));
    }

    return $arr;
  }
}
