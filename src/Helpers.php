<?php

namespace SuperAdmin\Admin\Helpers;

use SuperAdmin\Admin\Admin;
use SuperAdmin\Admin\Auth\Database\Menu;
use SuperAdmin\Admin\Extension;

class Helpers extends Extension
{
    /**
     * Bootstrap this package.
     *
     * @return void
     */
    public static function boot()
    {
        static::registerRoutes();

        Admin::extend('helpers', __CLASS__);
    }

    /**
     * Register routes for super-admin.
     *
     * @return void
     */
    public static function registerRoutes()
    {
        parent::routes(function ($router) {
            /* @var \Illuminate\Routing\Router $router */
            $router->get('helpers/terminal/database', 'SuperAdmin\Admin\Helpers\Controllers\TerminalController@database');
            $router->post('helpers/terminal/database', 'SuperAdmin\Admin\Helpers\Controllers\TerminalController@runDatabase');
            $router->get('helpers/terminal/artisan', 'SuperAdmin\Admin\Helpers\Controllers\TerminalController@artisan');
            $router->post('helpers/terminal/artisan', 'SuperAdmin\Admin\Helpers\Controllers\TerminalController@runArtisan');
            $router->get('helpers/scaffold', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@index')->name('scaffold.index');
            $router->get('helpers/scaffold/create', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@create')->name('scaffold.create');
            $router->get('helpers/scaffold/{id}/edit', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@edit')->name('scaffold.edit');
            //$router->get('helpers/scaffold/list', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@list')->name('scaffold.list');
            $router->post('helpers/scaffold/{id}/update', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@update')->name('scaffold.update');
            $router->delete('helpers/scaffold/{id}', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@destroy')->name('scaffold.destroy');
            $router->post('helpers/scaffold', 'SuperAdmin\Admin\Helpers\Controllers\ScaffoldController@store')->name('scaffold.store');
            $router->get('helpers/routes', 'SuperAdmin\Admin\Helpers\Controllers\RouteController@index');
        });
    }

    public static function import()
    {
        $lastOrder = Menu::max('order');

        $root = [
            'parent_id' => 0,
            'order'     => $lastOrder++,
            'title'     => 'Helpers',
            'icon'      => 'icon-cogs',
            'uri'       => '',
        ];

        $root = Menu::create($root);

        $menus = [
            [
                'title'     => 'Scaffold',
                'icon'      => 'icon-keyboard',
                'uri'       => 'helpers/scaffold',
            ],
            [
                'title'     => 'Database terminal',
                'icon'      => 'icon-database',
                'uri'       => 'helpers/terminal/database',
            ],
            [
                'title'     => 'Laravel artisan',
                'icon'      => 'icon-terminal',
                'uri'       => 'helpers/terminal/artisan',
            ],
            [
                'title'     => 'Routes',
                'icon'      => 'icon-list-alt',
                'uri'       => 'helpers/routes',
            ],
        ];

        foreach ($menus as $menu) {
            $menu['parent_id'] = $root->id;
            $menu['order'] = $lastOrder++;

            Menu::create($menu);
        }

        parent::createPermission('Admin helpers', 'ext.helpers', 'helpers/*');
    }
}
