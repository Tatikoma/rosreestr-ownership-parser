<?php


if(is_readable('rosreestr-apikey.txt')){
    $apiKey = file_get_contents('anticaptcha-apikey.txt');
}
else{
    $apiKey = readline('Please enter rosreestr.ru API Key: ');
}

$cityName = 'Москва';

$baseURL = 'https://rosreestr.ru';

require_once __DIR__ . '/anticaptcha.php';
require_once __DIR__ . '/imagetotext.php';

if(is_readable('anticaptcha-apikey.txt')){
    $anticaptchaKey = file_get_contents('anticaptcha-apikey.txt');
}
else{
    $anticaptchaKey = readline('Please enter AntiCaptcha.com API Key: ');
}
$api = new ImageToText();
$api->setVerboseMode(true);
$api->setKey($anticaptchaKey);
$api->setNumericFlag(true);

$solveCaptcha = function($content) use($api){
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

$ch = curl_init();
curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8080');
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/79.0.3945.79 Chrome/79.0.3945.79 Safari/537.36');
curl_setopt($ch, CURLOPT_ENCODING, 'deflate, gzip');
//curl_setopt($ch, CURLOPT_COOKIEFILE, 'php://memory');
//curl_setopt($ch, CURLOPT_COOKIEJAR, 'php://memory');
//curl_setopt($ch, CURLOPT_COOKIEFILE, 'ir_egrn.cookie.tmp');
//curl_setopt($ch, CURLOPT_COOKIEJAR, 'ir_egrn.cookie.tmp');
curl_setopt($ch, CURLOPT_COOKIE, 'DigestTracker=AAABcCCV0tM; __utmc=224553113; __utmz=224553113.1581081094.1.1.utmcsr=google|utmccn=(organic)|utmcmd=organic|utmctr=(not%20provided); PHPSESSID=20158d03462ae86e677dc3cf564b04d2; BITRIX_SM_CONTRAST=Y; _ym_uid=1581081160487965584; _ym_d=1581081160; _ga=GA1.2.1149076744.1581081094; _gid=GA1.2.1273251918.1581081160; _ym_isad=1; JSESSIONID_8=0000DDmqsGrBMAdhk8TbRcBT-Yg:19ct8hikv; __utma=224553113.1149076744.1581081094.1581090219.1581094002.4; _ym_visorc_18809125=w; __utmt=1; __utmb=224553113.7.10.1581094002');

curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_URL, $baseURL . '/wps/portal/p/cc_present/ir_egrn');

do {
    $result = curl_exec($ch);
    $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if($responseCode >= 500){
        // @todo limit retry
        print "Request failed, error {$responseCode}. Retry\n";
    }
}
while($responseCode >= 500);
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
    'curDate' => time() . random_int(100,999),
    'wsver' => $appConfig['versionInfo']['vaadinVersion'],
]);

$portletFullURL = $baseURL . preg_replace('#(?<=!!/).+#', $appConfig['portletUidlURLBase'], $location);

function vaadinQuery($uri, $data){
    global $ch, $portletFullURL;

    print $data . "\n";

    curl_setopt($ch, CURLOPT_URL, $url = $portletFullURL . '?' . $uri);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/plain;charset=UTF-8',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // GS, group separator??
    do {
        $result = curl_exec($ch);
        $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($responseCode >= 500){
            // @todo limit retry
            print "Request failed, error {$responseCode}. Retry\n";
        }
    }
    while($responseCode >= 500);
    $result = str_replace('for(;;);', '', $result);
    $result = json_decode($result, 1);
    if(!is_array($result)){
        throw new \RuntimeException('Failed to execute Vaadin query');
    }
    if(isset($result[0]['meta']['appError'])){
        throw new RuntimeException($result[0]['meta']['appError']['caption']);
    }
    return $result;
}

function vaadinSetFieldText($field, $text){
    global $appLogin;

    $textLength = strlen($text);
    vaadinQuery('windowName=1',
        //"{$appLogin['Vaadin-Security-Key']}\x1D{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
        //"{$appLogin['Vaadin-Security-Key']}\x1D977\x1FPID0\x1Fheight\x1Fi\x1E755\x1FPID0\x1Fwidth\x1Fi\x1E1905\x1FPID0\x1FbrowserWidth\x1Fi\x1E862\x1FPID0\x1FbrowserHeight\x1Fi\x1E{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
        "{$appLogin['Vaadin-Security-Key']}\x1D{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
    );
}

function vaadinClickButton($field){
    global $appLogin;
    return vaadinQuery('windowName=1',
        "{$appLogin['Vaadin-Security-Key']}\x1Dtrue\x1F{$field}\x1Fstate\x1Fb\x1E1,0,0,false,false,false,false,1,30,17\x1F{$field}\x1Fmousedetails\x1Fs"
    );

}

function findKeyByCaption(array $data, $caption){
    foreach($data as $k => $v){
        if(is_array($v)){
            try{
                return findKeyByCaption($v, $caption);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
        elseif($k === 'caption' && $v === $caption){
            return $data['key'];
        }
    }
    throw new RuntimeException("Button {$caption} not found!");
}

function findButtonByCaption(array $data, $caption){
    foreach($data as $k => $v){
        if(is_array($v)){
            try{
                return findButtonByCaption($v, $caption);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
        elseif($k === 'caption' && $v === $caption){
            return $data['id'];
        }
    }
    throw new RuntimeException("Button {$caption} not found!");
}

function findButtonByPrompt(array $data, $caption){
    foreach($data as $k => $v){
        if(is_array($v)){
            try{
                return findButtonByPrompt($v, $caption);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
        elseif($k === 'prompt' && $v === $caption){
            return $data['id'];
        }
    }
    throw new RuntimeException("Button {$caption} not found!");
}

function findFieldByStyle(array $data, $style){
    foreach($data as $k => $v){
        if(is_array($v)){
            try{
                return findFieldByStyle($v, $style);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
        elseif($k === 'style' && $v === $style){
            return $data['id'];
        }
    }
    throw new RuntimeException("Button {$style} not found!");
}

function findSrcByContentType(array $data, $contentType){
    foreach($data as $k => $v){
        if(is_array($v)){
            try{
                return findSrcByContentType($v, $contentType);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
        elseif($k === 'mimetype' && $v === $contentType){
            return $data['src'];
        }
    }
    throw new RuntimeException("Src {$contentType} not found!");
}

function findFocusedField(array $data){
    foreach($data as $k => $v){
        if(is_array($v)){
            try{
                return findFocusedField($v);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
        elseif($k === 'focused'){
            return $v;
        }
    }
    throw new RuntimeException('Focused field not found!');
}

function findFieldWithEvent(array $data, $event){
    foreach($data as $k => $v){
        if($k === 'eventListeners' && is_array($v) && in_array($event, $v)){
            return $data['id'];
        }
        elseif(is_array($v)){
            try{
                return findFieldWithEvent($v, $event);
            }
            catch(\Throwable $exception){
                // it's ok, do nothing
            }
        }
    }
    throw new RuntimeException("Event {$event} not found!");
}

$appLogin = vaadinQuery($repaintOptions, "init\x1D")[0];
print "AppInit request ok\n";
$result = $appLogin;

$isAuthFormAppeared = false;
try {
    $field = findFocusedField($appLogin);
    $isAuthFormAppeared = true;
}
catch(\Throwable $exception){
    // it's ok
    print "Already logged in\n";
}

if($isAuthFormAppeared) {
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


$searchFormData = vaadinClickButton(findButtonByCaption($result, 'Запрос по правообладателю'));

$searchFormData = vaadinClickButton(findButtonByCaption($searchFormData, 'Поиск объектов недвижимости'));
print "Find property form ok\n";

// fill search fields
vaadinSetFieldText(findButtonByPrompt($searchFormData, 'Кадастровый номер'), '77:09:0004006:11445');
print "Cadastral no ok\n";

$regionField = findButtonByPrompt($searchFormData, 'Регион');
$cityList = vaadinQuery('windowName=1',
    "{$appLogin['Vaadin-Security-Key']}\x1D{$cityName}\x1F{$regionField}\x1Ffilter\x1Fs\x1E0\x1F{$regionField}\x1Fpage\x1Fi"
);
$dropDownKey = findKeyByCaption($cityList, $cityName);
vaadinQuery('windowName=1',
    "{$appLogin['Vaadin-Security-Key']}\x1D{$dropDownKey}\x1C\x1F{$regionField}\x1Fselected\x1Fc"
);
print "Region ok\n";

$searchingData = vaadinClickButton(findButtonByCaption($searchFormData, 'Найти'));
print "Form filled and sent\n";

$refreshField = findButtonByCaption($searchingData, 'Поиск объектов недвижимости');
$targetElement = null;
do{
    // @todo limit retry
    usleep(500000);
    $result = vaadinQuery('windowName=1',
        "{$appLogin['Vaadin-Security-Key']}\x1D832\x1F{$refreshField}\x1Fpositionx\x1Fi\x1E404\x1F{$refreshField}\x1Fpositiony\x1Fi"
    );
    try {
        $targetElement = findFieldWithEvent($result, 'itemClick');
        break;
    }
    catch(\Throwable $exception){
        // it's ok: continue polling
    }
}
while(1);

if(!$targetElement){
    throw new RuntimeException('Data was not found!');
}

$result = vaadinQuery('windowName=1',
    "{$appLogin['Vaadin-Security-Key']}\x1D1\x1F{$targetElement}\x1FclickedKey\x1Fs\x1E1\x1F{$targetElement}\x1FclickedColKey\x1Fs\x1E1,572,680,false,false,false,false,8,-1,-1\x1F{$targetElement}\x1FclickEvent\x1Fs\x1Etrue\x1F{$targetElement}\x1FclearSelections\x1Fb\x1E1\x1C\x1F{$targetElement}\x1Fselected\x1Fc"
);


$captchaSrc = $baseURL . preg_replace('#(?<=!!/).+#', findSrcByContentType($result, 'application/octet-stream'), $location);

do {
    curl_setopt($ch, CURLOPT_URL, $captchaSrc . '?refresh=true&time=' . time() . random_int(100, 999));
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        ' image/webp,image/apng,image/*,*/*;q=0.8',
    ]);
    do {
        $captcha = curl_exec($ch);
        $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseCode >= 500 || empty($captcha)) {
            // @todo limit retry
            print "Request failed, error {$responseCode}. Retry\n";
        }
    } while ($responseCode >= 500 || empty($captcha));



    $solvedCaptcha = $solveCaptcha($captcha);
    if(!$solvedCaptcha){
        continue;
    }
    vaadinSetFieldText(findFieldByStyle($result, 'srv-field'), $solvedCaptcha);

    break;
}
while(1);

$result = vaadinClickButton(findButtonByCaption($result, 'Отправить запрос'));
