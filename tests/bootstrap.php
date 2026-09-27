<?php
declare(strict_types=1);

if (!defined('IN_PHPBB')) {
    define('IN_PHPBB', true);
}

if (is_file(__DIR__ . '/../vendor_dev/autoload.php')) {
    require_once __DIR__ . '/../vendor_dev/autoload.php';
} elseif (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$phpbbRoot = dirname(__DIR__, 4) . '/';
if (is_file($phpbbRoot . 'vendor/autoload.php')) {
    require_once $phpbbRoot . 'vendor/autoload.php';
}

if (is_file($phpbbRoot . 'includes/constants.php')) {
    require_once $phpbbRoot . 'includes/constants.php';
}

if (!function_exists('generate_board_url')) {
    function generate_board_url($without_script_path = false) {
        if (!empty($GLOBALS['test_board_url'])) {
            return $GLOBALS['test_board_url'];
        }
        return 'https://localhost/phpbb';
    }
}

if (!function_exists('utf8_htmlspecialchars')) {
    function utf8_htmlspecialchars($value) {
        return htmlspecialchars((string) $value, ENT_COMPAT, 'UTF-8');
    }
}

if (!function_exists('unique_id')) {
    function unique_id($extra = 'c') {
        return md5(uniqid((string) mt_rand(), true));
    }
}

if (!function_exists('check_form_key')) {
    function check_form_key($form_name, $timespan = -1) {
        global $config, $user, $request;
        if (!isset($request)) {
            return false;
        }
        $creation_time = $request->variable('creation_time', 0);
        $token = $request->variable('form_token', '');
        if (!$creation_time || !$token) {
            return false;
        }
        $expected = sha1($creation_time . ($user->data['user_form_salt'] ?? '') . $form_name);
        return hash_equals($expected, $token);
    }
}

spl_autoload_register(function (string $class) use ($phpbbRoot): void {
    if (str_starts_with($class, 'phpbb\\')) {
        $file = $phpbbRoot . 'phpbb/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
