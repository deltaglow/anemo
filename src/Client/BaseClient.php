<?php

namespace DeltaGlow\Anemo\Client;

use DeltaGlow\Anemo\Pool;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class BaseClient
{
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

    protected function buildPath(Uri $uri): Uri
    {
        $path = new Uri();
        $path = $path->withPath($uri->getPath())
            ->withQuery($uri->getQuery())
            ->withFragment($uri->getFragment());

        if($path->getPath() === '') {
            $path = $path->withPath('/');
        }

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
            $uri = new Uri($this->options['proxy']['uri']);
            if($uri->getUserInfo() !== null) {
                list($user, $pass) = explode(':', $uri->getUserInfo());
                $settings['http_proxy_user'] = $user;
                $settings['http_proxy_password'] = $pass;
            }
            $settings['http_proxy_host'] = $uri->getHost();
            $settings['http_proxy_port'] = $uri->getPort();

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