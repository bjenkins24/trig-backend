<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHypernymTagColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tags', static function (Blueprint $table) {
            $table->string('hypernym')->after('tag')->index();
            $table->index('tag');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tags', static function (Blueprint $table) {
            $table->dropColumn('hypernym');
            $table->dropIndex('tags_tag_index');
        });
    }
}
