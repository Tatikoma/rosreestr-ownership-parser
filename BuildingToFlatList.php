<?php /** @noinspection PhpUndefinedFieldInspection */

if(!isset($argv[1]) || $argv[1] == '--help'){
    print "Вспомогательный скрипт для конвертации XML всего дома в CSV." . PHP_EOL;
    print "xml-файл должен быть в директории runtime;" . PHP_EOL;
    print "csv-файл будет помещен в директорию runtime." . PHP_EOL;
    print PHP_EOL;
    print "Usage: php BuildingToFlatList.php filename.xml" . PHP_EOL;
    exit;
}

$filenameInput = __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $argv[1];
$filenameOutput = __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $argv[1] . '.csv';
$xml = simplexml_load_string(file_get_contents($filenameInput));

$fh = fopen($filenameOutput, 'wb');
foreach($xml->Realty->Building->Flats->Flat as $flat){
    $cadastralNo = (string)$flat['CadastralNumber'];

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