<?php

use Illuminate\Database\Migrations\Migration;

class RenameAnyoneToAnyoneWithLink extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \DB::table('link_share_types')->insert([
            ['name' => 'anyoneWithLink'], // Login to org required
        ]);

        // Delete anyoneInWorkspace and anyone
        \DB::table('link_share_types')->where('name', 'anyoneInWorkspace')->delete();
        \DB::table('link_share_types')->where('name', 'anyone')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \DB::table('link_share_types')->insert([
            ['name' => 'anyoneInWorkspace'], // Login to org required
            ['name' => 'anyone'], // Login to org required
        ]);

        // Delete anyoneInWorkspace and anyone
        \DB::table('link_share_types')->where('name', 'anyoneWithLink')->delete();
    }
}
