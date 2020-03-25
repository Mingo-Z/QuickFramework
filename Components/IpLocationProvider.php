<?php
namespace Qf\Components;

use Qf\Kernel\Exception;

class IpLocationProvider extends Provider
{
    public $dbFilePath;

    const AREA_REDIRECT_MODE_0 = 0x0;
    const AREA_REDIRECT_MODE_1 = 0x1;
    const AREA_REDIRECT_MODE_2 = 0x2;

    /**
     * @var \SplFileObject
     */
    protected $dbFileObject;

    public function init()
    {
        if (!$this->dbFilePath || !is_readable($this->dbFilePath)) {
            throw new Exception('IP database file does not exist');
        } else {
            $this->dbFileObject = new \SplFileObject($this->dbFilePath);
        }
    }

    protected function getLongNum()
    {
        $long = 0;
        if(($result = unpack('Vlong', $this->dbFileObject->fread(4)))) {
            $long = $result['long'];
        }

        return $long;
    }

    protected function getLongNum3()
    {
        $long = 0;
        if(($result = unpack('Vlong', $this->dbFileObject->fread(3) . chr(0)))) {
            $long = $result['long'];
        }

        return $long;
    }

    public function getLocation($strIp)
    {
        $location = null;
        $longIp = ip2long($strIp);
        if ($longIp > 0) {
            $offsetPos = $this->getOffsetPos($longIp);
            $location = $this->getOffsetPosLocation($offsetPos);
        }

        return $location;
    }

    protected function getOffsetPosLocation($offsetPos)
    {
        $location = [
            'country' => '',
            'area' => '',
        ];

        $this->dbFileObject->fseek($offsetPos + 4);
        $segIpOffsetPos = $this->getLongNum3();
        $this->dbFileObject->fseek($segIpOffsetPos + 4);
        $redirectMode = $this->dbFileObject->fread(1);
        switch (ord($redirectMode)) {
            case self::AREA_REDIRECT_MODE_1:
                $cntOffsetPos = $this->getLongNum3();
                $this->dbFileObject->fseek($cntOffsetPos);
                $c = $this->dbFileObject->fread(1);
                if (ord($c) == self::AREA_REDIRECT_MODE_2) {
                    $this->dbFileObject->fseek($this->getLongNum3());
                    $location['country'] = $this->getName();
                    $this->dbFileObject->fseek($cntOffsetPos + 4);
                    $location['area'] = $this->getAreaName();
                } else {
                    $location['country'] = $this->getName($c);
                    $location['area'] = $this->getAreaName();
                }
                break;
            case self::AREA_REDIRECT_MODE_2:
                $cntOffsetPos = $this->getLongNum3();
                $this->dbFileObject->fseek($cntOffsetPos);
                $location['country'] = $this->getName($redirectMode);
                $this->dbFileObject->fseek($segIpOffsetPos + 8);
                $location['area'] = $this->getAreaName();
                break;
            default:
                $location['country'] = $this->getName($redirectMode);
                $location['area'] = $this->getAreaName();
        }
        if ($location['country'] == 'CZ88.NET') {
            $location['country'] = '';
        }
        if ($location['area'] == 'CZ88.NET') {
            $location['area'] = '';
        }
        if ($location['country']) {
            $location['country'] = iconv('gbk', 'utf-8', $location['country']);
        }
        if ($location['area']) {
            $location['area'] = iconv('gbk', 'utf-8', $location['area']);
        }

        return $location;
    }

    protected function getName($add = '')
    {
        $name = $add;
        while (($c = $this->dbFileObject->fread(1)) != chr(0)) {
            $name .= $c;
        }

        return $name;
    }

    protected function getAreaName()
    {
        $name = '';
        $redirectMode = $this->dbFileObject->fread(1);
        switch (ord($redirectMode)) {
            case self::AREA_REDIRECT_MODE_0:
                break;
            case self::AREA_REDIRECT_MODE_1:
            case self::AREA_REDIRECT_MODE_2:
                $this->dbFileObject->fseek($this->getLongNum3());
                $name = $this->getName();
                break;
            default:
                $name= $this->getName($redirectMode);

        }

        return $name;
    }

    protected function getOffsetPos($srcLongIp)
    {
        $beginLongIp = $this->getLongNum();
        $endLongIp = $this->getLongNum();
        $totalIpNum = (int)(($endLongIp - $beginLongIp) / 7);
        $offsetPos = $endLongIp;
        $beginIndex = 0;
        $endIndex = $totalIpNum;
        while ($beginIndex <= $endIndex) {
            $middleIndex = (int)(($beginIndex + $endIndex) / 2);
            $this->dbFileObject->fseek($beginLongIp + $middleIndex * 7);
            $thisBeginLongIp = $this->getLongNum();
            if ($srcLongIp < $thisBeginLongIp) {
                $endIndex -= 1;
            } else {
                $this->dbFileObject->fseek($this->getLongNum3());
                $thisEndLongIp = $this->getLongNum();
                if ($srcLongIp > $thisEndLongIp) {
                    $beginIndex += 1;
                } else {
                    $offsetPos = $beginLongIp + $middleIndex * 7;
                    break;
                }
            }
        }

        return $offsetPos;
    }

    public function __destruct()
    {
        if ($this->dbFileObject) {
            $this->dbFileObject = null;
        }
    }

}
