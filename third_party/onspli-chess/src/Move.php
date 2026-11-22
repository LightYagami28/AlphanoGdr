<?php

namespace Onspli\Chess;

/**
* Class for parsing moves in SAN (standard algebraic notation).
*/
class Move
{

  private $origin;
  private $target;
  private $piece;
  private $capture = '';
  private $castling = '';
  private $promotion = '';
  private $checkMate = '';
  private $annotation = '';

  public function __construct(string $move)
  {
    $matches = array();

    if (preg_match('/^([O-]+)([+#]?)([!?]*)$/', $move, $matches)) {
      $castling = $matches[1];
      if ($castling != 'O-O' && $castling != 'O-O-O') {
        throw new ParseException('Move "' . $move . '" is not valid.');
      }
      $this->piece = 'K';
      $this->castling = $castling;
      $this->origin = new Square;
      $this->target = new Square;
      $this->checkMate = $matches[2];
      $this->annotation = $matches[3];
      return;
    }

    if (!preg_match('/^([PNBRQK]?)([a-h]?[1-8]?)(x?)([a-h][1-8])(?:=([NBRQ]))?([+#]?)([!?]*)$/', $move, $matches)) {
      throw new ParseException('Move "' . $move . '" is not valid.');
    }

    $this->piece = $matches[1];
    $this->origin = new Square($matches[2]);
    $this->capture = $matches[3];
    $this->target = new Square($matches[4]);
    $this->promotion = $matches[5];
    $this->checkMate = $matches[6];
    $this->annotation = $matches[7];

    self::validatePromotion();
  }

  private function validatePromotion() : void
  {
    if ($this->getPromotion() && $this->getPieceType() != 'P') {
      throw new RulesException;
    }

    if ($this->getPromotion() && $this->target->getRankIndex() != 7 && $this->target->getRankIndex() != 0) {
      throw new RulesException;
    }

    if (!$this->getPromotion() && $this->getPieceType() == 'P' && ($this->target->getRankIndex() == 7 || $this->target->getRankIndex() == 0)) {
      throw new RulesException;
    }
  }

  public function export() : string
  {
    $str = '';
    if ($this->getCastling()) {
      $str .= $this->getCastling();
    } else {
      $str .= $this->sanPiece();
      $str .= $this->sanOrigin();
      $str .= $this->capture;
      $str .= $this->getTarget();
      $str .= $this->sanPromotion();
    }
    $str .= $this->sanExtension();
    return $str;
  }

  public function __toString() : string
  {
    return $this->export();
  }

  private function sanOrigin() : string
  {
    if (!$this->origin->isNull()) {
      return $this->origin->export();
    }
    return '';
  }

  private function sanPiece() : string
  {
    if ($this->getPieceType() != 'P') {
      return $this->getPieceType();
    }
    return '';
  }

  private function sanExtension() : string
  {
    return $this->getCheckMate() . $this->getAnnotation();
  }

  private function sanPromotion() : string
  {
    if ($this->getPromotion()) {
      return '=' . $this->getPromotion();
    }
    return '';
  }

  public function getCapture() : bool
  {
    return $this->capture;
  }

  public function getTarget(bool $as_object = false)
  {
    if ($as_object) {
      return $this->target;
    } else {
      return $this->target->export();
    }
  }

  public function getOrigin(bool $as_object = false)
  {
    if ($as_object) {
      return $this->origin;
    } else {
      return $this->origin->export();
    }
  }

  public function setOrigin($square) : void
  {
    if (is_string($square)) {
      $square = new Square($square);
    }
    $this->origin = $square;
  }

  public function getPieceType() : string
  {
    if ($this->piece == '') {
      return 'P';
    }
    return $this->piece;
  }

  public function getCastling() : string
  {
    return $this->castling;
  }

  public function getPromotion() : string
  {
    return $this->promotion;
  }

  public function getCheckMate() : string
  {
    return $this->checkMate;
  }

  public function getAnnotation() : string
  {
    return $this->annotation;
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
