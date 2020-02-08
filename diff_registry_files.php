<?php

$registryFile = 'registry_20.08.2019.csv';
$registryFree = 'registry_free_04.02.2020.csv';

$oldData = [];
$fh = fopen($registryFile, 'rb');
while($row = fgetcsv($fh)){
    $oldData[$row[0]] = $row[5];
}
fclose($fh);

$newData = [];
$fh = fopen($registryFree, 'rb');
while($row = fgetcsv($fh)){
    $newData[$row[3]] = $row[4];
}
fclose($fh);

if(count(array_diff(array_keys($oldData), array_keys($newData))) > 0){
    throw new \RuntimeException('Incomplete files. All cadastral no should match.');
}

foreach($newData as $cadastralNo => $newOwnership){
    $oldOwnership = trim($oldData[$cadastralNo]);
    $newOwnership = trim($newOwnership);

    // remove date
    $oldOwnership = preg_replace('#\d+\.\d+\.\d+#', '', $oldOwnership);
    $newOwnership = preg_replace('#\d+\.\d+\.\d+#', '', $newOwnership);
    // remove all except record number
    $oldOwnership = preg_replace("#[^0-9:\\-/\n]#", '', $oldOwnership);
    $newOwnership = preg_replace("#[^0-9:\\-/\n]#", '', $newOwnership);

    // split ownership
    $oldOwnership = explode("\n", $oldOwnership);
    $newOwnership = explode("\n", $newOwnership);

    if(count(array_diff($oldOwnership, $newOwnership)) > 0){
        file_put_contents('diff.csv', $cadastralNo . "\n", FILE_APPEND);
    }
}