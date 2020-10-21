<?php

$config = include __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if(isset($argv[1]) && $argv[1] == '--help'){
    print "Скрипт для создания заявок во ФГИС ЕГРН" . PHP_EOL;
    print PHP_EOL;
    print "Usage: php ir_egrn_create.php" . PHP_EOL;
    exit;
}

$dbh = getDBH($config);

$statement = $dbh->query('
    SELECT * FROM task
');
$existsPremiseTasklist = [];
while($row = $statement->fetch(PDO::FETCH_ASSOC)){
    $existsPremiseTasklist[$row['premise_id']] = true;
}

$egrn = new IR_EGRN($config);

$insertStatement = $dbh->prepare('
    INSERT INTO task(premise_id, date_added, rosreestr_id)
    VALUES (:premise_id, :date_added, :rosreestr_id)
');

$statement = $dbh->query('
    SELECT * FROM premise
    WHERE (owner_name IS NULL OR owner_name = \'\')
    AND ownership IS NOT NULL
    AND ownership != \'\'
', PDO::FETCH_ASSOC);

while($row = $statement->fetch(PDO::FETCH_ASSOC)){
    if(isset($existsPremiseTasklist[$row['premise_id']])){
        print "По помещению {$row['cadastral_no']} уже есть запрос, пропускаем" . PHP_EOL;
        continue;
    }
    $insertStatement->execute([
        ':premise_id' => $row['premise_id'],
        ':date_added' => time(),
        ':rosreestr_id' => $egrn->createRequest($row['cadastral_no']),
    ]);
}