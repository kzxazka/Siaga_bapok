-- Database: siagabapok_db
-- Sistem Informasi Harga Bahan Pokok dengan Multi-User Role

CREATE DATABASE IF NOT EXISTS siagabapok_db;
USE siagabapok_db;

-- Tabel Users untuk autentikasi dan role management
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'uptd', 'masyarakat') NOT NULL,
    market_assigned INT NULL, -- Untuk UPTD, pasar yang ditugaskan (foreign key ke pasar.id_pasar)
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (market_assigned) REFERENCES pasar(id_pasar) ON DELETE SET NULL
);

-- Tabel Pasar untuk menyimpan informasi pasar
CREATE TABLE pasar (
    id_pasar INT PRIMARY KEY AUTO_INCREMENT,
    nama_pasar VARCHAR(100) NOT NULL,
    alamat TEXT,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Komoditas untuk menyimpan daftar komoditas
CREATE TABLE commodities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    unit VARCHAR(20) NOT NULL DEFAULT 'kg',
    kategori VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Prices untuk menyimpan data harga
CREATE TABLE prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commodity_id INT NOT NULL, -- Foreign key ke commodities.id
    price DECIMAL(10,2) NOT NULL,
    market_id INT NOT NULL, -- Foreign key ke pasar.id_pasar
    uptd_user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (commodity_id) REFERENCES commodities(id) ON DELETE CASCADE,
    FOREIGN KEY (market_id) REFERENCES pasar(id_pasar) ON DELETE CASCADE,
    FOREIGN KEY (uptd_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_commodity (commodity_id),
    INDEX idx_market (market_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
);

-- Tabel Sessions untuk manajemen login
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_expires (expires_at)
);

-- Insert sample pasar data
INSERT INTO pasar (nama_pasar, alamat, keterangan) VALUES
('Pasar Tugu', 'Jl. Tugu No. 123, Jakarta', 'Pasar tradisional terbesar di Jakarta'),
('Pasar Bambu Kuning', 'Jl. Bambu Kuning No. 45, Jakarta', 'Pasar sayur dan buah segar'),
('Pasar Smep', 'Jl. Smep No. 67, Jakarta', 'Pasar ikan dan daging'),
('Pasar Kangkung', 'Jl. Kangkung No. 89, Jakarta', 'Pasar kebutuhan sehari-hari');

-- Insert sample commodities data
INSERT INTO commodities (name, unit, kategori) VALUES
('Beras Premium', 'kg', 'Beras'),
('Beras Medium', 'kg', 'Beras'),
('Cabai Merah', 'kg', 'Sayuran'),
('Cabai Rawit', 'kg', 'Sayuran'),
('Bawang Merah', 'kg', 'Bumbu'),
('Bawang Putih', 'kg', 'Bumbu'),
('Minyak Goreng', 'liter', 'Minyak'),
('Gula Pasir', 'kg', 'Gula'),
('Daging Sapi', 'kg', 'Daging'),
('Daging Ayam', 'kg', 'Daging'),
('Telur Ayam', 'kg', 'Telur'),
('Ikan Tongkol', 'kg', 'Ikan');

-- Insert Default Admin User
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert Sample UPTD Users (with proper market_assigned foreign keys)
INSERT INTO users (username, email, password, full_name, role, market_assigned) VALUES
('uptd_tugu', 'uptd.tugu@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Tugu', 'uptd', 1),
('uptd_bambu', 'uptd.bambu@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Bambu Kuning', 'uptd', 2),
('uptd_smep', 'uptd.smep@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Smep', 'uptd', 3),
('uptd_kangkung', 'uptd.kangkung@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Kangkung', 'uptd', 4);

-- Insert Sample Masyarakat User
INSERT INTO users (username, email, password, full_name, role) VALUES
('masyarakat1', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Masyarakat User', 'masyarakat');

-- Generate Sample Price Data
DELIMITER //

CREATE PROCEDURE GenerateSamplePrices()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_id INT;
    DECLARE market_id INT;
    DECLARE day_counter INT DEFAULT 0;
    DECLARE current_date DATE;
    
    -- Cursor untuk UPTD users
    DECLARE uptd_cursor CURSOR FOR 
        SELECT id, market_assigned FROM users WHERE role = 'uptd' AND market_assigned IS NOT NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Generate data untuk 30 hari terakhir
    WHILE day_counter < 30 DO
        SET current_date = DATE_SUB(CURDATE(), INTERVAL day_counter DAY);
        SET done = FALSE;
        
        OPEN uptd_cursor;
        uptd_loop: LOOP
            FETCH uptd_cursor INTO user_id, market_id;
            IF done THEN
                LEAVE uptd_loop;
            END IF;
            
            -- Insert sample prices for main commodities
            INSERT INTO prices (commodity_id, price, market_id, uptd_user_id, status, approved_by, approved_at, created_at) VALUES
            (1, 15000 + (RAND() * 3000), market_id, user_id, 'approved', 1, current_date, current_date),
            (2, 12000 + (RAND() * 2000), market_id, user_id, 'approved', 1, current_date, current_date),
            (3, 25000 + (RAND() * 20000), market_id, user_id, 'approved', 1, current_date, current_date),
            (4, 30000 + (RAND() * 20000), market_id, user_id, 'approved', 1, current_date, current_date),
            (5, 18000 + (RAND() * 7000), market_id, user_id, 'approved', 1, current_date, current_date),
            (6, 22000 + (RAND() * 6000), market_id, user_id, 'approved', 1, current_date, current_date),
            (7, 16000 + (RAND() * 2000), market_id, user_id, 'approved', 1, current_date, current_date),
            (8, 13000 + (RAND() * 2000), market_id, user_id, 'approved', 1, current_date, current_date),
            (9, 120000 + (RAND() * 20000), market_id, user_id, 'approved', 1, current_date, current_date),
            (10, 28000 + (RAND() * 7000), market_id, user_id, 'approved', 1, current_date, current_date),
            (11, 24000 + (RAND() * 4000), market_id, user_id, 'approved', 1, current_date, current_date),
            (12, 20000 + (RAND() * 5000), market_id, user_id, 'approved', 1, current_date, current_date);
            
        END LOOP;
        CLOSE uptd_cursor;
        
        SET day_counter = day_counter + 1;
    END WHILE;
    
    -- Insert some pending data for demo
    INSERT INTO prices (commodity_id, price, market_id, uptd_user_id, status, created_at) VALUES
    (1, 16500, 1, 2, 'pending', NOW()),
    (3, 35000, 2, 3, 'pending', NOW()),
    (7, 17500, 3, 4, 'pending', NOW());
END //

DELIMITER ;

-- Execute the procedure
CALL GenerateSamplePrices();
DROP PROCEDURE GenerateSamplePrices;

-- Create useful views
CREATE VIEW view_approved_prices AS
SELECT 
    p.*,
    c.name AS commodity_name,
    c.unit,
    ps.nama_pasar AS market_name,
    u.full_name as uptd_name,
    admin.full_name as approved_by_name
FROM prices p
JOIN commodities c ON p.commodity_id = c.id
JOIN pasar ps ON p.market_id = ps.id_pasar
JOIN users u ON p.uptd_user_id = u.id
LEFT JOIN users admin ON p.approved_by = admin.id
WHERE p.status = 'approved';

CREATE VIEW view_latest_prices AS
SELECT 
    c.name AS commodity_name,
    c.unit,
    ps.nama_pasar AS market_name,
    p.price,
    p.uptd_user_id,
    p.created_at,
    ROW_NUMBER() OVER (PARTITION BY p.commodity_id, p.market_id ORDER BY p.created_at DESC) as rn
FROM prices p
JOIN commodities c ON p.commodity_id = c.id
JOIN pasar ps ON p.market_id = ps.id_pasar
WHERE p.status = 'approved';

CREATE VIEW view_price_trends AS
SELECT 
    c.name AS commodity_name,
    c.unit,
    DATE(p.created_at) as price_date,
    AVG(p.price) as avg_price,
    MIN(p.price) as min_price,
    MAX(p.price) as max_price,
    COUNT(*) as market_count
FROM prices p
JOIN commodities c ON p.commodity_id = c.id
WHERE p.status = 'approved'
GROUP BY c.name, DATE(p.created_at)
ORDER BY c.name, price_date DESC;

-- Show statistics
SELECT 'Database Statistics:' as info;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_prices FROM prices;
SELECT COUNT(*) as approved_prices FROM prices WHERE status = 'approved';
SELECT COUNT(*) as pending_prices FROM prices WHERE status = 'pending';

-- Default login credentials info
SELECT 'Default Login Credentials:' as info;
SELECT 'Admin: admin / password' as admin_login;
SELECT 'UPTD: uptd_tugu / password' as uptd_login;
SELECT 'Masyarakat: masyarakat1 / password' as public_login;