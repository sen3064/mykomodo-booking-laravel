<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingBoatPassenger extends Model
{
    use HasFactory;
    protected $connection = 'kabtour_db';
}
