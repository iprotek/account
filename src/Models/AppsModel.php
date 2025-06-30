<?php

namespace iProtek\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use iProtek\Core\Models\_CommonModel;

class AppsModel extends _CommonModel
{
    use HasFactory, SoftDeletes;

}
