<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLinkShareTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('link_share_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        \DB::table('link_share_types')->insert([
            ['name' => 'anyoneInOrganization'], // Login to org required
            ['name' => 'anyone'], // No login required
            ['name' => 'public'], // No login required and indexable
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('link_share_types');
    }
}
