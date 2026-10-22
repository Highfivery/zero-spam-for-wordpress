# PHPStan stubs

`wp-cli-stubs.php` is copied verbatim from
[php-stubs/wp-cli-stubs v2.12.0](https://github.com/php-stubs/wp-cli-stubs/tree/v2.12.0)
(MIT, see `LICENSE`). It is vendored here rather than required through
Composer because that package pins `php-stubs/wordpress-stubs` to 6.x while
this plugin analyses against the 7.x stubs.

To refresh them, replace the file with the same-named file from a
newer tag and update the version above.

`elementor-pro-stubs.php` is hand-written: it declares only the Elementor Pro
forms classes and methods that `modules/elementor/class-elementor.php` calls.
Add to it when the module starts using more of that API.
