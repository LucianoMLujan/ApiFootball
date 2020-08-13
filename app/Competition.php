<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    protected $table = 'competitions';

    public function teams()
    {
        return $this->belongsToMany('App\Team');
    }

}
