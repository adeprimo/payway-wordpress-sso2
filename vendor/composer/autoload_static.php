<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3d18b08db8fe8fa9caab42d26f89d912
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3d18b08db8fe8fa9caab42d26f89d912::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3d18b08db8fe8fa9caab42d26f89d912::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3d18b08db8fe8fa9caab42d26f89d912::$classMap;

        }, null, ClassLoader::class);
    }
}
