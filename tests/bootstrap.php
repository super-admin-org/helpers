<?php
spl_autoload_register(function($class){
    $prefix = 'SuperAdmin\\Admin\\Helpers\\';
    $baseDir = __DIR__ . '/../src/';
    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

if (!class_exists('Illuminate\\Routing\\Controller')) {
    class Illuminate_Routing_Controller {}
    class_alias('Illuminate_Routing_Controller', 'Illuminate\\Routing\\Controller');
}

if (!class_exists('Illuminate\\Support\\Arr')) {
    class Illuminate_Support_Arr {
        public static function sort($array, callable $callback) {
            uasort($array, function($a, $b) use ($callback) {
                return $callback($a) <=> $callback($b);
            });
            return $array;
        }
    }
    class_alias('Illuminate_Support_Arr', 'Illuminate\\Support\\Arr');
}
