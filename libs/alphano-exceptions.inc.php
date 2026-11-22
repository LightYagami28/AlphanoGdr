<?php
declare(strict_types=1);

/** Eccezioni dedicate del motore: sostituiscono le eccezioni generiche delle librerie. */
class AlphanoStorageException extends RuntimeException {}
class AlphanoDomainException extends DomainException {}
class AlphanoValidationException extends InvalidArgumentException {}
