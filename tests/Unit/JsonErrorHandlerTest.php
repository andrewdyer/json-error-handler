<?php

declare(strict_types=1);

namespace AndrewDyer\JsonErrorHandler\Tests\Unit;

use AndrewDyer\JsonErrorHandler\JsonErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\CallableResolver;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Unit tests for JsonErrorHandlerTest.
 */
class JsonErrorHandlerTest extends TestCase
{
    /**
     * Asserts that HTTP not found exceptions are mapped to a structured not found payload.
     */
    public function testRespondsWithMappedHttpExceptionPayload(): void
    {
        $request = $this->createRequest();
        $exception = new HttpNotFoundException($request);

        $response = $this->invokeHandler($request, $exception, false);
        $body = $this->decodeJson($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('RESOURCE_NOT_FOUND', $body['error']['type'] ?? null);
        $this->assertSame('Not found.', $body['error']['description'] ?? null);
    }

    /**
     * Asserts that throwable messages are hidden when error details are disabled.
     */
    public function testHidesThrowableMessageWhenDisplayErrorDetailsIsDisabled(): void
    {
        $request = $this->createRequest();
        $exception = new RuntimeException('Visible only in debug mode');

        $response = $this->invokeHandler($request, $exception, false);
        $body = $this->decodeJson($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('SERVER_ERROR', $body['error']['type'] ?? null);
        $this->assertSame(
            'An internal error has occurred while processing your request.',
            $body['error']['description'] ?? null
        );
    }

    /**
     * Asserts that throwable messages are included when error details are enabled.
     */
    public function testIncludesThrowableMessageWhenDisplayErrorDetailsIsEnabled(): void
    {
        $request = $this->createRequest();
        $exception = new RuntimeException('Visible only in debug mode');

        $response = $this->invokeHandler($request, $exception, true);
        $body = $this->decodeJson($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('SERVER_ERROR', $body['error']['type'] ?? null);
        $this->assertSame('Visible only in debug mode', $body['error']['description'] ?? null);
    }

    /**
     * Asserts that JSON encoding failures return the safe fallback error payload.
     */
    public function testFallsBackToSafePayloadWhenJsonEncodingFails(): void
    {
        $request = $this->createRequest();
        $exception = new RuntimeException("Invalid UTF-8 byte: \xB1");

        $response = $this->invokeHandler($request, $exception, true);
        $body = $this->decodeJson($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('SERVER_ERROR', $body['error']['type'] ?? null);
        $this->assertSame(
            'An internal error has occurred while processing your request.',
            $body['error']['description'] ?? null
        );
    }

    /**
     * Creates a server request configured for JSON error responses.
     *
     * @return ServerRequestInterface Returns the JSON server request.
     * @internal
     */
    private function createRequest(): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Processes an exception through the HTTP error handler.
     *
     * @param ServerRequestInterface $request Indicates the request used for handler invocation.
     * @param \Throwable $exception Indicates the exception to process.
     * @param bool $displayErrorDetails Indicates whether detailed exception messages are enabled.
     * @return ResponseInterface Returns the generated error response.
     * @internal
     */
    private function invokeHandler(
        ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails
    ): ResponseInterface {
        $handler = new JsonErrorHandler(new CallableResolver(), new ResponseFactory());

        return $handler($request, $exception, $displayErrorDetails, false, false);
    }

    /**
     * Returns the decoded JSON response body as an associative array.
     *
     * @param ResponseInterface $response Indicates the response containing a JSON body.
     * @return array<string, mixed> Returns the decoded response payload.
     * @throws \JsonException When the response body cannot be decoded as valid JSON.
     * @internal
     */
    private function decodeJson(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
