<?php
declare(strict_types=1);

/** Eccezioni dedicate: i punti di lancio non usano Exception/RuntimeException generiche. */
/** Errori di elaborazione delle richieste API. */
class AlphanoApiException extends \RuntimeException {}
/** Errori delle bacheche e delle discussioni. */
class AlphanoBachecaException extends \RuntimeException {}
/** Errori di trasporto e consegna della posta. */
class AlphanoMailException extends \RuntimeException {}
/** Errori dei giochi da tavolo. */
class AlphanoBoardGameException extends \RuntimeException {}
/** Errori di accesso al database. */
class AlphanoDatabaseException extends \RuntimeException {}
/** Errori di generazione dei PDF. */
class AlphanoPdfException extends \RuntimeException {}
/** Errori di caricamento dei profili. */
class AlphanoProfileException extends \RuntimeException {}
/** Errori di registrazione delle notifiche push. */
class AlphanoPushException extends \RuntimeException {}
/** Errori delle operazioni amministrative. */
class AlphanoAdminException extends \RuntimeException {}
/** Errori applicativi generici. */
class AlphanoRuntimeException extends \RuntimeException {}

