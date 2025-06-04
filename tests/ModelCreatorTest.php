<?php

use PHPUnit\Framework\TestCase;
use SuperAdmin\Admin\Helpers\Scaffold\ModelCreator;

class ModelCreatorTest extends TestCase
{
    private function invokeReplaceClass(ModelCreator $creator, &$stub, $name)
    {
        $ref = new ReflectionClass(ModelCreator::class);
        $method = $ref->getMethod('replaceClass');
        $method->setAccessible(true);
        $method->invokeArgs($creator, [&$stub, $name]);
    }

    public function testReplaceClassSubstitutesClassName()
    {
        $creator = new ModelCreator('users', 'App\\Models\\User', new stdClass());
        $stub = 'class DummyClass {}';
        $this->invokeReplaceClass($creator, $stub, 'App\\Models\\User');
        $this->assertSame('class User {}', $stub);
    }
}
