<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'code', "currency", 'country', 'type'
    ];

      /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at'
    ];



}
