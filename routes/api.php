<?php

use Illuminate\Support\Facades\Route; 
use iProtek\Core\Http\Controllers\Manage\FileUploadController; 
use iProtek\Core\Http\Controllers\AppVariableController;

Route::prefix('api')->middleware('api')->name('api')->group(function(){ 

    Route::prefix('account/group/{group_id}')->middleware(['pay.api', 'policy.control'])->name('.account')->group(function(){
      
      //FILE UPLOADS
      //include(__DIR__.'/api/file-upload.php');

      //FILE UPLOADS
      //include(__DIR__.'/api/meta-data.php'); 

    });
 
}); 
