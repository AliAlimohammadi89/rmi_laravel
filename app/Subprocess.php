<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subprocess extends Model
{
//    public $timestamps = false;
////    public $timestamps = [ "created_at" ];
//
//    public static function boot()
//    {
//        parent::boot();
//
//        static::creating(function ($model) {
//            $model->created_at = $model->freshTimestamp();
//        });
//    }

    public function process()
    {
        return $this->belongsTo(Process::class);
    }
     public function ali2($item)
    {
       dd ($item);
    }


}
