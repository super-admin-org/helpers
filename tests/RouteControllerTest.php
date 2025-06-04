<?php

use PHPUnit\Framework\TestCase;
use SuperAdmin\Admin\Helpers\Controllers\RouteController;

class RouteControllerTest extends TestCase
{
    private function invokeSortRoutes(RouteController $controller, $column, $routes)
    {
        $ref = new ReflectionClass(RouteController::class);
        $method = $ref->getMethod('sortRoutes');
        $method->setAccessible(true);
        return $method->invokeArgs($controller, [$column, $routes]);
    }

    public function testSortRoutesOrdersByGivenColumn()
    {
        $controller = new RouteController();

        $routes = [
            ['uri' => 'c', 'name' => 'route3'],
            ['uri' => 'a', 'name' => 'route1'],
            ['uri' => 'b', 'name' => 'route2'],
        ];

        $sorted = $this->invokeSortRoutes($controller, 'uri', $routes);
        $this->assertSame(['a', 'b', 'c'], array_values(array_column($sorted, 'uri')));
    }
}
