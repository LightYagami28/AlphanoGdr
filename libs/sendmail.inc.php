<?php
declare(strict_types=1);
const SENDMAIL_INC_MAIL = '[mail] ';

// Carica il tuo SMTPMailer (mettilo accanto a questo file)
require_once __DIR__ . '/kernel.inc.php';
require_once __DIR__ . '/SMTPMailer.php';
require_once __DIR__.'/email-theme.inc.php';

function alphanoMailer(): SMTPMailer
{
    return new SMTPMailer(
        host: alphanoConfig('smtp_host'), port: alphanoConfig('smtp_port'),
        tls: alphanoConfig('smtp_tls'), user: alphanoConfig('smtp_user'), pass: alphanoConfig('smtp_pass')
    );
}


/**
 * Invia la mail di benvenuto con password al nuovo utente
 *
 * @param string $email    Email destinatario
 * @param string $nome     Nome del personaggio
 * @param string $frase    Frase epica (non usata nel corpo)
 * @param string $password La password in chiaro
 * @return bool
 */
/**
 * Invia la mail di recupero password
 *
 * @param string $email
 * @param string $nome
 * @param string $frase
 * @param string $password
 * @return bool
 */
function sendMail(string $email,string $nome):bool {
 try{$replace=['{name}'=>$nome,'{game}'=>(string)alphanoConfig('game_name'),'{url}'=>(string)alphanoConfig('base_url')];$subject=strtr((string)alphanoConfig('welcome_subject'),$replace);$body=strtr((string)alphanoConfig('welcome_body'),$replace);return alphanoMailer()->send((string)alphanoConfig('mail_from'),$email,$subject,$body,alphanoEmailRender($subject,$body,'welcome'));}catch(Throwable $e){error_log('[welcome email] '.$e->getMessage());return false;}
}

/**
 * Invia la mail quando viene rigenerata una nuova password
 *
 * @param string $email
 * @param string $nome
 * @param string $frase
 * @param string $password
 * @return bool
 */
function sendNewPass(string $email, string $nome): bool
{
    // Legacy callers now receive a reset link; never send a reusable password.
    $db=connectDB();$user=$db->query('SELECT id FROM utenti WHERE email=? AND nome=?',[$email,$nome])[0]??null;
    if(!$user){ return false; }$token=bin2hex(random_bytes(32));
    $db->execute('REPLACE INTO password_resets(pg,token_hash,created_at,expires_at) VALUES(?,?,?,?)',[(int)$user['id'],hash('sha256',$token),time(),time()+3600]);
    return sendPasswordReset($email,$nome,$token);
}

/**
 * Notifica un giocatore che il suo personaggio è stato cancellato
 *
 * @param array $value Assoc array con chiavi: email, nome, ultimoingresso, id
 * @return void
 */
function sendDelAdvice(array $value): void
{
    $email = $value['email'] ?? '';
    $nome  = $value['nome']  ?? '';
    $id    = (int) ($value['id'] ?? 0);
    $last  = (int) ($value['ultimoingresso'] ?? 0);
    $path='/main.php';

    $mailer = alphanoMailer();

    $from    = alphanoConfig('mail_from');
    $subject = alphanoConfig('game_name').': Il tuo personaggio è stato cancellato';
    $body    = "{$nome} è stato cancellato per inattività.";

    if ($last > 0 && $id > 0) {
        $expiry = date('d/m/Y', time() + 7 * 24 * 3600);
        $token = bin2hex(random_bytes(32));
        connectDB()->execute('UPDATE recuperopg SET token_hash = ? WHERE id = ?', [hash('sha256', $token), $id]);
        $path="/recuperopg.php?identity={$id}@{$token}";$link=alphanoEmailUrl($path);

        $body .= "\n\nSono passati 60 giorni dalla tua ultima visita e il tuo personaggio è stato rimosso.";
        $body .= "\nSe vuoi ripristinarlo, segui questo link entro il {$expiry}:\n{$link}";
        $body .= "\n\nNota: eventuali privilegi amministrativi o cariche sono stati revocati.";
    }

    try { $mailer->send($from, $email, $subject, $body,alphanoEmailRender($subject,$body,'alerts',$path)); }
    catch (Throwable $e) { error_log(SENDMAIL_INC_MAIL . $e->getMessage()); }
}

/**
 * Invia il link di attivazione bug
 *
 * @param string $email
 * @param string $codice
 * @return bool
 */
function sendBugActivation(string $email, string $codice): bool
{
    $mailer = alphanoMailer();

    $from    = alphanoConfig('mail_from');
    $subject = 'Attivazione segnalazione bug per '.alphanoConfig('game_name');
    $link = rtrim(alphanoConfig('base_url'), '/') . '/bug/?id=' . rawurlencode($codice);
    $gameName=(string)alphanoConfig('game_name');
    $body    = <<<EOT
Grazie per aver segnalato un bug su {$gameName}!

Per attivare la tua segnalazione, apri questo link:
{$link}

—
La gestione del gioco
EOT;

    try { return $mailer->send($from, $email, $subject, $body,alphanoEmailRender($subject,$body,'alerts','/bug/?id='.rawurlencode($codice))); }
    catch (Throwable $e) { error_log(SENDMAIL_INC_MAIL . $e->getMessage()); return false; }
}

/** Il link permette al titolare della casella di scegliere la nuova password. */
function sendPasswordReset(string $email, string $name, string $token): bool
{
    $link = rtrim(alphanoConfig('base_url'), '/') . '/recuperopasswd.php?token=' . rawurlencode($token);
    $body = "Ciao {$name},\n\nper scegliere una nuova password apri questo collegamento entro un'ora:\n{$link}\n\nSe non hai chiesto il recupero, puoi ignorare questa email.\nLa gestione del gioco";
    try { return alphanoMailer()->send(alphanoConfig('mail_from'), $email, 'Recupero password '.alphanoConfig('game_name'), $body,alphanoEmailRender('Recupero password '.alphanoConfig('game_name'),$body,'security','/recuperopasswd.php?token='.rawurlencode($token))); }
    catch (Throwable $exception) { error_log(SENDMAIL_INC_MAIL . $exception->getMessage()); return false; }
}
