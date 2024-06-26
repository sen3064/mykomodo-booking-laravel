<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class BravoCar extends Model
{
    use SoftDeletes;
    use Notifiable;
    protected $table                              = 'bravo_cars';
    public    $type                               = 'hotel';
}