<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TermTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('term_types')->insert([
            ['term_type' => 'disabled_functions', 'color' => 'red'],
            ['term_type' => 'program_execution_functions', 'color' => 'yellow'],
        ]);
    }
}
