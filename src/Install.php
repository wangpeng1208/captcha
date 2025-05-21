<?php
/**
 * 插件安装类
 * @author wangpeng1208
 */
declare(strict_types=1);

namespace Wangpeng1208\Captcha;

class Install
{
    /**
     * 安装
     * @return void
     */
    public static function install()
    {
        static::installByRelativePath('config/plugin/wangpeng1208/captcha');
    }

    /**
     * 卸载
     * @return void
     */
    public static function uninstall()
    {
        static::uninstallByRelativePath('config/plugin/wangpeng1208/captcha');
    }

    /**
     * 通过相对路径复制文件
     * @param string $relativePath
     * @return void
     */
    protected static function installByRelativePath($relativePath)
    {
        $source = __DIR__ . "/../$relativePath";
        
        if (!is_dir($source)) {
            return;
        }
        
        $destination = base_path() . "/$relativePath";
        
        // 创建目录
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        // 复制文件和目录
        $dir_iterator = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    /**
     * 通过相对路径删除文件
     * @param string $relativePath
     * @return void
     */
    protected static function uninstallByRelativePath($relativePath)
    {
        $path = base_path() . "/$relativePath";
        
        if (!is_dir($path)) {
            return;
        }
        
        static::removeDir($path);
    }

    /**
     * 递归删除目录
     * @param string $path
     * @return bool
     */
    protected static function removeDir($path)
    {
        $files = scandir($path);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $fullPath = $path . DIRECTORY_SEPARATOR . $file;
                
                if (is_dir($fullPath)) {
                    static::removeDir($fullPath);
                } else {
                    unlink($fullPath);
                }
            }
        }
        
        return rmdir($path);
    }
} 