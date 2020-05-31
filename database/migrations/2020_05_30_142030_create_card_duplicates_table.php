<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardDuplicatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('card_duplicates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('primary_card_id');
            $table->unsignedBigInteger('duplicate_card_id');
            $table->foreign('primary_card_id')->references('id')->on('cards');
            $table->foreign('duplicate_card_id')->references('id')->on('cards');
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
        Schema::dropIfExists('card_duplicates');
    }
}
