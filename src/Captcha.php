<?php
/**
 * 验证码处理类
 * @author wangpeng1208
 */
declare(strict_types=1);

namespace Wangpeng1208\Captcha;

use support\Cache;
use Exception;

class Captcha
{
    /**
     * 验证验证码是否正确
     * @param string $code 用户验证码
     * @param string $key 用户验证码key
     * @return bool
     */
    public static function check(string $code, string $key): bool
    {
        $config = config('plugin.wangpeng1208.captcha.app.captcha');
        $cacheKey = $config['prefix'] . $key;
        
        if (!Cache::has($cacheKey)) {
            return false;
        }
        
        $cacheData = Cache::get($cacheKey);
        if (!$cacheData || !isset($cacheData['key'])) {
            return false;
        }
        
        $hash = $cacheData['key'];
        $code = mb_strtolower($code, 'UTF-8');
        $res = password_verify($code, $hash);
        
        if ($res) {
            Cache::delete($cacheKey);
        }
        
        return $res;
    }

    /**
     * 生成Base64格式验证码
     * @param array $_config 自定义配置
     * @return array
     * @throws Exception
     */
    public static function base64(array $_config = []): array
    {
        $config = config('plugin.wangpeng1208.captcha.app.captcha');
        if (!empty($_config)) {
            $config = array_merge($config, $_config);
        }
        
        $generator = self::generate($config);
        
        // 图片宽(px)
        $config['imageW'] = $config['imageW'] ?: $config['length'] * $config['fontSize'] * 1.5 + $config['length'] * $config['fontSize'] / 2;
        // 图片高(px)
        $config['imageH'] = $config['imageH'] ?: $config['fontSize'] * 2.5;
        
        // 建立一幅图像
        $im = imagecreate((int)$config['imageW'], (int)$config['imageH']);
        // 设置背景
        imagecolorallocate($im, $config['bg'][0], $config['bg'][1], $config['bg'][2]);

        // 验证码字体随机颜色
        $color = imagecolorallocate($im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        $fontttf = self::resolveFontFile($config);

        if ($config['useImgBg']) {
            self::background($config, $im);
        }

        if ($config['useNoise']) {
            // 绘杂点
            self::writeNoise($config, $im);
        }
        
        if ($config['useCurve']) {
            // 绘干扰线
            self::writeCurve($config, $im, $color);
        }

        // 绘验证码
        $text = $config['useZh'] ? preg_split('/(?<!^)(?!$)/u', $generator['value']) : str_split($generator['value']);

        foreach ($text as $index => $char) {
            $x = $config['fontSize'] * ($index + 1) * mt_rand((int)1.2, (int)1.6) * ($config['math'] ? 1 : 1.5);
            $y = $config['fontSize'] + mt_rand(10, 20);
            $angle = $config['math'] ? 0 : mt_rand(-40, 40);
            imagettftext($im, $config['fontSize'], $angle, (int)$x, (int)$y, $color, $fontttf, $char);
        }

        ob_start();
        imagepng($im);
        $content = ob_get_clean();
        imagedestroy($im);

        return [
            'key' => $generator['key'],
            'base64' => 'data:image/png;base64,' . base64_encode($content),
        ];
    }

    /**
     * 解析验证码字体文件路径
     * @param array $config
     * @return string
     * @throws Exception
     */
    protected static function resolveFontFile(array &$config): string
    {
        $ttfPath = self::resolveAssetsDirectory($config, $config['useZh'] ? 'zhttfs' : 'ttfs');

        if (empty($config['fontttf'])) {
            $dir = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if (substr($file, -4) === '.ttf' || substr($file, -4) === '.otf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();

            if (empty($ttfs)) {
                throw new Exception('Captcha font file not found.');
            }

            $config['fontttf'] = $ttfs[array_rand($ttfs)];
        }

        $fontttf = $config['fontttf'];
        if (!self::isAbsolutePath($fontttf)) {
            $fontttf = $ttfPath . ltrim($fontttf, '/\\');
        }

        $fontttf = realpath($fontttf) ?: $fontttf;
        if (!is_file($fontttf)) {
            throw new Exception(sprintf('Captcha font file not found: %s', $fontttf));
        }

        return self::normalizeFontPath($fontttf);
    }

    /**
     * 获取验证码静态资源根目录
     * @param array $config
     * @return string
     */
    protected static function getAssetsPath(array $config): string
    {
        if (!empty($config['assets_path']) && is_string($config['assets_path'])) {
            $path = $config['assets_path'];
        } else {
            $path = function_exists('public_path')
                ? \public_path() . '/static/wangpeng1208/captcha'
                : dirname(__DIR__) . '/assets';
        }

        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $path !== '' ? $path : dirname(__DIR__) . '/assets';
    }

    /**
     * 获取验证码静态资源子目录
     * 优先读取配置目录，不存在时回退 vendor 目录
     * @param array $config
     * @param string $subdir
     * @return string
     * @throws Exception
     */
    protected static function resolveAssetsDirectory(array $config, string $subdir): string
    {
        $subdir = trim($subdir, '/\\');
        $candidates = [
            self::getAssetsPath($config) . DIRECTORY_SEPARATOR . $subdir,
            dirname(__DIR__) . '/assets/' . $subdir,
        ];

        foreach ($candidates as $candidate) {
            $path = realpath($candidate) ?: $candidate;
            if (is_dir($path)) {
                return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
            }
        }

        throw new Exception(sprintf('Captcha assets directory not found: %s', $candidates[0]));
    }

    /**
     * 修正 GD 在 Windows 下无法读取中文路径字体的问题
     * @param string $fontttf
     * @return string
     */
    protected static function normalizeFontPath(string $fontttf): string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return $fontttf;
        }

        if (!preg_match('/[^\x20-\x7E]/', $fontttf)) {
            return $fontttf;
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wangpeng1208-captcha-fonts';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $extension = pathinfo($fontttf, PATHINFO_EXTENSION) ?: 'ttf';
        $tempFont = $tempDir . DIRECTORY_SEPARATOR . md5($fontttf . '|' . (string)@filemtime($fontttf) . '|' . (string)@filesize($fontttf)) . '.' . $extension;

        if (!is_file($tempFont)) {
            @copy($fontttf, $tempFont);
        }

        return is_file($tempFont) ? $tempFont : $fontttf;
    }

    /**
     * 判断路径是否为绝对路径
     * @param string $path
     * @return bool
     */
    protected static function isAbsolutePath(string $path): bool
    {
        return (bool)preg_match('/^(?:[A-Za-z]:[\\\\\\/]|[\\\\\\/]{2}|\/)/', $path);
    }

    /**
     * 创建验证码
     * @param array $config 配置
     * @return array
     * @throws Exception
     */
    protected static function generate(array $config): array
    {
        $bag = '';
        if ($config['math']) {
            $config['useZh'] = false;
            $config['length'] = 5;
            $x = random_int(10, 30);
            $y = random_int(1, 9);
            $bag = "{$x} + {$y} = ";
            $key = (string)($x + $y);
        } else {
            if ($config['useZh']) {
                $characters = preg_split('/(?<!^)(?!$)/u', $config['zhSet']);
            } else {
                $characters = str_split($config['codeSet']);
            }

            for ($i = 0; $i < $config['length']; $i++) {
                $bag .= $characters[rand(0, count($characters) - 1)];
            }

            $key = mb_strtolower($bag, 'UTF-8');
        }

        $captchaKey = md5($key . uniqid('', true));
        $hash = password_hash($key, PASSWORD_DEFAULT);
        // 使用 Cache 保存验证码
        $cacheKey = $config['prefix'] . $captchaKey;
        $expireTime = $config['expire'] ?? 60;
        
        Cache::set($cacheKey, ['key' => $hash], $expireTime);
        
        return ['value' => $bag, 'key' => $captchaKey];
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线
     * @param array $config
     * @param $im
     * @param $color
     */
    protected static function writeCurve(array $config, $im, $color): void
    {
        $py = 0;

        // 曲线前部分
        $A = mt_rand(1, (int)($config['imageH'] / 2)); // 振幅
        $b = mt_rand(-(int)($config['imageH'] / 4), (int)($config['imageH'] / 4)); // Y轴方向偏移量
        $f = mt_rand(-(int)($config['imageH'] / 4), (int)($config['imageH'] / 4)); // X轴方向偏移量
        $T = mt_rand((int)$config['imageH'], (int)($config['imageW'] * 2)); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand((int)($config['imageW'] / 2), (int)($config['imageW'] * 0.8)); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $config['imageH'] / 2; // y = Asin(ωx+φ) + b
                $i = (int)($config['fontSize'] / 5);
                while ($i > 0) {
                    imagesetpixel($im, (int)$px + $i, (int)$py + $i, (int)$color);
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A = mt_rand(1, (int)($config['imageH'] / 2)); // 振幅
        $f = mt_rand(-(int)($config['imageH'] / 4), (int)($config['imageH'] / 4)); // X轴方向偏移量
        $T = mt_rand((int)$config['imageH'], (int)($config['imageW'] * 2)); // 周期
        $w = (2 * M_PI) / $T;
        $b = $py - $A * sin($w * $px + $f) - $config['imageH'] / 2;
        $px1 = $px2;
        $px2 = $config['imageW'];

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $config['imageH'] / 2; // y = Asin(ωx+φ) + b
                $i = (int)($config['fontSize'] / 5);
                while ($i > 0) {
                    imagesetpixel($im, (int)$px + $i, (int)$py + $i, (int)$color);
                    $i--;
                }
            }
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     * @param array $config
     * @param $im
     */
    protected static function writeNoise(array $config, $im): void
    {
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        
        for ($i = 0; $i < 10; $i++) {
            // 杂点颜色
            $noiseColor = imagecolorallocate($im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring(
                    $im, 
                    5, 
                    mt_rand(-10, (int)$config['imageW']), 
                    mt_rand(-10, (int)$config['imageH']), 
                    $codeSet[mt_rand(0, 29)], 
                    (int)$noiseColor
                );
            }
        }
    }

    /**
     * 绘制背景图片
     * 注：如果验证码输出图片比较大，将占用比较多的系统资源
     * @param array $config
     * @param $im
     * @throws Exception
     */
    protected static function background(array $config, $im): void
    {
        $path = self::resolveAssetsDirectory($config, 'bgs');
        $dir = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

        if (empty($bgs)) {
            throw new Exception(sprintf('Captcha background image not found: %s', $path));
        }

        $gb = $bgs[array_rand($bgs)];

        [$width, $height] = @getimagesize($gb);
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled(
            $im, 
            $bgImage, 
            0, 
            0, 
            0, 
            0, 
            (int)$config['imageW'], 
            (int)$config['imageH'], 
            $width, 
            $height
        );
        
        @imagedestroy($bgImage);
    }
}
