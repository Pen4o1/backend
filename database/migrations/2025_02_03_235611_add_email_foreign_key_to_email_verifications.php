<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailForeignKeyToEmailVerifications extends Migration
{
    public function up()
    {
        Schema::table('email_verifications', function (Blueprint $table) {
            $table->foreign('email')->references('email')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('email_verifications', function (Blueprint $table) {
            $table->dropForeign(['email']);
        });
    }
}
