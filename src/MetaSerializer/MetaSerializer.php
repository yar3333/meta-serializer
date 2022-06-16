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

    /**
     * @param object $src
     * @param array $dest
     * @param string $property
     * @param \SplObjectStorage $usedObjects
     * @throws \ReflectionException
     */
    private function serializePropertyViaMethod($src, array &$dest, string $property, \SplObjectStorage $usedObjects) : void
    {
        $method = $property . $this->methodSuffix;
        if (method_exists($src, $method)) {
            $m = new \ReflectionMethod($src, $method);
            $m->setAccessible(true);
            $m->invokeArgs($src, [&$dest, $property, $this, $usedObjects]);
        } else {
            $this->serializeProperty($src, $dest, $property, $usedObjects);
        }
    }

    /**
     * @param object $src
     * @param array $dest
     * @param string $property
     * @param \SplObjectStorage|null $usedObjects
     * @throws MetaSerializerException
     * @throws \ReflectionException
     * @throws MetaSerializerException
     */
    private function serializeProperty($src, array &$dest, string $property, \SplObjectStorage $usedObjects = null) : void
    {
        if ($usedObjects === null) $usedObjects = new \SplObjectStorage();

        $p = new \ReflectionProperty($src, $property);

        $ignoreNull = false;
        $renameTo = null;

        $phpDoc = $p->getDocComment();
        if (is_string($phpDoc)) {
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'ignore\b/', $phpDoc)) {
                return;
            }
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'ignoreNull\b/', $phpDoc)) {
                $ignoreNull = true;
            }
            if (preg_match('/@' . $this->phpDocMetaPrefix . 'renameTo\s+([A-Za-z_]\w*)/', $phpDoc, $matches)) {
                $renameTo = $matches[1];
            }
        }

        if ($ignoreNull && $src->$property === null) return;

        $dest[$renameTo ?? $property] = $this->serializeValue($src->$property, $usedObjects);
    }

    /**
     * @param mixed $value
     * @param \SplObjectStorage|null $usedObjects
     * @return mixed
     * @throws MetaSerializerException
     * @throws \ReflectionException
     */
    public function serializeValue($value, \SplObjectStorage $usedObjects = null)
    {
        if ($usedObjects === null) $usedObjects = new \SplObjectStorage();

        if (is_array($value) || $value instanceof \ArrayObject) {
            $r = [];
            foreach ($value as $k => $v) {
                $r[$k] = $this->serializeValue($v, $usedObjects);
            }
            return $r;
        }

        if (is_object($value)) {
            return $this->serializeObject($value, null, $usedObjects);
        }

        return $value;
    }

    /**
     * @param object $obj
     * @param array|null $properties
     * @param \SplObjectStorage|null $usedObjects
     * @return array
     * @throws MetaSerializerException
     * @throws \ReflectionException
     */
    public function serializeObject($obj, array $properties = null, \SplObjectStorage $usedObjects = null) : array
    {
        if ($usedObjects === null) $usedObjects = new \SplObjectStorage();
        if ($usedObjects->contains($obj)) $this->onRecursiveObjectReferenceDetected($obj);
        $usedObjects->attach($obj);

        if ($properties === null) $properties = array_keys(get_object_vars($obj));

        $r = [];
        foreach ($properties as $k) {
            $this->serializePropertyViaMethod($obj, $r, $k, $usedObjects);
        }
        return $r;
    }

    protected function onRecursiveObjectReferenceDetected($value)
    {
        throw new MetaSerializerException("Recursive object reference detected for value type `" . get_class($value) . "`");
    }
}