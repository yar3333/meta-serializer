<?php

namespace MetaSerializer;

class MetaSerializer
{
    public $methodSuffix;

    function __construct(string $methodSuffix)
    {
        $this->methodSuffix = $methodSuffix;
    }

    private function serializePropertyViaMethod(object $src, array &$dest, string $property) : void
    {
        $method = $property . $this->methodSuffix;
        if (method_exists($src, $method)) {
            $m = new \ReflectionMethod($src, $method);
            $m->setAccessible(true);
            $m->invokeArgs($src, [ &$dest, $property ]);
        }
        else {
            $this->serializeProperty($src, $dest, $property);
        }
    }

    private function serializeProperty(object $src, array &$dest, string $property) : void
    {
        $dest[$property] = $this->serializeValue($src->$property);
    }

    function serializeValue($value)
    {
    	if (is_array($value) || $value instanceof \ArrayObject)
        {
            $r = [];
            foreach ($value as $k => $v) {
                $r[$k] = $this->serializeValue($v);
            }
            return $r;
        }

    	if (is_object($value))
        {
            return $this->serializeObject($value);
        }

    	return $value;
    }

    function serializeObject(object $obj, array $properties=null) : array
    {
        if ($properties === null) $properties = array_keys(get_object_vars($obj));

        $r = [];
        foreach ($properties as $k) {
            $this->serializePropertyViaMethod($obj, $r, $k);
        }
        return $r;
    }
}