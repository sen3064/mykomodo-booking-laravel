<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class BoatPort
 * @package App\Models
 * @version June 12, 2022, 6:15 pm UTC
 *
 * @property \Illuminate\Database\Eloquent\Collection $bravoBoats
 * @property \Illuminate\Database\Eloquent\Collection $bravoBoat1s
 * @property string $name
 * @property string $code
 * @property string $address
 * @property integer $location_id
 * @property string $description
 * @property string $map_lat
 * @property string $map_lng
 * @property integer $map_zoom
 * @property string $port_type
 * @property string $status
 * @property integer $create_user
 * @property integer $update_user
 */
class BoatPort extends Model
{
    use SoftDeletes;

    use HasFactory;

    public $table = 'bravo_port';
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    protected $dates = ['deleted_at'];



    public $fillable = [
        'name',
        'code',
        'address',
        'location_id',
        'description',
        'map_lat',
        'map_lng',
        'map_zoom',
        'port_type',
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
        'name' => 'string',
        'code' => 'string',
        'address' => 'string',
        'location_id' => 'integer',
        'description' => 'string',
        'map_lat' => 'string',
        'map_lng' => 'string',
        'map_zoom' => 'integer',
        'port_type' => 'string',
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
        'name' => 'nullable|string|max:191',
        'code' => 'required|string|max:191',
        'address' => 'nullable|string|max:191',
        'location_id' => 'nullable|integer',
        'description' => 'nullable|string',
        'map_lat' => 'nullable|string|max:20',
        'map_lng' => 'nullable|string|max:20',
        'map_zoom' => 'nullable|integer',
        'port_type' => 'nullable|string|max:50',
        'status' => 'nullable|string|max:50',
        'create_user' => 'nullable',
        'update_user' => 'nullable',
        'deleted_at' => 'nullable',
        'created_at' => 'nullable',
        'updated_at' => 'nullable'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     **/
    public function bravoBoats()
    {
        return $this->hasMany(\App\Models\BravoBoat::class, 'port_from');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     **/
    public function bravoBoat1s()
    {
        return $this->hasMany(\App\Models\BravoBoat::class, 'port_to');
    }
}
