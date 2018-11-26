<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    public function subProcesses()
    {
        return $this->hasMany(Subprocess::class);
    }


    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
