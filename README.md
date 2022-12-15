# address-smart-parse

# 使用默认数据源

```php
$address = '18000000000 贵州黔南州平塘县卡蒲毛南族乡河中村 李子海 ';

$smartParse = new AddressSmartParse();

$res = $smartParse->parse($address);

print_r($res);
//output:
/* Array
(
    [province] => 贵州省
    [city] => 黔南布依族苗族自治州
    [district] => 平塘县
    [town] => 卡蒲毛南族乡
    [mobile] => 18000000000
    [name] => 李子海
    [address] => 河中村
)
*/


```

# 自定义数据源


```php


class RegionService {
    
    public function getRegion($regionName)
    {
      
        $region = Region::query()->where('name','=',$name)->first(['id','name','parent_id']);
        return $region  ? $region->toArray() : '';

    }
}

$service = new RegionService();
$address = '18000000000 广东省中山市南头镇民安社区 ';

$service = new RegionService();
$smartParse = new AddressSmartParse([$service,'getRegion']);
$res = $smartParse->parse($address);
print_r($res);

//output:
/* Array
(
    [province] => 广东省
    [city] => 中山市
    [district] => 
    [town] => 南头镇
    [mobile] => 18000000000
    [name] => 李子海
    [address] => 南头大道中59号之
)
*/


```
