<?php

namespace iProtek\Account\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class AccountHelper
{ 
    public static function submitLoginRequest(Request $request){

        
        $response = AccountHttpHelper::post_client("api/login-request", [
            "requestor_origin"=>"",
            "requestor_origin_url"=>request()->fullUrl(),
        ], false);
        //return $request->headers->get('Origin');
        //return ["status"=>1,"message"=>"data"];
        return $response;

        //return
    }

    public static function verifyLoginRequest($id){

    }


}
