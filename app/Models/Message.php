<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'reply_to_id',
        'message',
        'conversation_id',
        'message_type',
        'file_url',
        'file_name',
        'is_read',
        'read_at',
        'deleted_by_sender',
        'deleted_by_receiver',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

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

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }
}
