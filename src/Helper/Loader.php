<?php
/**
 * Loader
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */
namespace FhpPhalconGrid\Helper;

use Phalcon\Config;

class Loader
{
    /** @var Config $config */
    public static $config = null;

    /** @param Config */
    public function __construct(Config $config = null)
    {
        if ($config === null) {
            $this->setConfig();
        } else {
            self::$config = $config;
        }
    }

    private function setConfig()
    {
        $config = new Config();
        $dir = __DIR__ . '/../Config/';
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_file($dir . $file)) {
                        $tmp = include $dir . $file;
                        $config->merge($tmp);
                    }
                }
                closedir($dh);
            }
        }
        self::$config = $config;
    }

    /** @return Loader */
    public function register()
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $loader = new \Phalcon\Loader();
        $loader->registerNamespaces(array(
            'FhpPhalconGrid\Entity' => self::$config['application']->entitiesDir,
            'FhpPhalconGrid\Helper' => __DIR__.'/../Helper',
            'FhpPhalconGrid\Rbac' => __DIR__.'/../Rbac',
            'FhpPhalconGrid\Service' => __DIR__ . '/../Service',
            'FhpPhalconGrid\Grid' => __DIR__ . '/../Grid',
            'FhpPhalconGrid\Grid\Source' => __DIR__ . '/../Grid/Source',
            'FhpPhalconGrid\Grid\Mapper' => __DIR__ . '/../Grid/Mapper',
            'FhpPhalconGrid\Form' => __DIR__ . '/../Form',
            'FhpPhalconGrid\Grid\Validator' => __DIR__ . '/../Grid/Validator',
        ))->registerDirs(
            array(
                self::$config['application']->controllersDir,
            )
        )->register();

        return $loader;
    }
}
