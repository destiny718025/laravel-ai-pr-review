<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'scope',
    'custom_instructions',
])]
class ReviewInstructionSetting extends Model
{
    //
}
