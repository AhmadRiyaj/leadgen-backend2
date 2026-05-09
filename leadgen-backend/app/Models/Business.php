<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_name',
        'owner_name',
        'mobile',
        'city',
        'category',
        'address',
        'website',
        'has_website',
        'source',
        'lead_score',
        'ai_analysis',
        'status',
        'opted_out',
        'last_contacted_at',
    ];

    protected $casts = [
        'has_website'        => 'boolean',
        'opted_out'          => 'boolean',
        'ai_analysis'        => 'array',
        'last_contacted_at'  => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
