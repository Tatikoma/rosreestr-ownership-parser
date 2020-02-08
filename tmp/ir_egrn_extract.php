<?php

$path = __DIR__ . '/zip/';
$dh = opendir($path);

if(!is_readable(__DIR__ . '/zip.bak') || !is_dir(__DIR__ . '/zip.bak')) {
    mkdir(__DIR__ . '/zip.bak');
}

$zip = new ZipArchive();

$fh = fopen('extracted.csv', 'wb');

while($fileName = readdir($dh)){
    if(!is_file($path . $fileName)){
        continue;
    }
    if(!$zip->open($path . $fileName)){
        throw new RuntimeException('Failed to open archive!');
    }
    $isZipFound = false;
    $isDataFound = false;
    $data = '';
    for($i = 0; $i < $zip->count(); $i++){
        $name = $zip->getNameIndex($i);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if($ext == 'zip'){
            $isZipFound = true;
            $data = $zip->getFromIndex($i);
            file_put_contents('zip.tmp', $data);
            $zip->close();
            $x = $zip->open('zip.tmp');
            for($j = 0; $j < $zip->count(); $j++){
                $name = $zip->getNameIndex($j);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if($ext == 'xml'){
                    $isDataFound = true;
                    $data = $zip->getFromIndex($j);
                    $zip->close();
                    unlink('zip.tmp');
                    break 2;
                }
            }
        }
    }
    if(!$isZipFound || !$isDataFound || !$data){
        throw new RuntimeException('Broken archive!');
    }
    //print $data . "\n";
    $xml = simplexml_load_string($data);
    $cadastralNo = (string)$xml->Realty->Flat['CadastralNumber'];
    $area = (string)$xml->Realty->Flat->Area;
    $owners = [
        'registry' => [],
        'names' => [],
    ];
    /*foreach($xml->Realty->Flat->Rights->Right as $right){
        $ownership = '№ ' . (string)$right->Registration->RegNumber
            . ' от ' . (string)$right->Registration->RegDate
            . ' (' . (string)$right->Name . ')'
        ;
        $ownerNames = [];
        foreach($right->Owners->Owner as $owner){
            $ownerNames[] = (string)$owner->Person->FamilyName
                . ' ' . (string)$owner->Person->FirstName
                . ' ' . (string)$owner->Person->Patronymic
            ;
        }
        $owners['registry'][] = $ownership;
        $owners['names'][] = implode("\n", $ownerNames);
    }*/

    foreach($xml->ReestrExtract->ExtractObjectRight->ExtractObject->ObjectRight->Right as $right){
        $owners['registry'][] = (string)$right->Registration->Name;
        $ownerNames = [];
        foreach($right->Owner as $owner){
            $name = (string)$owner->Person->Content;
            if($owner->Organization){
                $name = (string)$owner->Organization->Content;
            }

            $ownerNames[] = $name;
        }
        $owners['names'][] = implode("\n", $ownerNames);
    }

    fputcsv($fh, [
        $cadastralNo,
        $area,
        implode("\n", $owners['names']),
        implode("\n", $owners['registry']),
    ]);
    rename(__DIR__ . '/zip/' . $fileName, __DIR__ . '/zip.bak/' . $fileName);
}
fclose($fh);