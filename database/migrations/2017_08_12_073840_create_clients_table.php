<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('unique_key', 255)->nullable();
            $table->string('datalink_api', 255)->nullable();
            $table->string('shopify_access_token', 255)->nullable();
            $table->string('shopify_store', 255)->nullable();
//            $table->string('shopify_api_key', 255)->nullable();
//            $table->string('shopify_password', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clients');
    }
}
