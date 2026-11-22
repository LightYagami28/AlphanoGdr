<?php

namespace Onspli\Chess;

/** Extracted to keep the public class surface small. */
trait PgnMetadataTrait
{

  /**
  * Export tag pairs section (headers) of PGN.
  */
  public function exportTags() : string
  {
    $pgn = '';
    foreach ($this->tags as $name => $value) {
      $pgn .= '[' . $name . ' "' . $value . '"]' . PHP_EOL;
    }
    return $pgn;
  }


  /**
  * Set tag pair (header).
  */
  public function setTag(string $name, ?string $value) : void
  {
    if ($value !== null) {
      $this->tags[$name] = $value;
      if ($name == 'FEN') {
        $this->setInitialFen($value);
      }
    } elseif (isset($this->tags[$name])) {
      unset($this->tags[$name]);
      if ($name == 'FEN') {
        $this->unsetInitialFen();
      }
    }
  }


  /**
  * Remove tag pair (header).
  */
  public function unsetTag(string $name) : void
  {
    $this->setTag($name, null);
  }


  /**
  * Read tag pair (header) value.
  */
  public function getTag(string $name) : ?string
  {
    if (!isset($this->tags[$name])) {
      return null;
    }
    return $this->tags[$name];
  }


  /**
  * Get all tags as associative array
  */
  public function getTags() : array
  {
    return $this->tags;
  }


  /**
  * Parse tag section and save all tag values.
  * The method does NOT clear existing tags, however it overrides them.
  */
  private function parseTagSection(string $pgn) : void
  {
    $tags = [];
    preg_match_all('/\[[^\]]*\]/', $pgn, $tags);
    foreach ($tags[0] as $tag) {
      $pair = [];
      if (!preg_match('/\[\s*(\S+)\s+"(.*)"\s*\]/', $tag, $pair)) {
        continue;
      }
      $this->setTag($pair[1], $pair[2]);
    }
  }
}
