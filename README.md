# Polymorphine Message
[![Build Status](https://travis-ci.org/shudd3r/polymorphine-message.svg?branch=develop)](https://travis-ci.org/shudd3r/polymorphine-message)
[![Coverage Status](https://coveralls.io/repos/github/shudd3r/polymorphine-message/badge.svg?branch=develop)](https://coveralls.io/github/shudd3r/polymorphine-message?branch=develop)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/polymorphine/message/dev-develop.svg)](https://packagist.org/packages/polymorphine/message)
[![Packagist](https://img.shields.io/packagist/l/polymorphine/message.svg)](https://packagist.org/packages/polymorphine/message)
### PHP Psr-7 Http message implementation

### Installation with [Composer](https://getcomposer.org/)
    php composer.phar require polymorphine/message

### Basic usage

##### ServerRequest instance
The straight forward way is to instantiate [`ServerRequest`](src/ServerRequest.php) from
server globals using `ServerRequest::fromGlobals()` named constructor:

    use Polymorphine\Message\ServerRequest;
    
    $override['get'] = ['id' => 123];
    $request = ServerRequest::fromGlobals($override);
    
Instead overriding server provided data you can fake it entirely by passing filled arrays to
factory's constructor:

    use Polymorphine\Message\ServerRequest;
    use Polymorphine\Message\ServerData;
    
    $request = new ServerRequest::fromServerData(new ServerData([
        'server' => [...],
        'get'    => [...],
        'post'   => [...],
        'cookie' => [...],
        'files'  => [...]
    ]));

Because complete ServerRequest contains large amount of data, other methods of instantiation
would require much more efforts, and they'll be used mostly for testing with only necessary
values provided - check [`ServerRequest`](src/ServerRequest.php) constructor parameters.
