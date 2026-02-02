# Anemo

**Anemo** is a lightweight, high-performance Swoole HTTP Client Wrapper. It provides a simple and fluent interface for making HTTP/1.1, HTTP/2, and WebSocket requests using the power of Swoole's coroutine-based architecture.

## Requirements

- PHP >= 8.1
- ext-swoole >= 6.0
- ext-json

## Installation

Install the library using Composer:

```bash
composer require deltaglow/anemo
```

## Usage

### Basic HTTP Request

Use `Anemo::http()` to create an HTTP/1.1 client.

```php
use DeltaGlow\Anemo\Anemo;

$client = Anemo::http(['timeout' => 5]);

// GET request
$response = $client->get('https://api.example.com/users');
var_dump($response->getBody());

// POST request with JSON body
$response = $client->asJson()->post('https://api.example.com/users', ['name' => 'John Doe', 'email' => 'john@example.com']);
```

### HTTP/2 Request

Use `Anemo::http2()` for HTTP/2 support.

```php
use DeltaGlow\Anemo\Anemo;

$client = Anemo::http2(['ssl' => ['verify_peer' => false]]);

$response = $client->get('https://http2.example.com/stream');
```

### WebSockets

Use `Anemo::ws()` to interact with WebSocket servers. It automatically handles to pings.

```php
use DeltaGlow\Anemo\Anemo;

$ws = Anemo::ws();

if ($ws->upgrade('wss://echo.websocket.org')) {
    $ws->push('Hello Swoole!');
    $frame = $ws->receive();
    echo "Received: {$frame->data}\n";
    
    $ws->pushText('This is a text message');
    $ws->receiveText();
    
    $ws->pushBinary('This is a binary message');
    $ws->receive();
    
    $ws->pushJson(['foo' => 'bar']);
    $ws->receiveJson();
    
    $ws->close();
}
```

### Concurrent Requests (Pool)

Execute multiple requests concurrently using `Anemo::pool()`. 

**Attention**: This method needs to be called within a coroutine.

```php
use DeltaGlow\Anemo\Anemo;
use DeltaGlow\Anemo\Pool;

\Swoole\Coroutine\run(function () {
    $results = Anemo::pool(function (Pool $pool) {
        // Add a request to the pool
        $pool->addRequest('users', function () {
            $client = Anemo::http();
            return $client->get('https://api.example.com/users')->json();
        });
    
        // Add another request
        $pool->addRequest('posts', function () {
            $client = Anemo::http();
            return $client->get('https://api.example.com/posts')->json();
        });
    });
    
    // Access results by key
var_dump($results['users']['result']);
var_dump($results['posts']['result']);
});
```

### Configuration Options

The client factory methods accept an array of options:

| Option            | Type   | Default | Description                                          |
|-------------------|--------|---------|------------------------------------------------------|
| `base_uri`   | `string \| null` |  `null` | Base URI for requests (prepended to relative paths). |
| `timeout`    | `int \| null`    |     `0` | Request timeout in seconds (`0` is no timeout).      |
| `keep_alive` | `bool`           | `false` | Whether to reuse connections (HTTP keep-alive).      |
| `proxy.uri`      | `string \| null` |  `null` | Full proxy URI (e.g., `http://user:pass@host:port`). If set, it override the individual host/port/user/password fields. |
| `proxy.host`     | `string \| null` |  `null` | Proxy hostname or IP.                                                                                                       |
| `proxy.port`     | `int \| null`    |  `null` | Proxy port number.                                                                                                          |
| `proxy.user`     | `string \| null` |  `null` | Username for proxy authentication.                                                                                          |
| `proxy.password` | `string \| null` |  `null` | Password for proxy authentication.                                                                                          |
| `ssl.verify_peer`       | `bool`           |  `true` | Verify the peer’s SSL certificate. Set to `false` to skip verification (not recommended). |
| `ssl.host_name`         | `string \| null` |  `null` | Expected peer hostname (for SNI/verification).                                            |
| `ssl.allow_self_signed` | `bool`           | `false` | Allow self-signed certificates when verifying.                                            |
| `ssl.cert_file`         | `string \| null` |  `null` | Path to client certificate file.                                                          |
| `ssl.key_file`          | `string \| null` |  `null` | Path to client private key file.                                                          |
| `ssl.passphrase`        | `string \| null` |  `null` | Passphrase for the private key, if encrypted.                                             |
| `ssl.cafile`            | `string \| null` |  `null` | Path to a CA bundle file for verification.                                                |
| `ssl.capath`            | `string \| null` |  `null` | Path to a directory containing CA certificates.                                           |
| `ws.autoping`          | `bool`            | `false` | Automatically send ping frames to keep the connection alive. |
| `ws.autoping_interval` | `int`             |    `15` | Interval in seconds between auto-pings.                      |
| `ws.autoping_data`     | `Closure \| null` |  `null` | Callable returning the ping payload (if any).                |


### Request Helpers

The client provides fluent methods to configure your request:

- **Content Types**
  - `$client->asJson()`: Send data as JSON.
  - `$client->asForm()`: Send data as `application/x-www-form-urlencoded`.
  - `$client->acceptJson()`: Set `Accept: application/json` header.

- **Authentication / Headers**
  - `$client->withBearerToken('token')`: Set Bearer token.
  - `$client->withBasicAuth('user', 'pass')`: Set Basic Auth credentials.
  - `$client->withApikey('key', 'Type')`: Set custom API key auth.
  - `$client->withHeader('Key', 'Value')`: Add a single header.
  - `$client->withHeaders(['Key' => 'Value'])`: Add multiple headers.

- **Cookies**
  - `$client->withCookie('name', 'value')`: Set a cookie.
  - `$client->withCookies(['name' => 'value'])`: Set multiple cookies.

### Response Helpers

The `Response` object offers several methods to inspect the result:

- **Body**
  - `$response->body()`: Get raw response body as string.
  - `$response->json()`: Get body decoded as array.
  - `$response->object()`: Get body decoded as object.

- **Status & Headers**
  - `$response->status()`: Get HTTP status code.
  - `$response->header('Content-Type')`: Get specific header.
  - `$response->headers()`: Get all headers.

- **Status Checks**
  - `$response->successful()`: Status is 2xx.
  - `$response->redirect()`: Status is 3xx.
  - `$response->failed()`: Status is 4xx or 5xx.
  - `$response->clientError()`: Status is 4xx.
  - `$response->serverError()`: Status is 5xx.

## License

This project is licensed under the MIT License.
