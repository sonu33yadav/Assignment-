<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roleuser extends Model
{
    protected $table = 'role_user';
    protected $fillable = ['user_id','role_id'];

     public function Role()
    {
        return $this->belongsToMany(Role::class,'role_id','id');
    }    
}
