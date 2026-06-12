<?php

namespace iProtek\Account\Helpers;

use Illuminate\Http\Request;

/**
 * Helper class for managing authentication handshake requests with the iProtek Account System.
 *
 * This class coordinates the initial login handshake request creation and the subsequent
 * login validation.
 */
class AccountHelper
{
    /**
     * Submits a request to the iProtek Account System to initialize a login handshake.
     *
     * This method is called by the application to prepare a login session on the authorization server.
     * It generates the origin URLs and requests a unique code which will be used in the front-end
     * popup handshake flow.
     *
     * @param Request $request The incoming HTTP request.
     * @return array The API response from the account server containing status and request codes.
     */
    public static function submitLoginRequest(Request $request)
    {
        $scheme = config('session.secure') ? 'https' : 'http';
        $origin = $scheme . '://' . $request->getHost();
        $fullUrl = $scheme . '://' . $request->getHost() . $request->getRequestUri();

        return AccountHttpHelper::post_client("api/login-request", [
            "requestor_origin"     => $origin,
            "requestor_origin_url" => $fullUrl,
            "requestor_app_type"   => config('iprotek_account.app_type')
        ]);
    }

    /**
     * Verifies the authorized login handshake credentials against the iProtek Account System.
     *
     * Once the user authorizes the request via the popup, the callback message returns the authorization codes.
     * This method sends these credentials back to the authorization server to exchange them for the user's
     * account profiles and access tokens.
     *
     * @param string|int $loginRequestId The ID of the handshake request.
     * @param string $loginCode The code linked to the handshake request.
     * @param string $loginAccountAuthCode The verification auth code returned by the user authorization.
     * @return array The API response containing the user profiles, access tokens, and status.
     */
    public static function verifyLoginRequest($loginRequestId, $loginCode, $loginAccountAuthCode)
    {
        return AccountHttpHelper::post_client("api/login-request/render-authorization", [
            "login_request_id"        => $loginRequestId,
            "login_code"              => $loginCode,
            "login_account_auth_code" => $loginAccountAuthCode,
            "target_app_name"         => config('iprotek_account.app_type')
        ]);
    }
}
