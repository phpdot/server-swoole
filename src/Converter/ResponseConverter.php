<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Converter;

use PHPdot\Server\Swoole\CallbackStreamInterface;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleResponse;

/**
 * ResponseConverter.
 *
 * Converts a PSR-7 ResponseInterface into a Swoole HTTP response.
 * Supports callback streams, file sendfile, chunked transfer, cookies,
 * and HTTP/2 trailer headers.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ResponseConverter
{
    /**
     * Create a new ResponseConverter.
     *
     * @param int $chunkSize Maximum chunk size in bytes for large responses
     */
    public function __construct(
        private readonly int $chunkSize = 1048576,
    ) {}

    /**
     * Write a PSR-7 response to a Swoole response object.
     *
     * Handles status codes, headers, cookies, trailer headers, and body
     * emission using the most efficient strategy available.
     *
     * @param ResponseInterface $psrResponse The PSR-7 response to send
     * @param SwooleResponse $swooleResponse The Swoole response to write to
     */
    public function toSwoole(ResponseInterface $psrResponse, SwooleResponse $swooleResponse): void
    {
        $swooleResponse->status($psrResponse->getStatusCode(), $psrResponse->getReasonPhrase());

        $trailerNames = [];
        if ($psrResponse->hasHeader('Trailer')) {
            $trailerNames = array_map(
                static fn(string $name): string => strtolower(trim($name)),
                explode(',', $psrResponse->getHeaderLine('Trailer')),
            );
        }

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $lower = strtolower($name);
            if ($lower === 'set-cookie') {
                continue;
            }
            if ($lower === 'transfer-encoding') {
                continue;
            }
            if (in_array($lower, $trailerNames, true)) {
                continue;
            }
            $swooleResponse->header($name, implode(', ', $values));
        }

        $this->emitCookies($psrResponse, $swooleResponse);
        $this->emitTrailers($psrResponse, $swooleResponse);
        $this->emitBody($psrResponse, $swooleResponse);
    }

    /**
     * Parse a Set-Cookie header string into its components.
     *
     * @param string $header Raw Set-Cookie header value
     * @return array{name: string, value: string, expires: int, path: string, domain: string, secure: bool, httpOnly: bool, sameSite: string, partitioned: bool}
     */
    public function parseCookieHeader(string $header): array
    {
        $result = [
            'name' => '',
            'value' => '',
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'sameSite' => '',
            'partitioned' => false,
        ];

        if ($header === '') {
            return $result;
        }

        $parts = explode(';', $header);
        $firstPart = array_shift($parts);

        $equalPos = strpos($firstPart, '=');
        if ($equalPos === false) {
            $result['name'] = trim($firstPart);
        } else {
            $result['name'] = trim(substr($firstPart, 0, $equalPos));
            $result['value'] = trim(substr($firstPart, $equalPos + 1));
        }

        foreach ($parts as $part) {
            $part = trim($part);
            $lowerPart = strtolower($part);

            if ($lowerPart === 'secure') {
                $result['secure'] = true;
                continue;
            }

            if ($lowerPart === 'httponly') {
                $result['httpOnly'] = true;
                continue;
            }

            if ($lowerPart === 'partitioned') {
                $result['partitioned'] = true;
                continue;
            }

            $attrEqualPos = strpos($part, '=');
            if ($attrEqualPos === false) {
                continue;
            }

            $attrName = strtolower(trim(substr($part, 0, $attrEqualPos)));
            $attrValue = trim(substr($part, $attrEqualPos + 1));

            switch ($attrName) {
                case 'expires':
                    $timestamp = strtotime($attrValue);
                    $result['expires'] = $timestamp !== false ? $timestamp : 0;
                    break;
                case 'max-age':
                    $result['expires'] = time() + (int) $attrValue;
                    break;
                case 'path':
                    $result['path'] = $attrValue;
                    break;
                case 'domain':
                    $result['domain'] = $attrValue;
                    break;
                case 'samesite':
                    $result['sameSite'] = $attrValue;
                    break;
            }
        }

        return $result;
    }

    /**
     * Emit Set-Cookie headers as Swoole cookies.
     *
     * @param ResponseInterface $psrResponse The PSR-7 response containing Set-Cookie headers
     * @param SwooleResponse $swooleResponse The Swoole response to set cookies on
     */
    private function emitCookies(ResponseInterface $psrResponse, SwooleResponse $swooleResponse): void
    {
        $cookieHeaders = $psrResponse->getHeader('Set-Cookie');
        foreach ($cookieHeaders as $cookieHeader) {
            $parsed = $this->parseCookieHeader($cookieHeader);
            $swooleResponse->rawcookie(
                $parsed['name'],
                $parsed['value'],
                $parsed['expires'],
                $parsed['path'],
                $parsed['domain'],
                $parsed['secure'],
                $parsed['httpOnly'],
                $parsed['sameSite'],
                '',
                $parsed['partitioned'],
            );
        }
    }

    /**
     * Emit HTTP/2 trailer headers if present.
     *
     * @param ResponseInterface $psrResponse The PSR-7 response
     * @param SwooleResponse $swooleResponse The Swoole response
     */
    private function emitTrailers(ResponseInterface $psrResponse, SwooleResponse $swooleResponse): void
    {
        if (!$psrResponse->hasHeader('Trailer')) {
            return;
        }

        $trailerNames = array_map('trim', explode(',', $psrResponse->getHeaderLine('Trailer')));
        foreach ($trailerNames as $trailerName) {
            if ($psrResponse->hasHeader($trailerName)) {
                $swooleResponse->trailer($trailerName, $psrResponse->getHeaderLine($trailerName));
            }
        }
    }

    /**
     * Emit the response body using the most efficient strategy.
     *
     * Strategies in order: callback stream, file sendfile, empty body,
     * chunked transfer for large bodies, or direct end for small bodies.
     *
     * @param ResponseInterface $psrResponse The PSR-7 response
     * @param SwooleResponse $swooleResponse The Swoole response
     */
    private function emitBody(ResponseInterface $psrResponse, SwooleResponse $swooleResponse): void
    {
        $body = $psrResponse->getBody();

        if ($body instanceof CallbackStreamInterface) {
            $callback = $body->getCallback();
            $callback(static function (string $chunk) use ($swooleResponse): void {
                $swooleResponse->write($chunk);
            });
            $swooleResponse->end();
            return;
        }

        $meta = $body->getMetadata();
        if (
            is_array($meta)
            && ($meta['wrapper_type'] ?? '') === 'plainfile'
            && isset($meta['uri'])
            && is_string($meta['uri'])
            && is_file($meta['uri'])
        ) {
            $swooleResponse->sendfile($meta['uri']);
            return;
        }

        $size = $body->getSize();
        if ($size === 0) {
            $swooleResponse->end();
            return;
        }

        $content = (string) $body;
        if ($content === '') {
            $swooleResponse->end();
            return;
        }

        if (strlen($content) > $this->chunkSize) {
            $contentLength = strlen($content);
            for ($offset = 0; $offset < $contentLength; $offset += $this->chunkSize) {
                $swooleResponse->write(substr($content, $offset, $this->chunkSize));
            }
            $swooleResponse->end();
            return;
        }

        $swooleResponse->end($content);
    }
}
