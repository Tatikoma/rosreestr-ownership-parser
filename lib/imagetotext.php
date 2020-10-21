<?php

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "Этот скрипт не должен выполняться напрямую" . PHP_EOL;
    exit;
}

class ImageToText extends Anticaptcha implements AntiCaptchaTaskProtocol {
    private $body;
    private $phrase = false;
    private $case = false;
    private $numeric = false;
    private $math = 0;
    private $minLength = 0;
    private $maxLength = 0;


    public function getPostData() {
        return array(
            "type"      =>  "ImageToTextTask",
            "body"      =>  str_replace("\n", "", $this->body),
            "phrase"    =>  $this->phrase,
            "case"      =>  $this->case,
            "numeric"   =>  $this->numeric,
            "math"      =>  $this->math,
            "minLength" =>  $this->minLength,
            "maxLength" =>  $this->maxLength
        );
    }

    public function getTaskSolution() {
        return $this->taskInfo->solution->text;
    }

    public function setBody($fileBody){
        if(strlen($fileBody) <= 100){
            $this->setErrorMessage("file payload too small or empty");
            return false;
        }
        $this->body = base64_encode($fileBody);
        return true;
    }

    public function setFile($fileName) {

        if (file_exists($fileName)) {

            if (filesize($fileName) > 100) {
                $this->body = base64_encode(file_get_contents($fileName));
                return true;
            } else {
                $this->setErrorMessage("file $fileName too small or empty");
            }

        } else {
            $this->setErrorMessage("file $fileName not found");
        }
        return false;

    }

    public function setPhraseFlag($value) {
        $this->phrase = $value;
    }

    public function setCaseFlag($value) {
        $this->case = $value;
    }

    public function setNumericFlag($value) {
        $this->numeric = $value;
    }

    public function setMathFlag($value) {
        $this->math = $value;
    }

    public function setMinLengthFlag($value) {
        $this->minLength = $value;
    }

    public function setMaxLengthFlag($value) {
        $this->maxLength = $value;
    }

}