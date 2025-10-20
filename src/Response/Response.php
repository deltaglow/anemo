<?php

namespace DeltaGlow\Anemo\Response;

use Swoole\Coroutine\Http\Client;
use Swoole\Http2\Response as Http2Response;

class Response
{
    private Client|Http2Response $object;
    private string $body;
    private int $statusCode;
    private array $headers;

    public function __construct(Client|Http2Response $object)
    {
        $this->object = $object;
        if($object instanceof Client) {
            $this->body = $object->getBody() ?? '';
            $this->statusCode = $object->getStatusCode();
            $this->headers = $object->getHeaders() ?? [];
            $object->close();
        } elseif ($object instanceof Http2Response) {
            $this->body = $object->data;
            $this->statusCode = $object->statusCode;
            $this->headers = $object->headers;
        }
    }

    /**
     * Get the response body as a string.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Get the response body as a JSON decoded array.
     */
    public function json(): ?array
    {
        return json_decode($this->body, true);
    }

    public function object(): ?object
    {
        return json_decode($this->body);
    }

    /**
     * Get the HTTP status code.
     */
    public function status(): int
    {
        return $this->statusCode;
    }

    /**
     * Get a specific header from the response.
     */
    public function header(string $key): ?string
    {
        return $this->headers[strtolower($key)] ?? null;
    }

    /**
     * Get all response headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Check if the request was successful (2xx status code).
     */
    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if the request was a redirect (3xx status code).
     */
    public function redirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if the request failed (4xx or 5xx status code).
     */
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Check for a client error (4xx status code).
     */
    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check for a server error (5xx status code).
     */
    public function serverError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Get the underlying Swoole error code, if any.
     */
    public function swooleErrorCode(): int
    {
        return $this->object->errCode ?? 0;
    }

    /**
     * Get the underlying Swoole error message, if any.
     */
    public function swooleErrorMessage(): string
    {
        return $this->object?->errMsg ?? '';
    }

    /**
     * Magically convert the response to a string (the body).
     */
    public function __toString(): string
    {
        return $this->body();
    }
}