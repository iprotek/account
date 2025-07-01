<?php

namespace iProtek\Account\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class AccountHelper
{ 
    public static function submitLoginRequest(Request $request){

        /*
        Log::error("Origin:". $request->headers->get('Origin') );
        Log::error("Referrer: ". $request->headers->get('Referer'));
        Log::error($request->url());
        Log::error($request->getSchemeAndHttpHost());
        */
        $response = AccountHttpHelper::post_client("api/login-request", [
            "requestor_origin"=>$request->getSchemeAndHttpHost(),//config("app.url"),
            "requestor_origin_url"=>request()->fullUrl(),
        ]);
        //return $request->headers->get('Origin');
        //return ["status"=>1,"message"=>"data"];
        return $response;

        //return
    }

    public static function verifyLoginRequest($id){

    }


}
