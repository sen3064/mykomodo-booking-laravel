<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourParent extends Model
{
    use HasFactory;

    protected $table = 'bravo_tour_parent';
    protected $fillable = [
        'title',
        'slug',
        'content',
        'image_id',
        'gallery',
        'location_id',
        'status',
        'create_user',
        'update_user'
    ];
}
