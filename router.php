<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$blocked = [
    '/.env',
    '/.env.example',
    '/.gitignore',
    '/package.json',
    '/router.php',
];

if (in_array($path, $blocked, true) || substr($path, 0, 6) === '/.git/') {
    http_response_code(404);
    echo 'Not found';
    return true;
}

return false;
