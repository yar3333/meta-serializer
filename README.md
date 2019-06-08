meta-serializer
===============

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
	
	public $b = "str";
	protected b__toJson(&$data, $property) { $data['myJsonFieldName'] = $this->b . "InJson"; }
	protected b__fromJson($data, $property) { $this->b = $data['myJsonFieldName']; }
}

$ser = new MetaSerializer("__toJson");
$data = $ser->serializeObject(new MyClass()); // [ "a" => 5, "myJsonFieldName" => "strInJson" ]

$des = new MetaDeserializer("__fromJson");

$obj = $des->deserializeObject($data, MyClass::class); // $obj->a = 5, $obj->b = "strInJson"

$obj = new MyClass(); // manually object creating
$des->deserializeObjectProperties($data, $obj);
```
