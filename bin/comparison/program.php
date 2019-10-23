#!/usr/bin/php -q
<?php

class Plans {
    public function start($filename)
    {
        $content    =   file_get_contents($filename); //read file
        $json       =   json_decode($content, true); //decode to json

        //get the STDIN and explode it to lines.
        $inputs = stream_get_contents(STDIN);
        $inputs = explode("\n",$inputs);

        foreach ($inputs as $value) {
            if (strpos($value, 'price') !== false) {
                $split = explode(' ', $value);
                //price calculations
                $temp = $this->planPriceCalculations($json,$split[1]);
                //sort the array by the key second parametre is key default total
                $temp = $this->sortArray($temp);
                $this->printArray($temp); 
            } else if (strpos($value, 'usage') !== false) {
                $split = explode(' ', $value);
                //calculate the kwh from the money spend also print it
                $result = $this->calculateKWH($json, $split[1], $split[2], $split[3])."\n";
                fwrite(STDOUT, $result);
            } else if (strpos($value, 'exit') !== false) {
                exit;
            }
        }
    }

    function calculateKWH($plansArray, $supplier, $plan, $spend)
    {
        $selectedPlan = [];
        $kwh = 0;
        //loop until finding the suplier and plan in the array
        foreach ($plansArray as $key => $value) {
            if($value['supplier'] == $supplier && $value['plan'] == $plan){
                $selectedPlan = $value;
                break;
            }
        }
        //convert monhtly spend money to yearly and pence
        $inPence = $spend * 12 * 100;


        //if standing charge is exists first calculate without tax and subtract from pence
        if(array_key_exists("standing_charge", $selectedPlan)){
            $standing = $this->calculateStandigCharge($selectedPlan['standing_charge']);
            $inPence = $this->calculateWithoutTax($inPence) - $standing;
        }else{
            $inPence = $this->calculateWithoutTax($inPence);
        }

        //if there are more than 1 rates calculate the threshold with 
        //the price and subtract from the total pence
        //if the total pence is less then the first threshold*price divide the total pence to price
        //and add the result to kwh
        if(count($selectedPlan['rates']) > 1){
            foreach ($selectedPlan['rates'] as $key => $value) {
                if(array_key_exists("threshold", $value)){
                    $total = 0;
                    $total += $value['threshold'] * $value['price'];
                    if($total < $inPence){
                        $kwh += $value['threshold'];
                        $inPence -= $total;
                    }else{
                        $kwh += $inPence / $value['price'];
                        $inPence = 0;
                    }
                    unset($selectedPlan['rates'][$key]);
                }
            }
        }
        //if we still have pence calculate the rest by dividin to the price
        if($inPence > 0){
            foreach ($selectedPlan['rates'] as $value) {
                if(array_key_exists("price", $value)){
                    $kwh += $inPence / $value['price'];
                }
            }
        }

        return round($kwh);
    }

    function printArray($array)
    {
        foreach ($array as $key => $value) {
            $result = $value['supplier'].",".$value['plan'].",".$value['total']."\n";
            fwrite(STDOUT, $result);
        }
    }

    function planPriceCalculations($array,$kwh)
    {
        foreach ($array as $key => $value) {
            //check if suplier or plan or rates has not been provided.
            if(!array_key_exists("supplier", $value)){
                throw new Exception("One of the required parametre is not exist : supplier", 1);
            }
            if(!array_key_exists("plan", $value)){
                throw new Exception("One of the required parametre is not exist : plan", 1);
            }
            if(!array_key_exists("rates", $value)){
                throw new Exception("One of the required parametre is not exist : rates", 1);
            }
            $rates = $this->calculatePrice($value['rates'],$kwh);
            
            //if there is standing_charge calculate it before the tax
            if(array_key_exists("standing_charge", $value)){
                $standing = $this->calculateStandigCharge($value['standing_charge']);
                $rates += $standing;
            }
        
            $addedTax = $this->calculateTax($rates);
            $pennyToPounds = $addedTax / 100;
            $rounded = round($pennyToPounds,2);
            $array[$key]['total'] = $rounded;
        }
        return $array;
    }

    function calculatePrice($array,$kwh)
    {
        $total = 0;
        //if only one that means there is no threshold so just calculate

        if(count($array)>1){
            foreach ($array as $key => $value) {
                if(array_key_exists("threshold", $value)){
                    $total += $value['threshold'] * $value['price'];
                    $kwh = $kwh - $value['threshold'];
                    unset($array[$key]);
                }
            }
        }
        
        foreach ($array as $value) {
            if(array_key_exists("price", $value)){
                $total += $kwh * $value['price'];
            }
        }

        return $total;
    }

    function calculateTax($price)
    {
        $tax = 0.05;
        return $price + ($price * $tax);
    }

    function calculateWithoutTax($price)
    {
        return $price / 1.05;
    }

    function calculateStandigCharge($charge)
    {
        $day = 365;
        return $day * $charge;
    }

    function sortArray($array, $key = 'total')
    {
        usort($array, function ($a, $b) use ($key) {return $a[$key] > $b[$key];});
        return $array;
    }
}

$plans = new Plans;
//get file name by arg
$plans->start($argv[1]);