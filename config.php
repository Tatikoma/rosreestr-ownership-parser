<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'init.php';

if(is_readable(__DIR__ . DIRECTORY_SEPARATOR . 'config.local.php')){
    return include __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
}

return [
    'db' => 'egrn.db', // произвольное имя, файл с данными будет в папке runtime
    'region' => 'Москва', // регион по ФГИС ЕГРН
    'rosreestr_key' => '00000000-0000-0000-0000-000000000000', // API ключ Росреестра
    'anticaptcha_key' => '00000000000000000000000000000000', // API ключ anti-captcha.com
    'rosreestr_interval' => 5*60 + 31, // пауза между запросами в росреестр, в секундах
    'rosreestr_hang_timer' => 86400 * 2, // через двое суток переставать ждать ответа на выписку
    'python' => 'C:/python/python3.exe', // путь до исполняемого файла python
    // https://github.com/0-6-1-7/rosreestr
    'captcha_solver' => 'C:/path/to/rosreestr/CaptchaServer/cgi-bin/captcha.py', // полный путь до файла
    'captcha_method' => 'captcha_solver', // captcha_solver (бесплатно) или anticaptcha (платно)
];