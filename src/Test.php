<?php
use Firezihai\AddressSmartParse\AddressSmartParse;

require './AddressSmartParse.php';


$address = '18000000000 贵州黔南州平塘县卡蒲毛南族乡河中村 李子海 ';



class RegionService {
    
    public function getRegion($name)
    {
        echo $name."==name\r\n";
        if ($name == '贵州') {
            return [
                'name'=>'贵州'
            ];
        }
        return '';
    }
}

$service = new RegionService();
$smartParse = new AddressSmartParse([$service,'getRegion']);
$res = $smartParse->parse($address);
print_r($res);
