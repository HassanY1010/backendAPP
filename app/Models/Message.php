<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['sender_id', 'receiver_id', 'message', 'conversation_id', 'message_type', 'file_url', 'is_read', 'read_at'];

    public function getFileUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_contains($value, 'storage/chat/')) {
            return str_replace('storage/chat/', 'local-cdn/chat/', $value);
        }
        return $value;
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
