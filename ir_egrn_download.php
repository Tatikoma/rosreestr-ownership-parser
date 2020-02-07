<?php

include __DIR__ . '/ir_egrn_login.php';

$searchFormData = vaadinClickButton(findButtonByCaption($result, 'Запрос по правообладателю'));

try {
    $searchFormData = vaadinClickButton(findButtonByCaption($searchFormData, 'Мои заявки'));
}
catch(\Throwable $exception){
    $searchFormData = vaadinClickButton(findButtonByCaption($result, 'Мои заявки'));
}

if(!file_exists(__DIR__ . '/zip') && !@mkdir(__DIR__ . '/zip')){
    throw new RuntimeException('Cannot create folder for resulting files');
}

$fh = fopen('querylist.csv', 'rb');

$fieldQueryInput = findFieldByText($searchFormData, '');
$buttonRefresh = findButtonByCaption($searchFormData, 'Обновить');
$areaField = findFieldBySelectMode($searchFormData, 'single');

while($row = fgetcsv($fh)){
    $queryId = $row[0];
    $queryIdLength = strlen($queryId);

    $searchFormData = vaadinQuery('windowName=1',
        "{$appLogin['Vaadin-Security-Key']}\x1D1261\x1FPID0\x1Fheight\x1Fi\x1E755\x1FPID0\x1Fwidth\x1Fi\x1E1905\x1FPID0\x1FbrowserWidth\x1Fi\x1E911\x1FPID0\x1FbrowserHeight\x1Fi\x1Etrue\x1F{$areaField}\x1FclearSelections\x1Fb\x1E\x1F{$areaField}\x1Fselected\x1Fc\x1E{$queryId}\x1F{$fieldQueryInput}\x1Ftext\x1Fs\x1E{$queryIdLength}\x1F{$fieldQueryInput}\x1Fc\x1Fi\x1Etrue\x1F{$buttonRefresh}\x1Fstate\x1Fb\x1E1,1176,525,false,false,false,false,1,23,6\x1F{$buttonRefresh}\x1Fmousedetails\x1Fs"
    );

    $zipSrc = $baseURL . preg_replace('#(?<=!!/).+#', findSrcByIcon($searchFormData, 'theme://img/download.png'), $location);

    curl_setopt($ch, CURLOPT_URL, $zipSrc);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        ' image/webp,image/apng,image/*,*/*;q=0.8',
    ]);
    do {
        $zipFile = curl_exec($ch);
        $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseCode >= 500 || empty($zipFile)) {
            // @todo limit retry
            print "Request failed, error {$responseCode}. Retry\n";
        }
    } while ($responseCode >= 500 || empty($zipFile));

    file_put_contents(__DIR__ . '/zip/' . $queryId . '.zip', $zipFile);
}
fclose($fh);