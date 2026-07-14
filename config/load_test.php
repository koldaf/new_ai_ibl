<?php

return [
    /*
     * PHP_BINARY resolves to the wrong executable when this runs under
     * PHP-FPM (it points at php-fpm itself, not the php CLI binary), which
     * makes every spawned load-test subprocess fail immediately with no
     * output. Set LOAD_TEST_PHP_BINARY to an absolute path (e.g. output of
     * `which php` on the server) if the 'php' default isn't on PATH for
     * your PHP-FPM pool's environment.
     */
    'php_binary' => env('LOAD_TEST_PHP_BINARY', 'php'),
];
