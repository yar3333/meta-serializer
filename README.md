meta-serializer
===============

[![Build Status](https://travis-ci.org/yar3333/meta-serializer.svg?branch=master)](https://travis-ci.org/yar3333/meta-serializer)
[![Latest Stable Version](https://poser.pugx.org/meta-serializer/meta-serializer/version)](https://packagist.org/packages/meta-serializer/meta-serializer)
[![Total Downloads](https://poser.pugx.org/meta-serializer/meta-serializer/downloads)](https://packagist.org/packages/meta-serializer/meta-serializer)

PHP serializer/deserializer with types from phpdoc and annotation-like functions.
Support array, ArrayObject, nested objects and associative arrays.

Install
-------
```sh
composer require meta-serializer/meta-serializer
```

Using
-----

Quick example:
```php
class MyClass
{
	/**
	 * @var ?int This is nullable int.
	 */
	public $a = 5;
	
	/**
	 * Example of using meta-like methods.
	 */
	public $b = "str";
	protected function b__toJson(array &$data, string $prop, MetaSerializer $ser) { $data['myJsonFieldName'] = $this->b . "InJson"; }
	protected function b__fromJson(array $data, string $prop, MetaDeserializer $des) { $this->b = $data['myJsonFieldName']; }
	
	/**
	 * Example of using phpdoc-meta.
	 * For serializer: `ignore`, `ignoreNull` and `renameTo`.
	 * For deserializer: `optional` and `sourceName`.
	 * @var string
	 * @toJson_ignoreNull
	 * @toJson_renameTo fieldC
	 * @fromJson_optional
	 * @fromJson_sourceName fieldC
	 */
	public $c = "thisIsC";
}

$ser = new MetaSerializer("__toJson", "toJson_");
$data = $ser->serializeObject(new MyClass()); // [ "a" => 5, "myJsonFieldName" => "strInJson", "fieldC" => "thisIsC" ]

$des = new MetaDeserializer("__fromJson", "fromJson_");

$obj = $des->deserializeObject($data, MyClass::class); // $obj->a = 5, $obj->b = "strInJson", $obj->c = "thisIsC"

$obj = new MyClass(); // manually object creating
$des->deserializeObjectProperties($data, $obj);
```
