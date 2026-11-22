<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait FenPseudoMovesTrait
{

    private function getReachableSquaresOrigins(string $piece) : array
    {
      $reachable_squares_origins = [];
      $origin_candidates = $this->board->findSquaresWithPiece($piece, true);
      foreach ($origin_candidates as $origin_square) {
        $reachable_squares = $this->board->getReachableSquares($origin_square, $piece);
        foreach ($reachable_squares as $reachable_square) {
          if (!array_key_exists($reachable_square, $reachable_squares_origins)) {
            $reachable_squares_origins[$reachable_square] = [];
          }
          $reachable_squares_origins[$reachable_square][] = $origin_square;
        }
      }
      return $reachable_squares_origins;
    }


    private function pushPiecesPseudolegalMovesToArray(array &$arr, string $piece) : void
    {
      $reachable_squares_origins = $this->getReachableSquaresOrigins($piece);

      foreach ($reachable_squares_origins as $target_square_str => $origin_squares) {
        $this->pushPiecePseudolegalMovesToSpecificTargetSquareToArray($arr, $piece, $origin_squares, new Square($target_square_str));
      }
    }


    private function pushPiecePseudolegalMovesToSpecificTargetSquareToArray(array &$arr, string $piece, array $origin_squares, Square $target_square) : void
    {

      $capture = $this->isCapture($target_square) ? 'x' : '';
      $piece_type = Board::getPieceOfColor($piece, 'w');

      if (sizeof($origin_squares) == 1) {
        $arr[] = $piece_type . $capture . $target_square;
      } elseif (sizeof($origin_squares) == 2) {
        if ($origin_squares[0]->getFileIndex() != $origin_squares[1]->getFileIndex()) {
          $arr[] = $piece_type . $origin_squares[0]->getFile() . $capture . $target_square;
          $arr[] = $piece_type . $origin_squares[1]->getFile() . $capture . $target_square;
        } else {
          $arr[] = $piece_type . $origin_squares[0]->getRank() . $capture . $target_square;
          $arr[] = $piece_type . $origin_squares[1]->getRank() . $capture . $target_square;
        }
      } else {
        foreach ($origin_squares as $origin_square) {
          $arr[] = $piece_type . $origin_square . $capture . $target_square;
        }
      }
    }


    /**
    * Add possible moves for all pawns of specified color.
    */
    private function pushPawnsPseudolegalMovesToArray(array &$arr, string $color) : void
    {
      $piece = Board::getPieceOfColor('P', $color);
      $origin_squares = $this->board->findSquaresWithPiece($piece, true);
      foreach ($origin_squares as $origin_square) {
        $target_squares = $this->board->getReachableSquares($origin_square, $piece, $this->getEnPassant(), true);
        foreach ($target_squares as $target_square) {
          $this->pushPawnPseudolegalMoveToArray($arr, $origin_square, $target_square);
        }
      }
    }


    /**
    * Add pawn's moves from specified origin to specified target including all possible promotions.
    */
    private function pushPawnPseudolegalMoveToArray(array &$arr, Square $origin_square, Square $target_square) : void
    {
      $promotions = $this->getPromotionStrings($target_square);
      foreach ($promotions as $promotion) {
        // Engine patch: en passant lands on an empty target square, but is a capture.
        if ($this->isCapture($target_square) || $target_square->export() === $this->getEnPassant()) {
          $arr[] = $origin_square->getFile() . 'x' . $target_square . $promotion;
        } else {
          $arr[] = $target_square . $promotion;
        }
      }
    }


    private function getPromotionStrings(Square $target_square) : array
    {
      if (self::isPromotingSquare($target_square, $this->getActiveColor())) {
        return ['=N', '=B', '=R', '=Q'];
      } else {
        return [''];
      }
    }


    private function isCapture(Square $target_square) : bool
    {
      return $this->getSquare($target_square) != '';
    }


    private static function isPromotingSquare(Square $target_square) : bool
    {
      $rankIndex = $target_square->getRankIndex();
      return $rankIndex == 7 || $rankIndex == 0;
    }


    /**
    * Array of all possible moves in current position.
    */
    public function getLegalMoves() : array
    {
      // assert
      $this->validateCastling($this->castlingRights, $this->kingsFile, $this->kingsRookFile, $this->queensRookFile);

      $pseudolegal_moves = [];

      $this->pushPawnsPseudolegalMovesToArray($pseudolegal_moves, $this->getActiveColor());
      $this->pushPiecesPseudolegalMovesToArray($pseudolegal_moves, $this->getActivePiece('N'));
      $this->pushPiecesPseudolegalMovesToArray($pseudolegal_moves, $this->getActivePiece('B'));
      $this->pushPiecesPseudolegalMovesToArray($pseudolegal_moves, $this->getActivePiece('R'));
      $this->pushPiecesPseudolegalMovesToArray($pseudolegal_moves, $this->getActivePiece('Q'));
      $this->pushPiecesPseudolegalMovesToArray($pseudolegal_moves, $this->getActivePiece('K'));

      if ($this->getCastlingAvailability($this->getActivePiece('Q'))) {
        $pseudolegal_moves[] = 'O-O-O';
      }
      if ($this->getCastlingAvailability($this->getActivePiece('K'))) {
        $pseudolegal_moves[] = 'O-O';
      }

      return array_values(array_filter($pseudolegal_moves, [$this, 'isLegalMove']));
    }


    /**
    * Tests if move is legal.
    */
    public function isLegalMove(string $move) : bool
    {
      $fen = clone $this;
      try {
        $fen->move($move);
      } catch (\Exception $e) {
        return false;
      }
      return true;
    }
}
