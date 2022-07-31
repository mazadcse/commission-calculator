<?php
require_once 'vendor/autoload.php';
use Commission\Service\FeeCalculator;

if ($argc > 1){
    $feeCalculatorObj = new FeeCalculator($argv[1]);
    $feeCalculatorObj->getFee( isset($argv[2]));
}else{
    echo 'No input file found'.PHP_EOL;
}
