<?php
namespace Qf\Components\RateLimit;

use Qf\Components\Facades\Cache;
use Qf\Components\Facades\Log;

class TokenBucketRateLimit extends RateLimit
{
    const REDIS_LUA_GET_TOKENS_SCRIPT = <<<CODE
    local bucketName = KEYS[1]
    local bucketNewTokenRate = tonumber(ARGV[1]) -- 产生令牌的速率
    local bucketCap = tonumber(ARGV[2]) -- 桶容量
    
    local requestTime = tonumber(ARGV[3]) -- 请求时间，单位：微秒
    local requestTokens = tonumber(ARGV[4]) -- 请求的令牌数
    local isAllow = 0 -- 是否允许
    local jsonValue = {}
    
    local origValue = redis.call('hget', 'tokenBucketPool', bucketName) -- bucketName桶状态json字符串
    if origValue then
        jsonValue = cjson.decode(origValue)
        local deltaNs = math.max(0, requestTime - jsonValue.lastRequestedTime)
        jsonValue.lastRequestedTime = requestTime -- 更新最近请求时间
        local newTokens = math.floor(deltaNs * bucketNewTokenRate) -- 增加的令牌数
        local remainedTokens = jsonValue.tokens + newTokens
        if remainedTokens >= requestTokens then
            remainedTokens = remainedTokens - requestTokens
            isAllow = 1
        end
        jsonValue.tokens = math.min(bucketCap, remainedTokens) -- 桶可用的令牌数量
    else
        jsonValue = {lastRequestedTime = requestTime, tokens = bucketCap}
    end
    redis.call('hset', 'tokenBucketPool', bucketName, cjson.encode(jsonValue))
    
    return {isAllow, jsonValue.tokens}
CODE;

    /**
     * 桶容量
     *
     * @var int
     */
    public $bucketCap;

    /**
     * 桶可用令牌数量
     *
     * @var int
     */
    public $bucketTokens;

    /**
     * 产生令牌的速率，n/ms
     *
     * @var double
     */
    public $newTokenRate;

    /**
     * @param string $requestId 请求标识
     * @return bool
     */
    public function isAllow($requestId)
    {
        $isAllow = true;

        $result =Cache::evalLuaCode(self::REDIS_LUA_GET_TOKENS_SCRIPT, 1,
        $requestId, $this->newTokenRate, $this->bucketCap, getNowTimestampMs(), 1);

        if (is_array($result) && isset($result[0])) {
            $isAllow = ($result[0] === 1);
        }

        return $isAllow;
    }

}
