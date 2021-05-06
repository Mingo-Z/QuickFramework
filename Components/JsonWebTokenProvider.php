<?php
namespace Qf\Components;

use Qf\Kernel\Exception;
use Qf\Utils\OtherHelper;

class JsonWebTokenProvider extends Provider
{
    /**
     * 支持的签名算法
     * todo support openssl sign
     * @var array
     */
    static protected $algTable = [
        'HS256' => ['func' => 'hmac', 'alg' => 'sha256']
    ];

    /**
     * 签名密钥
     *
     * @var string
     */
    public $securityKey;

    /**
     * 签发人
     *
     * @var string
     */
    public $iss;

    /**
     * 主题
     *
     * @var string
     */
    public $sub;

    /**
     * 受众，用户或应用等
     *
     * @var string
     */
    public $aud;

    /**
     * 有效时间，单位：毫秒
     *
     * @var int
     */
    public $lifetime;

    const JWT_ERR_PAYLOAD_EMPTY = 'JsonWebToken payload can not empty';
    const JWT_ERR_NOT_SUPPORT_ALG = 'JsonWebToken not support signature algorithm';
    const JWT_ERR_TOKEN_HAS_EXPIRED = 'Token has expired';
    const JWT_ERR_TOKEN_IS_NOT_VALID = 'Token is not valid';
    const JWT_ERR_TOKEN_IS_WRONG_TIME = 'Token is wrong issuance time';
    const JWT_ERR_TOKEN_IS_WRONG_FORMAT = 'Token is wrong format';
    const JWT_ERR_SIGNATURE_VERIFICATION_FAILED = 'Signature verification failed';


    /**
     * 生成json web token
     *
     * @param array $payload 业务字段
     * @param string $algName 签名算法
     * @param int $lifetime 有效时间，单位：毫秒
     * @return string
     * @throws Exception
     */
    public function encodeJwt(array $payload, $algName = 'HS256', $lifetime = null)
    {
        if (!$payload) {
            throw new Exception(self::JWT_ERR_PAYLOAD_EMPTY);
        } elseif (!$algName || !isset(self::$algTable[$algName])) {
            throw new Exception(self::JWT_ERR_NOT_SUPPORT_ALG);
        }

        $lifetime = is_null($lifetime) ? $this->lifetime : (int)$lifetime;
        $headerArray = [
            'alg' => $algName,
            'type' => 'jwt',
        ];
        $nowTimestampMs = getNowTimestampMs();
        $payloadArray = array_merge([
            'iss' => $this->iss,
            'sub' => $this->sub,
            'aud' => $this->aud,
            'exp' => $nowTimestampMs + $lifetime, // 过期时间
            'iat' => $nowTimestampMs, // 签发时间
            'nbf' => $nowTimestampMs, // 生效时间
        ], $payload);

        $headerUrlSafeBase64Str = OtherHelper::urlSafeBase64Encode(json_encode($headerArray));
        $payloadUrlSafeBase64Str = OtherHelper::urlSafeBase64Encode(json_encode($payloadArray));
        $signature = $this->sign($headerUrlSafeBase64Str . $payloadUrlSafeBase64Str, $algName);

        return $headerUrlSafeBase64Str . '.' . $payloadUrlSafeBase64Str . '.' . $signature;
    }

    /**
     * 生成签名
     *
     * @param string $data 原始数据
     * @param string $algName 签名算法
     * @return false|string|null
     * @throws Exception
     */
    protected function sign($data, $algName = 'HS256')
    {
        $algConfig = $signature = null;
        if (!$algName || !($algConfig = self::$algTable[$algName]) ?? null) {
            throw new Exception(self::JWT_ERR_NOT_SUPPORT_ALG);
        }
        $algFunc = $algConfig['func'];
        $alg = $algConfig['alg'];
        if ($algFunc == 'hmac') {
            $signature = hash_hmac($alg, $data, $this->securityKey);
        }

        return $signature;
    }

    /**
     * 解析验证token
     *
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function decodeJwt($token)
    {
        list($headerUrlSafeBase64Str, $payloadUrlSafeBase64Str, $clientSignature) = explode('.', $token);
        if (!$headerUrlSafeBase64Str || !$payloadUrlSafeBase64Str || !$clientSignature) {
            throw new Exception(self::JWT_ERR_TOKEN_IS_WRONG_FORMAT);
        }
        $headerArray = json_decode(OtherHelper::urlSafeBase64Decode($headerUrlSafeBase64Str), true);
        $payloadArray = json_decode(OtherHelper::urlSafeBase64Decode($payloadUrlSafeBase64Str), true);
        if (!$headerArray || !$payloadArray) {
            throw new Exception(self::JWT_ERR_TOKEN_IS_WRONG_FORMAT);
        }
        $algName = $headerArray['alg'] ?? 'HS256';

        $serverSignature = $this->sign($headerUrlSafeBase64Str . $payloadUrlSafeBase64Str, $algName);
        if ($serverSignature !== $clientSignature) {
            throw new Exception(self::JWT_ERR_SIGNATURE_VERIFICATION_FAILED);
        }
        $nowTimestampMs = getNowTimestampMs();
        $iat = $payloadArray['iat'] ?? 0;
        $exp = $payloadArray['exp'] ?? 0;
        $nbf = $payloadArray['nbf'] ?? 0;

        if ($nbf > $nowTimestampMs || $iat > $nowTimestampMs) {
            throw new Exception(self::JWT_ERR_TOKEN_IS_NOT_VALID);
        } elseif ($exp <= $nowTimestampMs) {
            throw new Exception(self::JWT_ERR_TOKEN_HAS_EXPIRED);
        }

        return [
            'header' => $headerArray,
            'payload' => $payloadArray,
            'signature' => $clientSignature,
        ];
    }
}
