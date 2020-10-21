<?php
if (version_compare(PHP_VERSION, '7.0.0') < 0) {
    print "Ошибка! Для работы скрипта требуется версия PHP не ниже 7.0.0" . PHP_EOL;
    exit;
}

$requiredExtensions = ['curl', 'sqlite3', 'zip', 'xml'];
foreach ($requiredExtensions as $requiredExtension){
    if(!extension_loaded($requiredExtension)){
        print "Ошибка! Для работы скрипта необходимо расширение $requiredExtension." . PHP_EOL;
        exit;
    }
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "Этот скрипт не должен выполняться напрямую" . PHP_EOL;
    exit;
}
/***
 * @param array $config
 * @return PDO
 */
function getDBH($config = []){
    $fileDb = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . $config['db'];
    $dbh = new PDO('sqlite:' . $fileDb);
    if(!$dbh){
        print "Невозможно открыть файл базы $fileDb" . PHP_EOL;
        exit;
    }
    return $dbh;
}

class Ownership{
    public $cadastralNo;
    public $ownership = [];
    public $names = [];
    public $area;
    public $xml;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'anticaptcha.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'imagetotext.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'ir_egrn.lib.php';