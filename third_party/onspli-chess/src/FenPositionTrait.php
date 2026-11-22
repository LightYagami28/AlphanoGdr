<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait FenPositionTrait
{

    public function __clone()
    {
      $this->board = clone $this->board;
      $this->enPassant = clone $this->enPassant;
    }


    /**
    * Export whole FEN string.
    * @return string FEN string
    */
    public function export() : string
    {
      return implode(' ', [$this->getBoard(), $this->getActiveColor(), $this->getCastling(), $this->getEnPassant(), $this->getHalfmove(), $this->getFullmove()]);
    }


    /**
    * Export FEN string without halfmoves count and fullmove number.
    *
    * They really are not so important to describe chess position.
    */
    public function exportShort() : string
    {
      return implode(' ', [$this->getBoard(), $this->getActiveColor(), $this->getCastling(), $this->getEnPassant()]);
    }


    public function __toString() : string
    {
      return $this->export();
    }


    /**
    * Preview of the board in ASCII graphics.
    * @return string ascii preview of the board
    */
    public function preview() : string
    {
      return $this->board->preview();
    }


    /**
    * Get piece placement.
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
    *
    * @param bool $as_object return object instead of string
    * @return string|Board
    */
    public function getBoard(bool $as_object = false)
    {
      if ($as_object) {
        return $this->board;
      }
      return $this->board->export();
    }


    /**
    * Setup piece placement.
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
    *
    * @param string|Board $board piece placement
    */
    public function setBoard($board) : void
    {
      if (is_string($board)) {
        $board = new Board($board);
      }
      $this->board = $board;
    }


    /**
    * Get piece on a particular square.
    *
    * @param string|Square square on the board.
    * @return string piece - one of PNBRQKpnbrqk or empty string for empty square
    */
    public function getSquare($square) : string
    {
      return $this->board->getSquare($square);
    }


    /**
    * Set piece on a particular square.
    *
    * @param string|Square square on the board.
    * @param string piece - one of PNBRQKpnbrqk or empty string for empty square
    */
    public function setSquare($square, string $piece) : void
    {
      $this->board->setSquare($square, $piece);
    }


    /**
    * Active color.
    *
    * "w" means White moves next, "b" means Black moves next.
    *
    * @return string w|b
    */
    public function getActiveColor() : string
    {
      return $this->active;
    }


    /**
    * Set active color.
    *
    * "w" means White moves next, "b" means Black moves next.
    *
    * @param string $color w|b
    */
    public function setActiveColor(string $color) : void
    {
      if ($color != 'w' && $color != 'b') {
        throw new ParseException("Active color must be either 'w' or 'b', it is '$color'.");
      }
      $this->active = $color;
    }


    /**
    * Get En Passant target square.
    *
    * En passant target square in algebraic notation. If there's no en passant
    * target square, this is "-". If a pawn has just made a two-square move,
    * this is the position "behind" the pawn. This is recorded regardless of
    * whether there is a pawn in position to make an en passant capture.
    *
    * @param bool $as_object return object instead of string
    * @return string|Square
    */
    public function getEnPassant(bool $as_object = false)
    {
      if ($as_object) {
        return $this->enPassant;
      }
      return $this->enPassant->export();
    }


    /**
    * Set En Passant target square.
    *
    * En passant target square in algebraic notation. If there's no en passant
    * target square, this is "-". If a pawn has just made a two-square move,
    * this is the position "behind" the pawn. This is recorded regardless of
    * whether there is a pawn in position to make an en passant capture.
    *
    * @param string|Square en passant target square
    */
    public function setEnPassant($square) : void
    {
      if (is_string($square)) {
        $square = new Square($square);
      }
      if (!$square->isNull() && $square->getRankIndex() != 2 && $square->getRankIndex() != 5) {
        throw new ParseException("Invalid En passant square '".$square->export()."'.");
      }
      $this->enPassant = $square;
    }


    /**
    * Get Halfmove clock
    *
    * The number of halfmoves since the last capture or pawn
    * advance, used for the fifty-move rule.
    */
    public function getHalfmove() : int
    {
      return $this->halfmove;
    }


    /**
    * Set Halfmove clock
    *
    * The number of halfmoves since the last capture or pawn
    * advance, used for the fifty-move rule.
    */
    public function setHalfmove($halfmove) : void
    {
      if (intval($halfmove) != $halfmove || $halfmove < 0) {
        throw new ParseException("Halfmove clock '$halfmove' must be non-negative integer.");
      }
      $this->halfmove = $halfmove;
    }


    /**
    * Get Fullmove number
    *
    * The number of the full move. It starts at 1, and is
    * incremented after Black's move.
    */
    public function getFullmove() : int
    {
      return $this->fullmove;
    }


    /**
    * Set Fullmove number
    *
    * The number of the full move. It starts at 1, and is
    * incremented after Black's move.
    */
    public function setFullmove($fullmove) : void
    {
      if (intval($fullmove) != $fullmove || $fullmove <= 0) {
        throw new ParseException("Fullmove number '$fullmove' must be positive integer.");
      }
      $this->fullmove = $fullmove;
    }
}
