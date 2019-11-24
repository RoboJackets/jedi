<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableReturnTypeHintSpecification

namespace App\Services;

use App\Exceptions\DownstreamServiceException;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

abstract class Service
{
    /**
     * Verifies that a passed ResponseInterface has an expected response code and throws an exception if not
     *
     * @param ResponseInterface $response An HTTP response
     * @param array<int> $expected               Possible response codes
     *
     * @return void
     */
    protected static function expectStatusCodes(ResponseInterface $response, int ...$expected): void
    {
        $received = $response->getStatusCode();

        if (!in_array($received, $expected, true)) {
            throw new DownstreamServiceException(
                'Service returned unexpected HTTP response code ' . $received . ', expected '
                . implode(' or ', $expected) . ', response body: ' . $response->getBody()->getContents()
            );
        }
    }

    protected static function decodeToObject(ResponseInterface $response): object
    {
        $ret = json_decode($response->getBody()->getContents());

        if (!is_object($ret)) {
            throw new DownstreamServiceException(
                'Service did not return an object - ' . $response->getBody()->getContents()
            );
        }

        return $ret;
    }

    protected static function decodeToArray(ResponseInterface $response): array
    {
        $ret = json_decode($response->getBody()->getContents());

        if (!is_array($ret)) {
            throw new DownstreamServiceException(
                'Service did not return an array - ' . $response->getBody()->getContents()
            );
        }

        return $ret;
    }

    abstract public static function client(): Client;
}
