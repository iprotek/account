<?php

namespace iProtek\Apps\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
//use iProtek\Core\Models\UserAdminPayAccount;

class AppsHttpHelper
{
    //AUTH 

    public static function auth_client($token){
        $pay_url = config('iprotek.pay_url');
        $apps_system_url = config("iprotek_apps.app_system_url");
        $client_id = config('iprotek.pay_client_id');
        $client_secret = config('iprotek.pay_client_secret'); 
        
        $headers = [
            "Accept"=>"application/json",
            "CLIENT-ID"=>$client_id,
            "SECRET"=>$client_secret,
            "PAY-URL"=>$pay_url,
            "Authorization"=>"Bearer ",$token
        ];
        
        $client = new \GuzzleHttp\Client([
            'base_uri' => $apps_system_url,
            "http_errors"=>false, 
            "verify"=>false, 
            "curl"=>[
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Specify HTTP/2
            ],
            "headers"=>$headers
         ]);
        return $client;

    }

    //CLIENT
    public static function client($token=null, $is_api = false){
        //Preparation of Headers
        $apps_system_url = config("iprotek_apps.app_system_url");
        $pay_url = config('iprotek.pay_url');
        $client_id = config('iprotek.pay_client_id');
        $client_secret = config('iprotek.pay_client_secret'); 
 

        $proxy_id = 0;
        $pay_app_user_account_id = 0;
        
        if(auth()->check()){
            $user = auth()->user();
            $pay_account = \iProtek\Core\Models\UserAdminPayAccount::where('user_admin_id', $user->id)->first();
            if( $pay_account ){ 
                $proxy_id = $pay_account->own_proxy_group_id;
                $pay_app_user_account_id = $pay_account->pay_app_user_account_id;
                $token = $token ?: $pay_account->access_token;
            }
        }


        $headers = [
            "Accept"=>"application/json",
            'Content-Type' => 'application/json',
            "CLIENT-ID"=>$client_id,
            "SECRET"=>$client_secret,
            "PAY-URL"=>$pay_url,
            "SOURCE-URL"=>config('app.url'),
            "SOURCE-NAME"=>config('app.name'),
            "PAY-USER-ACCOUNT-ID"=>$pay_app_user_account_id."",
            "PAY-PROXY-ID"=>$proxy_id,
            "Authorization"=>"Bearer ".($token?:""),
            "SYSTEM-ID" => config('iprotek.system_id'), 
            "SYSTEM-URL" => config('iprotek.system')
        ];
        
        $base_url = $is_api ? $apps_system_url."/api/group/$proxy_id" : $apps_system_url;
        //Log::error($base_url);

        $client = new \GuzzleHttp\Client([
            'base_uri' => $base_url,
            "http_errors"=>false, 
            "verify"=>false, 
            "curl"=>[
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Specify HTTP/2
            ],
            "headers"=>$headers
         ]);
        return $client;

    }
    /**
     * $url - required
     * $raw_response - true actual response / false modified formatted response
     * $error_default - if the result is not OK then it will return the error_default value
     */
    public static function get_client( $url, $raw_response = false, $error_default = null, $is_api=false){

        //PRECHECKING
        $apps_system_url = config("iprotek_apps.app_system_url");
        if(!$apps_system_url){
            return [
                "status"=>0,
                "message"=>"Application url not set"
            ];
        }
        
        $client = static::client(null, $is_api);
        
        $response = $client->get($url);
        return static::response_result($response, $raw_response, $error_default);
    }

    public static function get_api_client($url, $raw_response = false, $error_default = null){
        return static::get_client( $url, $raw_response , $error_default, true);
    }

    public static function response_result( $response, $raw_response, $error_default){
        $response_code = $response->getStatusCode(); 
        if($raw_response){
            if($response_code != 200 && $response_code != 201){
                if($error_default){
                    return $error_default;
                }
            }
            return $response;
        } 

        if($response_code != 200 && $response_code != 201){
            if(!$error_default){
                $result = $response->getBody();
                if($result){
                    return response()->json($result, $response_code);
                    /*
                    return [
                        "status"=>0,
                        "result"=> json_decode($response->getBody(), true),
                        "Api Invalidated."
                    ];
                    */ 
                }
                return [
                    "status"=>0,
                    "result"=>[],
                    "Api Invalidated."
                ];
            }
            return [
                "status"=>0,
                "result"=> $error_default,
                "Api Invalidated."
            ]; 
        }
        $result = json_decode($response->getBody(), true);
        return [
            "status"=>1, 
            "result"=> $result,
            "message"=>"Api Successful."
        ];

    }

    public static function post_client($url, $body, $raw_response = false, $error_default = null, $is_api = false){

        if(is_array($body)){
            $body = json_encode($body);
        }
        else if(is_object($body)){
            $body = json_encode($body);
        }


        //PRECHECKING
        $apps_system_url = config("iprotek_apps.app_system_url");
        if(!$apps_system_url){
            return [
                "status"=>0,
                "message"=>"Application url not set"
            ];
        }
        
        $client = static::client(null, $is_api);
        
        $response = $client->post($url, ["body"=>$body]);
        return static::response_result($response, $raw_response, $error_default);
    }
    public static function post_api_client($url, $body, $raw_response = false, $error_default = null){
        return static::post_client($url, $body, $raw_response, $error_default, true);
    }

}
