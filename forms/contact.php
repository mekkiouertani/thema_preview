<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';

// carico configurazione SMTP: C:\MAMP\htdocs\thema\config.mail.php
$config = require __DIR__ . '/../../config.mail.php';

// accetto solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Metodo non valido.';
  exit;
}

// leggo i campi del form
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$message = trim($_POST['message'] ?? '');

// tutti i campi sono obbligatori
if ($name === '' || $email === '' || $phone === '' || $message === '') {
  echo 'Compila tutti i campi obbligatori.';
  exit;
}

// normalizza il nome: rimuove spazi doppi e mette le iniziali maiuscole
if ($name !== '') {
  // sostituisce spazi multipli con un solo spazio
  $name = preg_replace('/\s+/', ' ', $name);

  // "marco rossi" -> "Marco Rossi" in UTF-8
  $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}

// validazione email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo 'Inserisci un indirizzo email valido.';
  exit;
}

// sanitizzo per l'HTML della mail
$safeName    = htmlspecialchars($name,    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmail   = htmlspecialchars($email,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safePhone   = htmlspecialchars($phone,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
$sentAt      = date('d/m/Y H:i');

$mail = new PHPMailer(true);

try {
  // Config SMTP (Mailtrap)
  $mail->isSMTP();
  $mail->Host       = $config['smtp_host'];
  $mail->SMTPAuth   = true;
  $mail->Username   = $config['smtp_user'];
  $mail->Password   = $config['smtp_pass'];
  $mail->Port       = $config['smtp_port'];

  if ((int) $config['smtp_port'] === 465) {
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  } else {
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  }

  $mail->CharSet = 'UTF-8';

  // from_email nel config, altrimenti riuso to_email
  $fromEmail = $config['from_email'] ?? $config['to_email'];

  $mail->setFrom($fromEmail, 'Sito THEMA');
  $mail->addAddress($config['to_email']);

  $mail->Subject = 'Nuova richiesta dal sito THEMA';

  // corpo HTML “carino”
  $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Nuova richiesta dal sito THEMA</title>
  <style>
    body {
      margin: 0;
      padding: 16px;
      background: #f3f4f6;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: #111827;
    }
    .wrapper {
      max-width: 640px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      padding: 24px 24px 20px;
    }
    h1 {
      font-size: 20px;
      margin: 0 0 8px;
      color: #111827;
    }
    p {
      margin: 0 0 12px;
      line-height: 1.5;
    }
    .meta {
      margin: 16px 0 8px;
      padding: 12px 16px;
      background: #f9fafb;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
    }
    .meta dt {
      font-weight: 600;
      font-size: 13px;
      color: #6b7280;
      margin-top: 4px;
    }
    .meta dd {
      margin: 0 0 4px;
      font-size: 14px;
      color: #111827;
    }
    .message-box {
      margin-top: 16px;
      padding: 16px;
      border-radius: 8px;
      background: #f3f4ff;
      border: 1px solid #e0e7ff;
      font-size: 14px;
      line-height: 1.6;
      white-space: pre-wrap;
    }
    .footer {
      margin-top: 20px;
      font-size: 11px;
      color: #9ca3af;
      text-align: center;
    }
    a {
      color: #1d4ed8;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <h1>Nuova richiesta di contatto – THEMA S.r.l.</h1>
    <p>Hai ricevuto un nuovo messaggio dal form di contatto del sito THEMA.</p>

    <dl class="meta">
      <dt>Nome e cognome</dt>
      <dd>{$safeName}</dd>

      <dt>Email</dt>
      <dd><a href="mailto:{$safeEmail}">{$safeEmail}</a></dd>

      <dt>Telefono</dt>
      <dd><a href="tel:{$safePhone}">{$safePhone}</a></dd>

      <dt>Data invio</dt>
      <dd>{$sentAt}</dd>
    </dl>

    <div class="message-box">
      {$safeMessage}
    </div>

    <div class="footer">
      Questa email è stata generata automaticamente dal sito THEMA S.R.L.<br>
      Puoi rispondere direttamente al mittente utilizzando l’indirizzo indicato sopra.
    </div>
  </div>
</body>
</html>
HTML;

  $mail->isHTML(true);
  $mail->Body    = $htmlBody;
  $mail->AltBody = "Nuova richiesta dal sito THEMA\n\n"
    . "Nome: {$name}\n"
    . "Email: {$email}\n"
    . "Telefono: {$phone}\n"
    . "Data invio: {$sentAt}\n\n"
    . "Messaggio:\n{$message}\n";

  $mail->send();

  // php-email-form/validate.js considera "OK" come successo
  echo 'OK';
} catch (Exception $e) {
  error_log('Errore invio email contatto: ' . $mail->ErrorInfo);
  echo 'Si è verificato un errore durante l\'invio del messaggio. Riprova più tardi.';
}
