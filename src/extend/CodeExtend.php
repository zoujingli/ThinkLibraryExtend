<?php

declare (strict_types=1);

namespace think\admin\extend;

/**
 * 随机数码管理扩展
 * Class CodeExtend
 * @package think\admin\extend
 */
class CodeExtend
{
    /**
     * 获取随机字符串编码
     * @param integer $size 编码长度
     * @param integer $type 编码类型(1纯数字,2纯字母,3数字字母)
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function random(int $size = 10, int $type = 1, string $prefix = ''): string
    {
        $numbs = '0123456789';
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        if ($type === 1) $chars = $numbs;
        if ($type === 3) $chars = "{$numbs}{$chars}";
        $code = $prefix . $chars[rand(1, strlen($chars) - 1)];
        while (strlen($code) < $size) $code .= $chars[rand(0, strlen($chars) - 1)];
        return $code;
    }

    /**
     * 唯一日期编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqidDate(int $size = 16, string $prefix = ''): string
    {
        if ($size < 14) $size = 14;
        $code = $prefix . date('Ymd') . (date('H') + date('i')) . date('s');
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 唯一数字编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqidTime(int $size = 12, string $prefix = ''): string
    {
        $time = time() . '';
        if ($size < 10) $size = 10;
        $code = $prefix . (intval($time[0]) + intval($time[1])) . substr($time, 2) . rand(0, 9);
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 数据解密处理
     * @param mixed $data 加密数据
     * @param string $skey 安全密钥
     * @return string
     */
    public static function encrypt($data, string $skey): string
    {
        $iv = self::random(16, 3);
        $value = openssl_encrypt(serialize($data), 'AES-256-CBC', $skey, 0, $iv);
        return self::safeBase64Encode(json_encode(['iv' => $iv, 'value' => $value]));
    }

    /**
     * 数据加密处理
     * @param string $data 解密数据
     * @param string $skey 安全密钥
     * @return mixed
     */
    public static function decrypt(string $data, string $skey)
    {
        $attr = json_decode(self::safeBase64Decode($data), true);
        return unserialize(openssl_decrypt($attr['value'], 'AES-256-CBC', $skey, 0, $attr['iv']));
    }

    /**
     * Base64安全编码
     * @param string $content
     * @return string
     */
    public static function safeBase64Encode(string $content): string
    {
        return rtrim(strtr(base64_encode($content), '+/', '-_'), '=');
    }

    /**
     * Base64安全解码
     * @param string $content
     * @return string
     */
    public static function safeBase64Decode(string $content): string
    {
        return base64_decode(str_pad(strtr($content, '-_', '+/'), strlen($content) % 4, '='));
    }
}