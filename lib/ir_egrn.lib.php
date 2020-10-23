<?php
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "Этот скрипт не должен выполняться напрямую" . PHP_EOL;
    exit;
}

class IR_EGRN{
    protected $rosreestrKey;
    protected $rosreestrInterval;
    protected $anticaptchaKey;
    protected $region;
    protected $anticaptcha;
    protected $captchaMethod;
    protected $python;
    protected $captchaSolver;

    protected function solveCaptcha($content){
        switch($this->captchaMethod){
            case 'captcha_solver':
                $result = shell_exec($x = strtr(':python :script imgData=:img', [
                    ':python' => escapeshellarg($this->python),
                    ':script' => escapeshellarg($this->captchaSolver),
                    ':img' => escapeshellarg(strtr(base64_encode($content), [
                        '+' => '.',
                        '/' => '_',
                        '=' => '-',
                    ])),
                ]));
                $result = preg_replace('#[^0-9]#', '', $result);
                return $result;
                break;
            case 'anticaptcha':
                if(!$this->anticaptcha) {
                    $this->anticaptcha = new ImageToText();
                    $this->anticaptcha->setVerboseMode(true);
                    $this->anticaptcha->setKey($this->anticaptchaKey);
                    $this->anticaptcha->setNumericFlag(true);
                }

                $this->anticaptcha->setBody($content);
                if (!$this->anticaptcha->createTask()) {
                    return false;
                }

                if (!$this->anticaptcha->waitForResult()) {
                    return false;
                }

                return $this->anticaptcha->getTaskSolution();
                break;
            default:
                throw new RuntimeException('Unknown captcha solver method');
                break;
        }
    }

    public function __construct($config){
        $this->rosreestrKey = $config['rosreestr_key'];
        $this->anticaptchaKey = $config['anticaptcha_key'];
        $this->region = $config['region'];
        $this->rosreestrInterval = $config['rosreestr_interval'];
        $this->captchaMethod = $config['captcha_method'];
        $this->python = $config['python'];
        $this->captchaSolver = $config['captcha_solver'];
    }

    protected $baseURL = 'https://rosreestr.gov.ru';
    protected $portletFullURL;
    protected $appLogin;
    protected $location;
    protected $result;
    protected $ch;
    protected $lastQueryTime = 0;

    public $cookieFile = 'ir_egrn.cookie';

    public function login(){
        if($this->ch){
            curl_close($this->ch);
        }
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/79.0.3945.79 Chrome/79.0.3945.79 Safari/537.36');
        curl_setopt($this->ch, CURLOPT_ENCODING, 'deflate, gzip');
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .  'runtime' . DIRECTORY_SEPARATOR . $this->cookieFile);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .  'runtime' . DIRECTORY_SEPARATOR . $this->cookieFile);

        curl_setopt($this->ch, CURLOPT_POST, 0);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_URL, $this->baseURL . '/wps/portal/p/cc_present/ir_egrn');

        do {
            $result = curl_exec($this->ch);
            $responseCode = (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
            if ($responseCode >= 500) {
                var_dump(curl_getinfo($this->ch));
                var_dump(curl_error($this->ch));
                // @todo limit retry
                print "Request failed, error {$responseCode}. Retry\n";
            }
            $isError = false;


            preg_match('#Content-Location: ([^\n\r]+)#u', $result, $location);
            if(isset($location[1])) {
                $this->location = $location[1];
            }
            else{
                $isError = true;
            }

            /** @noinspection RegExpRedundantEscape */
            preg_match('#vaadin\.vaadinConfigurations\["(.+?)"\]\s*=\s*(.+?\});#', $result, $vaadin);
            if(!isset($vaadin[1])){
                $isError = true;
            }

        } while ($responseCode >= 500 || $isError);

        print "Initial request ok\n";


        $appConfig = $vaadin[2];
        $appConfig = str_replace('\'', '"', $appConfig);
        $appConfig = preg_replace('#([a-z]+):#ui', '"\\1":', $appConfig);
        $appConfig = json_decode($appConfig, 1);

        try{
            $random = random_int(100, 999);
        }
        catch(Throwable $exception){
            print "Ошибка! Так быть точно не должно..." . PHP_EOL;
            exit;
        }

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
            'curDate' => time() . $random,
            'wsver' => $appConfig['versionInfo']['vaadinVersion'],
        ]);

        $this->portletFullURL = $this->baseURL . preg_replace('#(?<=!!/).+#', $appConfig['portletUidlURLBase'], $this->location);

        $this->appLogin = $this->vaadinQuery($repaintOptions, "init\x1D")[0];
        $this->result = $this->appLogin;
        print "AppInit request ok\n";

        /** @noinspection PhpUnusedLocalVariableInspection */
        $isAuthFormAppeared = false;
        try {
            $field = $this->findFocusedField($this->appLogin);
            $isAuthFormAppeared = true;
        } catch (Throwable $exception) {
            // it's ok
            print "Already logged in\n";
            return;
        }

        if ($isAuthFormAppeared) {
            $apiKeyLength = strlen($this->rosreestrKey);
            $this->vaadinQuery('windowName=1',
                "{$this->appLogin['Vaadin-Security-Key']}\x1D{$apiKeyLength}\x1F{$field}\x1Fc\x1Fi\x1E{$this->rosreestrKey}\x1F{$field}\x1FcurText\x1Fs"
            );

            print "API key entered\n";

            // click the auth button
            $enterButton = $this->findButtonByCaption($this->appLogin, 'Войти');
            $this->result = $this->vaadinClickButton($enterButton);
            if (isset($this->result[0]['changes'][0][2][4][2][1]['style']) && $this->result[0]['changes'][0][2][4][2][1]['style'] === 'error') {
                throw new RuntimeException('Auth failed. Wrong API key?');
            }
            print "API key ok\n";
        }
    }

    protected function findFieldByText(array $data, $text)
    {
        foreach ($data as $k => $v) {
            if ($k === 'v' && isset($v['text']) && $v['text'] == $text) {
                return $data['id'];
            } elseif (is_array($v)) {
                try {
                    return $this->findFieldByText($v, $text);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            }
        }
        throw new RuntimeException("Text {$text} not found!");
    }

    protected function findFieldBySelectMode(array $data, $mode)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findFieldBySelectMode($v, $mode);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'selectmode' && $v === $mode) {
                return $data['id'];
            }
        }
        throw new RuntimeException("Mode {$mode} not found!");
    }

    protected function findSrcByIcon(array $data, $icon)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findSrcByIcon($v, $icon);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'icon' && $v === $icon) {
                return $data['src'];
            }
        }
        throw new RuntimeException("Src {$icon} not found!");
    }

    public function getResult($queryId){
        $this->login();

        $searchFormData = $this->vaadinClickButton($this->findButtonByCaption($this->result, 'Запрос по правообладателю'));

        try {
            $searchFormData = $this->vaadinClickButton($this->findButtonByCaption($searchFormData, 'Мои заявки'));
        }
        catch(Throwable $exception){
            $searchFormData = $this->vaadinClickButton($this->findButtonByCaption($this->result, 'Мои заявки'));
        }

        $fieldQueryInput = $this->findFieldByText($searchFormData, '');
        $buttonRefresh = $this->findButtonByCaption($searchFormData, 'Обновить');
        $areaField = $this->findFieldBySelectMode($searchFormData, 'single');

        $queryIdLength = strlen($queryId);

        $searchFormData = $this->vaadinQuery('windowName=1',
            "{$this->appLogin['Vaadin-Security-Key']}\x1D1261\x1FPID0\x1Fheight\x1Fi\x1E755\x1FPID0\x1Fwidth\x1Fi\x1E1905\x1FPID0\x1FbrowserWidth\x1Fi\x1E911\x1FPID0\x1FbrowserHeight\x1Fi\x1Etrue\x1F{$areaField}\x1FclearSelections\x1Fb\x1E\x1F{$areaField}\x1Fselected\x1Fc\x1E{$queryId}\x1F{$fieldQueryInput}\x1Ftext\x1Fs\x1E{$queryIdLength}\x1F{$fieldQueryInput}\x1Fc\x1Fi\x1Etrue\x1F{$buttonRefresh}\x1Fstate\x1Fb\x1E1,1176,525,false,false,false,false,1,23,6\x1F{$buttonRefresh}\x1Fmousedetails\x1Fs"
        );

        try {
            $zipSrc = $this->baseURL . preg_replace('#(?<=!!/).+#', $this->findSrcByIcon($searchFormData, 'theme://img/download.png'), $this->location);

            curl_setopt($this->ch, CURLOPT_URL, $zipSrc);
            curl_setopt($this->ch, CURLOPT_POST, 0);
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
                'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
            ]);
            do {
                $zipFile = curl_exec($this->ch);
                $responseCode = (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
                if ($responseCode >= 500 || empty($zipFile)) {
                    // @todo limit retry
                    print "Request failed, error {$responseCode}. Retry\n";
                }
            } while ($responseCode >= 500 || empty($zipFile));

            file_put_contents($fileName = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $queryId . '.zip', $zipFile);
            return $fileName;
        }
        catch(Throwable $exception){
            return null;
        }
    }

    public function parseZipArchive($fileName){
        $zip = new ZipArchive();
        if(!is_readable($fileName)){
            throw new RuntimeException(strtr('File :filename is not readable', [
                ':filename' => $fileName,
            ]));
        }
        if(!$zip->open($fileName)){
            throw new RuntimeException('Failed to open archive!');
        }
        $isZipFound = false;
        $isDataFound = false;
        $data = '';
        $i = 0;
        while(($name = $zip->getNameIndex($i++)) !== false){
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            if($ext == 'zip'){
                $isZipFound = true;
                $data = $zip->getFromIndex($i-1);
                file_put_contents($fileName . '.tmp', $data);
                $zip->close();
                $zip->open($fileName . '.tmp');
                $j = 0;
                while(($name = $zip->getNameIndex($j++)) !== false){
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    if($ext == 'xml'){
                        $isDataFound = true;
                        $data = $zip->getFromIndex($j-1);
                        $zip->close();
                        break 2;
                    }
                }
            }
        }
        @unlink($fileName);
        @unlink($fileName . '.tmp');
        if(!$isZipFound || !$isDataFound || !$data){
            throw new RuntimeException('Broken archive!');
        }

        file_put_contents($fileName . '.xml', $data);
        return $fileName . '.xml';
    }

    /** @noinspection PhpUndefinedFieldInspection */
    public function parseXMLFile($fileName){
        if(!is_readable($fileName)){
            throw new RuntimeException('File ' . $fileName . ' is not readable');
        }

        $data = file_get_contents($fileName);

        $result = new Ownership();
        $result->xml = $data;

        $xml = simplexml_load_string($data);
        $result->cadastralNo = (string)$xml->Realty->Flat['CadastralNumber'];
        $area = (string)$xml->Realty->Flat->Area;
        if($area){
            $area = str_replace(',', '.', $area);
        }
        $result->area = $area;

        foreach($xml->ReestrExtract->ExtractObjectRight->ExtractObject->ObjectRight->Right as $right){
            $result->ownership[] = (string)$right->Registration->Name;
            $ownerNames = [];
            foreach($right->Owner as $owner){
                $name = (string)$owner->Person->Content;
                if($owner->Organization){
                    $name = (string)$owner->Organization->Content;
                }

                $ownerNames[] = $name;
            }
            $result->names[] = implode("\n", $ownerNames);
        }

        @unlink($fileName);

        return $result;
    }

    public function createRequest($cadastralNo){
        while(1) {
            try {
                $presleep = $this->rosreestrInterval - 30;
                if(time() - $this->lastQueryTime < $presleep){
                    $toSleep = $presleep - (time() - $this->lastQueryTime);
                    print "Предварительная пауза $toSleep секунд между запросами в Росреестр\n";
                    sleep($toSleep);
                }
                // вообще конечно так быть не должно, но это быстрое решение проблемы обрыва соединения
                $this->login();

                $searchFormData = $this->vaadinClickButton($this->findButtonByCaption($this->result, 'Запрос по правообладателю'));

                try {
                    $searchFormData = $this->vaadinClickButton($this->findButtonByCaption($searchFormData, 'Поиск объектов недвижимости'));
                } catch (Throwable $exception) {
                    $searchFormData = $this->vaadinClickButton($this->findButtonByCaption($this->result, 'Поиск объектов недвижимости'));
                }


                // fill search fields
                $this->vaadinSetFieldText($this->findButtonByPrompt($searchFormData, 'Кадастровый номер'), $cadastralNo);
                print "Cadastral no ok\n";

                $regionField = $this->findButtonByPrompt($searchFormData, 'Регион');
                $cityList = $this->vaadinQuery('windowName=1',
                    "{$this->appLogin['Vaadin-Security-Key']}\x1D{$this->region}\x1F{$regionField}\x1Ffilter\x1Fs\x1E0\x1F{$regionField}\x1Fpage\x1Fi"
                );
                $dropDownKey = $this->findKeyByCaption($cityList, $this->region);
                $this->vaadinQuery('windowName=1',
                    "{$this->appLogin['Vaadin-Security-Key']}\x1D{$dropDownKey}\x1C\x1F{$regionField}\x1Fselected\x1Fc"
                );
                print "Region ok\n";

                $searchingData = $this->vaadinClickButton($this->findButtonByCaption($searchFormData, 'Найти'));
                print "Form filled and sent\n";

                $refreshField = $this->findButtonByCaption($searchingData, 'Поиск объектов недвижимости');
                $targetElement = null;
                do {
                    // @todo limit retry
                    usleep(500000);
                    $result = $this->vaadinQuery('windowName=1',
                        "{$this->appLogin['Vaadin-Security-Key']}\x1D832\x1F{$refreshField}\x1Fpositionx\x1Fi\x1E404\x1F{$refreshField}\x1Fpositiony\x1Fi"
                    );
                    try {
                        $targetElement = $this->findFieldWithEvent($result, 'itemClick');
                        break;
                    } catch (Throwable $exception) {
                        // it's ok: continue polling
                    }
                } while (1);

                if (!$targetElement) {
                    throw new RuntimeException('Data was not found!');
                }

                $result = $this->vaadinQuery('windowName=1',
                    "{$this->appLogin['Vaadin-Security-Key']}\x1D1\x1F{$targetElement}\x1FclickedKey\x1Fs\x1E1\x1F{$targetElement}\x1FclickedColKey\x1Fs\x1E1,572,680,false,false,false,false,8,-1,-1\x1F{$targetElement}\x1FclickEvent\x1Fs\x1Etrue\x1F{$targetElement}\x1FclearSelections\x1Fb\x1E1\x1C\x1F{$targetElement}\x1Fselected\x1Fc"
                );

                $captchaSrc = $this->baseURL . preg_replace('#(?<=!!/).+#', $this->findSrcByContentType($result, 'application/octet-stream'), $this->location);

                do {
                    curl_setopt($this->ch, CURLOPT_URL, $captchaSrc . '?refresh=true&time=' . time() . random_int(100, 999));
                    curl_setopt($this->ch, CURLOPT_POST, 0);
                    curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
                        ' image/webp,image/apng,image/*,*/*;q=0.8',
                    ]);
                    do {
                        $captcha = curl_exec($this->ch);
                        $responseCode = (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
                        if ($responseCode >= 500 || empty($captcha)) {
                            // @todo limit retry
                            print "Request failed, error {$responseCode}. Retry\n";
                            usleep(500000);
                        }
                    } while ($responseCode >= 500 || empty($captcha));


                    $solvedCaptcha = $this->solveCaptcha($captcha);
                    if (!$solvedCaptcha) {
                        continue;
                    }
                    $this->vaadinSetFieldText($this->findFieldByStyle($result, 'srv-field'), $solvedCaptcha);

                    break;
                } while (1);

                if(time() - $this->lastQueryTime < $this->rosreestrInterval){
                    $toSleep = $this->rosreestrInterval - (time() - $this->lastQueryTime);
                    print "Финальная пауза $toSleep секунд между запросами в Росреестр\n";
                    sleep($toSleep);
                }

                $result = $this->vaadinClickButton($this->findButtonByCaption($result, 'Отправить запрос'));

                try {
                    $this->findFieldByStyle($result, 'error');
                    throw new RuntimeException('Server error while adding query');
                } catch (Throwable $exception) {

                }

                $this->lastQueryTime = time();

                return $this->findRegexp($result, '#<b>(\d+\-\d+)</b>#ui')[1];
            } catch (Throwable $exception) {
                var_dump($exception->getMessage());
                $this->login();
                continue;
            }
        }
    }

    protected function findRegexp(array $data, $regexp)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findRegexp($v, $regexp);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif (preg_match($regexp, $v, $matches)) {
                return $matches;
            }
        }
        throw new RuntimeException("Pattern {$regexp} not found!");
    }

    protected function findFieldByStyle(array $data, $style)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findFieldByStyle($v, $style);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'style' && $v === $style) {
                return $data['id'];
            }
        }
        throw new RuntimeException("Button {$style} not found!");
    }

    protected function findSrcByContentType(array $data, $contentType)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findSrcByContentType($v, $contentType);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'mimetype' && $v === $contentType) {
                return $data['src'];
            }
        }
        throw new RuntimeException("Src {$contentType} not found!");
    }

    protected function findKeyByCaption(array $data, $caption)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findKeyByCaption($v, $caption);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'caption' && $v === $caption) {
                return $data['key'];
            }
        }
        throw new RuntimeException("Button {$caption} not found!");
    }

    protected function findFieldWithEvent(array $data, $event)
    {
        foreach ($data as $k => $v) {
            if ($k === 'eventListeners' && is_array($v) && in_array($event, $v)) {
                return $data['id'];
            } elseif (is_array($v)) {
                try {
                    return $this->findFieldWithEvent($v, $event);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            }
        }
        throw new RuntimeException("Event {$event} not found!");
    }

    protected function vaadinSetFieldText($field, $text)
    {
        $textLength = strlen($text);
        $this->vaadinQuery('windowName=1',
            "{$this->appLogin['Vaadin-Security-Key']}\x1D{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
        );
    }

    protected function findButtonByPrompt(array $data, $caption)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findButtonByPrompt($v, $caption);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'prompt' && $v === $caption) {
                return $data['id'];
            }
        }
        throw new RuntimeException("Button {$caption} not found!");
    }

    protected function findFocusedField(array $data)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findFocusedField($v);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'focused') {
                return $v;
            }
        }
        throw new RuntimeException('Focused field not found!');
    }


    protected function vaadinQuery($uri, $data){
        print $data . "\n";

        curl_setopt($this->ch, CURLOPT_URL, $url = $this->portletFullURL . '?' . $uri);
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain;charset=UTF-8',
        ]);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data); // GS, group separator??
        do {
            $result = curl_exec($this->ch);
            $responseCode = (int)curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
            if ($responseCode >= 500) {
                // @todo limit retry
                print "Request failed, error {$responseCode}. Retry\n";
            }
        } while ($responseCode >= 500);
        $result = str_replace('for(;;);', '', $result);
        $result = json_decode($result, 1);
        if (!is_array($result)) {
            throw new RuntimeException('Failed to execute Vaadin query');
        }
        if (isset($result[0]['meta']['appError'])) {
            throw new RuntimeException($result[0]['meta']['appError']['caption']);
        }
        return $result;
    }

    protected function findButtonByCaption(array $data, $caption)
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                try {
                    return $this->findButtonByCaption($v, $caption);
                } catch (Throwable $exception) {
                    // it's ok, do nothing
                }
            } elseif ($k === 'caption' && $v === $caption) {
                return $data['id'];
            }
        }
        throw new RuntimeException("Button {$caption} not found!");
    }

    protected function vaadinClickButton($field)
    {
        return $this->vaadinQuery('windowName=1',
            "{$this->appLogin['Vaadin-Security-Key']}\x1Dtrue\x1F{$field}\x1Fstate\x1Fb\x1E1,0,0,false,false,false,false,1,30,17\x1F{$field}\x1Fmousedetails\x1Fs"
        );

    }

    public function getOwnershipAndAreaFree($cadastralNo){
        do {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/79.0.3945.79 Chrome/79.0.3945.79 Safari/537.36');
            curl_setopt($ch, CURLOPT_COOKIEFILE, 'php://memory');
            curl_setopt($ch, CURLOPT_COOKIEJAR, 'php://memory');

            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_URL, $this->baseURL . '/wps/portal/p/cc_ib_portal_services/online_request');
            $result = curl_exec($ch);
            print "Initial request ok\n";

            preg_match('#<img src="([^"]+)" id=\"captchaImage2\">#u', $result, $captcha);
            $captcha = $captcha[1];
            preg_match('#Content-Location: ([^\n\r]+)#u', $result, $location);
            $location = $location[1];

            preg_match('#<form action="([^"]+)"#u', $result, $form);
            $form = $form[1];

            $captchaURL = $this->baseURL . $location . $captcha . '?refresh=true&time=' . time() . random_int(100, 999);
            $formURL = $this->baseURL . $location . $form;

            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_URL, $captchaURL);

            $result = curl_exec($ch);
            if(strlen($result) < 100){
                print 'Wrong captcha (length ' . strlen($result) . ")\n";
                // wrong captcha
                curl_close($ch);
                continue;
            }
            print "Captcha ok\n";

            $solvedCaptcha = $this->solveCaptcha($result);
            if(!$solvedCaptcha){
                curl_close($ch);
                continue;
            }

            curl_setopt($ch, CURLOPT_URL, $formURL);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'search_action' => 'true',
                'subject' => '',
                'region' => '',
                'settlement' => '',
                'search_type' => 'CAD_NUMBER',
                'cad_num' => $cadastralNo,
                'start_position' => '',
                'obj_num' => '',
                'old_number' => '',
                'street_type' => 'str0',
                'street' => '',
                'house' => '',
                'building' => '',
                'structure' => '',
                'apartment' => '',
                'right_reg' => '',
                'encumbrance_reg' => '',
                'captchaText' => $solvedCaptcha,
            ]));
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $result = curl_exec($ch);

            if(strpos($result, 'Текст с картинки введен неверно') !== false){
                curl_close($ch);
                continue;
            }

            preg_match('#<a href="([^"]+dbName=firLite&region_key=\d+)">#u', $result, $propertyDataUrl);
            $propertyDataUrl = $propertyDataUrl[1];
            preg_match('#Content-Location: ([^\n\r]+)#u', $result, $location);
            $location = $location[1];

            $finalURL = $this->baseURL . $location . $propertyDataUrl;
            curl_setopt($ch, CURLOPT_URL, $finalURL);
            curl_setopt($ch, CURLOPT_POST, 0);
            $result = curl_exec($ch);

            curl_close($ch);

            preg_match_all("#>([^<]+обственность\)[^<]+)<#mu", $result, $ownershipIds);
            $ownershipIds = $ownershipIds[1];

            foreach($ownershipIds as &$ownershipId){
                $ownershipId = html_entity_decode($ownershipId, null, 'UTF-8');
                $ownershipId = str_replace(["\n","\r"],' ', $ownershipId);
                $ownershipId = preg_replace('#\s+#u', ' ', $ownershipId);
                $ownershipId = trim($ownershipId);
            }
            unset($ownershipId);

            preg_match('#Площадь ОКС\'a:.+?<b>([^<]+)</b>#usm', $result, $area);
            $area = $area[1];

            return [
                'ownership' => $ownershipIds,
                'area' => $area,
            ];
        }
        while(1);
    }
}