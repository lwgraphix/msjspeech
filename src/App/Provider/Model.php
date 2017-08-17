<?php

namespace App\Provider;

class Model
{
    private static $repository;

    public static function get($name)
    {
        if (!isset(self::$repository[$name])) {
            self::$repository[$name] = self::_load($name);
        }

        return self::$repository[$name];
    }

    private static function _load($name)
    {
        $preparedName = null;
        $items = explode('_', $name);
        foreach($items as $item)
        {
            $preparedName .= ucfirst($item);
        }

        if (file_exists(__DIR__ . '/../Model/' . $preparedName . 'Model.php'))
        {
            $modelName = '\\App\\Model\\' . $preparedName . 'Model';
            $instance = new $modelName(); // HERE PUT PARAMS IN MODEL CONSTRUCT
            return $instance;
        }
        else
        {
            throw new \Exception('Model "' . $preparedName . '" not found');
        }
    }
}