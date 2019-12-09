<?php

namespace SilverStripe\Cow\Model\Modules;

/**
 * Legacy support for silverstripe-theme. Will be deprecated in the future
 */
class Theme extends Module
{
    public static function isThemeDir($dir)
    {
        if (!static::isLibraryPath($dir)) {
            return false;
        }
        $data = json_decode(file_get_contents($path . '/composer.json'), true);
        return isset($data['type']) && $data['type'] === 'silverstripe-theme';
    }
}
