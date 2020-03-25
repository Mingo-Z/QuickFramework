<?php
namespace Qf\Components;

/**
 *
 *苹果通知推送封装
 *
 * @version $Id: $
 */

class ApplePushNotificationProvider extends Provider
{
    use ConnectionTrait;

    /**
     * APNS服务地址
     *
     * @var string
     */
    public $ssl;
    /**
     * 证书文件路径
     *
     * @var string
     */
    public $cert;
    /**
     * 证书密码
     *
     * @var string
     */
    public $passphrase;
    public $isPersistent;
    public $timeout;
    public $sound;
    
    public function __construct()
    {
        // 开发者环境 tls://gateway.sandbox.push.apple.com:2195
        $this->ssl = 'tls://gateway.sandbox.push.apple.com:2195';
        $this->isPersistent = true;
        $this->timeout = 30;
        $this->sound = 'default';
        $this->isConnected = false;
    }

    public function isConnected($isAutoConnect = true)
    {
        if (!$this->isConnected && $isAutoConnect) {
            $this->connect();
        }

        return $this->isConnected;
    }

    protected function connect()
    {
        $options = array(
            'ssl' => array(
                'local_cert' => $this->cert,
                'passphrase' => $this->passphrase
            )
        );
        $context = stream_context_create($options);
        $flags = STREAM_CLIENT_CONNECT;
        if ($this->isPersistent) {
            $flags |= STREAM_CLIENT_PERSISTENT;
        }
        $errno = 0;
        $errstr = '';
        $this->connection = stream_socket_client($this->ssl, $errno, $errstr, $this->timeout, $flags, $context);
        if (!$this->connection) {
            trigger_error("$errstr($errno)", E_USER_WARNING);
        } else {
            // 不读取返回数据,不用设置为非阻塞
//                stream_set_blocking($this->_conn, false);
            $this->isConnected = true;
        }

    }
    
    /**
     * 通知推送
     *
     * @param string $deviceToken 设备ID
     * @param string $message 原始消息
     * @param int $badge APP角标显示数字,默认为1
     * @return int >0标识与APNS交互成功
     */
    public function push($deviceToken, $message, $badge = 1, $custom = array())
    {
        $ret = 0;

        if ($this->isConnected()) {
            $notification = $this->packNotification($deviceToken, $message, $badge, $custom);
            // 屏蔽首次SSL写入错误
            $ret = @fwrite($this->connection, $notification, strlen($notification));
            // try once again for socket busy error (fwrite(): SSL operation failed with code 1.
            // OpenSSL Error messages:\nerror:1409F07F:SSL routines:SSL3_WRITE_PENDING)
            if (!$ret) {
                if ($this->ping(true)) {
                    $ret = fwrite($this->connection, $notification, strlen($notification));
                }
            }
        }

        return $ret;
    }
    
    /**
     * 保持tcp/ip连接存活,如果已经丢失则资源清理后重连
     *
     * @return bool
     */
    protected function driverPing()
    {
        if ($this->connection && feof($this->connection)) {
            $this->disConnect();
            trigger_error("$this->ssl connection lost", E_USER_WARNING);
        }

        return $this->isConnected(false);
    }

    protected function driverDisConnect()
    {
        fclose($this->connection);
    }

    /**
     * 通知按照APNS格式打包
     *
     * @param string $deviceToken 设备ID
     * @param string $message 原始消息
     * @param int $badge APP角标显示数字,默认为1
     * @return string
     */
    protected function packNotification($deviceToken, $message, $badge = 1, $custom = array())
    {
        $body['aps'] = array(
            'alert' => $message,
            'badge' => $badge,
            'sound' => $this->sound,
            'custom' => $custom
        );
        $payload = json_encode($body);
        $payloadLen = strlen($payload);
        $notification = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', $payloadLen) . $payload;

        return $notification;
    }
    
    public function __destruct()
    {
        if ($this->isConnected(false) && !$this->isPersistent) {
            $this->disConnect();
        }
        $this->ssl = '';
        $this->cert = '';
        $this->passphrase = '';
        $this->isPersistent = true;
    }
}