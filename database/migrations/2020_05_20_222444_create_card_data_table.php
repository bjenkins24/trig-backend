<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('card_data', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('keyword')->nullable();
            $table->string('author')->nullable();
            $table->string('last_author')->nullable();
            $table->string('comment')->nullable();
            $table->string('language')->nullable();
            $table->string('subject')->nullable();
            $table->integer('revisions')->nullable();
            $table->dateTime('created')->nullable();
            $table->dateTime('modified')->nullable();
            $table->dateTime('print_date')->nullable();
            $table->dateTime('save_date')->nullable();
            $table->integer('line_count')->nullable();
            $table->integer('page_count')->nullable();
            $table->integer('paragraph_count')->nullable();
            $table->integer('word_count')->nullable();
            $table->integer('character_count')->nullable();
            $table->integer('character_count_with_spaces')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('copyright')->nullable();
            $table->longText('content')->nullable();
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
        Schema::dropIfExists('card_data');
    }
}
