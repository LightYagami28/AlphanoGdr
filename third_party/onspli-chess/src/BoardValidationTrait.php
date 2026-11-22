<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait BoardValidationTrait
{

  /**
  * Returns the color of the piece.
  * @return string w|b
  */
  public static function getColorOfPiece(string $piece) : string
  {
    self::validateRegularPiece($piece);
    if ($piece == self::getPieceOfColor($piece, 'w')) {
      return 'w';
    } else {
      return 'b';
    }
  }


  /**
  * Converts piece to requested color.
  */
  public static function getPieceOfColor(string $piece, string $color) : string
  {
    self::validateRegularPiece($piece);
    self::validateColor($color);
    if ($color == 'w') {
      return strtoupper($piece);
    } else {
      return strtolower($piece);
    }
  }


  /**
  * Get color opposite to color passed as an argument.
  */
  public static function getOppositeColor(string $color) : string
  {
    self::validateColor($color);
    if ($color == 'b') {
      return 'w';
    } else {
      return 'b';
    }
  }


  /**
  * Tells whether the square is attacked by particular piece
  *
  * The method also distinguishes the color of the piece.
  */
  public function isSquareAttackedByPiece($square, string $piece) : bool
  {
    self::validateRegularSquare($square);
    self::validateRegularPiece($piece);

    $shadow_piece = $piece;
    if ($piece == 'p' || $piece == 'P') {
      $shadow_piece = self::getPieceOfColor($piece, self::getOppositeColor(self::getColorOfPiece($piece)));
    }

    $candidate_squares = $this->getDefendedSquares($square, $shadow_piece);
    $attackers = $this->getPiecesOnSquares($candidate_squares);
    return in_array($piece, $attackers);
  }


  /**
  * Tells whether the square is attacked by the color specified.
  */
  public function isSquareAttacked($square, string $attacking_color) : bool
  {
    self::validateRegularSquare($square);
    self::validateColor($attacking_color);

    foreach (['P', 'N', 'B', 'R', 'Q', 'K'] as $piece) {
      if ($this->isSquareAttackedByPiece($square, self::getPieceOfColor($piece, $attacking_color))) {
        return true;
      }
    }
    return false;
  }



  /**
  * Tells whether the king of color specified is in check.
  */
  public function isCheck(string $color) : bool
  {
    self::validateColor($color);
    $king_square = $this->findSquareWithPiece(self::getPieceOfColor('K', $color), true);
    return $this->isSquareAttacked($king_square, self::getOppositeColor($color));
  }


  private static function validateColor(string $color) : void
  {
    if (!in_array($color, ['w', 'b'])) {
      throw new ParseException;
    }
  }


  private static function validatePiece(string $piece) : void
  {
    if (!in_array($piece, ['', 'P', 'N', 'B', 'R', 'Q', 'K', 'p', 'n', 'b', 'r', 'q', 'k'])) {
      throw new ParseException("Invalid piece '$piece'.");
    }
  }


  private static function validateRegularPiece(string $piece) : void
  {
    if (!in_array($piece, ['P', 'N', 'B', 'R', 'Q', 'K', 'p', 'n', 'b', 'r', 'q', 'k'])) {
      throw new ParseException("Invalid piece '$piece'.");
    }
  }


  private static function validateSquare(&$square) : void
  {
    if (is_string($square)) {
      $square = new Square($square);
    }
  }


  private static function validateRegularSquare(&$square) : void
  {
    self::validateSquare($square);
    if (!$square->isRegular()) {
      throw new \OutOfBoundsException;
    }
  }
}
