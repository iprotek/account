<?php

use Illuminate\Support\Facades\Route; 
use iProtek\Apps\Http\Controllers\AppsController;

include(__DIR__.'/api.php');

Route::middleware(['web'])->group(function(){
 
    Route::middleware(['auth:admin'])->prefix('manage')->name('manage')->group(function(){
        
        Route::prefix('apps')->name('.account')->group(function(){

            //GET APP LISTS BASED ON THE LINK
            Route::get('list',[ 
                "uses"=>[AppsController::class, 'list'],
                "description"=>"List of apps",
                "is_visible"=>false,
                "is_allow"=>true
            ])->name('.list');
            
        }); 

    });
  
});