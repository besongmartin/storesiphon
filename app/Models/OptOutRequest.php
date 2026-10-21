<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptOutRequest extends Model
{
    protected $fillable = ['host', 'email', 'token', 'ip'];

    protected $hidden = ['email', 'ip'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
