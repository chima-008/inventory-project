<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'category' => 'Electronics',
                'name' => 'Wireless Keyboard',
                'sku' => 'ELEC-KEY-001',
                'description' => 'Compact wireless keyboard for desktop workstations.',
                'price' => 18500.00,
                'stock_quantity' => 24,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'Electronics',
                'name' => '27-inch LED Monitor',
                'sku' => 'ELEC-MON-001',
                'description' => '27-inch Full HD LED monitor for office and development work.',
                'price' => 185000.00,
                'stock_quantity' => 8,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'Office Supplies',
                'name' => 'A4 Copy Paper',
                'sku' => 'OFFC-PAP-001',
                'description' => 'A4 white copy paper suitable for everyday office printing.',
                'price' => 12500.00,
                'stock_quantity' => 42,
                'low_stock_threshold' => 10,
                'is_active' => true,
            ],
            [
                'category' => 'Office Supplies',
                'name' => 'Ballpoint Pen Pack',
                'sku' => 'OFFC-PEN-001',
                'description' => 'Pack of reliable black ballpoint pens for office use.',
                'price' => 3500.00,
                'stock_quantity' => 6,
                'low_stock_threshold' => 10,
                'is_active' => true,
            ],
            [
                'category' => 'Furniture',
                'name' => 'Ergonomic Office Chair',
                'sku' => 'FURN-CHR-001',
                'description' => 'Adjustable ergonomic chair designed for extended desk work.',
                'price' => 145000.00,
                'stock_quantity' => 4,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'Furniture',
                'name' => 'Office Work Desk',
                'sku' => 'FURN-DSK-001',
                'description' => 'Durable workstation desk suitable for modern offices.',
                'price' => 120000.00,
                'stock_quantity' => 12,
                'low_stock_threshold' => 3,
                'is_active' => true,
            ],
            [
                'category' => 'Networking',
                'name' => '8-Port Gigabit Switch',
                'sku' => 'NETW-SWT-001',
                'description' => 'Eight-port Gigabit Ethernet switch for small office networks.',
                'price' => 42000.00,
                'stock_quantity' => 15,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'Networking',
                'name' => 'Dual-Band Wi-Fi Router',
                'sku' => 'NETW-RTR-001',
                'description' => 'Dual-band wireless router for reliable office connectivity.',
                'price' => 68000.00,
                'stock_quantity' => 3,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'Accessories',
                'name' => 'USB-C Hub',
                'sku' => 'ACCS-HUB-001',
                'description' => 'Multi-port USB-C hub for laptops and workstations.',
                'price' => 28500.00,
                'stock_quantity' => 19,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'Accessories',
                'name' => 'HDMI Cable',
                'sku' => 'ACCS-HDM-001',
                'description' => 'High-speed HDMI cable for monitors and display devices.',
                'price' => 8500.00,
                'stock_quantity' => 0,
                'low_stock_threshold' => 5,
                'is_active' => false,
            ],
        ];

        foreach ($products as $product) {
            $category = Category::where('name', $product['category'])->firstOrFail();

            Product::updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $product['name'],
                    'slug' => Str::slug($product['name']),
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'stock_quantity' => $product['stock_quantity'],
                    'low_stock_threshold' => $product['low_stock_threshold'],
                    'is_active' => $product['is_active'],
                ]
            );
        }
    }
}