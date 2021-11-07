<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardTweetLinks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('card_tweet_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_tweet_id')->constrained()->onDelete('cascade');
            $table->string('href');
            $table->string('image_src');
            $table->string('url');
            $table->string('title');
            $table->text('description');
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
        Schema::dropIfExists('card_tweet_links');
    }
}
