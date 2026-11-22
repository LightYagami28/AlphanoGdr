<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait FenCastlingMovesTrait
{

    private function castleKingside(Move &$move) : void
    {
      if (!$this->getCastlingAvailability($this->getActivePiece('K'))) {
        throw new RulesException("Castling not available.");
      }

      if ($this->getActiveColor() == 'w') {
        $rank = 1;
      } else {
        $rank = 8;
      }

      $origin_king = new Square($this->kingsFile.$rank);
      $target_king = new Square('g'.$rank);
      $origin_rook = new Square($this->kingsRookFile.$rank);
      $target_rook = new Square('f'.$rank);

      $this->castleGeneric($move, $origin_king, $target_king, $origin_rook, $target_rook);
    }


    private function castleQueenside(Move &$move) : void
    {
      if (!$this->getCastlingAvailability($this->getActivePiece('Q'))) {
        throw new RulesException("Castling not available.");
      }

      if ($this->getActiveColor() == 'w') {
        $rank = 1;
      } else {
        $rank = 8;
      }

      $origin_king = new Square($this->kingsFile.$rank);
      $target_king = new Square('c'.$rank);
      $origin_rook = new Square($this->queensRookFile.$rank);
      $target_rook = new Square('d'.$rank);

      $this->castleGeneric($move, $origin_king, $target_king, $origin_rook, $target_rook);
    }


    private function castleGeneric(Move &$move, Square $origin_king, Square $target_king, Square $origin_rook, Square $target_rook)
    {
      if (!$this->areCastlingSquaresVacant($origin_king, $target_king, $origin_rook->getFile())) {
        throw new RulesException("Castling not available. There are pieces in the way of King.");
      }
      if (!$this->areCastlingSquaresVacant($origin_rook, $target_rook, $origin_rook->getFile())) {
        throw new RulesException("Castling not available. There are pieces in the way of Rook.");
      }
      if (!$this->areCastlingSquaresSafe($origin_king, $target_king)) {
        throw new RulesException("Castling not available. King in check.");
      }

      $this->board->setSquare($origin_king, '');
      $this->board->setSquare($origin_rook, '');
      $this->board->setSquare($target_rook, $this->getActivePiece('R'));
      $this->board->setSquare($target_king, $this->getActivePiece('K'));

      $move->setOrigin($origin_king);
    }


    private function areCastlingSquaresVacant(Square $origin, Square $target, $allowed_rook_file)
    {
      $min_file = min($origin->getFile(), $target->getFile());
      $max_file = max($origin->getFile(), $target->getFile());

      $rank = $origin->getRank();
      for ($file = $min_file; $file <= $max_file; $file++)
      {
        if ($file == $allowed_rook_file || $file == $this->kingsFile) {
          continue;
        }
        $piece = $this->board->getSquare($file.$rank);
        if ($piece) {
          return false;
        }
      }
      return true;
    }


    private function areCastlingSquaresSafe(Square $origin, Square $target)
    {
      $min_file = min($origin->getFile(), $target->getFile());
      $max_file = max($origin->getFile(), $target->getFile());

      $rank = $origin->getRank();
      for ($file = $min_file; $file <= $max_file; $file++)
      {
        if ($this->board->isSquareAttacked($file.$rank, Board::getOppositeColor($this->getActiveColor()))) {
          return false;
        }
      }
      return true;
    }
}
