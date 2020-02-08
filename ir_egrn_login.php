<?php

if (is_readable('rosreestr-apikey.txt')) {
    $apiKey = file_get_contents('rosreestr-apikey.txt');
} else {
    $apiKey = readline('Please enter rosreestr.ru API Key: ');
}

$cityName = 'Москва';

$baseURL = 'https://rosreestr.ru';

require_once __DIR__ . '/anticaptcha.php';
require_once __DIR__ . '/imagetotext.php';

if (is_readable('anticaptcha-apikey.txt')) {
    $anticaptchaKey = file_get_contents('anticaptcha-apikey.txt');
} else {
    $anticaptchaKey = readline('Please enter AntiCaptcha.com API Key: ');
}
$api = new ImageToText();
$api->setVerboseMode(true);
$api->setKey($anticaptchaKey);
$api->setNumericFlag(true);

$solveCaptcha = function ($content) use ($api) {
    $api->setBody($content);
    if (!$api->createTask()) {
        return false;
    }

    if (!$api->waitForResult()) {
        return false;
    } else {
        return $api->getTaskSolution();
    }
};

require_once 'ir_egrn.lib.php';

$ch = curl_init();
//curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8080');
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/79.0.3945.79 Chrome/79.0.3945.79 Safari/537.36');
curl_setopt($ch, CURLOPT_ENCODING, 'deflate, gzip');
//curl_setopt($ch, CURLOPT_COOKIEFILE, 'php://memory');
//curl_setopt($ch, CURLOPT_COOKIEJAR, 'php://memory');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'ir_egrn.cookie.tmp');
curl_setopt($ch, CURLOPT_COOKIEJAR, 'ir_egrn.cookie.tmp');

curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_URL, $baseURL . '/wps/portal/p/cc_present/ir_egrn');

do {
    $result = curl_exec($ch);
    $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($responseCode >= 500) {
// @todo limit retry
        print "Request failed, error {$responseCode}. Retry\n";
    }
} while ($responseCode >= 500);
/*if(!is_readable('ir_egrn.tmp')) {
$result = curl_exec($ch);
file_put_contents('ir_egrn.tmp', $result);
}
else{
$result = file_get_contents('ir_egrn.tmp');
}*/
print "Initial request ok\n";

preg_match('#Content-Location: ([^\n\r]+)#u', $result, $location);
$location = $location[1];

preg_match('#vaadin\.vaadinConfigurations\["(.+?)"\]\s*=\s*(.+?\});#', $result, $vaadin);
$appName = $vaadin[1];
$appConfig = $vaadin[2];
$appConfig = str_replace('\'', '"', $appConfig);
$appConfig = preg_replace('#([a-z]+):#ui', '"\\1":', $appConfig);
$appConfig = json_decode($appConfig, 1);

$repaintOptions = http_build_query([
    'repaintAll' => 1,
    'sh' => 1080, // screen height
    'sw' => 1920, // screen width
    'cw' => 1905, // window width,
    'ch' => 911, // window height
    'vw' => 753, // parent width
    'vh' => 1, // parent height
    'fr' => '',
    'tzo' => -180, // timezone offset,
    'rtzo' => -180, // relative timezone offset
    'dstd' => 0, // daylight
    'dston' => 'false', // daylight
    'curDate' => time() . random_int(100, 999),
    'wsver' => $appConfig['versionInfo']['vaadinVersion'],
]);

$portletFullURL = $baseURL . preg_replace('#(?<=!!/).+#', $appConfig['portletUidlURLBase'], $location);

$appLogin = vaadinQuery($repaintOptions, "init\x1D")[0];
print "AppInit request ok\n";
$result = $appLogin;

$isAuthFormAppeared = false;
try {
    $field = findFocusedField($appLogin);
    $isAuthFormAppeared = true;
} catch (\Throwable $exception) {
// it's ok
    print "Already logged in\n";
}

if ($isAuthFormAppeared) {
    $apiKeyLength = strlen($apiKey);
    vaadinQuery('windowName=1',
        "{$appLogin['Vaadin-Security-Key']}\x1D{$apiKeyLength}\x1F{$field}\x1Fc\x1Fi\x1E{$apiKey}\x1F{$field}\x1FcurText\x1Fs"
    );

    print "API key entered\n";

// click the auth button
    $enterButton = findButtonByCaption($appLogin, 'Войти');
    $result = vaadinClickButton($enterButton);
    if (isset($result[0]['changes'][0][2][4][2][1]['style']) && $result[0]['changes'][0][2][4][2][1]['style'] === 'error') {
        throw new \RuntimeException('Auth failed. Wrong API key?');
    }
    print "API key ok\n";
}