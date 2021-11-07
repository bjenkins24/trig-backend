<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCardTweetReplies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('card_tweet_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_tweet_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('handle');
            $table->string('avatar');
            $table->string('replying_to');
            $table->text('content');
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
        Schema::dropIfExists('card_tweet_replies');
    }
}
