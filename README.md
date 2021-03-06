meta-serializer
===============

[![Build Status](https://travis-ci.org/yar3333/meta-serializer.svg?branch=master)](https://travis-ci.org/yar3333/meta-serializer)
[![Latest Stable Version](https://poser.pugx.org/meta-serializer/meta-serializer/version)](https://packagist.org/packages/meta-serializer/meta-serializer)
[![Total Downloads](https://poser.pugx.org/meta-serializer/meta-serializer/downloads)](https://packagist.org/packages/meta-serializer/meta-serializer)

Production ready simple and flexible PHP serializer and deserializer.
Support `array`, `ArrayObject`, nested objects and associative arrays.

Install
-------
```sh
composer require meta-serializer/meta-serializer
```


Using
-----
Control serialization/deserialization by phpdoc @annotations or by field-related class methods.
Methods have a higher priority.
Class attributes for serialization/deserialization must be public.

Serializer:
 * support special phpdoc annotations: `@ignore`, `@ignoreNull` and `@renameTo` (see example);
 * override `onRecursiveObjectReferenceDetected` if need (throws exception by default);
 * override `serializeValue`  to support additional types;
 * catch `MetaSerializerException` to detect deserialization errors.

Deserializer:
 * use type from @var phpdoc (specify full class name with namespace like `\MyNamespace\MyNestedClass` or `\DateTime`);
 * support special phpdoc annotations: `@ignore`, `@optional` and `@sourceName` (see example);
 * override `onNoValueProvided` if need (throws exception by default);
 * override `onNotNullableValueIsNull` if need (throws exception by default);
 * override `deserializeValueNotNullableType` to support additional types;
 * `\DateTime` supported from string (in format suatable for `\DateTime` constructor) or number (unix timestamp);
 * catch `MetaDeserializerException` to detect deserialization errors.


Example
-------
```php
class MyClass
{
    /**
     * @var int|null This is a nullable integer.
     */
    public $a = 5;
    
    /**
     * Using field-related methods.
     */
    public $b = "str";
    protected function b__toJson(array &$data, string $prop, MetaSerializer $ser) { $data['myJsonFieldName'] = $this->b . "InJson"; }
    protected function b__fromJson(array $data, string $prop, MetaDeserializer $des) { $this->b = $data['myJsonFieldName']; }
    
    /**
     * Using phpdoc annotations.
     * @var string
     * @toJson_ignoreNull
     * @toJson_renameTo fieldC
     * @fromJson_optional
     * @fromJson_sourceName fieldC
     */
    public $c = "thisIsC";
}


// SERIALIZATION

$serializer = new MetaSerializer("__toJson", "toJson_");
$data = $serializer->serializeObject(new MyClass()); 
// data: [ "a" => 5, "myJsonFieldName" => "strInJson", "fieldC" => "thisIsC" ]


// DESERIALIZATION

$deserializer = new MetaDeserializer("__fromJson", "fromJson_");

// object will be created by deserializeObject(), so
// it must has constructor with no/default parameters
$obj = $deserializer->deserializeObject($data, MyClass::class); 
// obj: MyClass { a:5, b:"strInJson", c:"thisIsC" }

// manually object creating
$obj = new MyClass();
$deserializer->deserializeObjectProperties($data, $obj);
```
