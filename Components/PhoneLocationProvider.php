<?php
namespace Qf\Components;

use Qf\Kernel\Exception;

/**
 *
 *手机归属地判断
 *
 * @version $Id: $
 */
class PhoneLocationProvider extends Provider
{
    const SUPPORT_AREA_FILE_VERSION = 1;
    const SUPPORT_AREA_FILE_ID = 17483;
    const SUPPORT_AREA_FILE_HEAD_LENGTH = 100;
    
    const SUPPORT_AREA_INDEX_LENGTH = 4;
    const SUPPORT_AREA_MAX_NUMBER_LENGTH = 8;
    const SUPPORT_AREA_MIN_NUMBER_LENGTH = 2;
    const SUPPORT_AREA_MAX_NAME_LENGTH = 50;
    
    /**
     * 归属地数据文件路径
     *
     * @var string
     */
    public $fileDbPath;
    
    protected $fileDbHandle;
    
    /**
     * 归属地数据文件头部信息
     *
     * @var array
     */
    protected $fileDbHeadInfo;
    
    /**
     * 归属地数据文件前置索引段
     *
     * @var string
     */
    protected $filePrefixIndex;
    
    protected $inited;
    
    public function __construct()
    {
        $this->fileDbPath = '';
        $this->fileDbHandle = null;
        $this->fileDbHeadInfo = array(
            'id' => 0,
            'version' => 0,
            'separator' => '',
            'name' => '',
            'dateModified' => '',
            'internationalAccessCode' => '',
            'internationalCode' => '',
            'countryCode' => '',
            'longDistanceCode' => '',
            'phoneMinLength' => 0,
            'phoneMaxLength' => 0,
            'fillTotal' => 0,
            'prefixIndexOffset' => 0,
            'prefixIndexSize' => 0,
            'suffixIndexOffset' => 0,
            'suffixIndexSize' => 0,
            'nameDataOffset' => 0,
            'nameDataSize' => 0,
            'nodeDataOffset' => 0,
            'nodeDataSize' => 0
        );
        $this->inited = false;
        $this->filePrefixIndex = '';
    }
    
    public function init()
    {
        if (!$this->inited) {
            if (!$this->fileDbPath || !is_file($this->fileDbPath) || !is_readable($this->fileDbPath)) {
                throw new Exception("MobileLocation fileDbPath not setting or is not a readable file", false, false);
            }
            $this->fileDbHandle = fopen($this->fileDbPath, 'rb');
            if (!$this->fileDbHandle) {
                throw new Exception("MobileLocation {$this->fileDbPath} file open failed");
            } else {
                if ($this->parseFileDbHeadInfo()) {
                    if (!$this->fileDbHeadInfo['separator']) {
                        $this->fileDbHeadInfo['separator'] = '|'; // 默认归属地与运营商分隔符
                    }
                    // 前索引数据段
                    fseek($this->fileDbHandle, $this->fileDbHeadInfo['prefixIndexOffset'], SEEK_SET);
                    $this->filePrefixIndex = fread($this->fileDbHandle, $this->fileDbHeadInfo['prefixIndexSize']);
                    $this->inited = true;
                }
            }
        }
    }
    
    /**
     * 解析归属地数据文件头部信息
     *
     * @return bool
     */
    protected function parseFileDbHeadInfo()
    {
        $ret = false;
        // 区域文件头部格式
        $format = 'vid/vversion';
        $format .= '/c4separator/c16name/c16dateModified';
        $format .= '/c5internationalAccessCode/c5internationalCode';
        $format .= '/c5internationalCode/c5longDistanceCode';
        $format .= '/vphoneMinLength/vphoneMaxLength';
        $format .= '/VfillTotal/VprefixIndexOffset/VprefixIndexSize';
        $format .= '/VsuffixIndexOffset/VsuffixIndexSize';
        $format .= '/VnameDataOffset/VnameDataSize';
        $format .= '/VnodeDataOffset/VnodeDataSize';
        $array = unpack($format, fread($this->fileDbHandle, self::SUPPORT_AREA_FILE_HEAD_LENGTH));
        if ($array) {
            foreach ($array as $key => $value) {
                if (isset($this->fileDbHeadInfo[$key])) {
                    $this->fileDbHeadInfo[$key] = $value;
                } elseif ($value) {
                    $realKey = rtrim($key, '0...9');
                    if (isset($this->fileDbHeadInfo[$realKey]) && $value) {
                        $this->fileDbHeadInfo[$realKey] .= chr($value);
                    }
                }
                if ($this->fileDbHeadInfo['id'] == self::SUPPORT_AREA_FILE_ID && $this->fileDbHeadInfo['version'] == self::SUPPORT_AREA_FILE_VERSION
                && $this->fileDbHeadInfo['prefixIndexOffset'] && $this->fileDbHeadInfo['prefixIndexSize']
                && $this->fileDbHeadInfo['suffixIndexOffset'] && $this->fileDbHeadInfo['suffixIndexSize']
                && $this->fileDbHeadInfo['nameDataOffset'] && $this->fileDbHeadInfo['nameDataSize']
                && $this->fileDbHeadInfo['nodeDataOffset'] && $this->fileDbHeadInfo['nodeDataSize']
                && $this->fileDbHeadInfo['phoneMinLength'] > 0 && $this->fileDbHeadInfo['phoneMaxLength'] > 0) {
                    $ret = true;
                }
            }
        }
        return $ret;
    }
    
    /**
     * 格式化电话号码
     *
     * @param string $num 电话号码
     * @return array
     */
    protected function encode($num)
    {
        $ret = array(
            'num' => '',
            'numIndexLen' => 0,
            'isLongDistanceCode' => 0
        );

        $isLongDistanceCode = 0;
        
        $num = ltrim($num, '+');
        $internationalAccessCodeLen = strlen($this->fileDbHeadInfo['internationalAccessCode']);
        $internationalCodeLen = strlen($this->fileDbHeadInfo['internationalCode']);
        $countryCodeLen = strlen($this->fileDbHeadInfo['countryCode']);
        $longDistanceCodeLen = strlen($this->fileDbHeadInfo['longDistanceCode']);
        if (!strncmp($num, $this->fileDbHeadInfo['internationalAccessCode'], $internationalAccessCodeLen)) {
            $num = substr($num, $internationalAccessCodeLen);
        } elseif(!strncmp($num, $this->fileDbHeadInfo['internationalCode'], $internationalCodeLen)) {
            $num = substr($num, $internationalCodeLen);
        }
        if (!strncmp($num, $this->fileDbHeadInfo['countryCode'], $countryCodeLen)) {
            $num = substr($num, $countryCodeLen);
            $isLongDistanceCode = 1;
        }
        if (!strncmp($num, $this->fileDbHeadInfo['longDistanceCode'], $longDistanceCodeLen)) {
            $num = substr($num, $longDistanceCodeLen);
            $isLongDistanceCode = 1;
        }
        $numIndexLen = strlen($num);
        if ($numIndexLen >= $this->fileDbHeadInfo['phoneMinLength']) {
            if ($numIndexLen > $this->fileDbHeadInfo['phoneMaxLength']) {
                $numIndexLen = $this->fileDbHeadInfo['phoneMaxLength'];
            }
            $ret['num'] = $num;
            $ret['numIndexLen'] = $numIndexLen;
            $ret['isLongDistanceCode'] = $isLongDistanceCode;
        }

        return $ret;
    }
    
    protected function getNumIndex($num, $numIndexLen)
    {
        $ret = array(
            'prefixIndex' => 0,
            'suffixIndex' => 0
        );

        if ($numIndexLen > self::SUPPORT_AREA_MAX_NUMBER_LENGTH) {
            $numIndexLen = self::SUPPORT_AREA_MAX_NUMBER_LENGTH;
        } elseif ($numIndexLen < self::SUPPORT_AREA_INDEX_LENGTH) {
            $numIndexLen = self::SUPPORT_AREA_INDEX_LENGTH;
        }
        $ret['prefixIndex'] = intval('1' . substr($num, 0, self::SUPPORT_AREA_INDEX_LENGTH));
        if ($numIndexLen > self::SUPPORT_AREA_INDEX_LENGTH) {
            $ret['suffixIndex'] = intval('1' . substr($num, self::SUPPORT_AREA_INDEX_LENGTH,
                    $numIndexLen - self::SUPPORT_AREA_INDEX_LENGTH));
        }
        return $ret;
    }
    
    /**
     * 电话号码前置索引位置搜索
     *
     * @param int $numPrefixIndex 电话号码前置索引
     * @return float|int
     */
    protected function searchPrefixIndexPos($numPrefixIndex)
    {
        $indexPos = -1;
        $start = $mid = 0;
        $end = $this->fileDbHeadInfo['prefixIndexSize'] / 2;
        $findPrefixIndex = 0;
        while ($start <= $end) {
            $mid = floor(($start + $end) / 2);
            $array = unpack('v', substr($this->filePrefixIndex,  ($mid - 1) * 2, 2));
            $findPrefixIndex = $array[1];
            if ($findPrefixIndex > $numPrefixIndex) {
                $end = $mid - 1;
            } elseif ($findPrefixIndex < $numPrefixIndex) {
                $start = $mid + 1;
            } else {
                $indexPos = $mid - 1;
                break;
            }
        }
        return $indexPos;
    }
    
    /**
     * 电话号码归属地、运营商查询
     *
     * @param $num 带区号的座机和、机号码
     * @return array
     */
    public function getLocation($num)
    {
        static $numLocations = array();
        $ret = array(
            'location' => '',
            'sp' => ''
        );
        if (!isset($numLocations[$num])) {
            $this->init();
            if ($this->inited) {
                $numInfo = $this->encode($num);
                if ($numInfo['num']) {
                    $prefixLen = self::SUPPORT_AREA_INDEX_LENGTH;
                    $suffixLen = 0;
                    if ($numInfo['numIndexLen'] >= self::SUPPORT_AREA_INDEX_LENGTH) {
                        $suffixLen = $numInfo['numIndexLen'] - self::SUPPORT_AREA_INDEX_LENGTH;
                    }
                    $numIndex = $this->getNumIndex($numInfo['num'], $numInfo['numIndexLen']);
                    $findPrefixIndex = $numIndex['prefixIndex'];
                    if ($findPrefixIndex) {
                        for (; ;) {
                            if (($prefixIndexPos = $this->searchPrefixIndexPos($findPrefixIndex)) >= 0) {
                                fseek($this->fileDbHandle, $this->fileDbHeadInfo['suffixIndexOffset'] + $prefixIndexPos * 8, SEEK_SET);
                                $findSuffixIndexInfo = unpack('vstart/vend/VnodeIndex', fread($this->fileDbHandle, 8));
                                $findSuffixIndex = $prefixLen < self::SUPPORT_AREA_INDEX_LENGTH ? 0 : $numIndex['suffixIndex'];
                                for (; ;) {
                                    // $findSuffixIndexInfo['nodeIndex'] > 1时,左移31位,uint32将溢出,最终值为0
//                                    $uInt32s = unpack('V', pack('V', $findSuffixIndexInfo['nodeIndex'] << 31)); // 32 bit unsigned int
                                    if ($findSuffixIndex >= $findSuffixIndexInfo['start'] &&
                                        $findSuffixIndex <= $findSuffixIndexInfo['end'] &&
                                        ($findSuffixIndexInfo['nodeIndex'] > 1 || ($findSuffixIndexInfo['nodeIndex'] & $numInfo['isLongDistanceCode']))
                                    ) {
                                        fseek($this->fileDbHandle, $this->fileDbHeadInfo['nodeDataOffset'] +
                                            (($findSuffixIndexInfo['nodeIndex'] >> 1) + $findSuffixIndex - $findSuffixIndexInfo['start']) * 4, SEEK_SET);
                                        $nodeInfo = unpack('vnameOffset/vtypeOffset', fread($this->fileDbHandle, 4));
                                        if ($nodeInfo['nameOffset']) {
                                            fseek($this->fileDbHandle, $this->fileDbHeadInfo['nameDataOffset'] + $nodeInfo['nameOffset'], SEEK_SET);
                                            $string = fread($this->fileDbHandle, self::SUPPORT_AREA_MAX_NAME_LENGTH * 2);
                                            $ret['location'] = substr($string, 0, strpos($string, "\x00"));
                                            if ($nodeInfo['typeOffset']) {
                                                fseek($this->fileDbHandle, $this->fileDbHeadInfo['nameDataOffset'] + $nodeInfo['typeOffset'], SEEK_SET);
                                                $string = fread($this->fileDbHandle, self::SUPPORT_AREA_MAX_NAME_LENGTH * 2);
                                                $ret['sp'] = substr($string, 0, strpos($string, "\x00"));
                                            }
                                            $numLocations[$num] = $ret;
                                            break;
                                        }
                                    }
                                    if (--$suffixLen < 0) {
                                        break;
                                    }
                                    if (($findSuffixIndex = (int)($findSuffixIndex / 10)) == 1) {
                                        $findSuffixIndex = 0;
                                    }
                                }
                            }
                            if (--$prefixLen < $this->fileDbHeadInfo['phoneMinLength']) {
                                break;
                            }
                            $findPrefixIndex = (int)($findPrefixIndex / 10);
                        }
                    }
                }
            }
        } else {
            $ret = $numLocations[$num];
        }
        return $ret;
    }
    
    public function __destruct()
    {
        if ($this->fileDbHandle) {
            fclose($this->fileDbHandle);
        }
        $this->fileDbPath = '';
        $this->fileDbHandle = null;
        $this->fileDbHeadInfo = array();
        $this->filePrefixIndex = '';
        $this->inited = false;
    }
}