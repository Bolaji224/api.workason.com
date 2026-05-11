<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployerNameToDisputesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
 public function up()
{
    Schema::table('disputes', function (Blueprint $table) {
        $table->string('employer_name')->nullable()->after('phone');
    });
}

}
