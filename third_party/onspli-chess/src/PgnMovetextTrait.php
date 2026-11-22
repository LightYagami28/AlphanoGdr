<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait PgnMovetextTrait
{

  /**
  * Export PGN string.
  */
  public function export() : string
  {
    return $this->exportTags() . $this->exportMovetext();
  }


  /**
  * Export movetext section of pgn.
  */
  public function exportMovetext() : string
  {
    $pgn = '';
    for ($halfmove_number = $this->getInitialHalfmoveNumber(); $halfmove_number <= $this->getLastHalfmoveNumber(); $halfmove_number++) {
      $move_number = ceil($halfmove_number / 2);
      if ($halfmove_number % 2 == 0 && $halfmove_number == $this->getInitialHalfmoveNumber()) {
        $pgn .= $move_number . '... ';
      } elseif ($halfmove_number % 2 == 1) {
        $pgn .= $move_number . '. ';
      }
      $pgn .= $this->getHalfmove($halfmove_number) . ' ';
    }
    return trim($pgn);
  }


  /**
  * Validate all moves make sense according to chess rules.
  */
  public function validateMoves() : void
  {
    $this->getCurrentFen();
  }



  /**
  * Perform move.
  *
  * The method validates syntax of the move, however it doesn't check
  * the move is valid acording to chess rules.
  */
  public function move(string $move) : void
  {
    $this->halfmoves[] = new Move($move);
  }


  /**
  * Removes tag pair section, all comments, move number indicators and game termination marker.
  * What remains is the plain list of moves separated by spaces.
  */
  private function getMovetextSection(string $pgn) : string
  {
    // remove all tags
    $pgn = preg_replace('/\[[^\]]*\]/', ' ', $pgn);

    // remove all comments
    $pgn = preg_replace('/{[^}]*}/', ' ', $pgn);

    // remove all move number indicators
    $pgn = preg_replace('/\d+\.+/', ' ', $pgn);

    // remove excessive whitespaces
    $pgn = preg_replace('/\s+/', ' ', $pgn);

    // remove leading and trailing whitespace
    $pgn = trim($pgn);

    // remove game termination markers
    $pgn = preg_replace('/0-1$/', '', $pgn);
    $pgn = preg_replace('/1-0$/', '', $pgn);
    $pgn = preg_replace('#1/2-1/2$#', '', $pgn);
    $pgn = preg_replace('/\*$/', '', $pgn);

    $pgn = trim($pgn);

    return $pgn;
  }
}
