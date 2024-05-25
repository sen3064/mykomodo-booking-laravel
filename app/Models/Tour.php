<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tour extends Model
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table                              = 'bravo_tours';

    protected $fillable                           = [
        //Tour info
        'title',
        'content',
        'image_id',
        'banner_image_id',
        'short_desc',
        'category_id',
        'location_id',
        'address',
        'map_lat',
        'map_lng',
        'map_zoom',
        'is_featured',
        'gallery',
        'video',
        'price',
        'price_weekend',
        'price_holiday',
        'discount',
        'discount_weekend',
        'discount_holiday',
        'sale_price',
        //Tour type
        'duration',
        'max_people',
        'min_people',
        'stock',
        'is_private',
        'parent_id',
        //Extra Info
        'faqs',
        'status',
        'include',
        'exclude',
        'itinerary',
        'surrounding',
        'min_day_before_booking',
    ];
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'faqs'      => 'array',
        'include'   => 'array',
        'exclude'   => 'array',
        'itinerary' => 'array',
        'service_fee' => 'array',
        'surrounding' => 'array',

    ];
}
