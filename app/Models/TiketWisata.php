<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class TiketWisata extends Model
{
    use SoftDeletes;
    use Notifiable;
    protected $table                              = 'bravo_tiket_wisata';
    public    $type                               = 'hotel';
}