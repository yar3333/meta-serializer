<?php

namespace MetaSerializer;

class MetaSerializer
{
    public $methodSuffix;
    public $phpDocMetaPrefix;

    public function __construct(string $methodSuffix = "_serialize", string $phpDocMetaPrefix = "")
    {
        $this->methodSuffix = $methodSuffix;
        $this->phpDocMetaPrefix = $phpDocMetaPrefix;
    }

    private function serializePropertyViaMethod(object $src, array &$dest, string $property) : void
    {
        $method = $property . $this->methodSuffix;
        if (method_exists($src, $method)) {
            $m = new \ReflectionMethod($src, $method);
            $m->setAccessible(true);
            $m->invokeArgs($src, [ &$dest, $property, $this ]);
        }
        else {
            $this->serializeProperty($src, $dest, $property);
        }
    }

    private function serializeProperty(object $src, array &$dest, string $property) : void
    {
        $p = new \ReflectionProperty($src, $property);

        $ignoreNull = false;
        $renameTo = null;

        $phpDoc = $p->getDocComment();
        if (is_string($phpDoc)) {
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'ignoreNull\b/', $phpDoc)) {
                $ignoreNull = true;
            }
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'renameTo\s+([A-Za-z_][A-Za-z_0-9]*)/', $phpDoc, $matches)) {
                $renameTo = $matches[1];
            }
        }

        if ($ignoreNull && $src->$property === null) return;

        $dest[$renameTo ?? $property] = $this->serializeValue($src->$property);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function serializeValue($value)
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

    public function serializeObject(object $obj, array $properties=null) : array
    {
        if ($properties === null) $properties = array_keys(get_object_vars($obj));

        $r = [];
        foreach ($properties as $k) {
            $this->serializePropertyViaMethod($obj, $r, $k);
        }
        return $r;
    }
}