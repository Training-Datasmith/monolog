<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Monolog\Handler\Curl;

use Curl_Handle;
/**
 * This class is marked as internal and it is not under the BC promise of the package.
 *
 * @internal
 */
final class Util
{
    /** @var array<int> */
    private static array $retriable_error_codes = [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_HTTP_NOT_FOUND, CURLE_READ_ERROR, CURLE_OPERATION_TIMEOUTED, CURLE_HTTP_POST_ERROR, CURLE_SSL_CONNECT_ERROR];
    /**
     * Executes a CURL request with optional retries and exception on failure
     *
     * @param  CurlHandle  $ch curl handler
     * @return bool|string @see curl_exec
     */
    public static function execute(Curl_Handle $ch, int $retries = 5): bool|string
    {
        while ($retries > 0) {
            $retries--;
            $curl_response = curl_exec($ch);
            if ($curl_response === false) {
                $curl_errno = curl_errno($ch);
                if (false === \in_array($curl_errno, self::$retriable_error_codes, true) || $retries === 0) {
                    $curl_error = curl_error($ch);
                    throw new \RuntimeException(sprintf('Curl error (code %d): %s', $curl_errno, $curl_error));
                }
                continue;
            }
            return $curl_response;
        }
        return false;
    }
}