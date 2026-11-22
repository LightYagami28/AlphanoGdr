# Codacy below-threshold — review delle categorie (2026-11-22)

## 1. Stato misurato

Overview API (prima lettura, `POST .../issues/overview` con `{}`):

| Livello | Conteggio |
|---|---:|
| Error | 454 |
| High | 1302 |
| Warning | 3212 |
| Info | 11924 |
| **Totale** | **16892** |

412 pattern distinti. La `search` con `levels=["Error","High"]` e cursor in query-string (`?cursor=N`, 18 pagine da 100) ha restituito **1756 finding**, ripartiti in 70 pattern. I primi 15 per volume:

| Pattern | Finding |
|---|---:|
| Oxlint_eslint_no-undef | 708 |
| Semgrep echoed-request | 203 |
| Semgrep html-in-template-string | 96 |
| Prospector_bandit | 62 |
| Semgrep insecure-document-method | 60 |
| Semgrep insecure-innerhtml | 60 |
| Oxlint no-use-before-define | 57 |
| SQLint_allIssues | 51 |
| Oxlint require-unicode-regexp | 49 |
| Lizard_ccn-critical | 41 |
| PHPCS_Generic_PHP_ClosingPHPTag | 32 |
| Oxlint unicorn_throw-new-error | 31 |
| Semgrep python assert-used | 24 |
| PHPCS_Squiz DisallowComparisonAssignment | 21 |
| Prospector_pyright | 21 |

Nota API: i parametri `page`/`cursor` nel body vengono ignorati; la paginazione funziona solo come query-string (`?cursor=2`). Il parametro `?branch=...` viene ignorato: non è possibile misurare la riduzione su un branch prima del merge.

## 2. Falsi positivi dimostrati (evidenza)

Le occorrenze citate sono state lette tramite `search` con filtro `patternIds` e verificate sul sorgente.

### 2.1 `Oxlint_eslint_no-undef` — 708 High — FP sistemico
10/10 campioni sono globali standard del browser: `window` (assets/install-complete.js:12, js/commerce-ui.js:16), `document` (js/ui.js:66, js/work-admin.js:2, js/polls.js:4, js/relation-effects.js:5), `fetch` (js/welcome-message.js:21), `location` (assets/management.js:16). L'analisi Oxlint è configurata senza environment browser, quindi segnala identificatori forniti dal runtime. Azzerarli richiederebbe una modifica di configurazione per nascondere i finding: non eseguita.

### 2.2 `SQLint_allIssues` — 51 High — FP di dialetto
Tutti i 51 finding sono su `database/schema.sql` (e una migration) con messaggio `syntax error at or near "`"`. Il file è MySQL: `CREATE TABLE` con identificatori tra backtick e `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;` (191 occorrenze di `ENGINE=InnoDB`, verificate con `Select-String`). Il backtick è un identificatore valido in MySQL; SQLint analizza in dialetto ANSI. Lo stesso pattern include `Semgrep_codacy.generic.sql.rac-table-access` su una `CONSTRAINT ... FOREIGN KEY` (DDL, non query).

### 2.3 `PHPCS_Generic_PHP_ClosingPHPTag` — 32 High — FP di standard
Messaggio: `The PHP open tag does not have a corresponding PHP close tag`. Verificato: `vari/menuvarie.php` e `tests/router.php` terminano senza `?>` (`TrimEnd().EndsWith('?>')` = False). È il comportamento corretto per file PHP puri: PSR-12 vieta il tag di chiusura. La regola è in contrasto con lo standard dichiarato.

### 2.4 `PHPCS_Squiz_PHP_DisallowComparisonAssignment` — 21 High — FP di idioma
Campioni: `$kind=($_GET['kind']??'player')==='npc'?'npc':'player';` (profili/progressione.php:6), `$mine=(int)$letter['idmittente']===$idpg;` (templates/mail/thread-messages.php:1), `$directory=$root ? realpath(...) : false;` (vari/galleria.php:9). Assegnare il risultato di un confronto o di un ternario è codice valido e leggibile; il caso `$historyCard=static function(...) use($h):void` è inoltre un errore di parsing della regola. Correggere peggiorerebbe il codice.

### 2.5 `Prospector_bandit` (62) e `Semgrep assert-used` (24) — FP di contesto
Campioni Bandit: `import subprocess` (B404), `assert 'return array (' in saved_local` (B101), `subprocess.run(['node', ...], check=False)` (B607/B603) — tutti in `tests/` o in `bin/package.py` con argomenti costruiti da percorsi interni. I 24 `assert-used` sono tutti in file `tests/` (es. tests/release_audit.py:14,60,61). Bandit B101 segnala le assertion come rischio in codice di produzione: nei test sono lo strumento di verifica. Non sono state aggiunte soppressioni.

### 2.6 `PHPCS_Generic_PHP_CharacterBeforePHPOpeningTag` — 10 High — FP di template
Campioni: `templates/sessions/location-picker.php:6` (`<section ...>` prima di `<?php`), `templates/mail/index.php:34` (`<details>...<?php require ... ?>`), `templates/admin/treasury-history.php:1` (`<table...><?php foreach...`). Sono template PHP con markup: il contenuto prima del tag non è un errore.

### 2.7 `Oxlint no-use-before-define` — 57 High — FP tecnico
Campioni: `showPanel(...)` (js/city/assemblea.js:30), `edit` (js/work-admin.js:7), `loadAll()` (js/corporazioni/trasferimenti.js:14), `closePanel()` (js/mobile-game.js:18), `show(i)` (js/registration-wizard.js:13). Sono function declaration: in JavaScript sono hoisted e chiamarle prima della definizione è valido.

### 2.8 `Semgrep echoed-request` — 203 High — FP parziale da approfondire
5/5 campioni sono già protetti: `echo json_encode(...JSON_THROW_ON_ERROR)` (api/demo.php:23), `FormatTimestamp((int)$object['datacreazione'])` (corporazioni/showobj.php:122), token CSRF e `$editType` (admin/world.php:28), `htmlentities($dati3['nome'])` (statistiche/ricompense.php:87). La regola segnala ogni `echo` nei file che leggono input, senza dataflow. Non classificato come FP totale: andrà rivisto caso per caso.

## 3. Finding reali corretti (PR aperte, non unite)

| PR | Pattern | Finding corretti | File | Verifica |
|---|---|---:|---:|---|
| #50 | PyLint W1510 + W0622 | 4 | 3 | `py -3 tests/syntax.py` → PASS 638 file; `ast.parse` OK |
| #51 | Oxlint unicorn_throw-new-error | 18 | 15 | `node --input-type=module --check` → 0 fallimenti |
| #53 | Oxlint require-unicode-regexp | 18 | 16 | `node --input-type=module --check` → 0 fallimenti |

PR #52 è stata chiusa: il suo primo commit era generato da un worktree con indice parziale e includeva cancellazioni non intenzionali; #53 contiene lo stesso fix con un diff di soli 16 file. Nessuna PR è stata unita.

Tutti i commit sono firmati G, autore/committer Light Yagami <maule2703@gmail.com>. Nessuna soppressione, nessuna modifica a `.codacy.yml`, soglie o baseline.

## 4. Blocchi e lavoro restante

1. **Conflitto con altro agente**: i branch `fix/sonar-*` toccano 569 file, inclusa la maggior parte dei file con finding Codacy. Esempi: i 21 `DisallowComparisonAssignment` sono su 20 file tutti occupati; 13/31 `throw-new-error` e 31/49 `require-unicode-regexp` sono su file occupati. Ho lavorato solo sui file liberi.
2. **FP sistemici non azzerabili senza config**: `no-undef` (708), `SQLint` (51), `ClosingPHPTag` (32), `DisallowComparisonAssignment` (21), `CharacterBeforePHPOpeningTag` (10), `no-use-before-define` (57), Bandit/assert nei test (86). Da soli sono circa 965 dei 1756 Error/High. La regola "zero soppressioni / nessuna configurazione per nascondere finding" impedisce di azzerarli modificando l'analisi.
3. **Refactoring complessi**: `Lizard_ccn-critical` (41), `Semgrep insecure-innerhtml` (60), `insecure-document-method` (60), `html-in-template-string` (96), `echoed-request` (203) richiedono revisione caso per caso su file in gran parte occupati.
4. **Warning/Info**: 3212 + 11924 finding, in maggioranza regole di formattazione in conflitto tra loro (es. `DisallowSpaceIndent` 143 vs `DisallowTabIndent` 13; `DisallowLongArraySyntax` vs `DisallowShortArraySyntax`). Non possono essere tutti soddisfatti contemporaneamente.

Stima: i 40 finding corretti nelle tre PR sono la parte facilmente correggibile senza toccare file altrui; per il resto serve prima che l'altro agente completi/unisca le proprie PR, poi una revisione per categoria con eventuale decisione esplicita su come gestire i FP sistemici (senza soppressioni).
