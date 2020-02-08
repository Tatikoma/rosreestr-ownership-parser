<?php

$newData = [];
$fh = fopen('extracted.csv', 'rb');
while($row = fgetcsv($fh)){
    $newData[$row[0]] = [
        'ownerName' => $row[2],
        'ownership' => $row[3],
    ];
}
fclose($fh);

$oldData = [];
$fh = fopen('registry_20.08.2019.csv', 'rb');
while($row = fgetcsv($fh)){
    // fix names
    $name = $row[4];
    if(substr_count($name, ' ') == 5){
        $name = explode(' ', $name);
        $name = $name[0] . ' ' . $name[1] . ' ' . $name[2]
            . "\n" . $name[3] . ' ' . $name[4] . ' ' . $name[5];
    }
    $oldData[$row[0]] = [
        'ownerName' => $name,
        'ownership' => $row[5],
    ];
}
fclose($fh);

$freeData = [];
$fh = fopen('registry_free_04.02.2020.csv', 'rb');
$rh = fopen('EGRN_registry_' . date('d.m.Y') . '.csv', 'wb');
while($row = fgetcsv($fh)){
    $cadastralNo = $row[3];

    $ownerName = '';
    $ownership = $row[4];
    if(isset($oldData[$cadastralNo])){
        $ownerName = $oldData[$cadastralNo]['ownerName'];
        $ownership = $oldData[$cadastralNo]['ownership'];
    }
    if(isset($newData[$cadastralNo])){
        $ownerName = $newData[$cadastralNo]['ownerName'];
        $ownership = $newData[$cadastralNo]['ownership'];
    }

    fputcsv($rh, [
        $row[0], // property type
        $row[1], // flat no
        $row[2], // area
        $cadastralNo,
        $ownerName,
        $ownership,
    ]);
}
fclose($fh);
fclose($rh);