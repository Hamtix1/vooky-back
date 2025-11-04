<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audio extends Model
{
    use HasFactory;
    protected $table = 'audios'; //así se llama la tabla realmente
    protected $fillable = ['url', 'description'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
