<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFileUrlToChatMessagesTable extends Migration
{
   public function up()
{
    Schema::table('messages', function (Blueprint $table) {
    $table->string('file_url', 500)->nullable()->after('message');
});
}

public function down()
{
    Schema::table('messages', function (Blueprint $table) {
        $table->dropColumn('file_url');
    });
}
}
