<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Boat
 * @package App\Models
 * @version June 2, 2022, 8:31 pm UTC
 *
 * @property \App\Models\BravoPort $portFrom
 * @property \App\Models\BravoPort $portTo
 * @property \App\Models\BravoBoatVendor $vendor
 * @property \Illuminate\Database\Eloquent\Collection $bookingBoatPassengers
 * @property \Illuminate\Database\Eloquent\Collection $bravoBoatSeats
 * @property string $title
 * @property string $code
 * @property number $review_score
 * @property time $departure_time
 * @property time $arrival_time
 * @property number $duration
 * @property number $min_price
 * @property integer $port_from
 * @property integer $port_to
 * @property number $price
 * @property number $price_weekend
 * @property integer $max_passengers
 * @property integer $image_id
 * @property string $gallery
 * @property integer $vendor_id
 * @property integer $parent_id
 * @property integer $agent_id
 * @property string $status
 * @property integer $create_user
 * @property integer $update_user
 */
class Boat extends Model
{
    use SoftDeletes;

    use HasFactory;

    public $table = 'bravo_boat';
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    protected $dates = ['deleted_at'];



    public $fillable = [
        'title',
        'code',
        'review_score',
        'departure_time',
        'arrival_time',
        'duration',
        'min_price',
        'port_from',
        'port_to',
        'price',
        'price_weekend',
        'max_passengers',
        'image_id',
        'gallery',
        'vendor_id',
        'parent_id',
        'agent_id',
        'status',
        'create_user',
        'update_user'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
        'code' => 'string',
        'review_score' => 'decimal:1',
        'duration' => 'float',
        'min_price' => 'decimal:2',
        'port_from' => 'integer',
        'port_to' => 'integer',
        'price' => 'decimal:2',
        'price_weekend' => 'decimal:2',
        'max_passengers' => 'integer',
        'image_id' => 'integer',
        'gallery' => 'string',
        'vendor_id' => 'integer',
        'parent_id' => 'integer',
        'agent_id' => 'integer',
        'status' => 'string',
        'create_user' => 'integer',
        'update_user' => 'integer'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [
        'title' => 'nullable|string|max:191',
        'code' => 'nullable|string|max:191',
        'review_score' => 'nullable|numeric',
        'departure_time' => 'nullable',
        'arrival_time' => 'nullable',
        'duration' => 'nullable|numeric',
        'min_price' => 'nullable|numeric',
        'port_from' => 'nullable',
        'port_to' => 'nullable',
        'price' => 'nullable|numeric',
        'price_weekend' => 'nullable|numeric',
        'max_passengers' => 'nullable|integer',
        'image_id' => 'nullable|integer',
        'gallery' => 'nullable|string|max:255',
        'vendor_id' => 'nullable',
        'parent_id' => 'nullable',
        'agent_id' => 'nullable',
        'status' => 'nullable|string|max:50',
        'create_user' => 'nullable',
        'update_user' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function createUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'create_user');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function agent()
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function portFrom()
    {
        return $this->belongsTo(\App\Models\BravoPort::class, 'port_from');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function portTo()
    {
        return $this->belongsTo(\App\Models\BravoPort::class, 'port_to');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function vendor()
    {
        return $this->belongsTo(\App\Models\BravoBoatVendor::class, 'vendor_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     **/
    public function bookingBoatPassengers()
    {
        return $this->hasMany(\App\Models\BookingBoatPassenger::class, 'boat_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     **/
    public function bravoBoatSeats()
    {
        return $this->hasMany(\App\Models\BravoBoatSeat::class, 'boat_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     **/
    public function bravoBoatTerms()
    {
        return $this->hasMany(\App\Models\BravoBoatTerm::class, 'target_id');
    }
}
