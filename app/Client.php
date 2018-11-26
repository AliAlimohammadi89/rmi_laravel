<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['unique_key','api_key','password','username_datalink','userpass_datalink','datalink_public_key','datalik_public_set', 'datalink_api', 'shopify_access_token', 'shopify_store'];

    public function getClient($id)
    {
        return $this->whereId($id)->first();
    }
    public function gettable1($table,$fild_value)
    {
//        $users = DB::table('users')->where([
//            ['status', '=', '1'],
//            ['subscribed', '<>', '1'],
//        ])->get();


        dd($table);
//        $users = DB::table($table)->where(
//            $fild_value
//       )->get();
    }

    public function processes()
    {
        return $this->hasMany(Process::class);
    }

}
