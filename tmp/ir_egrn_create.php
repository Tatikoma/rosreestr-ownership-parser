<?php

$queuedList = [];
$fh = fopen('querylist.csv', 'rb');
while($row = fgetcsv($fh)){
    $queuedList[$row[1]] = true;
}
fclose($fh);

$cadastalList = [];
$fh = fopen('diff.csv', 'rb');
while($row = fgetcsv($fh)){
    if(isset($queuedList[$row[0]])){
        continue;
    }
    $cadastalList[] = $row[0];
}
fclose($fh);


include __DIR__ . '/ir_egrn_login.php';

$cadastralNo = array_shift($cadastalList);

do {
    try {
        $searchFormData = vaadinClickButton(findButtonByCaption($result, 'Запрос по правообладателю'));

        try {
            $searchFormData = vaadinClickButton(findButtonByCaption($searchFormData, 'Поиск объектов недвижимости'));
        } catch (\Throwable $exception) {
            $searchFormData = vaadinClickButton(findButtonByCaption($result, 'Поиск объектов недвижимости'));
        }


        // fill search fields
        vaadinSetFieldText(findButtonByPrompt($searchFormData, 'Кадастровый номер'), $cadastralNo);
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
        do {
            // @todo limit retry
            usleep(500000);
            $result = vaadinQuery('windowName=1',
                "{$appLogin['Vaadin-Security-Key']}\x1D832\x1F{$refreshField}\x1Fpositionx\x1Fi\x1E404\x1F{$refreshField}\x1Fpositiony\x1Fi"
            );
            try {
                $targetElement = findFieldWithEvent($result, 'itemClick');
                break;
            } catch (\Throwable $exception) {
                // it's ok: continue polling
            }
        } while (1);

        if (!$targetElement) {
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
                    usleep(500000);
                }
            } while ($responseCode >= 500 || empty($captcha));


            $solvedCaptcha = $solveCaptcha($captcha);
            if (!$solvedCaptcha) {
                continue;
            }
            vaadinSetFieldText(findFieldByStyle($result, 'srv-field'), $solvedCaptcha);

            break;
        } while (1);

        $result = vaadinClickButton(findButtonByCaption($result, 'Отправить запрос'));

        try {
            findFieldByStyle($result, 'error');
            throw new RuntimeException('Server error while adding query');
        } catch (\Throwable $exception) {

        }

        //<b>80-128739641</b>
        $queryId = findRegexp($result, '#<b>(\d+\-\d+)</b>#ui')[1];
        $fh = fopen('querylist.csv', 'ab+');
        fputcsv($fh, [$queryId, $cadastralNo]);
        fclose($fh);
    }
    catch(\Throwable $exception){
        include __DIR__ . '/ir_egrn_login.php';
        continue;
    }

    if(!count($cadastalList)){
        break;
    }

    $cadastralNo = array_shift($cadastalList);
}
while(1);