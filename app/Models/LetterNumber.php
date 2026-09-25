<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterNumber extends Model
{
    protected $fillable = [
        'number',
        'prodi_key',
        'user_id',
        'subject',
        'attachment_path',
    ];

    protected $casts = [
        'number' => 'integer',
    ];
}
