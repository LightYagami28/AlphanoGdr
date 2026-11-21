# Revisione finding Codacy aperti — AlphanoGdr (2026-11-21)

## Ambito

Fonte: export Codacy `security_items_gh_LightYagami28 (3).csv`, filtro `repository=AlphanoGdr` e `closed_at` vuoto.
Item processati: **798** (39 pattern, 128 file). Ogni singolo item è classificato nell'allegato
`docs/codacy-classification-2026-11-21.csv` (colonne: id, pattern, file, riga, classificazione, motivazione).

Riepilogo:

| Classificazione | Item |
|---|---|
| Falso positivo dimostrato | 774 |
| Finding reale corretto | 20 |
| Saltato per conflitto con altro agente | 4 |
| **Totale** | **798** |

Nessuna soppressione introdotta: nessun `ignore` Codacy, `# nosec`, `eslint-disable`, `istanbul ignore`,
baseline, eccezione di configurazione o abbassamento di soglia.

## Conteggio per pattern

| Item | Pattern |
|---:|---|
| 229 | echoed-request |
| 139 | html-in-template-string |
| 94 | insecure-innerhtml |
| 94 | insecure-document-method |
| 62 | Prospector_bandit |
| 20 | jquery-insecure-method |
| 17 | Bandit_B608 |
| 17 | tainted-sql-string |
| 16 | node-ssrf |
| 15 | Bandit_B603 |
| 15 | tainted-filename |
| 13 | dangerous-subprocess-use-audit |
| 10 | dangerous-subprocess-use-tainted-env-args |
| 9 | unlink-use |
| 7 | tainted-callable |
| 5 | Bandit_B404 |
| 4 | jquery-insecure-selector |
| 4 | Bandit_B105 |
| 3 | regex-dos |
| 2 | Bandit_B607 |
| 2 | urllib-urlopen |
| 2 | Bandit_B310 |
| 2 | dynamic-urllib-use-detected |
| 2 | insecure-random-generator |
| 1 | rac-table-access, no-prototype-builtins, open-redirect-from-function, weak-crypto (x2), tainted-url-host, require-post-message-target-origin, unserialize-use, CKV_SECRET_6, DisallowRequestSuperglobal, insecure-object-assign, unsafe-dynamic-method, non-literal-fs-filename, Bandit_B106, react-unsanitized-method |

## Falsi positivi: dimostrazione per categoria

### echoed-request (229)

L'analyzer Semgrep PHP segnala qualsiasi `echo`/`<?=` di una variabile proveniente da `$_GET`/`$_POST`,
anche quando il valore è escapato o tipizzato. Verifica eseguita su tutti i 229 item:

```
python classify_echo.py
Counter({'LINE': 159, 'CTX': 37, 'RAW': 33})
```

- 159 item: funzione di escape/cast nella stessa riga (`json_encode`, `htmlspecialchars`, `htmlentities`,
  `alphano_escape`, `ProcessText`, `rawurlencode`, `(int)`, `(float)`, `number_format`, `round`).
- 37 item: escape/cast nella funzione di rendering di contesto (es. `converti()` in `admin/log.php`,
  `h()` in `admin/chats.php` e `profili/modifyuser.php`, `alphano_escape` in `libs/engine-settings.inc.php`).
- 33 item: valore intero o identificatore validato (es. `$corpId` da `alphano_corporation_context()`,
  `$id` da `CommercioIntero()` in `commercio/index.php:4`, `$dati["num"]` conteggio SQL,
  `$jsEncode()` in `control.php:22` che applica `JSON_HEX_*`, risposte JSON con `Content-Type: application/json`).

I 2 item con `GetTitolo()` grezzo in `<title>` (`vari/anteprimagml.php:12`, `organizzazioni/anteprima.php:11`)
e i 3 item di `admin/log.php` che passano per `converti()` **non** sono FP: sono stati corretti.

Helper di escape verificati:
```
libs/engine-settings.inc.php:5  alphano_escape() -> htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE)
libs/libtext.inc.php:209        ProcessText()
libs/libtext.inc.php:220        alphano_sanitize_html()
libs/ai-quest.inc.php:4         ai_quest_json() -> json_encode(..., JSON_THROW_ON_ERROR)
control.php:22                  $jsEncode -> json_encode con JSON_HEX_TAG|AMP|APOS|QUOT
```

### html-in-template-string / insecure-innerhtml / insecure-document-method (327 item, 179 righe uniche)

Le tre regole Semgrep segnalano la stessa costruzione HTML con tre nomi diversi: template literal con HTML,
`innerHTML` e metodi DOM definiti "insecure". Le righe usano sistematicamente:

- helper `esc()`/`escapeHtml()`/`escapeStatus()`/`commerceEscape()`/`tradeEscape()`/`mailEsc()` definiti nei file stessi
  (es. `js/app.js:17`, `js/forum.js:5`, `js/trasferimenti.js:155`);
- `Number()` per ogni id/quantità/prezzo interpolato in attributi o callback inline;
- `encodeURIComponent()` per parametri di URL;
- `textContent`/`createElement`/`replaceChildren` quando non serve markup;
- HTML generato lato server e già sanificato: `ProcessText()` (`libs/libtext.inc.php:209`),
  `alphano_chat_text()` (`libs/chat-format.inc.php:9`, `htmlspecialchars`+`nl2br` su ogni messaggio),
  form JSON da `/api/experience.php`.

Verifica:
```
Select-String -Path 'js/*.js' -Pattern 'const esc\s*=|function esc\s*=|escapeHtml\s*='
# helper presenti in app.js, board-games.js, commerce-thread.js, forum.js, inbox.js, manuali.js, ...
Select-String -Path 'libs/*.php' -Pattern 'function (ProcessText|alphano_chat_text|alphano_sanitize_html)'
# libs/chat-format.inc.php:9, libs/libtext.inc.php:209, libs/libtext.inc.php:220
```

Le 12 righe prive di escape (`js/ricerca.js` e `js/trasferimenti.js`) sono state corrette.

### Finding Python (Prospector_bandit 62 + Bandit_B608 17 + B603 15 + B404 5 + B105 4 + B607 2 + B310 2 + subprocess-audit 13 + tainted-env 10 + urlopen 2 + dynamic-urllib 2)

Tutti gli item sono in script di test (`tests/*.py`) o di impacchettamento (`bin/package.py`), non nel codice
server. Le chiamate `subprocess.run`/`Popen` usano liste di argomenti con eseguibili configurati
(`php`, `node`, percorso MySQL da `--basedir`), mai `shell=True`, e nessun input dell'utente.
`assert` e password sono fixture di test; `urlopen` punta al server HTTP locale avviato dal test stesso.

```
Select-String -Path 'tests/*.py','bin/*.py' -Pattern 'shell=True'
# 0 risultati
```

### tainted-sql-string (17)

Gli identificatori interpolati non arrivano dall'utente:

- `admin/log.php:47,74,234`: `$tabella` verificato con `in_array($tabella, $tables, true)` dove `$tables`
  viene da `SHOW TABLES` e filtrato sul prefisso `logs_`; `$field` da `SHOW FIELDS`.
- `admin/accademia.php:73,76,82`: `$academyTable` e `$academyKind` da mappe fisse convalidata da whitelist.
- `posta/deletesel.php:54,60,67`, `statistiche/ricompense.php:68`, `profili/index.php:47`,
  `api/corporazioni/trasferimenti.php:221`, `api/corporazioni/catalogo.php:279,340`: solo segnaposto
  `$ph`/`$cols` costruiti da `array_fill()` su array validati; i valori reali passano come parametri.
- `admin/appearance.php:36`, `corporazioni/oggetti-admin.php:5`: `SHOW COLUMNS`, nomi di colonna del DB.

### tainted-filename (15) / unlink-use (9) / tainted-callable (7)

- Path da hash SHA256 (`regole/pdf.php:29,74,80,81`), whitelist di definizioni
  (`api/manual-media.php:11,13`), regex restrittiva `^[a-f0-9]{32}\.png$` (`api/ai.php:18,20`),
  id interi (`admin/cariche.php:149`, `api/admin/utenti.php:283-284`, `profili/index.php:40`).
- `unlink()` rimuove solo file generati (cache PDF, upload con nome casuale, config temporanea).
- `tainted-callable`: closure `static fn` e array di costanti passati a `array_map`/`array_filter`.

### node-ssrf (16) / jquery-insecure-method (20) / jquery-insecure-selector (4) / regex-dos (3)

```
Select-String -Path 'js/*.js','js/**/*.js','chat/js/*.js' -Pattern "fetch\(['"]https?://"
# 0 risultati
```

Tutti i `fetch()` usano path same-origin (`/api/...`) o `URLSearchParams`. I 20 `jquery-insecure-method`
sono `append()`/`replaceChildren()` con nodi creati da `createElement`+`textContent`, non stringhe HTML.
I selettori sono costanti (`[data-engine-tab]`, `URLSearchParams(location.search)`).
Le 3 regex-dos sono pattern letterali (`/^\/chat\/index\.php\?/`, ecc.).

## Finding reali corretti (20 item su 5 file)

| File | Righe | Item | Problema | Soluzione |
|---|---|---:|---|---|
| `vari/anteprimagml.php` | 12 | 1 | `GetTitolo()` restituisce testo GML grezzo da POST, inserito in `<title>` | `htmlspecialchars(..., ENT_QUOTES\|ENT_SUBSTITUTE, 'UTF-8')` |
| `organizzazioni/anteprima.php` | 11 | 1 | stesso problema | stessa soluzione |
| `admin/log.php` | 144, 149, 170 | 3 | `converti()` inseriva etichette DB e argomenti `javascript:openProfile*()` senza escape | escape HTML delle etichette + `json_encode` con `JSON_HEX_*` per gli argomenti JS |
| `js/ricerca.js` | 78, 84 | 2 | `src`/`title`/`alt` con dati API (`imgorg`, `imgcorp`, `org`, `corp`, `araldica`) non escapati | helper `esc()` applicato a tutti i campi, `Number()` sull'id |
| `js/trasferimenti.js` | 201, 204, 207, 209, 211, 232-234, 240, 251 | 13 | `nomeobj`, `interlocutore`, `data`, chiavi magazzino interpolate grezze; id in callback inline non tipizzati | `commerceEscape()` sui campi testuali, `Number()` su id/quantità/prezzo |

Impatto: solo output encoding e coercizione numerica. Nessuna query, nessuna autorizzazione, nessun
comportamento di business modificato. Le pagine interessate sono anteprime GML, registro log amministrativo,
ricerca personaggi e pannello trasferimenti.

## File saltati per conflitto con altro agente

4 item, tutti su `sessione/viewlog.php` (righe 31, 65, 66, 75):
`PHPCS_Generic_PHP_DisallowRequestSuperglobal` (1) e `tainted-filename` (3).
Il file è in modifica da `ses_e005868a7` per i 22 BLOCKER Sonar. Nessun altro file della lista di conflitto
(`equip/showdetails.php`, `organizzazioni/showstatuto.php`, `libs/mysql.php`, `ricerca/index.php`,
`templates/search/index.php`, `css/theme/maps.css`) contiene item Codacy OPEN. Nessun file in conflitto è stato
toccato: lavoro eseguito in worktree dedicato su `origin/main`.

## Verifiche eseguite

```
php -l vari/anteprimagml.php                 -> No syntax errors detected
php -l organizzazioni/anteprima.php          -> No syntax errors detected
php -l admin/log.php                         -> No syntax errors detected
node --input-type=module --check js/ricerca.js       -> OK
node --input-type=module --check js/trasferimenti.js -> OK
python tests/syntax.py                       -> PASS syntax: 620 PHP and JavaScript files
php vendor/bin/phpunit                       -> OK (8 tests, 25 assertions)
python tests/release_audit.py                -> PASS release audit: 901 source files
php vendor/bin/phpstan                       -> NON VERIFICATO: non installato nel repo
```

Configurazione PHPUnit: `phpunit.xml.dist`, PHPUnit 12.5.45, PHP 8.6.0.
Nessuna soppressione, nessun test saltato, nessuna soglia modificata.
