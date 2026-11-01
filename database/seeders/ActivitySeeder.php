<?php

namespace Database\Seeders;

use App\Models\Activity;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        // Add more here as needed; a management screen comes with the settings module.
        foreach (['Taekwondo', 'Soccer'] as $name) {
            Activity::firstOrCreate(['name' => $name]);
        }
    }
}
