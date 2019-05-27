<?php

namespace MetaSerializer;

class MetaDeserializer
{
    public $methodSuffix;

    function __construct(string $methodSuffix)
    {
        $this->methodSuffix = $methodSuffix;
    }

    /**
     * @param array|\ArrayObject $src
     * @param object $dest
     * @param string $property
     * @throws MetaDeserializerException
     * @throws \ReflectionException
     */
    private function deserializePropertyViaMethod($src, object $dest, string $property) : void
    {
        $method = $property . $this->methodSuffix;
		if (method_exists($dest, $method)) {
            $m = new \ReflectionMethod($dest, $method);
            $m->setAccessible(true);
            $m->invokeArgs($dest, [ $src, $property ]);
        }
		else {
            $this->deserializeProperty($src, $dest, $property);
        }
    }

    protected function getPropertyType(object $obj, string $property) : ?string
    {
        $class = new \ReflectionClass($obj);
        $p = $class->getProperty($property);
        if ($p) {
            if (preg_match('/@var\s+([^\s]+)/', $p->getDocComment(), $matches)) {
                list(, $type) = $matches;
                if ($type) {
                    if (strpos('|', $type) === false) return $type;
                }
            }
        }
        return null;
    }

    /**
     * @param array|\ArrayObject $src
     * @param object $dest
     * @param string $property
     * @throws MetaDeserializerException
     */
    private function deserializeProperty($src, object $dest, string $property) : void
    {
        try {
            if (!array_key_exists($property, $src)) $this->noValueProvided($src, $dest, $property);
            $dest->$property = $this->deserializeValue($src[$property], $this->getPropertyType($dest, $property), get_class($dest));
        }
        catch (MetaDeserializerException $e) {
            throw new MetaDeserializerException("Property [$property] deserialization error", 0, $e);
        }
    }

    /**
     * Override to support additional types.
     * @param mixed $value
     * @param string $type "bool", "int", "MyClass"...
     * @param string $parentClass
     * @throws MetaDeserializerException
     * @return mixed
     */
    protected function deserializeValueNotNullableType($value, string $type, ?string $parentClass)
    {
        if (!$type) return $value;

        if ($value === null) throw new MetaDeserializerException("Value must not be null.");

        if (substr($type, -2) == "[]") {
            if (!is_array($value) && !($value instanceof \ArrayObject)) throw new MetaDeserializerException("Value must be array.");
            $r = [];
            foreach ($value as $k => $v) {
                $r[$k] = $this->deserializeValue($v, substr($type, 0, strlen($type) - 2), $parentClass);
            }
            return $r;
        }

        switch ($type)
        {
            case "string":
                return (string)$value;

            case "float":
                return (float)$value;

            case "int":
                return (int)$value;

            case "bool":
                return (bool)$value;

            case "array":
                if (!is_array($value) && !($value instanceof \ArrayObject)) throw new MetaDeserializerException("Value must be array.");
                $r = [];
                foreach ($value as $k => $v) {
                    $r[$k] = $this->deserializeValue($v, null, $parentClass);
                }
                return $r;

            case "DateTime": case "\\DateTime":
                if (is_string($value)) return new \DateTime($value);
                if (is_numeric($value)) {
                    $r = new \DateTime();
                    $r->setTimestamp($value);
                    return $r;
                }
                return (int)$value;
        }

        return $this->deserializeObject($this->getFullClassName($type, $parentClass), $value);
    }

    private function getFullClassName(string $type, ?string $parentClass) {
        if (!$parentClass || substr($type, 0, 1) === "\\") return $type;
        $n = strrpos("\\", $parentClass);
        if ($n === false || $n === 0) return $type;
        return substr($parentClass, 0, $n + 1) . $type;
    }

    /**
     * Override to ignore not present values.
     * @param $src
     * @param object $dest
     * @param string $property
     * @throws MetaDeserializerException
     */
    protected function noValueProvided($src, object $dest, string $property)
    {
        throw new MetaDeserializerException("Value must be specified", 0);
    }

    /**
     * Override to create object with parameters if need.
     * @param string $class
     * @return object
     */
    protected function createObject(string $class) : object
    {
        return new $class();
    }

    /**
     * @param mixed $value
     * @param string $type "?bool", "int", "MyClass"...
     * @param string $parentClass
     * @throws MetaDeserializerException
     * @return mixed
     */
    function deserializeValue($value, ?string $type, string $parentClass=null)
    {
        if (!$type) return $value;

        $nullable = substr($type, 0, 1) === "?";
        if ($nullable && $value === null) return null;
        if ($nullable) $type = substr($type, 1);

        return $this->deserializeValueNotNullableType($value, $type, $parentClass);
    }

    function deserializeObject(string $class, array $src, array $properties=null) : object
    {
		$dest = $this->createObject($class);

        if ($properties === null) $properties = array_keys(get_object_vars($dest));

		foreach ($properties as $k) {
            $this->deserializePropertyViaMethod($src, $dest, $k);
        }

		return $dest;
    }
}