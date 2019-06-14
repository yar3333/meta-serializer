<?php

namespace MetaSerializerTests;

require_once 'autoload.php';

use DateTime;
use MetaSerializer\MetaDeserializer;
use MetaSerializer\MetaSerializer;
use PHPUnit\Framework\TestCase;

class MainTest extends TestCase
{
    public function testSerializerBasic() : void
    {
        $ser = new MetaSerializer();

        self::assertEquals(1, $ser->serializeValue(1));
        self::assertEquals('abc', $ser->serializeValue('abc'));
    }

    public function testDeserializerBasic() : void
    {
        $des = new MetaDeserializer();
        self::assertEquals(1, $des->deserializeValue(1));
        self::assertEquals('abc', $des->deserializeValue('abc'));
        self::assertInstanceOf(DateTime::class, $des->deserializeValue("2000-01-01T00:00:00Z", DateTime::class));

    }

    public function testSerializerClass() : void
    {
        $ser = new MetaSerializer("__toJson", "toJson_");

        $data = $ser->serializeObject(new MyClass());

        self::assertEquals(5, $data['a']);
        self::assertEquals('strInJson', $data['myJsonFieldB']);
        self::assertEquals('thisIsC', $data['fieldC']);
        self::assertIsArray($data['obj']);
        self::assertEquals('zzz', $data['obj']['z']);
        self::assertArrayNotHasKey('e', $data);
        self::assertEquals(null, $data['d']);
    }

    public function testDeserializerClass() : void
    {
        $ser = new MetaSerializer("__toJson", "toJson_");
        $obj = new MyClass();
        $obj->obj->z = "myValueZ";
        $data = $ser->serializeObject($obj);
        self::assertEquals('myValueZ', $data['obj']['z']);
        $json_str = json_encode($data, JSON_FORCE_OBJECT);

        $des = new MetaDeserializer("__fromJson", "fromJson_");
        $obj = $des->deserializeObject(json_decode($json_str, true), MyClass::class);

        self::assertEquals(5, $obj->a);
        self::assertEquals('strInJson', $obj->b);
        self::assertEquals('thisIsC', $obj->c);
        self::assertInstanceOf(MyNestedClass::class, $obj->obj);
        self::assertEquals('myValueZ', $obj->obj->z);
    }
}
