<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/security.php';

class DatabaseSeeder {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    public function run() {
        echo "Seeding database...\n";
        
        // Clear existing data
        $this->truncateTables();
        
        // Seed data
        $this->seedMarkets();
        $this->seedCommodities();
        $this->seedAdminUser();
        $this->seedUptdUsers();
        $this->seedSliders();
        $this->seedPrices();
        
        echo "Database seeded successfully!\n";
    }
    
    private function truncateTables() {
        $tables = [
            'notifications',
            'logs',
            'price_history',
            'prices',
            'sliders',
            'users',
            'commodities',
            'markets'
        ];
        
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($tables as $table) {
            $this->db->execute("TRUNCATE TABLE `$table`");
            echo "Truncated table: $table\n";
        }
        
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');
    }
    
    private function seedMarkets() {
        $markets = [
            ['name' => 'Pasar Gading', 'address' => 'Jl. Gading No. 123, Jakarta Utara'],
            ['name' => 'Pasar Utama', 'address' => 'Jl. Utama No. 45, Jakarta Pusat'],
            ['name' => 'Pasar Baru', 'address' => 'Jl. Baru No. 67, Jakarta Selatan'],
            ['name' => 'Pasar Induk', 'address' => 'Jl. Induk No. 89, Jakarta Timur'],
            ['name' => 'Pasar Modern', 'address' => 'Jl. Modern No. 10, Jakarta Barat']
        ];
        
        foreach ($markets as $market) {
            $this->db->execute(
                "INSERT INTO markets (name, address, is_active) VALUES (?, ?, 1)",
                [$market['name'], $market['address']]
            );
        }
        
        echo "Seeded markets table\n";
    }
    
    private function seedCommodities() {
        $commodities = [
            ['name' => 'Beras Premium', 'unit' => 'kg', 'description' => 'Beras kualitas premium'],
            ['name' => 'Gula Pasir', 'unit' => 'kg', 'description' => 'Gula pasir kristal putih'],
            ['name' => 'Minyak Goreng', 'unit' => 'liter', 'description' => 'Minyak goreng sawit'],
            ['name' => 'Telur Ayam', 'unit' => 'kg', 'description' => 'Telur ayam ras'],
            ['name' => 'Daging Ayam', 'unit' => 'kg', 'description' => 'Daging ayam potong'],
            ['name' => 'Daging Sapi', 'unit' => 'kg', 'description' => 'Daging sapi kualitas bagus'],
            ['name' => 'Cabai Merah', 'unit' => 'kg', 'description' => 'Cabai merah keriting'],
            ['name' => 'Bawang Merah', 'unit' => 'kg', 'description' => 'Bawang merah lokal'],
            ['name' => 'Bawang Putih', 'unit' => 'kg', 'description' => 'Bawang putih impor'],
            ['name' => 'Garam', 'unit' => 'kg', 'description' => 'Garam konsumsi']
        ];
        
        foreach ($commodities as $commodity) {
            $this->db->execute(
                "INSERT INTO commodities (name, unit, description, is_active) VALUES (?, ?, ?, 1)",
                [$commodity['name'], $commodity['unit'], $commodity['description']]
            );
        }
        
        echo "Seeded commodities table\n";
    }
    
    private function seedAdminUser() {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (username, email, password, full_name, role, is_active) 
             VALUES (?, ?, ?, ?, 'admin', 1)",
            ['admin', 'admin@siagabapok.test', $password, 'Administrator']
        );
        
        echo "Seeded admin user\n";
    }
    
    private function seedUptdUsers() {
        $password = password_hash('uptd123', PASSWORD_DEFAULT);
        $markets = $this->db->fetchAll("SELECT id FROM markets");
        
        foreach ($markets as $index => $market) {
            $username = 'uptd' . ($index + 1);
            $email = $username . '@siagabapok.test';
            $fullName = 'Petugas UPTD Pasar ' . ($index + 1);
            
            $this->db->execute(
                "INSERT INTO users (username, email, password, full_name, role, market_assigned, is_active) 
                 VALUES (?, ?, ?, ?, 'uptd', ?, 1)",
                [$username, $email, $password, $fullName, $market['id']]
            );
        }
        
        echo "Seeded UPTD users\n";
    }
    
    private function seedSliders() {
        $sliders = [
            [
                'title' => 'Sistem Informasi Harga Bahan Pokok',
                'description' => 'Pantau harga bahan pokok terkini di seluruh pasar',
                'image_path' => '/assets/images/sliders/slider1.jpg',
                'link_url' => '/about'
            ],
            [
                'title' => 'Update Harga Harian',
                'description' => 'Dapatkan informasi harga terkini setiap harinya',
                'image_path' => '/assets/images/sliders/slider2.jpg',
                'link_url' => '/prices'
            ],
            [
                'title' => 'Analisis Harga',
                'description' => 'Analisis perbandingan harga antar pasar',
                'image_path' => '/assets/images/sliders/slider3.jpg',
                'link_url' => '/analytics'
            ]
        ];
        
        $adminId = $this->db->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1")['id'];
        
        foreach ($sliders as $order => $slider) {
            $this->db->execute(
                "INSERT INTO sliders (title, description, image_path, link_url, is_active, display_order, created_by) 
                 VALUES (?, ?, ?, ?, 1, ?, ?)",
                [
                    $slider['title'],
                    $slider['description'],
                    $slider['image_path'],
                    $slider['link_url'],
                    $order + 1,
                    $adminId
                ]
            );
        }
        
        echo "Seeded sliders\n";
    }
    
    private function seedPrices() {
        $commodities = $this->db->fetchAll("SELECT id FROM commodities");
        $markets = $this->db->fetchAll("SELECT id FROM markets");
        $uptdUsers = $this->db->fetchAll("SELECT id, market_assigned FROM users WHERE role = 'uptd'");
        $adminId = $this->db->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1")['id'];
        
        // Price ranges for each commodity (min, max)
        $priceRanges = [
            'Beras Premium' => [12000, 15000],
            'Gula Pasir' => [12000, 15000],
            'Minyak Goreng' => [14000, 20000],
            'Telur Ayam' => [28000, 35000],
            'Daging Ayam' => [35000, 45000],
            'Daging Sapi' => [120000, 150000],
            'Cabai Merah' => [30000, 80000],
            'Bawang Merah' => [25000, 50000],
            'Bawang Putih' => [20000, 40000],
            'Garam' => [10000, 15000]
        ];
        
        // Generate prices for the last 30 days
        $today = new DateTime();
        $startDate = (clone $today)->modify('-30 days');
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $today);
        
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            
            foreach ($markets as $market) {
                $marketId = $market['id'];
                
                // Find UPTD user for this market
                $uptdUser = array_filter($uptdUsers, function($user) use ($marketId) {
                    return $user['market_assigned'] == $marketId;
                });
                
                $uptdUserId = !empty($uptdUser) ? reset($uptdUser)['id'] : null;
                
                foreach ($commodities as $commodity) {
                    $commodityId = $commodity['id'];
                    $commodityName = $this->db->fetchOne(
                        "SELECT name FROM commodities WHERE id = ?", 
                        [$commodityId]
                    )['name'];
                    
                    // Get price range for this commodity
                    $range = $priceRanges[$commodityName] ?? [10000, 50000];
                    
                    // Generate random price within range
                    $price = mt_rand($range[0], $range[1]);
                    
                    // Add some daily variation
                    $variation = mt_rand(-10, 10) / 100; // -10% to +10%
                    $price = round($price * (1 + $variation));
                    
                    // Create price record
                    $this->db->execute(
                        "INSERT INTO prices 
                         (commodity_id, market_id, price, submitted_by, status, approved_by, approved_at, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, 'approved', ?, ?, ?, ?)",
                        [
                            $commodityId,
                            $marketId,
                            $price,
                            $uptdUserId ?? $adminId,
                            $adminId,
                            $dateStr . ' ' . mt_rand(9, 16) . ':' . str_pad(mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                            $dateStr . ' ' . mt_rand(9, 16) . ':' . str_pad(mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                            $dateStr . ' ' . mt_rand(9, 16) . ':' . str_pad(mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00'
                        ]
                    );
                }
            }
            
            echo "Seeded prices for $dateStr\n";
        }
        
        // Add some pending approvals
        foreach ($markets as $market) {
            $marketId = $market['id'];
            $uptdUser = array_filter($uptdUsers, function($user) use ($marketId) {
                return $user['market_assigned'] == $marketId;
            });
            
            $uptdUserId = !empty($uptdUser) ? reset($uptdUser)['id'] : null;
            
            if ($uptdUserId) {
                $randomCommodity = $commodities[array_rand($commodities)];
                $commodityId = $randomCommodity['id'];
                $commodityName = $this->db->fetchOne(
                    "SELECT name FROM commodities WHERE id = ?", 
                    [$commodityId]
                )['name'];
                
                $range = $priceRanges[$commodityName] ?? [10000, 50000];
                $price = mt_rand($range[0], $range[1]);
                
                $this->db->execute(
                    "INSERT INTO prices 
                     (commodity_id, market_id, price, submitted_by, status, created_at) 
                     VALUES (?, ?, ?, ?, 'pending', NOW())",
                    [$commodityId, $marketId, $price, $uptdUserId]
                );
            }
        }
        
        echo "Seeded pending price approvals\n";
        echo "Total prices seeded: " . $this->db->fetchOne("SELECT COUNT(*) as count FROM prices")['count'] . "\n";
    }
}

// Run the seeder
$seeder = new DatabaseSeeder();
$seeder->run();
