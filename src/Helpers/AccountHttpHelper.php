<?php

namespace iProtek\Account\Helpers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Helper class for handling HTTP requests to the iProtek Account System.
 *
 * This class wraps a Guzzle HTTP client with appropriate authorization headers,
 * proxy groups, and credentials to communicate with the central account API.
 */
class AccountHttpHelper
{
    /**
     * Creates a Guzzle client pre-configured for token-based authentication.
     *
     * @param string $token The API access token.
     * @return Client
     */
    public static function auth_client($token)
    {
        $payUrl = config('iprotek.pay_url');
        $accountUrl = config("iprotek_account.url");
        $clientId = config('iprotek.pay_client_id');
        $clientSecret = config('iprotek.pay_client_secret'); 
        
        $headers = [
            "Accept"        => "application/json",
            "CLIENT-ID"     => $clientId,
            "SECRET"        => $clientSecret,
            "PAY-URL"       => $payUrl,
            "Authorization" => "Bearer " . $token
        ];
        
        return new Client([
            'base_uri'    => $accountUrl,
            "http_errors" => false, 
            "verify"      => false, 
            "curl"        => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            ],
            "headers"     => $headers
        ]);
    }

    /**
     * Creates a Guzzle client configured with the current user session and app credentials.
     *
     * It appends application identity headers and the user's access token if logged in.
     *
     * @param string|null $token Optional explicit access token.
     * @param bool $isApi Whether this is a group API request.
     * @return Client
     */
    public static function client($token = null, $isApi = false)
    {
        $accountUrl = config("iprotek_account.url");
        $payUrl = config('iprotek.pay_url');
        $clientId = config('iprotek.pay_client_id');
        $clientSecret = config('iprotek.pay_client_secret'); 

        $proxyId = 0;
        $payAppUserAccountId = 0;
        
        if (auth()->check()) {
            $user = auth()->user();
            $sessionId = session()->getId();
            $payAccount = null;

            if ($sessionId && class_exists('\iProtek\Core\Models\UserAdminPayAccount')) {
                $payAccount = \iProtek\Core\Models\UserAdminPayAccount::where([
                    'user_admin_id'      => $user->id,
                    'browser_session_id' => $sessionId
                ])->first();
            }
            
            if (!$payAccount && class_exists('\iProtek\Core\Models\UserAdminPayAccount')) {
                $payAccount = \iProtek\Core\Models\UserAdminPayAccount::where('user_admin_id', $user->id)->first();
            }

            if ($payAccount) { 
                $proxyId = $payAccount->own_proxy_group_id;
                $payAppUserAccountId = $payAccount->pay_app_user_account_id;
                $token = $token ?: $payAccount->access_token;
            }
        }

        $headers = [
            "Accept"              => "application/json",
            'Content-Type'        => 'application/json',
            "CLIENT-ID"           => $clientId,
            "SECRET"              => $clientSecret,
            "PAY-URL"             => $payUrl,
            "SOURCE-URL"          => config('app.url'),
            "SOURCE-NAME"         => config('app.name'),
            "PAY-USER-ACCOUNT-ID" => $payAppUserAccountId . "",
            "PAY-PROXY-ID"        => $proxyId,
            "Authorization"       => "Bearer " . ($token ?: ""),
            "SYSTEM-ID"           => config('iprotek.system_id'), 
            "SYSTEM-URL"          => config('iprotek.system')
        ];
        
        $baseUrl = $isApi ? $accountUrl . "/api/group/$proxyId" : $accountUrl;

        return new Client([
            'base_uri'    => $baseUrl,
            "http_errors" => false, 
            "verify"      => false, 
            "curl"        => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            ],
            "headers"     => $headers
        ]);
    }

    /**
     * Performs a GET request to the account service.
     *
     * @param string $url The endpoint URL.
     * @param bool $rawResponse Whether to return the raw Guzzle Response object.
     * @param mixed $errorDefault Default fallback result on failure.
     * @param bool $isApi Whether this is a group API request.
     * @return array|\GuzzleHttp\Psr7\Response
     */
    public static function get_client($url, $rawResponse = false, $errorDefault = null, $isApi = false)
    {
        $accountUrl = config("iprotek_account.url");
        if (!$accountUrl) {
            return [
                "status"  => 0,
                "message" => "Application url not set"
            ];
        }
        
        $client = static::client(null, $isApi);
        $response = $client->get($url);
        
        return static::response_result($response, $rawResponse, $errorDefault);
    }

    /**
     * Performs a GET request on the group API endpoint.
     *
     * @param string $url The endpoint URL.
     * @param bool $rawResponse Whether to return the raw Guzzle Response object.
     * @param mixed $errorDefault Default fallback result on failure.
     * @return array|\GuzzleHttp\Psr7\Response
     */
    public static function get_api_client($url, $rawResponse = false, $errorDefault = null)
    {
        return static::get_client($url, $rawResponse, $errorDefault, true);
    }

    /**
     * Parses the HTTP response into a standardized result format.
     *
     * @param \GuzzleHttp\Psr7\Response $response Guzzle HTTP response.
     * @param bool $rawResponse Whether to return the raw response object.
     * @param mixed $errorDefault Default fallback if response is not successful.
     * @return array|\GuzzleHttp\Psr7\Response
     */
    public static function response_result($response, $rawResponse, $errorDefault)
    {
        $statusCode = $response->getStatusCode(); 
        
        if ($rawResponse) {
            if ($statusCode != 200 && $statusCode != 201) {
                if ($errorDefault) {
                    return $errorDefault;
                }
            }
            return $response;
        } 

        if ($statusCode != 200 && $statusCode != 201) {
            $bodyContent = (string) $response->getBody();
            $decodedBody = json_decode($bodyContent, true);
            
            return [
                "status"  => 0,
                "result"  => $decodedBody ?: ($errorDefault ?: []),
                "message" => "Api Invalidated."
            ];
        }

        $decodedBody = json_decode((string) $response->getBody(), true);
        return [
            "status"  => 1, 
            "result"  => $decodedBody,
            "message" => "Api Successful."
        ];
    }

    /**
     * Performs a POST request to the account service.
     *
     * @param string $url The endpoint URL.
     * @param array|object|string $body The request payload.
     * @param bool $rawResponse Whether to return the raw Guzzle Response object.
     * @param mixed $errorDefault Default fallback result on failure.
     * @param bool $isApi Whether this is a group API request.
     * @return array|\GuzzleHttp\Psr7\Response
     */
    public static function post_client($url, $body, $rawResponse = false, $errorDefault = null, $isApi = false)
    {
        if (is_array($body) || is_object($body)) {
            $body = json_encode($body);
        }

        $accountUrl = config("iprotek_account.url");
        if (!$accountUrl) {
            return [
                "status"  => 0,
                "message" => "Application url not set"
            ];
        }
        
        $client = static::client(null, $isApi);
        $response = $client->post($url, ["body" => $body]);
        
        return static::response_result($response, $rawResponse, $errorDefault);
    }

    /**
     * Performs a POST request on the group API endpoint.
     *
     * @param string $url The endpoint URL.
     * @param array|object|string $body The request payload.
     * @param bool $rawResponse Whether to return the raw Guzzle Response object.
     * @param mixed $errorDefault Default fallback result on failure.
     * @return array|\GuzzleHttp\Psr7\Response
     */
    public static function post_api_client($url, $body, $rawResponse = false, $errorDefault = null)
    {
        return static::post_client($url, $body, $rawResponse, $errorDefault, true);
    }
}
