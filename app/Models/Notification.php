<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'link',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected $appends = ['is_read'];

    public function getIsReadAttribute()
    {
        return $this->read_at !== null;
    }

    public function user()
    {
        return $this->belongsTo(Utilisateur::class, 'user_id');
    }

    /**
     * Scope to get only unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
