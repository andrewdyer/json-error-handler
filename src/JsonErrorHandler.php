<?php

declare(strict_types=1);

namespace YourVendor\YourPackage;

use AndrewDyer\Actions\Payloads\ActionError;
use AndrewDyer\Actions\Payloads\ActionPayload;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Throwable;

/**
 * Processes application exceptions into structured JSON error responses.
 */
final class JsonErrorHandler extends ErrorHandler
{
    /**
     * Indicates JSON encoding flags used for error payload serialisation.
     */
    private readonly int $jsonEncodeFlags;

    /**
     * Builds a new HTTP error handler.
     *
     * @param CallableResolverInterface $callableResolver Resolves error renderer callables.
     * @param ResponseFactoryInterface $responseFactory Creates PSR-7 response instances.
     * @param LoggerInterface|null $logger Logs handled errors when logging is enabled.
     * @param int $jsonEncodeFlags Indicates JSON encoding flags for payload serialisation.
     * @return void Returns after initialising the handler.
     */
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        ?LoggerInterface $logger = null,
        int $jsonEncodeFlags = JSON_PRETTY_PRINT
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);

        $this->jsonEncodeFlags = $jsonEncodeFlags;
    }

    /**
     * Processes the current exception into a JSON response payload.
     *
     * @return ResponseInterface Returns the JSON error response.
     * @internal
     */
    protected function respond(): ResponseInterface
    {
        $statusCode = 500;
        $description = 'An internal error has occurred while processing your request.';
        $exception = $this->exception;

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode() > 0 ? $exception->getCode() : 500;
            $description = $exception->getMessage();
            $error = $this->mapHttpExceptionToActionError($exception, $description);
        } elseif ($exception instanceof Throwable && $this->displayErrorDetails) {
            $description = $exception->getMessage();
            $error = ActionError::serverError($description);
        } else {
            $error = ActionError::serverError($description);
        }

        $payload = ActionPayload::error($error, $statusCode);

        $encodedPayload = json_encode($payload, $this->jsonEncodeFlags);
        if ($encodedPayload === false) {
            $encodedPayload = '{"error":{"type":"SERVER_ERROR","description":"An internal error has occurred while processing your request."}}';
            $statusCode = 500;
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write($encodedPayload);

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Returns an action error mapped from an HTTP exception type.
     *
     * @param HttpException $exception Indicates the HTTP exception instance to map.
     * @param string $description Indicates the error description to include in the payload.
     * @return ActionError Returns the mapped action error.
     * @internal
     */
    private function mapHttpExceptionToActionError(HttpException $exception, string $description): ActionError
    {
        return match (true) {
            $exception instanceof HttpNotFoundException => ActionError::notFound($description),
            $exception instanceof HttpMethodNotAllowedException => ActionError::notAllowed($description),
            $exception instanceof HttpUnauthorizedException => ActionError::unauthenticated($description),
            $exception instanceof HttpForbiddenException => ActionError::insufficientPrivileges($description),
            $exception instanceof HttpBadRequestException => ActionError::badRequest($description),
            $exception instanceof HttpNotImplementedException => ActionError::notImplemented($description),
            default => ActionError::serverError($description),
        };
    }
}
