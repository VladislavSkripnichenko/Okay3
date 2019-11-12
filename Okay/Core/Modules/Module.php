<?php


namespace Okay\Core\Modules;


use Monolog\Logger;
use Okay\Core\EntityFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\ModulesEntity;

/**
 * Class Module
 * @package Okay\Core\Modules
 * 
 * Класс предназначен для получения различной информации по модулю
 * 
 */

class Module
{
    const COMMON_MODULE_NAMESPACE = 'Okay\\Modules';
    const COMMON_MODULE_DIRECTORY = 'Okay/Modules/';

    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    private static $modulesIds;
    
    /**
     * Получить базовую область видимости для указанного модуля
     * @param string $vendor
     * @param string $moduleName
     * @return string
     */
    public function getBaseNamespace($vendor, $moduleName)
    {
        return self::COMMON_MODULE_NAMESPACE.'\\'.$vendor.'\\'.$moduleName;
    }
    
    /**
     * Получить область видимости контроллеров админки для указанного модуля
     * @param string $vendor
     * @param string $moduleName
     * @return string
     */
    public function getBackendControllersNamespace($vendor, $moduleName)
    {
        return self::COMMON_MODULE_NAMESPACE.'\\'.$vendor.'\\'.$moduleName.'\\Backend\\Controllers';
    }
    
    /**
     * Получить область видимости контроллеров админки для указанного модуля
     * @param string $vendor
     * @param string $moduleName
     * @return string
     * @throws \Exception
     */
    public function getBackendControllersDirectory($vendor, $moduleName)
    {
        return $this->getModuleDirectory($vendor, $moduleName) . 'Backend/Controllers/';
    }

    /**
     * Получить экземпляр конфигурационного класса указанного модуля
     * @param string $vendor
     * @param string $moduleName
     * @return string
     */
    public function getInitClassName($vendor, $moduleName)
    {
        $initClassName = $this->getBaseNamespace($vendor, $moduleName).'\\Init\\Init';
        if (class_exists($initClassName)) {
            return $initClassName;
        }

        return '';
    }

    /**
     * Получить базовую директорию для указанного модуля
     * @param string $vendor
     * @param string $moduleName
     * @throws \Exception
     * @return string
     */
    public function getModuleDirectory($vendor, $moduleName)
    {
        if (!preg_match('~^[\w]+$~', $vendor)) {
            throw new \Exception('"'.$vendor.'" is wrong name of vendor');
        }
        
        if (!preg_match('~^[\w]+$~', $moduleName)) {
            throw new \Exception('"'.$moduleName.'" is wrong name of module');
        }
        
        $dir = self::COMMON_MODULE_DIRECTORY.$vendor.'/'.$moduleName;
        return rtrim($dir, '/') . '/';
    }

    public function moduleDirectoryNotExists($vendor, $moduleName)
    {
         $moduleDir = $this->getModuleDirectory($vendor, $moduleName);

         if (is_dir($moduleDir)) {
             return false;
         }

        $moduleNotExistsMsg = 'Module "'.$vendor.'/'.$moduleName.'" installed but not exists';
        trigger_error($moduleNotExistsMsg, E_USER_WARNING);
        $this->logger->addWarning($moduleNotExistsMsg);
        return true;
    }

    /**
     * Получить список роутов модуля
     * @param string $vendor
     * @param string $moduleName
     * @throws \Exception
     * @return array
     */
    public function getRoutes($vendor, $moduleName)
    {
        $file = $this->getModuleDirectory($vendor, $moduleName).'/Init/routes.php';

        if (!file_exists($file)) {
            return [];
        }

        return include($file);
    }

    /**
     * Получить список сервисов модуля
     * @param string $vendor
     * @param string $moduleName
     * @throws \Exception
     * @return array
     */
    public function getServices($vendor, $moduleName)
    {
        $file = $this->getModuleDirectory($vendor, $moduleName).'/Init/services.php';

        if (!file_exists($file)) {
            return [];
        }

        return include($file);
    }

    /**
     * Получить список сервисов модуля
     * @param string $vendor
     * @param string $moduleName
     * @throws \Exception
     * @return array
     */
    public function getSmartyPlugins($vendor, $moduleName)
    {
        $file = $this->getModuleDirectory($vendor, $moduleName).'/Init/SmartyPlugins.php';

        if (!file_exists($file)) {
            return [];
        }

        return include($file);
    }

    public function isModuleClass($className)
    {
        return preg_match('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', $className);
    }

    public function getVendorName($className)
    {
        if (!$this->isModuleClass($className)) {
            throw new \Exception('Wrong module name');
        }
        return preg_replace('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', '$1', $className);
    }

    public function getModuleName($className)
    {
        if (!$this->isModuleClass($className)) {
            throw new \Exception('Wrong module name');
        }

        return preg_replace('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\?.*~', '$2', $className);
    }

    public function isModuleController($controllerName)
    {
        return preg_match('~Okay\\\\Modules\\\\([a-zA-Z0-9]+)\\\\([a-zA-Z0-9]+)\\\\Controllers\\\\?.*~', $controllerName);
    }

    /**
     * Получить параметры контроллера админки. Имя контроллера имеет структуру Vendor.Module.Controller
     * В случае если имя контроллера соответствует контрорллеру админки,
     * в ответ получим массив 
     * [
     *      'vendor' => 'Vendor',
     *      'module' => 'Module',
     *      'controller' => 'Controller',
     * ]
     * @param $vendorModuleController
     * @return bool|array
     * @throws \Exception
     */
    public function getBackendControllerParams($vendorModuleController)
    {
        if (preg_match('~([a-zA-Z0-9]+)\.([a-zA-Z0-9]+)\.([a-zA-Z0-9]+)+~', $vendorModuleController, $matches)) {
            $vendor = $matches[1];
            $moduleName = $matches[2];
            $controllerName = $matches[3];

            if (is_file($this->getBackendControllersDirectory($vendor, $moduleName) . $controllerName . '.php')) {
                return [
                    'vendor' => $vendor,
                    'module' => $moduleName,
                    'controller' => $controllerName,
                ];
            }
        }
        
        return false;
    }
    
    public function getBackendControllerName($vendor, $module, $controllerClass)
    {
        return $vendor . '.' . $module . '.' . $controllerClass;
    }

    public function generateModuleTemplateDir($vendor, $moduleName)
    {
        return realpath(__DIR__.'/../../Modules/'.$vendor.'/'.$moduleName.'/design/html/');
    }

    /**
     * Метод принимает по сути имя любого класса модуля, и возвращает id этого модуля в БД
     *
     * @param $namespace
     * @return int|bool id модуля в системе, или false в случае ошибки
     * @throws \Exception
     */
    public function getModuleIdByNamespace($namespace)
    {
        $vendor = $this->getVendorName($namespace);
        $moduleName = $this->getModuleName($namespace);
        
        if (!empty(self::$modulesIds[$vendor][$moduleName])) {
            return self::$modulesIds[$vendor][$moduleName];
        }
        
        $SL = new ServiceLocator();

        /** @var EntityFactory $entityFactory */
        $entityFactory = $SL->getService(EntityFactory::class);
        
        /** @var ModulesEntity $modulesEntity */
        $modulesEntity = $entityFactory->get(ModulesEntity::class);
        if ($module = $modulesEntity->getByVendorModuleName($vendor, $moduleName)) {
            return self::$modulesIds[$vendor][$moduleName] = $module->id;
        }
        return false;
    }

    /**
     * Метод возвращает изображение модуля, которое соответствует файлу с названием preview.* в корне модуля
     *
     * @throws \Exception
     * @param $vendor
     * @param $moduleName
     * @return mixed
     */
    public function findModulePreview($vendor, $moduleName)
    {
        $moduleDir = $this->getModuleDirectory($vendor, $moduleName);
        $matchedFiles = glob ($moduleDir."preview.*");

        if (empty($matchedFiles)) {
            return false;
        }

        foreach($matchedFiles as $file) {
            if ($this->fileHasAllowImageExtension($file)) {
                return $file;
            }
        }

        return false;
    }

    private function fileHasAllowImageExtension($file)
    {
        return preg_match('/\.(jpeg|jpg|png|gif|svg)$/ui', $file);
    }
}