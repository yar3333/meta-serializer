<?php

namespace MetaSerializer;

class MetaDeserializer
{
    protected const STD_TYPES = ['string', 'int', 'float', 'array', 'bool', 'object'];

    protected $methodSuffix;
    protected $phpDocMetaPrefix;

    public function __construct(string $methodSuffix = "_deserialize", string $phpDocMetaPrefix = "")
    {
        $this->methodSuffix = $methodSuffix;
        $this->phpDocMetaPrefix = $phpDocMetaPrefix;
    }

    /**
     * @param array|\ArrayObject $src
     * @param object $dest
     * @param string $property
     */
    private function deserializePropertyViaMethod($src, object $dest, string $property) : void
    {
        try {
            $method = $property . $this->methodSuffix;
            if (method_exists($dest, $method)) {
                $m = new \ReflectionMethod($dest, $method);
                $m->setAccessible(true);
                $m->invokeArgs($dest, [ $src, $property, $this ]);
            }
            else {
                $this->deserializeProperty($src, $dest, $property);
            }
        }
        catch (\Exception $e) {
            throw new MetaDeserializerException("Property [$property] deserialization error", 0, $e);
        }
    }

    protected function getPropertyType(object $obj, string $property, \ReflectionProperty $p = null) : ?string
    {
        $p = $p ?? new \ReflectionProperty($obj, $property);
        if (!$p) return null;

        if (!preg_match('/@var\s+([^\s]+)/', $p->getDocComment(), $matches)) return null;
        [, $type] = $matches;

        if (!$type || $type === 'mixed') return null;
        if (strpos($type, '|') !== false) return null;

        $optional = substr($type, 0, 1) === "?" ? "?" : "";
        if ($optional) $type = substr($type, 1);

        $arraySuffix = "";
        while (substr($type, -2) === "[]") { $type = substr($type, 0, -2); $arraySuffix .= '[]'; }

        if (in_array($type, self::STD_TYPES, true)) return $optional . $type . $arraySuffix;
        if (substr($type, 0, 1) === "\\") return $optional . substr($type, 1) . $arraySuffix;

        return $optional . ltrim($this->getObjectNamespace($obj) . $type, "\\") . $arraySuffix;
    }

    /**
     * @param array|\ArrayObject $src
     * @param object $dest
     * @param string $property
     * @throws MetaDeserializerException
     * @throws \ReflectionException
     */
    private function deserializeProperty($src, object $dest, string $property) : void
    {
        $p = new \ReflectionProperty($dest, $property);

        $optional = false;
        $sourceName = null;

        $phpDoc = $p->getDocComment();
        if (is_string($phpDoc)) {
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'optional\b/', $phpDoc, $matches)) {
                $optional = true;
            }
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'sourceName\s+([A-Za-z_][A-Za-z_0-9]*)/', $phpDoc, $matches)) {
                $sourceName = $matches[1];
            }
        }

        $type = $this->getPropertyType($dest, $property, $p);
        if (!$optional && !array_key_exists($sourceName ?? $property, $src)) {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $dest->$property = $this->onNoValueProvided($type);
        }
        else {
            $dest->$property = $this->deserializeValue($optional && !array_key_exists($sourceName ?? $property, $src) ? null : $src[$sourceName ?? $property], $type);
        }
    }

    protected function getObjectNamespace(object $obj) : string
    {
        $class = get_class($obj);
        $n = strrpos($class, "\\");
        if ($n === false || $n === 0) return "\\";
        return substr($class, 0, $n + 1);
    }

    /**
     * Override to support additional types.
     * @param $value
     * @param string $type "bool", "int", "MyClass"...
     * @return mixed
     * @throws MetaDeserializerException
     * @throws \ReflectionException
     */
    protected function deserializeValueNotNullableType($value, string $type)
    {
        if (substr($type, -2) === '[]') {
            if (!is_array($value) && !($value instanceof \ArrayObject)) throw new MetaDeserializerException("Value must be array.");
            $r = [];
            foreach ($value as $k => $v) {
                $r[$k] = $this->deserializeValue($v, substr($type, 0, -2));
            }
            return $r;
        }

        switch ($type) {
            case 'string':
                return (string)$value;

            case 'float':
                return (float)$value;

            case 'int':
                return (int)$value;

            case 'bool':
                return (bool)$value;

            case 'array':
                if (!is_array($value) && !($value instanceof \ArrayObject)) throw new MetaDeserializerException('Value must be array.');
                $r = [];
                foreach ($value as $k => $v) {
                    $r[$k] = $this->deserializeValue($v, null);
                }
                return $r;

            case 'object':
                if (!is_object($value)) throw new MetaDeserializerException("Value must be object.");
                $r = (object)[];
                foreach (get_object_vars($value) as $k => $v) {
                    $r->$k = $this->deserializeValue($v, null);
                }
                return $r;

            case 'DateTime':
                if (is_numeric($value)) {
                    $r = new \DateTime();
                    $r->setTimestamp($value);
                    return $r;
                }
                if (is_string($value)) return new \DateTime($value);
                throw new MetaDeserializerException('Expected string/date-time or number/timestamp');
        }

        return $this->deserializeObject($value, $type);
    }

    /**
     * Called if no value for property provided in source array.
     * Override to deal with not preset values. This function must throw exception or return result value.
     * @param string $type
     * @throws MetaDeserializerException
     */
    protected function onNoValueProvided(?string $type)
    {
        throw new MetaDeserializerException('Value must be specified');
    }

    /**
     * Called if null value for not-nullable type found.
     * Override to deal with null values. This function must throw exception or return result value.
     * @param string $type
     * @throws MetaDeserializerException
     */
    protected function onNotNullableValueIsNull(string $type)
    {
        throw new MetaDeserializerException('Value must not be null');
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
     * @throws MetaDeserializerException
     * @return mixed
     * @throws \ReflectionException
     */
    final function deserializeValue($value, string $type = null)
    {
        if (!$type) return $value;

        $nullable = substr($type, 0, 1) === "?";
        if ($nullable && $value === null) return null;
        if ($nullable) $type = substr($type, 1);

        if ($value === null) {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            return $this->onNotNullableValueIsNull($type);
        }

        return $this->deserializeValueNotNullableType($value, $type);
    }

    /**
     * @param array $src|\ArrayObject
     * @param string $class
     * @param string[] $properties
     * @return object
     * @throws MetaDeserializerException
     * @throws \ReflectionException
     */
    public function deserializeObject($src, string $class, array $properties=null) : object
    {
		$class = "\\" . ltrim($class, "\\");
		if (!class_exists($class)) {
            throw new MetaDeserializerException("Class `$class` not found");
        }
        $dest = $this->createObject($class);
        $this->deserializeObjectProperties($src, $dest, $properties);
        return $dest;
    }

    /**
     * @param array $src|\ArrayObject
     * @param object $dest
     * @param string[] $properties
     * @throws MetaDeserializerException
     * @throws \ReflectionException
     */
    public function deserializeObjectProperties($src, object $dest, array $properties=null) : void
    {
        if ($properties === null) $properties = array_keys(get_object_vars($dest));

		foreach ($properties as $k) {
            $this->deserializePropertyViaMethod($src, $dest, $k);
        }
    }
}