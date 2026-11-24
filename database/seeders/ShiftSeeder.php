<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Shift 1', '07:00', '15:00'], ['Shift 2', '15:00', '23:00'], ['Shift 3', '23:00', '07:00']] as [$name, $start, $end]) {
            Shift::firstOrCreate(['name' => $name], ['start_time' => $start, 'end_time' => $end]);
        }
    }
}
