<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::firstOrCreate(
            ['slug' => 'ayook-cafe'],
            [
                'name'            => 'Ayook Café',
                'logo_url'        => 'https://freight.cargo.site/t/original/i/a270fbbc9ca637f161d317f0c6615755a16e53de74df5e60e000433a8737528a/lepiz_151023_6913.jpg',
                'primary_color'   => '#1a1a2e',
                'secondary_color' => '#f7e6c4',
                'label_color'     => '#e2c299',
                'contact_email'   => 'hola@ayookcafe.com',
                'is_active'       => true,
            ]
        );

        LoyaltyProgram::firstOrCreate(
            ['google_class_suffix' => 'ayook-cafe-coffee-10'],
            [
                'business_id'        => $business->id,
                'name'               => 'Club del Café',
                'description'        => 'Compra 10 cafés y el siguiente es gratis.',
                'total_stamps'       => 10,
                'stamp_icon'         => 'coffee',
                'reward_title'       => 'Café gratis',
                'reward_description' => '1 café de tu elección completamente gratis.',
                'is_active'          => true,
            ]
        );
    }
}
