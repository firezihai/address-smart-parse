<?php

declare(strict_types=1);

namespace Firezihai\AddressSmartParse;

class AddressSmartParse
{
    /**
     * 数据源类型.
     * @var number
     */
    private $type;

    private $nameMaxLength = 5;

    private $region;

    private $names;

    private $province = [];

    private $city = [];

    private $district = [];

    private $town = [];

    private $callback;

    /**
     * @param int $type 1本地数据2数据库
     * @param null|mixed $callback
     */
    public function __construct($callback = null)
    {
        $this->names = json_decode(file_get_contents(__DIR__ . '/data/names.json'), true);
        $this->callback = $callback;
        if (empty($this->callback)) {
            $this->loadLocalSource();
        }
    }

    /**
     * 解析地址
     * @param string $address
     * @return string
     */
    public function parse($address)
    {
        if (! empty($this->callback) && ! is_callable($this->callback)) {
            throw new \Exception('callback 不是合法的可调用结构');
        }
        // 将180-1111-1111 替换成18011111111
        $address = preg_replace('/(\d{3})-(\d{4})-(\d{4})/', '$1$2$3', $address);

        $address = $this->cleanAddress($address);
        // 解新
        $mobile = $this->parseMobile($address);
        if ($mobile) {
            $address = str_replace($mobile, ' ', $address);
        }

        $unit = ['省', '特别行政区', '自治区'];
        $province = '';
        $address = trim($address);
        $result = $this->parseRegion($address, $unit, 0, $this->province);
        if (! empty($result['match'])) {
            $province = $result['match'];
            $address = $result['address'];
        }

        $unit = ['州', '盟', '市', '自治州', '自治盟'];
        $city = '';
        $parentId = $province ? intval($province['id']) : 0;
        $result = $this->parseRegion($address, $unit, $parentId, $this->city);
        if (! empty($result['match'])) {
            $city = $result['match'];
            $address = $result['address'];
        }

        $unit = ['区', '县', '自治县', '旗', '市'];
        $district = '';
        $parentId = $city ? intval($city['id']) : $parentId;
        $result = $this->parseRegion($address, $unit, $parentId, $this->district);
        if (! empty($result['match'])) {
            $district = $result['match'];
            $address = $result['address'];
        }
        $unit = ['镇', '乡', '街道'];
        $town = '';
        $parentId = $district ? intval($district['id']) : $parentId;
        $result = $this->parseRegion($address, $unit, $parentId, $this->town);
        if (! empty($result['match'])) {
            $town = $result['match'];
            $address = $result['address'];
        }
        // 使用空格拆分地址信息
        $splitAddress = explode(' ', $address);
        // 过滤多余的空格
        $splitAddress = array_filter(array_map(function ($item) {
            return trim($item);
        }, $splitAddress));

        // 解析姓名
        $name = '';
        foreach ($splitAddress as $value) {
            if (empty($name)) {
                $name = $this->parseName($value);
            }
        }
        // 过滤掉数组中的姓名
        $detail = array_unique(array_filter($splitAddress, function ($item) use ($name) {
            return $item !== $name;
        }));

        // 将剩下的文字拼接成为详细地址
        $address = $detail ? join('', $detail) : '';

        $region['province'] = $province['name'] ?? '';
        $region['city'] = $city['name'] ?? '';
        $region['district'] = $district['name'] ?? '';
        $region['town'] = $town['name'] ?? '';
        $region['mobile'] = $mobile;
        $region['name'] = $name;
        $region['address'] = $address;
        return $region;
    }

    /**
     * 识别地址
     * @param string $address 待识别的地址
     * @param array $unit 行政单位
     * @param int $parentRegionId 上一级地区id
     * @param array $regionList 地区数据源
     * @return array
     */
    public function parseRegion(string $address, array $unit, int $parentRegionId = 0, array $regionList = [])
    {
        if (! empty($this->callback)) {
            return $this->callbackMatchRegion($address, $unit, $parentRegionId, $regionList);
        }
        return $this->localSourceMatchRegion($address, $unit, $parentRegionId, $regionList);
    }

    /**
     * 使用回调方式匹配.
     * @param string $address 待识别的地址
     * @param array $unit 行政单位
     * @param int $parentRegionId 上一级地区id
     * @return array
     * @throws \Exception
     */
    public function callbackMatchRegion(string $address, array $unit, int $parentRegionId = 0)
    {
        $address = trim($address);
        $length = mb_strlen($address);
        // 地区最多15个字 积石山保安族东乡族撒拉族自治县 双江拉祜族佤族布朗族傣族自治县
        $length = $length > 15 ? 15 : $length;
        $match = '';
        // 市、县、乡除去行政单位，可能除只剩一个字的情况 例如横县 除去行政单位名称，就只有一个字,
        $start = $parentRegionId ? 1 : 2;
        for ($i = $start; $i <= $length; ++$i) {
            $temp = mb_substr($address, 0, $i);
            $unitTemp = mb_substr($address, $i, 1); // 尝试往后获取一个字符，看是否是行政单位

            if (empty($temp)) {
                throw new \Exception('地址信息为空');
            }
            if (in_array($unitTemp, $unit)) {
                if (is_array($this->callback)) {
                    $match = call_user_func_array($this->callback, [$temp . $unitTemp]);
                    if ($match === false) {
                        throw new \Exception('地址信息为空');
                    }
                } else {
                    $match = call_user_func($this->callback, $temp . $unitTemp);
                }
                // 未匹配
                if ($match && $parentRegionId && $parentRegionId != $match['parent_id']) {
                    $match = '';
                }
            } else {
                foreach ($unit as $v) {
                    if (! empty($v)) {
                        if (is_array($this->callback)) {
                            $match = call_user_func_array($this->callback, [$temp . $v]);
                            if ($match === false) {
                                throw new \Exception('回调参数调用失败');
                            }
                        } else {
                            $match = call_user_func($this->callback, $temp . $v);
                        }
                        // 已匹配 并且没有上一级
                        if ($match && empty($parentRegionId)) {
                            break;
                        }
                        if ($match && $parentRegionId && $parentRegionId == $match['parent_id']) {
                            break;
                        }
                    }
                }
            }

            if ($match) {
                $temp = $this->filterRegionUnit($temp, $address, $unit);
                $address = str_replace($temp, '', $address);
                break;
            }
        }
        return [
            'match' => $match,
            'address' => $address,
        ];
    }

    /**
     * @return array
     */
    public function localSourceMatchRegion(string $address, array $unit, int $parentRegionId = 0, array $regionList = [])
    {
        $address = trim($address);
        $match = '';
        foreach ($regionList as $region) {
            $region_name = $region['name'];
            $matchRegionName = '';
            $length = mb_strlen($region_name);
            for ($i = $length; $i > 1; --$i) {
                $temp = mb_substr($region_name, 0, $i);

                if (mb_strpos($address, $temp) !== false) {
                    // 限制父类id是否和匹配的上一次地址id一致
                    if ($parentRegionId && $parentRegionId == $region['parent_id']) {
                        $matchRegionName = $temp;
                        break;
                    }
                    if (empty($parentRegionId)) {
                        $matchRegionName = $temp;
                        break;
                    }
                }
            }
            if (! empty($matchRegionName)) {
                // 加行政后缀匹配清洗地址
                $matchRegionName = $this->filterRegionUnit($matchRegionName, $address, $unit);
                $match = $region;
                $address = str_replace($matchRegionName, '', $address);
                break;
            }
        }
        return [
            'match' => $match,
            'address' => $address,
        ];
    }

    /**
     * 尝试添加行政单位进行匹配.
     * @param string $match 待匹配字符
     * @param string $address 查找源
     * @param array $unit 要附加到 $match 后面的行政单位
     * @return string
     */
    public function filterRegionUnit(string $match, string $address, array $unit)
    {
        if (empty($unit)) {
            return $match;
        }
        foreach ($unit as $v) {
            $temp = $match . $v;
            if (mb_strpos($address, $temp) !== false) {
                $match = $temp;
                break;
            }
        }
        return $match;
    }

    /**
     * 地址清洗，去除特殊字符、符点符号、中文字符、特殊文字.
     * @param string $address
     * @return string
     */
    public function cleanAddress($address)
    {
        $address = str_replace([
            "\r\n",
            "\r",
            "\n",
            "\t",
        ], ' ', $address);
        // 多余文字
        $search = [
            '详细地址',
            '收货地址',
            '收件地址',
            '地址',
            '所在地区',
            '地区',
            '姓名',
            '收货人',
            '收件人',
            '联系人',
            '收',
            '邮编',
            '联系电话',
            '电话',
            '联系人手机号码',
            '手机号码',
            '手机号',
            '自治区直辖县级行政区划',
            '省直辖县级行政区划',
        ];

        foreach ($search as $value) {
            $address = str_replace($value, ' ', $address);
        }
        // 去除特责殊字符
        $reg = "/[`~!@#$^&*()=|{}':;',\\[\\].<>\\/?~！@#￥……&*（）——|{}【】‘；：”“’。，、？]/u";
        $address = preg_replace($reg, ' ', $address);
        // 将两个及以上的空格替换成一个空格
        return preg_replace('/\s{2,}/u', ' ', $address);
    }

    /**
     * 解析手机号码
     * @param string $address
     * @return mixed|string
     */
    public function parseMobile($address)
    {
        $match = [];
        $phone = '';
        // 电话匹配
        if (preg_match('/(86)?-?[1][0-9]{10}/', $address, $match)) {
            $phone = $match[0];
        }
        return $phone;
    }

    /**
     * 解析姓名.
     * @param string $address
     * @return string
     */
    public function parseName($address)
    {
        $names = $this->names;
        $nameFirst = mb_substr($address, 0, 1);
        $nameLen = mb_strlen($address);
        if ($nameLen <= $this->nameMaxLength && $nameLen > 1 && in_array($nameFirst, $names, true)) {
            return $address;
        }

        return '';
    }

    /**
     * 加载本地数据源.
     */
    private function loadLocalSource()
    {
        $this->region = json_decode(file_get_contents(__DIR__ . '/data/region.json'), true);
        // 将地区居源拆成省、市、区、镇，减少地区匹配时的循环次数
        foreach ($this->region as $value) {
            switch ($value['type']) {
                case 'provice':
                    $this->province[] = $value;
                    break;
                case 'city':
                    $this->city[] = $value;
                    break;
                case 'district':
                    $this->district[] = $value;
                    break;
                case 'town':
                    $this->town[] = $value;
                    break;
            }
        }
    }
}
