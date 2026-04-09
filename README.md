# wangpeng1208/captcha

适用于Webman的图形验证码库。基于 tinywan/captcha 修改而来，支持 Cache 多种存储后端。

## 环境要求

- PHP >= 8.1
- GD 扩展
- mbstring 扩展
- 支持 PSR-16 兼容缓存

## 安装

```bash
composer require wangpeng1208/captcha
```

## 使用方法

### 生成验证码

```php
use Wangpeng1208\Captcha\Captcha;

// 生成验证码
$captcha = Captcha::base64();

// 返回数据格式
/*
[
    'key' => '验证码hash值，用于后续验证',
    'base64' => 'data:image/png;base64,iVBORw0KGgoA...'
]
*/

// 前端显示
echo "<img src='{$captcha['base64']}' alt='captcha'>";
```

### 验证

```php
use Wangpeng1208\Captcha\Captcha;
use support\Request;

$code = $request->post('code'); // 用户输入的验证码
$key = $request->post('key'); // 验证码的key

if (Captcha::check($code, $key)) {
    // 验证通过
} else {
    // 验证失败
}
```

## 自定义配置

```php
// 自定义配置
$config = [
    'length' => 6, // 验证码长度
    'fontSize' => 30, // 字体大小
    'useCurve' => true, // 是否使用混淆曲线
    'useNoise' => true, // 是否添加杂点
];

$captcha = Captcha::base64($config);
```

## 完整配置

配置文件位于 `config/plugin/wangpeng1208/captcha/app.php`

```php
return [
    'enable' => true,
    'captcha' => [
        // 验证码静态资源目录（包含 bgs/ ttfs/ zhttfs/）
        'assets_path' => public_path() . '/static/wangpeng1208/captcha',
        // 验证码存储key前缀
        'prefix'  => 'captcha:',
        // 验证码字符集合
        'codeSet'  => 'ABCDEFGHJKLMNPQRTUVWXY2345678abcdefhijkmnpqrstuvwxyz',
        // 是否使用中文验证码
        'useZh'  => false,
        // 中文验证码字符串
        'zhSet'  => '...',
        // 是否使用背景图（不建议开启）
        'useImgBg' => false,
        // 是否使用混淆曲线
        'useCurve' => true,
        // 是否添加杂点
        'useNoise' => true,
        // 验证码图片高度
        'imageH'   => 0,
        // 验证码图片宽度
        'imageW'   => 0,
        // 验证码位数
        'length'   => 4,
        // 验证码字符大小
        'fontSize' => 25,
        // 验证码过期时间（秒）
        'expire'   => 600,
        // 验证码字体 不设置则随机
        'fontttf'  => '',
        // 背景颜色
        'bg'       => [243, 251, 254],
        // 是否使用算术验证码
        'math'     => false,
    ]
];
```

## 与 Redis 的区别

与 `tinywan/captcha` 相比，本扩展使用 `support\Cache` 替代了直接使用 Redis，遵循 webman 的缓存配置，使您可以：

1. 自由选择缓存驱动（文件、Redis、内存等）
2. 灵活配置缓存参数（连接池、过期时间等）
3. 更好的兼容性和可扩展性

## License

MIT License 
