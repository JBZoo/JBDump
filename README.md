JBDump
======

Script for dumping PHP vars and other debugging.
To put it simply, this tool is a perfectly replacement for print_r() and var_dump().

## Output example
![JBDump example#1](http://llfl.ru/images/_gif/jbdump2.gif)
![JBDump example#2](http://llfl.ru/images/ga/m6f.png)

## Install
Just include class.jbdump.php

You can see examples init:

`php.ini`  `.htaccess`  `include` 

# php.ini for windows
```sh
auto_prepend_file = Z:\home\adm\jbdump\class.jbdump.php
```
# php.ini for unix-like
```sh
auto_prepend_file = /var/www/jdump/data/public_html/class.jbdump.php
```
# .htaccess

```sh
php_value auto_prepend_file C:\OpenServer\domains\jbdump\class.jbdump.php
```

#  include
```sh
include './jbdump/class.jbdump.php';
```

## Using
`jbdump($myVar);`

## Live demo
http://jbdump.org/test/

## Composer
composer require "jbzoo/jbdump:1.x-dev"
