<?php
declare(strict_types=1);

const MAX_BODY_BYTES = 16384;

load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Metodo no permitido.']);
}

$requestBody = read_request_body();
$submissionId = bin2hex(random_bytes(16));

if (!is_allowed_origin()) {
    respond(403, ['ok' => false, 'message' => 'No se pudo enviar el mensaje desde este origen.']);
}

[$submission, $errors, $validationDetails] = validate_submission($requestBody);

if ($errors !== []) {
    respond(400, [
        'ok' => false,
        'message' => $errors['form'] ?? 'Revisa los campos marcados e intentalo de nuevo.',
        'errors' => $errors,
        'details' => [
            'reason' => 'validation_failed',
            'summary' => 'The server received the form, but one or more fields did not pass validation.',
            'receivedFields' => array_keys($requestBody),
            'fieldDetails' => $validationDetails,
        ],
    ]);
}

if (is_rate_limited()) {
    respond(429, [
        'ok' => false,
        'message' => 'Has enviado varios mensajes en poco tiempo. Intentalo de nuevo en unos minutos.',
    ]);
}

if (!has_email_config()) {
    error_log('Good Meals contact form email configuration is missing: ' . $submissionId);
    respond(503, [
        'ok' => false,
        'message' => 'El formulario no esta disponible ahora mismo. Intentalo de nuevo mas tarde.',
    ]);
}

$submittedAt = gmdate('c');

try {
    send_php_mail(build_business_email($submission, $submittedAt));
    send_php_mail(build_confirmation_email($submission));

    $emailDomain = substr(strrchr($submission['email'], '@') ?: '@unknown', 1);
    error_log('Good Meals contact form emails sent: ' . $submissionId . ' domain=' . $emailDomain);

    respond(200, [
        'ok' => true,
        'message' => 'Mensaje recibido. Te responderemos pronto.',
    ]);
} catch (Throwable $error) {
    error_log('Good Meals contact form email delivery failed: ' . $submissionId);
    respond(502, [
        'ok' => false,
        'message' => 'No pudimos enviar el mensaje ahora mismo. Intentalo de nuevo en unos minutos.',
    ]);
}

function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ((starts_with($value, '"') && ends_with($value, '"')) || (starts_with($value, "'") && ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

function env_value(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function read_request_body(): array
{
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > MAX_BODY_BYTES) {
        respond(413, ['ok' => false, 'message' => 'El mensaje es demasiado largo.']);
    }

    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (strpos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
        if ($rawBody === false || strlen($rawBody) > MAX_BODY_BYTES) {
            respond(413, ['ok' => false, 'message' => 'El mensaje es demasiado largo.']);
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            respond(400, ['ok' => false, 'message' => 'No se pudo leer el formulario.']);
        }

        return $decoded;
    }

    return $_POST;
}

function normalize_text($value, int $maxLength): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    return substr($value, 0, $maxLength);
}

function normalize_message($value, int $maxLength): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = str_replace("\r\n", "\n", $value);
    $value = preg_replace('/[ \t]+/', ' ', $value) ?? '';
    $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? '';
    return substr(trim($value), 0, $maxLength);
}

function validate_submission(array $body): array
{
    $rawFields = [
        'name' => $body['name'] ?? null,
        'email' => $body['email'] ?? null,
        'phone' => $body['phone'] ?? null,
        'message' => $body['message'] ?? null,
    ];

    $submission = [
        'name' => normalize_text($rawFields['name'], 100),
        'email' => strtolower(normalize_text($rawFields['email'], 254)),
        'phone' => normalize_text($rawFields['phone'], 40),
        'message' => normalize_message($rawFields['message'], 2000),
    ];

    $errors = [];
    $details = [
        'name' => field_detail($rawFields['name'], $submission['name'], true, 2, 100, 'text'),
        'email' => field_detail($rawFields['email'], $submission['email'], true, null, 254, 'email'),
        'phone' => field_detail($rawFields['phone'], $submission['phone'], true, 6, 40, 'phone'),
        'message' => field_detail($rawFields['message'], $submission['message'], true, 10, 2000, 'message'),
    ];

    if (strlen($submission['name']) < 2) {
        $errors['name'] = 'Indica tu nombre.';
        $details['name']['failedRule'] = 'minimum_length';
        $details['name']['hint'] = 'Send the field named "name" with at least 2 characters after trimming spaces.';
    }

    if (filter_var($submission['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Indica un email valido.';
        $details['email']['failedRule'] = 'valid_email';
        $details['email']['hint'] = 'Send the field named "email" with a valid email address, for example nombre@example.com.';
    }

    if (preg_match('/^[+()\d\s.-]{6,40}$/', $submission['phone']) !== 1) {
        $errors['phone'] = 'Indica un telefono valido.';
        $details['phone']['failedRule'] = 'phone_format';
        $details['phone']['hint'] = 'Send the field named "phone" with 6 to 40 characters. Allowed characters: numbers, spaces, +, parentheses, dots and hyphens.';
    }

    if (strlen($submission['message']) < 10) {
        $errors['message'] = 'Cuentanos un poco mas para poder ayudarte.';
        $details['message']['failedRule'] = 'minimum_length';
        $details['message']['hint'] = 'Send the field named "message" with at least 10 characters after trimming spaces. If you typed a longer message, check that the textarea still has name="message".';
    }

    return [$submission, $errors, $details];
}

function field_detail($rawValue, string $normalizedValue, bool $required, ?int $minLength, int $maxLength, string $type): array
{
    return [
        'required' => $required,
        'type' => $type,
        'received' => is_string($rawValue),
        'emptyAfterTrim' => $normalizedValue === '',
        'normalizedLength' => strlen($normalizedValue),
        'minLength' => $minLength,
        'maxLength' => $maxLength,
    ];
}

function is_allowed_origin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin === '') {
        return true;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);

    if ($originHost === null) {
        return false;
    }

    $allowedHosts = [
        parse_url((string)env_value('SITE_URL', ''), PHP_URL_HOST),
        normalize_host($_SERVER['HTTP_HOST'] ?? ''),
        normalize_host($_SERVER['SERVER_NAME'] ?? ''),
        'localhost',
        '127.0.0.1',
    ];

    $extraOrigins = array_filter(array_map('trim', explode(',', (string)env_value('ALLOWED_ORIGINS', ''))));
    foreach ($extraOrigins as $extraOrigin) {
        $allowedHosts[] = parse_url(starts_with($extraOrigin, 'http') ? $extraOrigin : 'https://' . $extraOrigin, PHP_URL_HOST);
    }

    $allowedHosts = array_map('normalize_host', array_filter($allowedHosts));

    return in_array(normalize_host($originHost), array_unique(array_filter($allowedHosts)), true);
}

function normalize_host(string $host): string
{
    return strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
}

function is_rate_limited(): bool
{
    $windowMs = (int)(env_value('CONTACT_RATE_LIMIT_WINDOW_MS', '900000'));
    $maxAttempts = (int)(env_value('CONTACT_RATE_LIMIT_MAX', '5'));
    $windowSeconds = max(60, (int)ceil($windowMs / 1000));
    $now = time();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $ip);
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'good-meals-contact-rate.json';
    $store = [];

    $handle = fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }

    flock($handle, LOCK_EX);
    $contents = stream_get_contents($handle);
    if (is_string($contents) && $contents !== '') {
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) {
            $store = $decoded;
        }
    }

    foreach ($store as $storedKey => $entry) {
        if (!is_array($entry) || (int)($entry['resetAt'] ?? 0) <= $now) {
            unset($store[$storedKey]);
        }
    }

    $entry = $store[$key] ?? ['count' => 0, 'resetAt' => $now + $windowSeconds];
    if ((int)$entry['resetAt'] <= $now) {
        $entry = ['count' => 0, 'resetAt' => $now + $windowSeconds];
    }

    $entry['count'] = (int)$entry['count'] + 1;
    $store[$key] = $entry;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($store));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $entry['count'] > $maxAttempts;
}

function has_email_config(): bool
{
    return filter_var(env_value('BUSINESS_EMAIL'), FILTER_VALIDATE_EMAIL) !== false
        && extract_email_address((string)env_value('EMAIL_FROM')) !== null;
}

function html_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function build_business_email(array $submission, string $submittedAt): array
{
    $safeName = html_escape($submission['name']);
    $safeEmail = html_escape($submission['email']);
    $safePhone = html_escape($submission['phone']);
    $safeMessage = nl2br(html_escape($submission['message']));
    $safeSubmittedAt = html_escape($submittedAt);

    return [
        'from' => env_value('EMAIL_FROM'),
        'to' => env_value('BUSINESS_EMAIL'),
        'reply_to' => $submission['email'],
        'subject' => 'Nuevo mensaje de ' . $submission['name'] . ' | Good Meals',
        'text' => implode("\n", [
            'Nuevo mensaje desde Good Meals',
            '',
            'Nombre: ' . $submission['name'],
            'Email: ' . $submission['email'],
            'Telefono: ' . $submission['phone'],
            'Fecha: ' . $submittedAt,
            '',
            'Mensaje:',
            $submission['message'],
        ]),
        'html' => "
            <h1>Nuevo mensaje desde Good Meals</h1>
            <p><strong>Nombre:</strong> {$safeName}</p>
            <p><strong>Email:</strong> {$safeEmail}</p>
            <p><strong>Telefono:</strong> {$safePhone}</p>
            <p><strong>Fecha:</strong> {$safeSubmittedAt}</p>
            <p><strong>Mensaje:</strong></p>
            <p>{$safeMessage}</p>
        ",
    ];
}

function build_confirmation_email(array $submission): array
{
    $safeName = html_escape($submission['name']);

    return [
        'from' => env_value('EMAIL_FROM'),
        'to' => $submission['email'],
        'reply_to' => env_value('BUSINESS_EMAIL'),
        'subject' => 'Hemos recibido tu mensaje | Good Meals',
        'text' => implode("\n", [
            'Hola ' . $submission['name'] . ',',
            '',
            'Gracias por contactar con Good Meals. Hemos recibido tu mensaje y te responderemos pronto con la informacion que necesitas.',
            '',
            'Un saludo,',
            'El equipo de Good Meals',
        ]),
        'html' => "
            <p>Hola {$safeName},</p>
            <p>Gracias por contactar con Good Meals. Hemos recibido tu mensaje y te responderemos pronto con la informacion que necesitas.</p>
            <p>Un saludo,<br>El equipo de Good Meals</p>
        ",
    ];
}

function send_php_mail(array $email): void
{
    $to = sanitize_header((string)$email['to']);
    $subject = sanitize_header((string)$email['subject']);
    $from = sanitize_header((string)$email['from']);
    $replyTo = sanitize_header((string)($email['reply_to'] ?? $from));
    $fromEmail = extract_email_address($from);
    $replyToEmail = extract_email_address($replyTo);
    $envelopeFrom = extract_email_address((string)env_value('MAIL_ENVELOPE_FROM', '')) ?? $fromEmail;
    $html = (string)$email['html'];

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || $subject === '' || $fromEmail === null || $envelopeFrom === null) {
        throw new RuntimeException('Email configuration error');
    }

    $messageIdHost = normalize_host((string)(parse_url((string)env_value('SITE_URL', ''), PHP_URL_HOST) ?: ($_SERVER['SERVER_NAME'] ?? 'goodmeals.es')));
    $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $messageIdHost !== '' ? $messageIdHost : 'goodmeals.es');

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from,
        'Reply-To: ' . ($replyToEmail ?? $fromEmail),
        'Return-Path: ' . $envelopeFrom,
        'Message-ID: ' . $messageId,
        'Date: ' . date(DATE_RFC2822),
        'X-Mailer: PHP/' . phpversion(),
    ];

    $parameters = '';
    if (stripos(PHP_OS, 'WIN') !== 0) {
        $parameters = '-f' . escapeshellarg($envelopeFrom);
    }

    $accepted = $parameters !== ''
        ? mail($to, $subject, $html, implode("\r\n", $headers), $parameters)
        : mail($to, $subject, $html, implode("\r\n", $headers));

    if (!$accepted) {
        throw new RuntimeException('PHP mail failed');
    }
}

function sanitize_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function extract_email_address(string $value): ?string
{
    $value = sanitize_header($value);

    if (preg_match('/<([^>]+)>/', $value, $matches) === 1) {
        $value = trim($matches[1]);
    }

    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
}

function starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function ends_with(string $value, string $suffix): bool
{
    if ($suffix === '') {
        return true;
    }

    return substr($value, -strlen($suffix)) === $suffix;
}

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}
