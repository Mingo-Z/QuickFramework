<?php
namespace Qf\Components;

class IdGeneratorProvider extends Provider
{
    public $machineId;
    /**
     * millisecond
     *
     * @var int
     */
    public $startTimestampTs;

    protected static $lastTimestamp;
    protected static $lastTimestampIndex;

    protected static $processId;

    public function __construct()
    {
        self::$processId = posix_getpid();
        $this->machineId = self::$processId;
        $this->startTimestampTs = 1545387360555;
    }

    public function init()
    {
    }

    public function getIds($num)
    {
        $ids = [];
        $num = (int)$num;
        while ($num-- > 0) {
            $ids[] = $this->getId();
        }

        return $ids;
    }

    public function getId()
    {
        $msi = self::getTimestampIndex(getNowTimestampMs());
        return ($msi[0] - $this->startTimestampTs) << 22 | ($this->machineId & 1023) << 12 | $msi[1];
    }

    protected static function getTimestampIndex($ms)
    {
        if (self::$lastTimestamp == $ms) {
            if (self::$lastTimestampIndex >= 4095) {
                while ((self::$lastTimestamp = getNowTimestampMs()) == $ms) ;
                self::$lastTimestampIndex = 0;
            } else {
                self::$lastTimestampIndex++;
            }
        } else {
            self::$lastTimestamp = $ms;
            self::$lastTimestampIndex = 0;
        }

        return [self::$lastTimestamp, self::$lastTimestampIndex];
    }

}