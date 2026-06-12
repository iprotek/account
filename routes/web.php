<?php

use Illuminate\Support\Facades\Route; 

// Include API routes
include(__DIR__.'/api.php');

// Web routes for the account package
Route::middleware(['web'])->group(function(){
     // Place future web routes here
});