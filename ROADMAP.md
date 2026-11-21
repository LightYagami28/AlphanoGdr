# Roadmap

Stato aggiornato al 2026-11-21. Fonti: README, codice e comandi di verifica eseguiti.

## Stato attuale

AlphanoGdr V1.0 è un motore open source per giochi di ruolo testuali via browser, scritto in PHP e MySQL e pensato anche per hosting condiviso (AlterVista/AlterSito). L'installazione avviene dal browser senza Composer, SSH o import manuale di SQL.

Verifiche eseguite sul repository:

- `php -l` su 489 file PHP (esclusi vendor e third_party): 0 errori di sintassi.
- `python3 tests/syntax.py`: 620 file PHP e JavaScript controllati, 0 errori.
- `php tests/http-context.php`, `tests/runtime-requirements.php`, `tests/board-engines.php`, `tests/recording-format.php`: tutti superati, 0 errori.
- `composer audit --locked`: nessuna vulnerabilità nota.
- `python3 tests/release_audit.py`: NON VERIFICATO (il comando non completa l'esecuzione in 300 secondi).

Le GitHub Action sono pinnate a SHA-256 e usano `permissions: contents: read`.

## Obiettivi

- Mantenere il pacchetto hosting installabile senza strumenti da terminale.
- Mantenere la compatibilità PHP 8.4/8.5 e il database MySQL 8.0.
- Mantenere zero vulnerabilità note nelle dipendenze Composer.

## Prossimi passi

1. Completare l'esecuzione di `tests/release_audit.py` e correggere eventuali problemi di rilascio.
2. Eseguire il flusso di packaging (`bin/package.py`) e il test di installazione del pacchetto in un ambiente pulito.
3. Verificare l'installazione guidata su un database MySQL reale, oltre alla verifica dello schema.
4. Aggiornare `CITATION.cff` con tutti gli autori storici dichiarati nel README.
