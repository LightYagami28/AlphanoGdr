<?php

namespace Onspli\Chess;


/**
* Class representing coordinates of a square on a chess board.
*
* Square can be on of the following types:
*  - regular square - 'e4'
*  - file - 'e' for e-file
*  - rank - '4' for 4th rank
*  - null square - '-', '', square not on a board
*
*/
class Square
{
  private $rankIndex;
  private $fileIndex;

  /**
  * Create square.
  *
  * Constructor accepts either SAN (standard algebraic notation) string,
  * or file and rank indexes.
  *  - regular square: `new Square('e4'); new Square(4, 3);`
  *  - null square: `new Square; new Square('-'); new Square(null, null);`
  *  - file: `new Square('e'); new Square(4, null);`
  *  - rank: `new Square('4'); new Square(null, 3);`
  *
  * Throws `ParseException` if SAN is invalid or if indices are not integers.
  * Creates null square if file or rank index is out of bounds.
  */
  public function __construct($san_or_file_index = null, $rankIndex = null)
  {
    if (is_string($san_or_file_index)) {
      $this->parseSan($san_or_file_index);
    } else {
      $this->fileIndex = $san_or_file_index;
      $this->rankIndex = $rankIndex;
      $this->validateIndices();
    }
  }

  public function __toString() : string
  {
    return $this->export();
  }

  /**
  * Get rank index of the square.
  *
  * For square 'e4' it returns 3.
  */
  public function getRankIndex() : int
  {
    return $this->rankIndex;
  }

  /**
  * Get rank of the square.
  *
  * For square 'e4' it returns '4'. Throws `\OutOfBoundsException` for null squares.
  * Returns empty string for files.
  */
  public function getRank() : string
  {
    if ($this->isNull()) {
      throw new \OutOfBoundsException;
    }
    if ($this->isFile()) {
      return '';
    }
    return $this->rankIndex + 1;
  }

  /**
  * Get file index of the square.
  *
  * For square 'e4' it returns 4.
  */
  public function getFileIndex() : int
  {
    return $this->fileIndex;
  }

  /**
  * Get file of the square.
  *
  * For square 'e4' it returns 'e'. Throws `\OutOfBoundsException` for null squares.
  * Returns empty string for ranks.
  */
  public function getFile() : string
  {
    if ($this->isNull()) {
      throw new \OutOfBoundsException;
    }
    if ($this->isRank()) {
      return '';
    }
    return chr(ord('a') + $this->fileIndex);
  }

  /**
  * Returns SAN (standard algebraic notation) string.
  *
  * For
  * - regular square - 'e4'
  * - file - 'e'
  * - rank - '4',
  * - null square - '-'
  */
  public function export() : string
  {
    if ($this->isNull()) {
      return '-';
    }

    return $this->getFile() . $this->getRank();
  }

  /**
  * Check wether square is regular square.
  */
  public function isRegular() : bool
  {
    return $this->rankIndex !== null && $this->fileIndex !== null;
  }

  /**
  * Check wether square is null.
  */
  public function isNull() : bool
  {
    return $this->rankIndex === null && $this->fileIndex === null;
  }

  /**
  * Check wether square is rank.
  */
  public function isRank() : bool
  {
    return $this->rankIndex !== null && $this->fileIndex === null;
  }

  /**
  * Check wether square is file.
  */
  public function isFile() : bool
  {
    return $this->rankIndex === null && $this->fileIndex !== null;
  }

  /**
  * Check wether square has rank.
  *
  * Regular squares and ranks has rank.
  */
  public function hasRank() : bool
  {
    return $this->rankIndex !== null;
  }


  /**
  * Check wether square has file.
  *
  * Regular squares and files has file.
  */
  public function hasFile() : bool
  {
    return $this->fileIndex !== null;
  }

  /**
  * Get square with relative position to this square.
  *
  * Throws `\OutOfBoundsException` when trying to get relative square of a non
  * regular squares. Returns null square if relative coordinates are outside
  * the board.
  */
  public function getRelativeSquare(int $east, int $north)
  {
    if (!$this->isRegular()) {
      throw new \OutOfBoundsException;
    }
    return new Square($this->getFileIndex() + $east, $this->getRankIndex() + $north);
  }

  /**
  * Add square to the end of array.
  *
  * Method ignores nonregular squares.
  * @param bool $as_object Add square as an object rather than SAN string.
  */
  public function pushToArray(array &$array, bool $as_object = false) : void
  {
    if (!$this->isRegular()) {
      return;
    }
    if ($as_object) {
      $array[] = $this;
    } else {
      $array[] = $this->export();
    }
  }

  private function setToNull() : void
  {
    $this->fileIndex = null;
    $this->rankIndex = null;
  }

  private static function isIndexInRange($index) : bool
  {
    return $index >= 0 && $index <= 7;
  }

  private function validateIndices() : void
  {
    if (!self::isIndexInRange($this->fileIndex) || !self::isIndexInRange($this->rankIndex)) {
      $this->setToNull();
    }
  }

  private function parseSan(string $san) : void
  {
    if ($san == '-' || $san == '') {
      $this->setToNull();
      return;
    }
    $matches = [];
    if (!preg_match('/^([a-h]?)([1-8]?)$/', $san, $matches)) {
      throw new ParseException;
    }
    if ($matches[1]) {
      $this->fileIndex = ord($matches[1]) - ord('a');
    }
    if ($matches[2]) {
      $this->rankIndex = intval($matches[2]) - 1;
    }
  }

  /**
   * Compatibilita' con la API storica in snake_case della libreria vendored.
   */
  public function __call(string $name, array $arguments): mixed
  {
    $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    if (method_exists($this, $camel)) {
      return $this->$camel(...$arguments);
    }
    throw new \BadMethodCallException('Metodo sconosciuto: ' . $name);
  }
}
