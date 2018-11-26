<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubprocessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subprocesses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->index();
            $table->unsignedInteger('process_id')->index();
            $table->string('product_id')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->text('error')->nullable();
            $table->text('varyants_id')->nullable();
            $table->tinyInteger('mode')->default(1)->comment('1:insert,2:update');
            $table->tinyInteger('status')->default(1)->comment('1:pending,2:success,3:failure');
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
        Schema::dropIfExists('subprocesses');
    }
}
