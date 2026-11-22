<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait FenStatusTrait
{

    /**
    * Castling availability.
    *
    * If neither side can castle, this is "-".
    * Otherwise, this has one or more letters: "K" (White can castle kingside),
    * "Q" (White can castle queenside), "k" (Black can castle kingside), and/or
    * "q" (Black can castle queenside). A move that temporarily prevents castling
    * does not negate this notation.
    * Shredder-FEN is also supported - instead of K and Q the letters
    * of files containing rooks are used. For standard chess its AHah.
    * X-FEN is not supported.
    *
    * @return string castling availability string
    */
    public function getCastling() : string
    {
      return $this->useShredderFen ? $this->shredderCastling() : $this->standardCastling();
    }

    private function shredderCastling() : string
    {
      $str =
          ($this->castlingRights['Q'] ? strtoupper((string)$this->queensRookFile) : '') .
          ($this->castlingRights['K'] ? strtoupper((string)$this->kingsRookFile) : '') .
          ($this->castlingRights['q'] ? $this->queensRookFile : '').
          ($this->castlingRights['k'] ? $this->kingsRookFile : '');
      return $str ? $str : '-';
    }

    private function standardCastling() : string
    {
      $str =
          ($this->castlingRights['K'] ? 'K' : '') .
          ($this->castlingRights['Q'] ? 'Q' : '') .
          ($this->castlingRights['k'] ? 'k' : '') .
          ($this->castlingRights['q'] ? 'q' : '');
      return $str ? $str : '-';
    }

    /**
    * Set castling availability.
    *
    * If neither side can castle, this is "-".
    * Otherwise, this has one or more letters: "K" (White can castle kingside),
    * "Q" (White can castle queenside), "k" (Black can castle kingside), and/or
    * "q" (Black can castle queenside). A move that temporarily prevents castling
    * does not negate this notation.
    * Shredder-FEN is also supported - instead of K and Q the letters
    * of files containing rooks are used. For standard chess its AHah.
    * X-FEN is not supported.
    *
    * @param string castling availability string
    */
    public function setCastling(string $castling) : void
    {
      [$shredder, $kingsRook, $queensRook, $kingsFile] = $this->castlingDefaults($castling);
      $rights = [ "K" => false, "Q" => false, "k" => false, "q" => false];
      $castlingRights = $castling == '-' ? [] : str_split($castling);

      foreach ($castlingRights as $castlingRight)
      {
        [$type, $kingsFile, $kingsRook, $queensRook] = $this->resolveCastlingRight(
          $castlingRight,
          $shredder,
          $kingsFile,
          $kingsRook,
          $queensRook,
          $castling
        );
        $rights[$type] = true;
      }

      $this->validateCastling($rights, $kingsFile, $kingsRook, $queensRook);
      $this->kingsFile = $kingsFile;
      $this->kingsRookFile = $kingsRook;
      $this->queensRookFile = $queensRook;
      $this->castlingRights = $rights;
      $this->useShredderFen = $shredder;
    }

    private function castlingDefaults(string $castling) : array
    {
      $standard = ['-', 'KQkq', 'KQk', 'KQq', 'KQ', 'Kkq', 'Kk', 'Kq', 'K', 'Qkq', 'Qk', 'Qq', 'Q', 'kq', 'k', 'q'];
      if (in_array($castling, $standard, true)) {
        return [false, 'h', 'a', 'e'];
      }
      return [true, null, null, null];
    }

    private function resolveCastlingRight(string $right, bool $shredder, ?string $kingsFile, ?string $kingsRook, ?string $queensRook, string $castling) : array
    {
      if (!$this->isShredderFile($right, $shredder)) {
        if (!$shredder) {
          return [$right, $kingsFile, $kingsRook, $queensRook];
        }
        throw new ParseException("Invalid castling right '$right' in string '$castling'.");
      }
      return $this->resolveShredderRight($right, $castling, $kingsFile, $kingsRook, $queensRook);
    }

    private function isShredderFile(string $right, bool $shredder) : bool
    {
      if (!$shredder) {
        return false;
      }
      $lower = strtolower($right);
      return $lower >= 'a' && $lower <= 'h';
    }

    private function resolveShredderRight(string $right, string $castling, ?string $kingsFile, ?string $kingsRook, ?string $queensRook) : array
    {
      $lower = strtolower($right);
      $actualKingFile = $this->board->findSquareWithPiece(Board::getPieceOfColor('K', ctype_upper($right) ? 'w' : 'b'), true)->getFile();
      if ($lower < $actualKingFile) {
        $type = ctype_upper($right) ? 'Q' : 'q';
        $queensRook = $this->assertUniqueRook($queensRook, $lower, $castling, "queen's");
      } else {
        $type = ctype_upper($right) ? 'K' : 'k';
        $kingsRook = $this->assertUniqueRook($kingsRook, $lower, $castling, "king's");
      }
      return [$type, $actualKingFile, $kingsRook, $queensRook];
    }

    private function assertUniqueRook(?string $current, string $rookFile, string $castling, string $side) : string
    {
      if ($current && $current != $rookFile) {
        throw new ParseException("Multiple $side rook castling rights in string '$castling'.");
      }
      return $rookFile;
    }

    private function validateCastling(array $castlingRights, ?string $kingsFile, ?string $kingsRookFile, ?string $queensRookFile) : void
    {
      $this->assertKingPlacement($castlingRights, $kingsFile);
      $this->assertRookPlacement($castlingRights, $kingsRookFile, $queensRookFile);
    }

    private function assertKingPlacement(array $castlingRights, ?string $kingsFile) : void
    {
      if (($castlingRights['K'] || $castlingRights['Q']) && $this->board->getSquare($kingsFile.'1') != 'K') {
        throw new ParseException("Invalid castling string. White king not in initial position.");
      }
      if (($castlingRights['k'] || $castlingRights['q']) && $this->board->getSquare($kingsFile.'8') != 'k') {
        throw new ParseException("Invalid castling string. Black king not in initial position.");
      }
    }

    private function assertRookPlacement(array $castlingRights, ?string $kingsRookFile, ?string $queensRookFile) : void
    {
      if ($castlingRights['K'] && $this->board->getSquare($kingsRookFile.'1') != 'R') {
        throw new ParseException("Invalid castling string. White king's rook not in initial position.");
      }
      if ($castlingRights['k'] && $this->board->getSquare($kingsRookFile.'8') != 'r') {
        throw new ParseException("Invalid castling string. Black king's rook not in initial position.");
      }
      if ($castlingRights['Q'] && $this->board->getSquare($queensRookFile.'1') != 'R') {
        throw new ParseException("Invalid castling string. White queen's rook not in initial position.");
      }
      if ($castlingRights['q'] && $this->board->getSquare($queensRookFile.'8') != 'r') {
        throw new ParseException("Invalid castling string. Black queen's rook not in initial position.");
      }
    }

    /**
    * Get castling availability of particular type.
    *
    * Possible castling types: "K" (White can castle kingside),
    * "Q" (White can castle queenside), "k" (Black can castle kingside), and/or
    * "q" (Black can castle queenside). A move that temporarily prevents castling
    * does not negate this notation.
    *
    * @param string castling type
    */
    private function getCastlingAvailability(string $type) : bool
    {
      return $this->castlingRights[$type];
    }

    /**
    * Set castling availability of particular type.
    *
    * Possible castling types: "K" (White can castle kingside),
    * "Q" (White can castle queenside), "k" (Black can castle kingside), and/or
    * "q" (Black can castle queenside). A move that temporarily prevents castling
    * does not negate this notation.
    *
    * @param string castling type
    * @param bool set availability
    */
    private function setCastlingAvailability(string $type, bool $avalability) : void
    {
      $this->castlingRights[$type] = $avalability;
    }

    /**
    * Returns true if king of active color is in mate.
    */
    public function isMate() : bool
    {
      return $this->isCheck() && sizeof($this->getLegalMoves()) == 0;
    }

    /**
    * Returns true if king of active color is in stalemate.
    */
    public function isStalemate() : bool
    {
      return !$this->isCheck() && sizeof($this->getLegalMoves()) == 0;
    }

    /**
    * Returns true if fifty move rule draw can be claimed by active color.
    */
    public function isFiftyMove() : bool
    {
      return $this->getHalfmove() >= 100;
    }

    /**
    * Returns true if king of active color is in check.
    */
    public function isCheck() : bool
    {
      return $this->board->isCheck($this->getActiveColor());
    }

    private function getActivePiece(string $piece) : string
    {
      return Board::getPieceOfColor($piece, $this->getActiveColor());
    }

    private function getOpponentsPiece(string $piece) : string
    {
      return Board::getPieceOfColor($piece, Board::getOppositeColor($this->getActiveColor()));
    }
}
