<p align="center">
    <a href="https://www.iprotek.net" target="_blank">
        <img src="https://www.iprotek.net/images/logo3.png" width="400" />
    </a>
</p>

# iProtek Account Integration Layer

The `iprotek/account` package acts as the integration and authorization layer between your Laravel application and the external **iProtek Account System** (e.g., `account.iprotek.net`). 

It functions similarly to OAuth-style external identity providers (like Google or GitHub Sign-In), allowing your application to securely delegate user authentication and retrieve authorized user profile/session details.

---

## Architecture Flow

The package facilitates a secure handshake flow between the client browser, the local application session, and the iProtek authorization server:

```
+-------------+         Handshake init         +-------------------------+
|             | -----------------------------> |                         |
|             |                                |  iProtek Account Package|
|             |     Redirect / Open Popup      |         (Local)         |
|   Browser   | <----------------------------- |                         |
|   Client    |                                +-------------------------+
|             |                                             |
|             |         Handshake handshake                 | HTTP API Call
|             | -----------------------------\              v
|             |                               \    +-------------------------+
|             | <------------------------------\-- |   account.iprotek.net   |
|             |       postMessage callback      \  |   (Authorization API)   |
|             |                                  > +-------------------------+
|             |                                 /           |
|             |         Submit Handshake Auth  /            | Exchange Token
|             | ----------------------------->/             v
|             |                               |    +-------------------------+
|             | <---------------------------- |    |   Application Session   |
|             |       Establish Session       |    |      & Permissions      |
+-------------+                               +-------------------------+
```

1. **Initialize Handshake**: The browser loads the login page, initiating a request helper (`AccountHelper::submitLoginRequest`).
2. **Retrieve Handshake Code**: The local app requests a transient handshake token from `account.iprotek.net` and renders a hidden validation form.
3. **Open Popup**: The browser opens an authorization popup pointing to `account.iprotek.net` with the transient handshake token.
4. **Authorize User**: The user logs in and authorizes the application on `account.iprotek.net`.
5. **postMessage Callback**: The popup sends the authorization verification code back to the parent page via HTML5 `window.postMessage`.
6. **Token Exchange**: The parent page submits the form to the local app, which calls `AccountHelper::verifyLoginRequest` to securely exchange the verification code for the user profile, default proxy groups, and access tokens.

---

## Installation

### Requirements
* PHP `>= 7.4`
* Laravel `>= 8.0`
* Guzzle HTTP client package

### Step-by-Step Installation

1. Add the local package repository link in your application's root `composer.json` under `repositories` if not already present:
   ```json
   "repositories": [
       {
           "type": "path",
           "url": "packages/iprotek/account"
       }
   ]
   ```
2. Install the package using composer:
   ```bash
   composer require iprotek/account
   ```
3. The service provider `iProtek\Account\AccountPackageServiceProvider` is automatically registered via Package Discovery.

---

## Configuration

The package relies on environment variables mapped to Laravel configuration keys. Configure these in your application's `.env` file:

```ini
# External iProtek Account System endpoint
IPROTEK_ACCOUNT_URL=https://account.iprotek.net

# Application identification type (e.g., ERP, CLIENT, ADMIN)
PAY_IPROTEK_TYPE=YOUR_APP_TYPE

# Shared System and Client IDs for signing/validating API requests
IPROTEK_PAY_URL=https://pay.iprotek.net
IPROTEK_PAY_CLIENT_ID=your_client_id
IPROTEK_PAY_CLIENT_SECRET=your_client_secret
IPROTEK_SYSTEM_ID=your_system_id
IPROTEK_SYSTEM_URL=your_system_url
```

---

## Usage Guide

### 1. Initiating the Login Handshake (Controller or Blade View)

When rendering your login page, query the external account service to register the login intent.

```php
use iProtek\Account\Helpers\AccountHelper;

// In your login route or controller action:
public function showLoginForm(Request $request)
{
    $response = AccountHelper::submitLoginRequest($request);

    if ($response['status'] === 1 && isset($response['result']['id'])) {
        return view('auth.login', [
            'loginRequestId' => $response['result']['id'],
            'loginRequestCode' => $response['result']['code']
        ]);
    }

    // Handle handshake failure
    return view('auth.login')->withErrors(['connection' => 'Unable to connect to login provider.']);
}
```

### 2. Rendering Handshake Form & Popup in Frontend

Embed the handshake parameters in a hidden form and launch the authorization popup when the user clicks the "Login with iProtek" button.

```html
<!-- hidden handshake submission form -->
<form id="login-request-form" method="POST" action="/login">
    @csrf   
    <input type="hidden" name="login_request_id" value="{{ $loginRequestId }}" />
    <input type="hidden" name="login_code" value="{{ $loginRequestCode }}" />
    <input type="hidden" id="login-account-auth-code" name="login_account_auth_code" value="" />
</form>

<button onclick="openAuthPopup()">Login with iProtek</button>

<script>
function openAuthPopup() {
    const popupWidth = 600;
    const popupHeight = 600;
    const left = window.screenX + (window.innerWidth - popupWidth) / 2;
    const top = window.screenY + (window.innerHeight - popupHeight) / 2;
    
    const url = encodeURIComponent(window.location.origin + window.location.pathname);
    const authUrl = `{{ config('iprotek_account.url') }}/handshake/login-request?login_request_id={{ $loginRequestId }}&requestor_origin_url=${url}`;
    
    const popup = window.open(authUrl, 'authPopup', `width=${popupWidth},height=${popupHeight},top=${top},left=${left}`);
    
    // Listen for the authorization message back from the popup window
    window.addEventListener('message', (event) => {
        // Verify code matches our handshake session
        if (event.data.code === '{{ $loginRequestCode }}') {
            document.querySelector('#login-account-auth-code').value = event.data.account_auth_code;
            document.querySelector('#login-request-form').submit();
        }
        if (event.data && event.data.is_close) {
            popup.close();
        }
    });
}
</script>
```

### 3. Exchanging Code for Account Profile (Callback Controller)

Upon form submission, verify the authorization credentials and retrieve the authenticated account profiles.

```php
use iProtek\Account\Helpers\AccountHelper;
use Illuminate\Support\Facades\Auth;

public function handleCallback(Request $request)
{
    $request->validate([
        'login_request_id'        => 'required',
        'login_code'              => 'required',
        'login_account_auth_code' => 'required'
    ]);

    // Exchange handshake code for tokens and profile information
    $response = AccountHelper::verifyLoginRequest(
        $request->login_request_id,
        $request->login_code,
        $request->login_account_auth_code
    );

    if ($response['status'] === 1 && $response['result']['status'] === 1) {
        $profile = $response['result'];
        
        $userAdmin = $profile['user_admin']; // User details
        $payAccount = $profile['pay_account']; // Credentials/Token info
        
        // Match user locally and log them in
        $user = \App\Models\User::firstOrCreate(
            ['email' => $userAdmin['email']],
            ['name' => $userAdmin['name']]
        );
        
        Auth::login($user, true);

        return redirect()->intended('/dashboard');
    }

    return redirect('/login')->withErrors(['email' => 'Authorization failed. Please try again.']);
}
```

---

## API Reference

### `AccountHelper`

#### `submitLoginRequest(Request $request): array`
Sends an API call to the account service to register a login handshake intent.
* **Parameters**:
  * `$request` (`Illuminate\Http\Request`): Current HTTP request containing host details.
* **Returns**:
  * `array`: `['status' => 0|1, 'result' => ['id' => '...', 'code' => '...'], 'message' => '...']`

#### `verifyLoginRequest($loginRequestId, $loginCode, $loginAccountAuthCode): array`
Exchanges verification codes for user credentials and API access tokens.
* **Parameters**:
  * `$loginRequestId` (`string|int`): Handshake session ID.
  * `$loginCode` (`string`): The transient code for verification.
  * `$loginAccountAuthCode` (`string`): The validation code posted from the auth provider.
* **Returns**:
  * `array`: Structured representation of user profiles and authentication tokens.

---

## Error Handling & Resiliency

All API endpoints queried via `AccountHttpHelper` return a standardized array schema:

```php
[
    'status'  => 0 | 1,           // 1 for successful call, 0 for failure/connection errors
    'result'  => [...],           // Body response or fallback array
    'message' => '...'            // Verbose error description
]
```

* **Network / API Timeout**: If the server fails to respond, it returns `status => 0` and standard diagnostics message. Pages will load without fatal crashes.
* **Configuration Issues**: If `IPROTEK_ACCOUNT_URL` is missing, API wrappers instantly return an error array stating `Application url not set`.

---

## Security Notes

1. **Token Protection**: Access tokens and refresh tokens returned by `verifyLoginRequest` must be treated as sensitive credentials. Store them securely inside database models or encrypted sessions, never expose them to client scripts.
2. **Strict Handshake Verification**: Ensure the `login_code` validation step inside your JavaScript event listener strictly matches the values generated on load to prevent cross-origin scripting issues.
3. **SSL/TLS**: In production environments, make sure that `IPROTEK_ACCOUNT_URL` and your local application utilize SSL/TLS (`https://`) to protect authorization codes in transit.

---

## Best Practices

* **When to use**: Use this package when integrating multiple sub-applications or modules into the unified iProtek systems platform, enabling single-sign-on (SSO).
* **When not to use**: Do not use this package for purely standalone applications that maintain local credentials and do not require connection to the wider iProtek ecosystem.
