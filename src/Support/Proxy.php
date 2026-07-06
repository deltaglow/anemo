<?php

namespace DeltaGlow\Anemo\Support;

use Uri\Rfc3986\Uri;

final readonly class Proxy
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $scheme = null,
        public ?string $username = null,
        public ?string $password = null,
    ) {

    }

    public static function parse(string $proxy): self
    {
        // add fake scheme if missing
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $proxy)) {
            $proxy = 'fakescheme://' . $proxy;
        }

        $path = Uri::parse($proxy);
        if($path === null) {
            throw new \InvalidArgumentException(sprintf('Invalid proxy string "%s"', $proxy));
        }

        return new self(
            host: $path->getHost(),
            port: $path->getPort(),
            scheme: $path->getScheme() === 'fakescheme' ? null : $path->getScheme(),
            username: $path->getUsername(),
            password: $path->getPassword(),
        );
    }
}