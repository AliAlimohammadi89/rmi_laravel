<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProcessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id')->index();
            $table->string('bucket_id')->index();
            $table->string('tracking_code');
            $table->string('email')->nullable();
            $table->unsignedMediumInteger('count_products')->default(1);
            $table->unsignedTinyInteger('status')->default(1)->comment('1:open,2:running,3:close');
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
        Schema::dropIfExists('processes');
    }
}
