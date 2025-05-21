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

        // 验证码使用随机字体
        $ttfPath = dirname(__DIR__) . '/assets/' . ($config['useZh'] ? 'zhttfs' : 'ttfs') . '/';

        if (empty($config['fontttf'])) {
            $dir = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if (substr($file, -4) == '.ttf' || substr($file, -4) == '.otf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
            $config['fontttf'] = $ttfs[array_rand($ttfs)];
        }

        $fontttf = $ttfPath . $config['fontttf'];

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

        $hash = password_hash($key, PASSWORD_BCRYPT, ['cost' => 10]);
        
        // 使用 Cache 保存验证码
        $cacheKey = $config['prefix'] . $hash;
        $expireTime = $config['expire'] ?? 60;
        
        Cache::set($cacheKey, ['key' => $hash], $expireTime);
        
        return ['value' => $bag, 'key' => $hash];
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
        $A = mt_rand(1, $config['imageH'] / 2); // 振幅
        $b = mt_rand(-$config['imageH'] / 4, $config['imageH'] / 4); // Y轴方向偏移量
        $f = mt_rand(-$config['imageH'] / 4, $config['imageH'] / 4); // X轴方向偏移量
        $T = mt_rand($config['imageH'], $config['imageW'] * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand($config['imageW'] / 2, (int)($config['imageW'] * 0.8)); // 曲线横坐标结束位置

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
        $A = mt_rand(1, $config['imageH'] / 2); // 振幅
        $f = mt_rand(-$config['imageH'] / 4, $config['imageH'] / 4); // X轴方向偏移量
        $T = mt_rand($config['imageH'], $config['imageW'] * 2); // 周期
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
     */
    protected static function background(array $config, $im): void
    {
        $path = dirname(__DIR__) . '/assets/bgs/';
        $dir = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

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