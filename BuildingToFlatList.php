<?php

$xml = simplexml_load_string(file_get_contents('Вандер.xml'));

$fh = fopen('vander.csv', 'wb');
foreach($xml->Realty->Building->Flats->Flat as $flat){
    $cadastralNo = (string)$flat['CadastralNumber'];
    var_dump($cadastralNo);
    $area = (string)$flat->Area;
    $levels = [];
    $numberOnPlan = [];
    foreach($flat->PositionInObject->Levels->Level as $level){
        $levels[] = (string)$level['Number'];
        $numberOnPlan[] = (string)$level->Position['NumberOnPlan'];
    }
    fputcsv($fh, [
        $cadastralNo,
        $area,
        implode(', ', $levels),
        implode(', ', $numberOnPlan),
    ]);
}
fclose($fh);