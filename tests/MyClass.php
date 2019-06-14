<?php

namespace MetaSerializerTests;

use MetaSerializer\MetaSerializer;
use MetaSerializer\MetaDeserializer;

class MyClass
{
	/**
	 * @var ?int
	 */
	public $a = 5;

	/**
	 * Example of using meta-like methods.
	 */
	public $b = 'str';
	protected function b__toJson(array &$data, string $prop, MetaSerializer $ser) { $data['myJsonFieldB'] = $this->b . "InJson"; }
	protected function b__fromJson(array $data, string $prop, MetaDeserializer $des) { $this->b = $data['myJsonFieldB']; }

	/**
	 * Example of using phpdoc-meta.
	 * For serializer: `ignoreNull` and `renameTo`.
	 * For deserializer: `optional` and `sourceName`.
	 * @var string
	 * @toJson_ignoreNull
	 * @toJson_renameTo fieldC
	 * @fromJson_optional
	 * @fromJson_sourceName fieldC
	 */
	public $c = 'thisIsC';

	/**
	 * @toJson_ignoreNull
	 * @fromJson_optional
	 */
	public $e;

	public $d;

    /**
     * @var MyNestedClass
     */
    public $obj;

    /**
     * @var string[] sdvcsda
     */
    public $arr = [ 1,2,3,4,5 ];

    public function __construct()
    {
        $this->obj = new MyNestedClass();
    }
}
