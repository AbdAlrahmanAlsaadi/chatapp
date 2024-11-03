<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class conversation extends Model
{
    use HasFactory;

    protected $fillable = ['user1_id', 'user2_id'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_user');
    }
    public function user1()
{
    return $this->belongsTo(User::class, 'user1_id');
}

public function user2()
{
    return $this->belongsTo(User::class, 'user2_id');
}
// في موديل Conversation
public function lastMessage()
{
    return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
}

}
