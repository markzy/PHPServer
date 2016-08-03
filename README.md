# PHPServer
HttpSever implemented with PHP

## Features
This server supports
* Multi-process and prefork mechanism
* HTTP GET, POST and Keep-alive
* Support php-cgi
* Yaf support (Only Rewrite rules) with Rewrite Engine (means you can run Yaf Applications!)

## Usage
Put your resources in `sites` folder

Running PHPServer directly
```shell
php MasterServer.php
```
In any browser (Chrome, Safari, etc.), enter localhost:12000/yoursite, then you can get what you want!

## Other Tips
You may change your site root in `config.php`
```php
class Config {
    static  $docroot ="where you like";
}
```
