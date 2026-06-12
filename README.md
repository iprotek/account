<p align="center">
    <a href="https://www.iprotek.net" target="_blank">
        <img src="https://www.iprotek.net/images/logo3.png" width="400" />
    </a>
</p>

# iProtek Account Integration Layer

The `iprotek/account` package acts as the integration and authorization layer between your Laravel application and the external **iProtek Account System** (e.g., `account.iprotek.net`). 

It functions similarly to OAuth-style external identity providers (like Google or GitHub Sign-In), allowing your application to securely delegate user authentication and retrieve authorized user profiles and session details.

---

## Architecture & Flow

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
|             | ------------------------------>/             v
|             |                               |    +-------------------------+
|             | <---------------------------- |    |   Application Session   |
|             |       Establish Session       |    |      & Permissions      |
+-------------+                               +-------------------------+
```

### Layer Responsibilities

* **Application (Local)**: 
  * Renders the login user interface.
  * Handles communication with the popup window (via HTML5 `window.postMessage`).
  * Establishes and manages the local user login session after authentication.
* **iProtek Account Package (Integration Layer)**:
  * Encapsulates communication with the authorization API.
  * Constructs Guzzle HTTP clients pre-configured with client IDs, secrets, and authorization headers.
  * Translates raw API responses into developer-friendly array structures.
* **account.iprotek.net (Central Auth Source)**:
  * Performs user credential verification.
  * Manages user permission consents and authorization records.
  * Issues transient handshake codes and verification tokens.
* **Authorization Response**:
  * The secure data structure returned to the package containing verified profile information, default proxy groups, and access tokens.
* **Application Session / Permissions**:
  * The resulting state where the user is logged into the local guard session, and access tokens are saved for subsequent API calls.

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
2. Install the package using Composer:
   ```bash
   composer require iprotek/account
   ```
3. The service provider `iProtek\Account\AccountPackageServiceProvider` is automatically registered via Package Discovery.

---

## Configuration

The package relies on environment variables mapped to Laravel configuration keys. Configure these in your application's `.env` file:

| Environment Variable | Configuration Key | Description |
|---|---|---|
| `IPROTEK_ACCOUNT_URL` | `iprotek_account.url` | Central iProtek Account System endpoint (e.g. `https://account.iprotek.net`) |
| `PAY_IPROTEK_TYPE` | `iprotek_account.app_type` | Identification name of your application (e.g. `ERP`, `CLIENT`, `ADMIN`) |
| `IPROTEK_PAY_URL` | `iprotek.pay_url` | Base URL of the billing/payment gateway integration |
| `IPROTEK_PAY_CLIENT_ID` | `iprotek.pay_client_id` | Client ID registered with the payment/auth gateway |
| `IPROTEK_PAY_CLIENT_SECRET` | `iprotek.pay_client_secret` | Client secret registered with the payment/auth gateway |
| `IPROTEK_SYSTEM_ID` | `iprotek.system_id` | Centralized system identifier |
| `IPROTEK_SYSTEM_URL` | `iprotek.system` | Centralized system URL |

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

    // Handle handshake failure gracefully
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
