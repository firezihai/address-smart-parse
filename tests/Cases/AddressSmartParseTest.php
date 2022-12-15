<?php

declare(strict_types=1);

namespace tests\Cases;

use Firezihai\AddressSmartParse\AddressSmartParse;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AddressSmartParseTest extends TestCase
{
    public function testParse()
    {
        $addressSmartParse = new AddressSmartParse();
        $address = '18000000000 贵州黔南州平塘县卡蒲毛南族乡河中村 李子海 ';
        $info = $addressSmartParse->parse($address);
        $result = [
            'province' => '贵州省',
            'city' => '黔南布依族苗族自治州',
            'district' => '平塘县',
            'town' => '卡蒲毛南族乡',
            'address' => '河中村',
            'name' => '李子海',
            'mobile' => '18000000000',
        ];

        $this->assertEquals($info, $result);
    }
}
