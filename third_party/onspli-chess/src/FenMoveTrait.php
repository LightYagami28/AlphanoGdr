<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait FenMoveTrait
{

    /**
    * Get array of origin squares candidates.
    * Square is origin square candidate if
    *  - it contains $piece
    *  - there is a pseudolegal move (ignoring king in check) of the $piece to $target_square
    * Returns array of Square objects.
    */
    private function getMoveOriginCandidates(string $piece, Square $target_square, bool $isCapture) : array
    {
      if ($piece == 'P' && !$isCapture) {
        $origin_candidates = [$target_square->getRelativeSquare(0, -1)];
        if ($target_square->getRankIndex() == 3 && $this->board->isSquareVacant($target_square->getRelativeSquare(0, -1))) {
          $origin_candidates[] = $target_square->getRelativeSquare(0, -2);
        }
      } else if($piece == 'p' && !$isCapture) {
        $origin_candidates = [$target_square->getRelativeSquare(0, 1)];
        if ($target_square->getRankIndex() == 4 && $this->board->isSquareVacant($target_square->getRelativeSquare(0, 1))) {
          $origin_candidates[] = $target_square->getRelativeSquare(0, 2);
        }
      } else {
        $origin_candidates = $this->board->getDefendedSquares($target_square, $this->getOpponentsPiece($piece), true);
      }

      $is_piece_on_square = function($square) use ($piece) { return $this->getSquare($square) == $piece; };
      return array_filter($origin_candidates, $is_piece_on_square);
    }


    /**
    * Get origin square of chess move.
    * Standard chess moves notation specifies only piece and target square and ommits origin square
    * when possible. We need to do some work to get the origin.
    */
    private function getMoveOrigin(Move $move) : Square
    {
      $piece = $this->getActivePiece($move->getPieceType());
      $move_origin = $move->getOrigin(true);

      $origin_candidates = $this->getMoveOriginCandidates($piece, $move->getTarget(true), $move->getCapture());

      $does_origin_candidate_comply_with_move_origin = function ($origin_candidate) use ($move_origin) {
        if ($move_origin->hasFile() && $origin_candidate->getFile() != $move_origin->getFile()) {
          return false;
        }
        if ($move_origin->hasRank() && $origin_candidate->getRank() != $move_origin->getRank()) {
          return false;
        }
        return true;
      };

      $origin_candidates = array_filter($origin_candidates, $does_origin_candidate_comply_with_move_origin);

      $origin_candidates_legal = [];
      $active_color = $this->getActiveColor();
      foreach ($origin_candidates as $origin)
      {
          $new_board = $this->getNewBoard($move, $origin);
          if (!$new_board->isCheck($active_color)) {
            $origin_candidates_legal[] = $origin;
          }
      }

      if (empty($origin_candidates_legal)) {
        throw new RulesException("Invalid move.");
      }

      if (sizeof($origin_candidates_legal) > 1) {
        throw new RulesException("Ambiguous move.");
      }

      return reset($origin_candidates_legal);
    }


    private function validateMoveTargetSquare(Move $move) : void
    {
      $target = $move->getTarget(true);
      $target_piece = $this->getSquare($target);

      if ($move->getCapture() && $target_piece == '' && !($target->export() == $this->getEnPassant() && $move->getPieceType() == 'P')) {
        throw new RulesException("Cannot capture on empty square.");
      }

      if (!$move->getCapture() && $target_piece) {
        throw new RulesException("Target square is occupied.");
      }

      if ($target_piece && Board::getColorOfPiece($target_piece) == $this->getActiveColor()) {
        throw new RulesException("Cannot capture player's own piece.");
      }
    }


    /**
    * Perform non-castling move.
    */
    private function standardMove(Move &$move) : void
    {
      $this->validateMoveTargetSquare($move);
      $origin = $this->getMoveOrigin($move);
      $new_board = $this->getNewBoard($move, $origin);
      $this->setBoard($new_board);
      $move->setOrigin($origin);
    }


    private function getNewBoard(Move $move, Square $origin) : Board
    {
      $new_board = clone $this->board;
      $piece = $this->getActivePiece($move->getPieceType());
      $target = $move->getTarget(true);

      $new_board->setSquare($origin, '');
      if ($move->getPromotion()) {
        $new_board->setSquare($target, Board::getPieceOfColor($move->getPromotion(), $this->getActiveColor()));
      } else {
        $new_board->setSquare($target, $piece);
        if ($move->getPieceType() == 'P' && $target->export() == $this->getEnPassant()) {
          if ($this->getActiveColor() == 'w') {
            $new_board->setSquare($target->getRelativeSquare(0, -1), '');
          } else {
            $new_board->setSquare($target->getRelativeSquare(0, 1), '');
          }
        }
      }
      return $new_board;
    }


    /**
    * Perform a move.
    */
    public function move(string $move) : void
    {
      // assert
      $this->validateCastling($this->castlingRights, $this->kingsFile, $this->kingsRookFile, $this->queensRookFile);

      $move = new Move($move);

      switch ($move->getCastling()) {
        case 'O-O':
          $this->castleKingside($move);
          break;
        case 'O-O-O':
          $this->castleQueenside($move);
          break;
        default:
          $this->standardMove($move);
      }

      $this->afterMoveUpdateHalfmove($move);
      $this->afterMoveSetEnPassant($move);
      $this->afterMoveUpdateCastlingAvailability($move->getPieceType(), $move->getOrigin(true), $move->getTarget(true));
      $this->afterMoveChangeActiveColor();
    }


    private function afterMoveUpdateCastlingAvailability(string $piece_type, Square $origin_square, Square $target_square) : void
    {
      $active_color = $this->getActiveColor();
      if ($active_color == 'w') {
        $active_first_rank = '1';
        $opponents_first_rank = '8';
      } else {
        $active_first_rank = '8';
        $opponents_first_rank = '1';
      }

      // moving king or rooks prevents castling
      $origin_square_str = $origin_square->export();
      if ($piece_type == 'R' && $origin_square_str == $this->queensRookFile.$active_first_rank) {
        $this->setCastlingAvailability($this->getActivePiece('Q'), false);
      } elseif ($piece_type == 'R' && $origin_square_str == $this->kingsRookFile.$active_first_rank) {
        $this->setCastlingAvailability($this->getActivePiece('K'), false);
      } elseif ($piece_type == 'K' && $origin_square_str == $this->kingsFile.$active_first_rank) {
        $this->setCastlingAvailability($this->getActivePiece('Q'), false);
        $this->setCastlingAvailability($this->getActivePiece('K'), false);
      }

      // capturing opponents rooks prevents castling
      $target_square_str = $target_square->export();
      if ($target_square_str == $this->queensRookFile.$opponents_first_rank) {
        $this->setCastlingAvailability($this->getOpponentsPiece('Q'), false);
      } elseif ($target_square_str == $this->kingsRookFile.$opponents_first_rank) {
        $this->setCastlingAvailability($this->getOpponentsPiece('K'), false);
      }

      // assert
      $this->validateCastling($this->castlingRights, $this->kingsFile, $this->kingsRookFile, $this->queensRookFile);
    }


    private function afterMoveChangeActiveColor() : void
    {
      if ($this->getActiveColor() == 'b') {
        $this->setFullmove($this->getFullmove() + 1);
        $this->setActiveColor('w');
      } else {
        $this->setActiveColor('b');
      }
    }


    private function afterMoveSetEnPassant(Move $move) : void
    {
      $move_piece = $this->getActivePiece($move->getPieceType());
      $target = $move->getTarget(true);
      $origin = $move->getOrigin(true);

      if ($move_piece == 'P' && $origin->getRankIndex() == 1 && $target->getRankIndex() == 3) {
        $this->setEnPassant($target->getRelativeSquare(0, -1));
      } elseif ($move_piece == 'p' && $origin->getRankIndex() == 6 && $target->getRankIndex() == 4) {
        $this->setEnPassant($target->getRelativeSquare(0, 1));
      } else {
        $this->setEnPassant('-');
      }
    }


    private function afterMoveUpdateHalfmove(Move $move) : void
    {
      if ($move->getPieceType() == 'P' || $move->getCapture()) {
        $this->setHalfmove(0);
      } else {
        $this->setHalfmove($this->getHalfmove() + 1);
      }
    }
}
