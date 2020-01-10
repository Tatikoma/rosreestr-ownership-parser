<?php

require_once __DIR__ . '/anticaptcha.php';
require_once __DIR__ . '/imagetotext.php';

$api = new ImageToText();
$api->setVerboseMode(true);
$api->setKey('ace54bccd74e3a7b1be6ac6630b9a879');
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

$getOwnershipAndArea = function($cadastralNo) use($solveCaptcha){
    $baseURL = 'https://rosreestr.ru';

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
        curl_setopt($ch, CURLOPT_URL, $baseURL . '/wps/portal/p/cc_ib_portal_services/online_request');
        $result = curl_exec($ch);
        print "Initial request ok\n";

        preg_match('#<img src="([^"]+)" id=\"captchaImage2\">#u', $result, $captcha);
        $captcha = $captcha[1];
        preg_match('#Content-Location: ([^\n\r]+)#u', $result, $location);
        $location = $location[1];

        preg_match('#<form action="([^"]+)"#u', $result, $form);
        $form = $form[1];

        $captchaURL = $baseURL . $location . $captcha . '?refresh=true&time=' . time() . random_int(100, 999);
        $formURL = $baseURL . $location . $form;

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

        $solvedCaptcha = $solveCaptcha($result);
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

        $finalURL = $baseURL . $location . $propertyDataUrl;
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
};

$parsedData = [];
if(is_readable('result.csv')){
    $fh = fopen('result.csv', 'rb');
    while($row = fgetcsv($fh)){
        $key = $row[0] . $row[1];
        $parsedData[$key] = true;
    }
    fclose($fh);
}

$fh = fopen('data-1576963478324.csv', 'rb');
$rh = fopen('result.csv', 'ab');
while($row = fgetcsv($fh)){
    $key = $row[1] . $row[0];
    if(isset($parsedData[$key])){
        continue;
    }
    print date('[Y-m-d H:i:s] - ') . "Processing next {$key}\n";
    $data = $getOwnershipAndArea($row[2]);
    fputcsv($rh, [
        $row[1],
        $row[0],
        $data['area'],
        $row[2],
        implode("\r\n", $data['ownership']),
    ]);
}
fclose($fh);
fclose($rh);
