<?php

namespace Database\Seeders;

use App\Models\MenuPermission;
use Illuminate\Database\Seeder;

class MenuPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allKeys = array_keys(MenuPermission::MENU_ITEMS);

        foreach (MenuPermission::DEFAULT_PERMISSIONS as $role => $allowedKeys) {
            foreach ($allKeys as $key) {
                MenuPermission::updateOrCreate(
                    ['role' => $role, 'menu_key' => $key],
                    ['allowed' => in_array($key, $allowedKeys)]
                );
            }
        }
    }
}
