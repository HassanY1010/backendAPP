<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_id',
        'sender_id',
        'receiver_id',
        'conversation_scope',
        'participant_min_id',
        'participant_max_id',
        'last_message_id',
        'last_message_at',
        'sender_deleted_at',
        'receiver_deleted_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'sender_deleted_at' => 'datetime',
        'receiver_deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Conversation $conversation) {
            if (!$conversation->sender_id || !$conversation->receiver_id) {
                return;
            }

            $senderId = (int) $conversation->sender_id;
            $receiverId = (int) $conversation->receiver_id;

            $conversation->participant_min_id = min($senderId, $receiverId);
            $conversation->participant_max_id = max($senderId, $receiverId);
            $conversation->conversation_scope = $conversation->ad_id
                ? 'ad:' . $conversation->ad_id
                : 'direct';
        });
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
}
