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
     * @throws MetaDeserializerException
     */
    private function deserializePropertyViaMethod($src, $dest, string $property) : void
    {
        try {
            $method = $property . $this->methodSuffix;
            if (method_exists($dest, $method)) {
                $m = new \ReflectionMethod($dest, $method);
                $m->setAccessible(true);
                $m->invokeArgs($dest, [$src, $property, $this]);
            } else {
                $this->deserializeProperty($src, $dest, $property);
            }
        } catch (\Exception $e) {
            throw new MetaDeserializerException("Property [$property] deserialization error. " . $e->getMessage(), 0, $e);
        }
    }

    private function normalizeTypeString(string $typeString) : array
    {
        if (!$typeString) return [null, null];
        $types = explode('|', $typeString);
        $types_wo_null = array_filter($types, static function ($x) {
            return strtolower($x) !== 'null';
        });
        if (count($types_wo_null) !== 1) return [null, null];
        return [count($types) > count($types_wo_null) ? '?' : '', $types_wo_null[0]];
    }

    protected function getPropertyType($obj, string $property, \ReflectionProperty $p = null) : ?string
    {
        $p = $p ?? new \ReflectionProperty($obj, $property);
        if (!$p) return null;

        if (!preg_match('/@var\s+((?:[a-zA-Z0-9_?\\\\|]|\[|\])+)/', $p->getDocComment(), $matches)) return null;
        [$optional, $type] = $this->normalizeTypeString($matches[1]);

        if (!$type || $type === 'mixed') return null;

        $arraySuffix = "";
        while (substr($type, -2) === "[]") {
            $type = substr($type, 0, -2);
            $arraySuffix .= '[]';
        }

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
    private function deserializeProperty($src, $dest, string $property) : void
    {
        $p = new \ReflectionProperty($dest, $property);

        $optional = false;
        $sourceName = null;

        $phpDoc = $p->getDocComment();
        if (is_string($phpDoc)) {
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'ignore\b/', $phpDoc, $matches)) {
                return;
            }
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'optional\b/', $phpDoc, $matches)) {
                $optional = true;
            }
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'sourceName\s+([A-Za-z_][A-Za-z_0-9]*)/', $phpDoc, $matches)) {
                $sourceName = $matches[1];
            }
        }

        $type = $this->getPropertyType($dest, $property, $p);
        $seeking_value = $sourceName ?? $property;
        $valueExists = is_array($src) ? array_key_exists($seeking_value, $src) : property_exists($src, $seeking_value);
        if (!$optional && !$valueExists) {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            $dest->$property = $this->onNoValueProvided($type);
        } else if ($optional && !$valueExists) {
            $dest->$property = null;
        } else {
            $dest->$property = $this->deserializeValue($src[$sourceName ?? $property], $type && $optional ? '?' . ltrim($type, '?') : $type);
        }
    }

    /**
     * @param object $obj
     * @return string
     */
    protected function getObjectNamespace($obj) : string
    {
        $class = get_class($obj);
        $n = strrpos($class, "\\");
        if ($n === false || $n === 0) return "\\";
        return substr($class, 0, $n + 1);
    }

    /**
     * @param $value
     * @param string $type
     * @throws MetaDeserializerException
     */
    private function setSimpleType($value, string $type)
    {
        if (is_array($value) || is_object($value) || !settype($value, $type))
            throw new MetaDeserializerException("Value must be $type.");
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
        if (substr($type, -2) === '[]') return $this->deserializeArray($value, substr($type, 0, -2));

        switch ($type) {
            case 'string':
            case 'float':
            case 'int':
                $this->setSimpleType($value, $type);
                return $value;
            case 'bool':
               return (bool) $value;

            case 'array':
                return $this->deserializeArray($value);

            case 'object':
                if (!is_object($value)) throw new MetaDeserializerException("Value must be object.");
                $r = (object)[];
                foreach (get_object_vars($value) as $k => $v) {
                    /** @noinspection PhpVariableVariableInspection */
                    $r->$k = $this->deserializeValue($v);
                }
                return $r;

            case 'DateTime':
                if (is_numeric($value)) return new \DateTime("@$value");
                if (is_string($value)) return new \DateTime($value);
                throw new MetaDeserializerException('Expected string/date-time or number/timestamp.');
        }

        return $this->deserializeObject($value, $type);
    }

    /**
     * @param $array
     * @param string|null $itemType
     * @return array
     * @throws MetaDeserializerException
     * @throws \ReflectionException
     */
    public function deserializeArray($array, string $itemType = null) : array
    {
        if (!is_array($array) && !($array instanceof \ArrayObject)) throw new MetaDeserializerException("Value must be array.");

        $r = [];
        foreach ($array as $k => $v) {
            $r[$k] = $this->deserializeValue($v, $itemType);
        }
        return $r;
    }

    /**
     * Called if no value for property provided in source array.
     * Override to deal with not preset values. This function must throw exception or return result value.
     * @param string $type
     * @throws MetaDeserializerException
     */
    protected function onNoValueProvided(?string $type)
    {
        throw new MetaDeserializerException('Value must be specified.');
    }

    /**
     * Called if null value for not-nullable type found.
     * Override to deal with null values. This function must throw exception or return result value.
     * @param string $type
     * @throws MetaDeserializerException
     */
    protected function onNotNullableValueIsNull(string $type)
    {
        throw new MetaDeserializerException('Value must not be null.');
    }

    /**
     * Override to create object with parameters if need.
     * @param string $class
     * @return object
     */
    protected function createObject(string $class)
    {
        return new $class();
    }

    /**
     * @param mixed $value
     * @param string $type "?bool", "int", "MyClass"...
     * @return mixed
     * @throws MetaDeserializerException
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
     * @param array $src |\ArrayObject
     * @param string $class
     * @param string[] $properties
     * @return object
     * @throws MetaDeserializerException
     */
    public function deserializeObject($src, string $class, array $properties = null)
    {
        $class = "\\" . ltrim($class, "\\");
        if (!class_exists($class)) {
            throw new MetaDeserializerException("Class `$class` not found.");
        }
        $dest = $this->createObject($class);
        $this->deserializeObjectProperties($src, $dest, $properties);
        return $dest;
    }

    /**
     * @param array $src |\ArrayObject
     * @param object $dest
     * @param string[] $properties
     * @throws MetaDeserializerException
     */
    public function deserializeObjectProperties($src, $dest, array $properties = null) : void
    {
        if ($properties === null) $properties = array_keys(get_object_vars($dest));

        foreach ($properties as $k) {
            $this->deserializePropertyViaMethod($src, $dest, $k);
        }
    }
}