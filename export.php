<?php

$config = include __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if(!isset($argv[1]) || $argv[1] == '--help'){
    print "Экспорт данных из внутреннего sqlite в csv-файл:" . PHP_EOL;
    print "csv-файл будет создан с директории runtime;" . PHP_EOL;
    print "csv-файл будет с разделителем запятая;" . PHP_EOL;
    print PHP_EOL;
    print "Usage: php export.php filename.csv" . PHP_EOL;
    exit;
}

$fileExport = __DIR__ . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $argv[1];
if(!is_writable(dirname($fileExport))){
    print "Ошибка! Директори " . dirname($fileExport) . " не доступна для записи." . PHP_EOL;
    exit;
}

$dbh = getDBH($config);

$statement = $dbh->query('
    SELECT *
    FROM premise
');

$fh = fopen($fileExport, 'w+b');

$totalCounter = 0;

while($row = $statement->fetch(PDO::FETCH_ASSOC)){
    $totalCounter++;
    $data = [
        $row['cadastral_no'],
        $row['ownership'],
        $row['owner_name'],
        $row['area'],
    ];
    $data = array_merge($data, json_decode($row['extradata'], 1));
    fputcsv($fh, $data);
}

fclose($fh);

print "Экспорт данных успешно завершен;" . PHP_EOL;
print "Всего обработано $totalCounter записей;" . PHP_EOL;