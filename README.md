![JSON Error Handler](https://public-assets.andrewdyer.rocks/images/covers/json-error-handler.png)

<p align="center">
  <a href="https://packagist.org/packages/andrewdyer/json-error-handler"><img src="https://poser.pugx.org/andrewdyer/json-error-handler/v/stable?style=for-the-badge" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/andrewdyer/json-error-handler"><img src="https://poser.pugx.org/andrewdyer/json-error-handler/downloads?style=for-the-badge" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/andrewdyer/json-error-handler"><img src="https://poser.pugx.org/andrewdyer/json-error-handler/license?style=for-the-badge" alt="License"></a>
  <a href="https://packagist.org/packages/andrewdyer/json-error-handler"><img src="https://poser.pugx.org/andrewdyer/json-error-handler/require/php?style=for-the-badge" alt="PHP Version Required"></a>
</p>

# JSON Error Handler

A structured JSON error handler for [Slim Framework](https://www.slimframework.com/) applications that maps exceptions to typed, consistent error payloads.

## Introduction

This library provides a JSON error handler for Slim applications. It extends Slim's built-in error handling to intercept exceptions and transform them into structured JSON responses, mapping HTTP exceptions to typed error payloads with appropriate status codes. The handler supports optional error detail exposure for debug environments and integrates directly with Slim's error middleware and shutdown handling workflows.

## Prerequisites

- **[PHP](https://www.php.net/)**: Version 8.3 or higher is required.
- **[Composer](https://getcomposer.org/)**: Dependency management tool for PHP.
- **[Slim Framework](https://www.slimframework.com/)**: Version 4 is required.

## Installation

```bash
composer require andrewdyer/json-error-handler
```

## Getting Started

### 1. Create the application

```php
use Slim\Factory\AppFactory;

$app = AppFactory::create();
```

### 2. Add error middleware

Add the error middleware and set `JsonErrorHandler` as the default handler. The `$displayErrorDetails` flag controls whether exception messages are included in responses — this should be `false` in production:

```php
use AndrewDyer\JsonErrorHandler\JsonErrorHandler;

$displayErrorDetails = true;

$errorMiddleware = $app->addErrorMiddleware(
    $displayErrorDetails,
    logErrors: true,
    logErrorDetails: true
);

$errorHandler = new JsonErrorHandler(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    logger: null
);

$errorMiddleware->setDefaultErrorHandler($errorHandler);
```

> **Note:** A PSR-3 logger can be passed as the third argument to enable error logging. [Monolog](https://github.com/Seldaek/monolog) is a popular choice for this.

By default, payloads are encoded with `JSON_PRETTY_PRINT`. Custom flags can be passed as the fourth constructor argument:

```php
$errorHandler = new JsonErrorHandler(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    logger: null,
    jsonEncodeFlags: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
```

Note that `JSON_THROW_ON_ERROR` is always masked out internally to prevent encoding failures from cascading during error handling.

### 3. Register routes

Register routes to handle incoming requests:

```php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

$app->get('/hello', function (Request $request, Response $response): Response {
    $response->getBody()->write(json_encode(['message' => 'Hello, world.']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/error', function (Request $request, Response $response): Response {
    throw new HttpNotFoundException($request);
});
```

### 4. Run the application

Start the application to begin handling incoming HTTP requests:

```php
$app->run();
```

## Usage

Once the handler is registered, Slim will route exceptions through `JsonErrorHandler` and return structured JSON error responses.

### Successful request

```
GET /hello
Accept: application/json
```

**Response: 200 OK**

```json
{
  "message": "Hello, world."
}
```

### Error request

```
GET /error
Accept: application/json
```

**Response: 404 Not Found**

```json
{
  "error": {
    "type": "RESOURCE_NOT_FOUND",
    "description": "Not found."
  }
}
```

## Advanced Usage

### Shutdown handler integration

For complete fatal error coverage — including errors that occur outside of Slim's request lifecycle — `JsonErrorHandler` can be integrated with [andrewdyer/shutdown-handler](https://github.com/andrewdyer/shutdown-handler):

```bash
composer require andrewdyer/shutdown-handler
```

Wrap `JsonErrorHandler` in a `CallableErrorResponder` and register a `ShutdownHandler` before running the application:

```php
use AndrewDyer\JsonErrorHandler\JsonErrorHandler;
use AndrewDyer\ShutdownHandler\Adapters\CallableErrorResponder;
use AndrewDyer\ShutdownHandler\Adapters\CallableResponseEmitter;
use AndrewDyer\ShutdownHandler\ShutdownHandler;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\ResponseEmitter;

$app = AppFactory::create();

$displayErrorDetails = true;

$errorMiddleware = $app->addErrorMiddleware(
    $displayErrorDetails,
    logErrors: true,
    logErrorDetails: true
);

$errorHandler = new JsonErrorHandler(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    logger: null
);

$errorMiddleware->setDefaultErrorHandler($errorHandler);

$request = ServerRequestCreatorFactory::create()->createServerRequestFromGlobals();

$shutdownHandler = new ShutdownHandler(
    $request,
    new CallableErrorResponder(
        static fn ($request, $exception, bool $displayErrorDetails) => $errorHandler(
            $request,
            $exception,
            $displayErrorDetails,
            logError: true,
            logErrorDetails: true
        )
    ),
    new CallableResponseEmitter(
        static fn ($response) => (new ResponseEmitter())->emit($response)
    ),
    $displayErrorDetails
);

register_shutdown_function($shutdownHandler);

$app->run();
```

Refer to the [shutdown-handler documentation](https://github.com/andrewdyer/shutdown-handler) for full details on implementing a response emitter.

## License

Licensed under the [MIT license](https://opensource.org/licenses/MIT) and is free for private or commercial projects.
