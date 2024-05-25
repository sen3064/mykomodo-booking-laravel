<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BravoReview extends Model
{
    use HasFactory;
    
    protected $connection = 'kabtour_db';
    protected $table='bravo_review';

    public function reviewer(){
        return $this->belongsTo(User::class,'create_user');
    }
}
