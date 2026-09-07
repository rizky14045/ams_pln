<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePeriodeAssetTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('periode_asset', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('periode_id')->nullable();
            $table->integer('asset_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('tanggal_inventaris')->nullable();
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
        //
    }
}
