<?php
namespace Commission\Service;

class FeeCalculator
{
    public $errorMsg='';
    public $fileLocation;
    public $weeks=[];
    public $cData;
    public $chargeFreeAmount = 1000;
    public $freeTimeLimit = 3;

    public function __construct($fileLocation)
    {
        $this->fileLocation = $fileLocation;
    }

    /**
     * Get real time currency from api
     *
     * @return false|mixed
     */
    public function currencyConversion()
    {
        try {
            $url = "http://api.exchangeratesapi.io/v1/latest?access_key=3587b1bbc7d1bbd09b47a69b345b2b1d&symbols=USD,JPY,EUR";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);
            return  json_decode($res);

        }catch (\Exception $e){
            echo $e->getMessage();
        }
        return false;
    }

    /**
     * Read data from csv file
     *
     * @return array|string
     */
    public function readCsv()
    {
        $dataArray = [];
        try{
            if(file_exists($this->fileLocation)){
                if (($open = fopen($this->fileLocation, "r")) !== FALSE)
                {
                    while (($data = fgetcsv($open, 10000, ",")) !== FALSE)
                    {
                        $dataArray[] = $data;
                    }
                    fclose($open);
                    return $dataArray;
                }
            }else{
               echo  'The input data file "'.$this->fileLocation.'" not exist'.PHP_EOL;
            }
        }catch (\Exception $e){
            echo $e->getMessage();
        }
        return false;
    }

    /**
     * Rounded up to currency's decimal places
     *
     * @param $value
     * @param int $places
     * @return float|int
     */
    public function roundedUp($value, $places=2)
    {
        $res = 0;
        try{
            if ($places < 0) { $places = 0; }
            $mult = pow(10, $places);
            $res = ceil((float) $value * $mult) / $mult;
            $res = number_format($res, 2, '.', '');
        }catch (\Exception $e){
            echo $e->getMessage();
        }
        return $res;
    }

    /**
     * Convert currency with amount
     *
     * @param $amount
     * @param string $from
     * @param string $to
     * @return float
     *
     */
    public function convertCurrency($amount=0, $from='EUR', $to='USD'){
        if (isset($this->cData->rates)){
            if($from == 'EUR' && $to == 'USD'){
                $res = $amount * $this->cData->rates->USD;
            }elseif ($from == 'EUR' && $to == 'JPY'){
                $res = $amount *  $this->cData->rates->JPY;
            }elseif($from == 'USD' && $to == 'EUR'){
                $res = $amount / $this->cData->rates->USD;
            }elseif($from == 'JPY' && $to == 'EUR'){
                $res =  $amount / $this->cData->rates->JPY;
            }else{
                $res = $amount;
            }
        }else{
            if($from == 'EUR' && $to == 'USD'){
                $res = $amount * 1.1497;
            }elseif ($from == 'EUR' && $to == 'JPY'){
                $res = $amount *  129.53;
            }elseif($from == 'USD' && $to == 'EUR'){
                $res = $amount / 1.1497;
            }elseif($from == 'JPY' && $to == 'EUR'){
                $res =  $amount / 129.53;
            }else{
                $res = $amount;
            }
        }

        return $res;
    }

    /**
     * Week range for a user operation date
     *
     * @param $operationDate
     * @param $userIdentifier
     * @return array
     */
    public function weekRange($operationDate, $userIdentifier)
    {
        date_default_timezone_set (date_default_timezone_get());
        $dt = strtotime ($operationDate);
        $weekStartDate = date ('N', $dt) == 1 ? date ('Y-m-d', $dt) : date ('Y-m-d', strtotime ('last monday', $dt));
        $weekEndDate = date('N', $dt) == 7 ? date ('Y-m-d', $dt) : date ('Y-m-d', strtotime ('next sunday', $dt));
        $startDate = date('Y-m-d', strtotime($weekStartDate));
        $endDate = date('Y-m-d', strtotime($weekEndDate));
        $weekIndex = $userIdentifier.'-'.$startDate.'-to-'.$endDate;

        return [$weekIndex, $startDate, $endDate];
    }

    /**
     * Get commission fee
     *
     * @param false $realCurrency
     * @return bool
     */
    public function getFee($realCurrency = false)
    {
        $this->cData = ($realCurrency)?$this->currencyConversion():'';
        if($data = $this->readCsv()){
            foreach ($data as $i=>$d){
                list($operationDate, $userIdentifier, $userType, $operationType, $amount, $currency) = $d;

                if(strtolower($operationType) =='deposit'){
                    $fee = $this->roundedUp((float) $amount * 0.03 / 100);

                }elseif ((strtolower($operationType) =='withdraw') && (strtolower($userType) =='private')) {
                    list($weekIndex, $startDate, $endDate) = $this->weekRange($operationDate, $userIdentifier);

                    $amountConvert = $this->convertCurrency( (float) $amount, $currency, 'EUR' );
                    if(isset($this->weeks[$weekIndex]) && ($operationDate >= $startDate) && ($operationDate <= $endDate)){
                        if( $this->weeks[$weekIndex]['counter'] <= $this->freeTimeLimit){
                            $oldFreeAmount = $this->weeks[$weekIndex]['charge_free_amount'];
                            $this->weeks[$weekIndex]['chargeable_amount'] = ($amountConvert > $oldFreeAmount) ? ($amountConvert - $oldFreeAmount): 0;
                            $this->weeks[$weekIndex]['charge_free_amount'] = ($amountConvert > $oldFreeAmount)? 0 : ( $oldFreeAmount - $amountConvert) ;
                        }else{
                            $this->weeks[$weekIndex]['chargeable_amount'] = $amountConvert;
                            $this->weeks[$weekIndex]['charge_free_amount'] = 0;
                        }
                        $this->weeks[$weekIndex]['counter']++;
                    }else{
                        $this->weeks[$weekIndex]['counter'] = 1;
                        $this->weeks[$weekIndex]['charge_free_amount'] = ($amountConvert >= $this->chargeFreeAmount) ? 0 : ($this->chargeFreeAmount - $amountConvert) ;
                        $this->weeks[$weekIndex]['chargeable_amount'] = ($amountConvert > $this->chargeFreeAmount) ? ($amountConvert - $this->chargeFreeAmount): 0;
                    }

                    $feeAmount =  $this->weeks[$weekIndex]['chargeable_amount']  * 0.3 / 100;
                    $feeAmount = $this->convertCurrency($feeAmount,'EUR', $currency );
                    $fee = $this->roundedUp($feeAmount);

                }elseif ((strtolower($operationType) =='withdraw') && (strtolower($userType) =='business')) {
                    $fee = $this->roundedUp((float) $amount * 0.5 / 100);
                }else{
                    $fee = 0;
                }

                echo $fee . PHP_EOL ;
            }
        }
        return true;
    }

}
