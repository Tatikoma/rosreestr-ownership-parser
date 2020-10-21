<?php

$config = include __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if(isset($argv[1]) && $argv[1] == '--help'){
    print "Скрипт для бесплатного парсинга номеров собственности" . PHP_EOL;
    print "Парсит все помещения за раз, без возможности прервать" . PHP_EOL;
    print PHP_EOL;
    print "Usage: php parse.php" . PHP_EOL;
    exit;
}

$dbh = getDBH($config);

$egrn = new IR_EGRN($config);

$statement = $dbh->query('
    SELECT premise_id, cadastral_no, ownership 
    FROM premise
', PDO::FETCH_ASSOC);

$counter = [
    'total' => 0,
    'updated' => 0,
    'notchanged' => 0,
];

$updateStatement = $dbh->prepare('
    UPDATE premise
    SET ownership = :ownership,
        owner_name = NULL,
        area = :area
    WHERE premise_id = :premise_id
');

while($row = $statement->fetch(PDO::FETCH_ASSOC)){
    print date('[Y-m-d H:i:s] - ') . "Processing next {$row['cadastral_no']}\n";
    $data = $egrn->getOwnershipAndAreaFree($row['cadastral_no']);

    $counter['total']++;

    $oldOwnership = trim($row['ownership']);
    $newOwnership = trim(implode("\n", $data['ownership']));

    // remove share info
    $oldOwnership = preg_replace('#\s\d+/\d+#', '', $oldOwnership);
    $newOwnership = preg_replace('#\s\d+/\d+#', '', $newOwnership);
    // remove date
    $oldOwnership = preg_replace('#\d+\.\d+\.\d+#', '', $oldOwnership);
    $newOwnership = preg_replace('#\d+\.\d+\.\d+#', '', $newOwnership);
    // remove all except record number
    $oldOwnership = preg_replace("#[^0-9:\\-/\n]#", '', $oldOwnership);
    $newOwnership = preg_replace("#[^0-9:\\-/\n]#", '', $newOwnership);

    // split ownership
    $oldOwnership = explode("\n", $oldOwnership);
    $newOwnership = explode("\n", $newOwnership);

    /** @var string[] $diff */
    $diff = array_diff($oldOwnership, $newOwnership);

    if(count($diff) > 0){
        $counter['updated']++;
        $updateStatement->execute([
            ':ownership' => implode("\n", $data['ownership']),
            ':area' => str_replace(',', '.', $data['area']),
            ':premise_id' => $row['premise_id'],
        ]);
    }
    else{
        $counter['notchanged']++;
    }
}

print "Всего обработано {$counter['total']} записей" . PHP_EOL;
print "Из них {$counter['updated']} записей обновлено" . PHP_EOL;
print "Из них {$counter['notchanged']} записей не изменилось" . PHP_EOL;