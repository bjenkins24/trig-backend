<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CardsNullableDatetimeFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cards', static function (Blueprint $table) {
            $table->dateTime('actual_created_at')->nullable()->change();
            $table->dateTime('actual_updated_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', static function (Blueprint $table) {
            $table->dateTime('actual_created_at')->nullable(false)->change();
            $table->dateTime('actual_updated_at')->nullable(false)->change();
        });
    }
}
