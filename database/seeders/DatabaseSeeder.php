<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::updateOrCreate(['id' => 1], [
            'display_name' => 'Nettoyeur Villeneuve',
            'legal_name' => '8419965 Canada inc',
            'gst_number' => '824989842',
            'qst_number' => '1219927670',
            'default_language' => 'fr',
            'default_thank_you_message' => 'NOUS VOUS REMERCIONS DE VOTRE CONFIANCE.',
            'default_payment_instructions' => "Svp faire tous les chèques à l'ordre de 8419965 Canada inc",
        ]);

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Administrateur',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'client_id' => null,
            'is_active' => true,
        ]);
    }
}
