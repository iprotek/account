<?php

namespace iProtek\Apps\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use iProtek\Core\Http\Controllers\_Common\_CommonController;
use iProtek\Apps\Helpers\AppsHttpHelper;

class AppsController extends _CommonController
{ 
    public $guard = 'admin';

    public function list(Request $request){
        //GET APPS FROM apps.iprotek.net
        $response = AppsHttpHelper::get_client("api/application-list");
        return $response;
        //return config("iprotek_apps.app_system_url");
    }

}