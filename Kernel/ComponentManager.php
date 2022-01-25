<?php
namespace Qf\Kernel;

use Qf\Kernel\Exception;

/**
 *
 * 扩展组件加载类
 *
 * @version $Id: $
 */
class ComponentManager
{
    public $configFile;
    
    public function __get($name)
    {
        return $this->_load($name);
    }
    
    /**
     * 根据组件配置文件，加载指定名称的组件并且初始化
     * 
     * @param string $name
     * @return object
     */
    protected function _load($name)
    {
        static $loadedObjects = [];
        
        if (!isset($loadedObjects[$name])) {
            $components = $this->_getComponents();
            if (!isset($components[$name])) {
                throw new Exception("component $name not defintion");
            }
            $comConfig = $components[$name];
            if (isset($comConfig['classFile'])) {
                if (is_file($comConfig['classFile'])) {
                    require_once $comConfig['classFile'];
                    if (!class_exists($comConfig['className'], false)) {
                        throw new Exception("class {$comConfig['className']} not defintion");
                    }
                } else {
                    throw new Exception("component $name classFile option error");
                }
            }
            if (isset($comConfig['dependFiles']) && $comConfig['dependFiles']) {
                foreach ($comConfig['dependFiles'] as $dependFile) {
                    if (is_file($dependFile)) {
                        require_once $dependFile;
                    }
                }
            }
            $comObj = new $comConfig['className'];
            $comObj->setComponentManager($this);
            if (isset($comConfig['initProperties']) && $comConfig['initProperties']) {
                foreach ($comConfig['initProperties'] as $key => $value) {
                    if (property_exists($comObj, $key)) {
                        $comObj->$key = $value;
                    }
                }
            }
            if (method_exists($comObj, 'init')) {
                $comObj->init();
            }

            $loadedObjects[$name] = $comObj;
        }
        return $loadedObjects[$name];
    }

    /**
     * 获取所有组件配置
     *
     * @return array
     */
    protected function _getComponents()
    {
        static $components = [];
        if (!$components && is_file($this->configFile)) {
            $components = require $this->configFile;
        }
        return $components;
    }

    /**
     * 检查组件是否存在
     *
     * @param string $name
     * @return bool
     */
    public function exists($name)
    {
        static $existsCache = [];
        if (!isset($existsCache[$name])) {
            $existsCache[$name] = array_key_exists($name, $this->_getComponents());
        }
        return $existsCache[$name];
    }
}
