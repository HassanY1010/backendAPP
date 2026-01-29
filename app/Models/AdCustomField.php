<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_id',
        'field_id',
        'value',
    ];

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }

    public function field()
    {
        return $this->belongsTo(CategoryField::class, 'field_id');
    }
}
