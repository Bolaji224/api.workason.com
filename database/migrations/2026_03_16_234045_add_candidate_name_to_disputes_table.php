<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCandidateNameToDisputesTable extends Migration
{
    public function up()
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('candidate_name')->nullable()->after('employer_name'); // ✅ Add this
        });
    }

    public function down()
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('candidate_name'); // ✅ Add this
        });
    }
}