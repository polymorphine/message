# Polymorphine/Message
[![Latest Stable Version](https://poser.pugx.org/polymorphine/message/version)](https://packagist.org/packages/polymorphine/message)
[![Build status](https://github.com/polymorphine/message/workflows/build/badge.svg)](https://github.com/polymorphine/message/actions)
[![Coverage Status](https://coveralls.io/repos/github/polymorphine/message/badge.svg?branch=master)](https://coveralls.io/github/polymorphine/message?branch=master)
[![PHP version](https://img.shields.io/packagist/php-v/polymorphine/message.svg)](https://packagist.org/packages/polymorphine/message)
[![LICENSE](https://img.shields.io/github/license/polymorphine/message.svg?color=blue)](LICENSE)
### PSR-7 Http message implementation

### Installation with [Composer](https://getcomposer.org/)
    php composer.phar require polymorphine/message

### Basic usage

Public API of base classes in this package is an implementation of [PSR-7: HTTP message interfaces](https://www.php-fig.org/psr/psr-7/)
and won't be described here. Below you'll find description of object instantiation methods and
their parameter formatting specific to this package.

#### PSR-17 Factories
Not every aspect of created objects can be changed with mutation methods (like in `UriInterface`),
that's where the need for common factory interfaces came from. Packages that want to rely on abstract
psr/message interface, and retain control of passing immutable parameters to encapsulated objects from
within their classes will depend on [PSR-17: HTTP Factories](https://www.php-fig.org/psr/psr-17/).
For interoperability reasons Package includes implementations of these [factories](src/Factory).

#### Direct instantiation
Constructor instantiation for each of the classes described below allows to create fully configured
objects, and since some of them contains many encapsulated parameters instantiating such object might
be tedious and unreadable. For some, frequently created objects static constructors are provided to
pass some predefined parameters.

##### ServerRequest
The straight forward way is to instantiate [`ServerRequest`](src/ServerRequest.php) from
server globals using `ServerRequest::fromGlobals()` named constructor and optionally overriding
its parameters passing array with `server`, `get`, `post`, `cookie` or `files` keys:

```php
use Polymorphine\Message\ServerRequest;

$override['get'] = ['id' => 123];
$request = ServerRequest::fromGlobals($override);
```

This will be most typical way to create ServerRequest instance. Because complete ServerRequest
contains large amount of data, other methods of instantiation in production application use cases
would require much more effort, and they'll be used mostly for testing with only necessary values
provided. For example instead overriding server provided data you can fake it entirely by passing
filled arrays to factory's constructor:

```php
use Polymorphine\Message\ServerRequest;
use Polymorphine\Message\ServerData;

$request = new ServerRequest::fromServerData(new ServerData([
    'server' => [...],
    'get'    => [...],
    'post'   => [...],
    'cookie' => [...],
    'files'  => [...]
]));
```

Or create new instance directly passing various parameters: request method string, `UriInterface`,
`StreamInterface` body, headers array and parameters with `UploadedFileInterface` array, protocol
version, parsed body, cookie array... etc. Check [`ServerRequest`](src/ServerRequest.php) constructor
phpDoc and some of its [parameters implementations](#uri-stream--uploadedfile) for more details.

##### Request & Response
[`Request`](src/Request.php) can be created with constructor similar to used in `ServerRequest`,
but `$params` array uses only `version` and `target` keys that defaults to `1.1` and string resolved
from `$uri` parameter.

[`Response`](src/Response.php) comes with several convenient static constructors that create instance
preconfigured with status code or specific headers (usually `Content-Type`). Default constructor
parameters are similar to `Request` constructor where method and Uri were replaced by status code,
and `reason` phrase instead `target` in params.

##### Uri, Stream & UploadedFile
Default constructor for [`Uri`](src/Uri.php) requires array of segments, which is not convenient, but
static `Uri::fromString()` method will create instance by parsing supplied string. 

Constructor for [`Stream`](src/Stream.php) takes stream resource type, but two static methods will
help creating one - either with uri and access mode or with body string to encapsulate.

[`UploadedFile`](src/UploadedFile.php) all constructor parameters can be derived from server's `$_FILES`
superglobal. Actually all, except `StreamInterface`, could be passed directly. Secondary constructor
method - `UploadedFile::fromFileArray()` - is a convenient way of translating superglobal into class
instance creating stream instance in the process.

Note that for multiple files superglobal data structure is populated in somewhat transposed fashion,
so extracting it to create multiple instances of `UploadedFile` requires some iterations over its
nested structure. Since these objects will be used mostly as server request property, instances for
multiple files are created within [`ServerData`](src/ServerData.php) object. You can use this class
to create the array of multiple uploaded files from transposed array (normally `$_FILES`) if you
want to do it separately:

```php
$serverData    = new ServerData(['files' => $_FILES]);
$uploadedFiles = $serverData->uploadeFiles();
```

##### UploadedFile for non-SAPI environments
[`UploadedFileFactory`](src/Factory/UploadedFileFactory.php) by default creates `UploadedFile` instance
for web server environments, which support `$_FILES` superglobal and security mechanism that can tell
whether given file was really uploaded or not (you cannot simply pick any file in the filesystem and
move it somewhere else). In case of other types of http servers (like command line scripts listening
for http requests in some event loop), you cannot use `move_uploaded_file()` function and need to
handle this process differently.

Package includes [`NonSAPIUploadedFile`](src/NonSAPIUploadedFile.php) that can move file (stream) in
non-SAPI environments, but security part (recognising that file was uploaded) depends on implementation
and should be resolved internally (when creating stream).
You can create its instance directly or with factory that was instantiated with specific sapi name
(for example: `cli`) or empty string. You can also resolve it automatically and create either
`UploadedFile` or `NonSAPIUploadedFile` depending on predefined `PHP_SAPI` constant:

```php
$factory = new UploadedFileFactory(PHP_SAPI);
$file    = $factory->createUploadedFile($stream);
```
