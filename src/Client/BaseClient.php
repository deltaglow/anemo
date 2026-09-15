<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Pool;
use DeltaGlow\Anemo\Support\Proxy;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Uri\Rfc3986\Uri;

abstract class BaseClient
{
    /**
     * Scheme assumed when a target is given without one (e.g. "192.168.1.10:8080/api").
     */
    protected const DEFAULT_SCHEME = 'http';

    /**
     * Schemes that imply a TLS connection.
     */
    protected const SECURE_SCHEMES = ['https'];

    // Properties for pool requests
    protected string $method;
    protected array $options = [];
    protected array $cookies = [];
    protected array $headers = [];
    protected ?Pool $pool = null;
    protected ?string $pool_key = null;

    public function __construct(array $options = [])
    {
        $this->resolveOptions($options);
    }

    private function resolveOptions(array $options): void
    {
        $resolver = new OptionsResolver();

        $resolver->define('base_uri')->allowedTypes('string', 'null')->default(null);
        $resolver->define('timeout')->allowedTypes('int', 'null')->default(0);
        $resolver->define('keep_alive')->allowedTypes('bool')->default(false);
        $resolver->setOptions('proxy', function (OptionsResolver $resolver) {
            $resolver->define('uri')->allowedTypes('string', 'null')->default(null);
            $resolver->define('host')->allowedTypes('string', 'null')->default(null);
            $resolver->define('port')->allowedTypes('int', 'null')->default(null);
            $resolver->define('user')->allowedTypes('string', 'null')->default(null);
            $resolver->define('password')->allowedTypes('string', 'null')->default(null);
        });
        $resolver->setOptions('ssl', function (OptionsResolver $resolver) {
            $resolver->define('verify_peer')->allowedTypes('bool')->default(true);
            $resolver->define('host_name')->allowedTypes('string', 'null')->default(null);
            $resolver->define('allow_self_signed')->allowedTypes('bool')->default(false);
            $resolver->define('cert_file')->allowedTypes('string', 'null')->default(null);
            $resolver->define('key_file')->allowedTypes('string', 'null')->default(null);
            $resolver->define('passphrase')->allowedTypes('string', 'null')->default(null);
            $resolver->define('cafile')->allowedTypes('string', 'null')->default(null);
            $resolver->define('capath')->allowedTypes('string', 'null')->default(null);
        });
        $resolver->setOptions('ws', function (OptionsResolver $resolver) {
            $resolver->define('autoping')->allowedTypes('bool')->default(false);
            $resolver->define('autoping_interval')->allowedTypes('int')->default(15);
            $resolver->define('autoping_data')->allowedTypes('Closure', 'null')->default(null);
        });

        $this->options = $resolver->resolve($options);
    }

    public function setPool(Pool $pool, ?string $key = null): void
    {
        if($key === null) {
            $key = uniqid();
        }
        $this->pool = $pool;
        $this->pool_key = $key;
    }


    /**
     * Turn a user supplied target into an absolute Uri.
     *
     * Without a base_uri a scheme-less string is treated as an absolute target, so
     * "192.168.1.10:8080/api" and "example.com/api" both work. That prefixing is skipped
     * when a base_uri is configured, because there a scheme-less string is a relative path.
     */
    protected function buildUri(string|Uri $uri): Uri
    {
        $base = null;

        if ($this->options['base_uri'] !== null) {
            $base = Uri::parse($this->addDefaultScheme($this->options['base_uri']));

            if ($base === null || $base->getHost() === null) {
                throw new \InvalidArgumentException(
                    sprintf('Option "base_uri" must be an absolute url, "%s" given.', $this->options['base_uri'])
                );
            }
        }

        if ($uri instanceof Uri) {
            if ($uri->getHost() !== null || $base === null) {
                return $uri;
            }

            return $base->resolve($uri->toString());
        }

        if ($base === null) {
            $uri = $this->addDefaultScheme($uri);
        }

        // With a base the second argument resolves the reference per RFC 3986,
        // which also normalises dot segments and inherits the base query/fragment.
        $parsed = Uri::parse($uri, $base);

        if ($parsed === null) {
            throw new \InvalidArgumentException(sprintf('Invalid url string "%s"', $uri));
        }

        return $parsed;
    }

    /**
     * RFC 3986 requires a scheme to start with a letter, so "192.168.1.10:8080/api" is not a
     * valid reference at all and "example.com:8080/api" parses as the scheme "example.com".
     * Prefixing the default scheme makes both behave the way a user expects.
     */
    private function addDefaultScheme(string $url): string
    {
        if ($url === '' || str_starts_with($url, '/')) {
            // "//host/path" is a scheme relative reference, it only misses the scheme itself.
            return str_starts_with($url, '//') ? static::DEFAULT_SCHEME . ':' . $url : $url;
        }

        if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $url)) {
            return $url;
        }

        return static::DEFAULT_SCHEME . '://' . $url;
    }

    protected function isSecure(Uri $uri): bool
    {
        return in_array(strtolower($uri->getScheme() ?? ''), static::SECURE_SCHEMES, true);
    }

    protected function resolvePort(Uri $uri, bool $secure): int
    {
        return $uri->getPort() ?? ($secure ? 443 : 80);
    }

    /**
     * The host to hand to Swoole's socket layer.
     *
     * ext-uri returns IP literals wrapped in brackets ("[::1]"), but Swoole passes the string
     * straight to the resolver, so the brackets have to go.
     */
    protected function connectHost(Uri $uri): string
    {
        $host = $uri->getHost();

        if ($host === null || $host === '') {
            throw new \InvalidArgumentException(sprintf('Url "%s" has no host.', $uri->toString()));
        }

        if (str_starts_with($host, '[')) {
            return substr($host, 1, -1);
        }

        return $host;
    }

    /**
     * Swoole derives Host/:authority from the connect host, which is unbracketed, so an IPv6
     * target would send "Host: ::1:8080". Send the correct value ourselves in that case.
     */
    protected function buildHeaders(Uri $uri, int $port, bool $secure): array
    {
        $host = $uri->getHost();

        if ($host === null || !str_starts_with($host, '[')) {
            return $this->headers;
        }

        foreach (array_keys($this->headers) as $name) {
            if (strcasecmp((string) $name, 'Host') === 0) {
                return $this->headers;
            }
        }

        if ($port !== ($secure ? 443 : 80)) {
            $host .= ':' . $port;
        }

        return ['Host' => $host] + $this->headers;
    }

    protected function buildPath(Uri $uri): string
    {
        $path = $uri->getPath() ?: '/';

        if (($query = $uri->getQuery()) !== null && $query !== '') {
            $path .= '?' . $query;
        }

        // The fragment is a client side concern and is never sent on the wire.

        return $path;
    }

    protected function buildSettings(): array
    {
        $settings = [
            'ssl_verify_peer' => $this->options['ssl']['verify_peer'],
            'ssl_host_name' => $this->options['ssl']['host_name'],
            'ssl_allow_self_signed' => $this->options['ssl']['allow_self_signed'],
            'ssl_cert_file' => $this->options['ssl']['cert_file'],
            'ssl_key_file' => $this->options['ssl']['key_file'],
            'ssl_passphrase' => $this->options['ssl']['passphrase'],
            'ssl_cafile' => $this->options['ssl']['cafile'],
            'ssl_capath' => $this->options['ssl']['capath'],
            'timeout' => $this->options['timeout'],
            'keep_alive' => $this->options['keep_alive'],
        ];

        // setup proxy
        if($this->options['proxy']['uri'] !== null) {
            // format username:password@host:port
            $uri = Proxy::parse($this->options['proxy']['uri']);
            $settings['http_proxy_host'] = $uri->host;
            $settings['http_proxy_port'] = $uri->port;
            if($uri->username !== null) {
                $settings['http_proxy_user'] = $uri->username;
            }
            if($uri->password !== null) {
                $settings['http_proxy_password'] = $uri->password;
            }

        } elseif($this->options['proxy']['host'] !== null) {
            $settings['http_proxy_host'] = $this->options['proxy']['host'];
            $settings['http_proxy_user'] = $this->options['proxy']['user'];

            if($this->options['proxy']['port'] !== null) {
                $settings['http_proxy_port'] = $this->options['proxy']['port'];
            }

            if($this->options['proxy']['password'] !== null) {
                $settings['http_proxy_password'] = $this->options['proxy']['password'];
            }
        }

        return $settings;
    }

    /**
     * Set cookies to be sent with the request.
     * Note: This makes the PendingRequest instance stateful for subsequent requests.
     */
    public function withCookies(array $cookies): self
    {
        $this->cookies = array_merge($this->cookies, $cookies);
        return $this;
    }

    public function withCookie(string $name, string $value): self
    {
        $this->cookies[$name] = $value;
        return $this;
    }

    /**
     * Set the request headers.
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Add a bearer token to the request.
     */
    public function withBearerToken(string $token): self
    {
        $this->headers['Authorization'] = trim('Bearer ' . $token);
        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->headers['Authorization'] = trim('Basic ' . base64_encode($username . ':' . $password));
        return $this;
    }

    public function withApikey(string $token, string $type = 'Apikey'): self
    {
        $this->headers['Authorization'] = trim($type.' ' . $token);
        return $this;
    }
}