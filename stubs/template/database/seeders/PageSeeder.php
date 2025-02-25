<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Page::updateOrCreate(['id' => 1], [
            'title' => 'Home',
            'menuitem' => false,
            'slug' => 'home',
        ]);
        Page::updateOrCreate(['id' => 2], [
            'title' => 'Producten',
            'slug' => 'producten',
        ]);
        Page::updateOrCreate(['id' => 3], [
            'title' => 'Product 1',
            'parent' => 2,
            'slug' => 'product-1',
        ]);
        Page::updateOrCreate(['id' => 4], [
            'title' => 'Product A',
            'parent' => 2,
            'slug' => 'product-a',
        ]);
        Page::updateOrCreate(['id' => 5], [
            'title' => 'Contact',
            'slug' => 'contact',
        ]);
    }
}
