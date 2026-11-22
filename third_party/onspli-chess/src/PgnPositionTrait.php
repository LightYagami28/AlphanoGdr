<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait PgnPositionTrait
{

  /**
  * Set custom initial position.
  */
  public function setInitialFen(string $fen) : void
  {
    $this->initialFen = new FEN($fen);
    $this->tags['FEN'] = $this->initialFen->export();
    $this->initialHalfmoveNumber = self::getHalfmoveNumber($this->initialFen->getFullmove(), $this->initialFen->getActiveColor());
    // chached fens depend on initial position, clear cache
    $this->fens = [];
  }


  /**
  * Unset custom initial position - use the standard initial position.
  */
  public function unsetInitialFen() : void
  {
    $this->initialFen = new FEN;
    if (isset($this->tags['FEN'])) {
      unset($this->tags['FEN']);
    }
    $this->initialHalfmoveNumber = 1;
    // chached fens depend on initial position, clear cache
    $this->fens = [];
  }


  /**
  * Get initial position.
  */
  public function getInitialFen(bool $as_object = false)
  {
    if ($as_object) {
      return clone $this->initialFen;
    }
    return $this->initialFen->export();
  }


  private function getHalfmoveIndex(int $halfmove_number) : int
  {
    return $halfmove_number - $this->getInitialHalfmoveNumber();
  }


  /**
  * Get FEN of current position.
  *
  * Return position after last move.
  */
  public function getCurrentFen(bool $as_object = false)
  {
    return $this->getFenAfterHalfmove($this->getLastHalfmoveNumber(), $as_object);
  }


  /**
  * Get position after given halfmove.
  *
  * Some edge cases:
  * ```php
  * $pgn->getInitialFen() = $pgn->getFenAfterHalfmove($pgn->getInitialHalfmoveNumber() - 1);
  * $pgn->getCurrentFen() = $pgn->getFenAfterHalfmove($pgn->getLastHalfmoveNumber());
  * ```
  */
  public function getFenAfterHalfmove(int $halfmove_number, bool $as_object = false)
  {
    if ($halfmove_number == 0) {
      return $this->getInitialFen($as_object);
    }
    $halfmove_index = $this->getHalfmoveIndex($halfmove_number);
    $this->computeFens($halfmove_number);
    if (!isset($this->fens[$halfmove_index])) {
      throw new \OutOfBoundsException;
    }
    $fen = $this->fens[$halfmove_index];
    if ($as_object) {
      return clone $fen;
    }
    return $fen->export();
  }


  /**
  * Get halfmove number of the last recorded move.
  */
  public function getLastHalfmoveNumber() : int
  {
    return sizeof($this->halfmoves) + $this->getInitialHalfmoveNumber() - 1;
  }


  /**
  * Get halfmove number of the first recorder move.
  */
  public function getInitialHalfmoveNumber() : int
  {
    return $this->initialHalfmoveNumber;
  }


  private function getLastComputedFen() : FEN
  {
    $computed = sizeof($this->fens);
    if ($computed == 0) {
      return $this->getInitialFen(true);
    }
    return clone $this->fens[$computed - 1];
  }


  private function computeFens(int $max_halfmove_to_compute) : void
  {
    if ($max_halfmove_to_compute > $this->getLastHalfmoveNumber()) {
      throw new \OutOfBoundsException;
    }
    $fen = $this->getLastComputedFen();
    for ($halfmove_number = sizeof($this->fens) + $this->getInitialHalfmoveNumber(); $halfmove_number <= $max_halfmove_to_compute; $halfmove_number ++) {
      $halfmove = $this->getHalfmove($halfmove_number);
      try {
        $fen->move($halfmove);
      } catch (\Exception $e) {
        throw new RulesException('Move ' . ceil($halfmove_number / 2) . ($halfmove_number % 2 ? '. ' : '... ') . $halfmove . ' is invalid. FEN ' . $this->getFenAfterHalfmove($halfmove_number - 1), 0, $e);
      }
      $this->fens[] = clone $fen;
    }
  }


  /**
  * Get move in standard algebraic notation.
  *
  * Halfmove number starts with 1 (white's first move). One move has
  * two halfmoves (for white and black player).
  */
  public function getHalfmove(int $halfmove_number, bool $as_object = false)
  {
    $halfmove_index = $this->getHalfmoveIndex($halfmove_number);
    if (!isset($this->halfmoves[$halfmove_index])) {
      throw new \OutOfBoundsException;
    }
    $halfmove = $this->halfmoves[$halfmove_index];
    if ($as_object) {
      return $halfmove;
    }
    return $halfmove->export();
  }



  /**
  * Converts (move number, color) to halfmove number.
  */
  public static function getHalfmoveNumber(int $move_number, string $color) : int
  {
    if ($color == 'w') {
      $halfmove_number = $move_number * 2 - 1;
    } else {
      $halfmove_number = $move_number * 2;
    }
    return $halfmove_number;
  }
}
