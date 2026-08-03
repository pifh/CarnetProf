<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('students')->where('special_needs', '')->update(['special_needs' => null]);

        DB::table('students')
            ->whereNotNull('special_needs')
            ->get(['id', 'special_needs'])
            ->each(fn (object $row) => DB::table('students')->where('id', $row->id)->update([
                'special_needs' => json_encode([$row->special_needs]),
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('students')
            ->whereNotNull('special_needs')
            ->get(['id', 'special_needs'])
            ->each(function (object $row): void {
                $tags = json_decode($row->special_needs, true) ?: [];

                DB::table('students')->where('id', $row->id)->update([
                    'special_needs' => implode(', ', $tags),
                ]);
            });
    }
};
