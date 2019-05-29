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
=====

```php
class MyClass
{
	/**
	 * @var ?int This is nullable int.
	 */
	public $a;
	
	public $b = "str";
	protected b__toJson(&$data, $property) { $data['myJsonFieldName'] = $this->b . "InJson"; }
	protected b__fromJson($data, $property) { $this->b = $data['myJsonFieldName']; }
}

$ser = new MetaSerializer("__toJson");
$data = $ser->serializeObject(new MyClass());
var_dump($data);

$des = new MetaDeserializer("__fromJson");
$obj = $des->deserializeObject($data, MyClass::class);
var_dump($obj);
```
