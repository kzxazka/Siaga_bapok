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
    market_assigned VARCHAR(100) NULL, -- Untuk UPTD, pasar yang ditugaskan
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Prices untuk menyimpan data harga
CREATE TABLE prices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commodity_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    market_name VARCHAR(100) NOT NULL,
    uptd_user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uptd_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_commodity (commodity_name),
    INDEX idx_market (market_name),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
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

CREATE TABLE komoditas (
    id_komoditas INT PRIMARY KEY AUTO_INCREMENT,
    nama_komoditas VARCHAR(100) NOT NULL UNIQUE,
    kategori VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO komoditas (nama_komoditas) VALUES
('Beras Premium'), ('Beras Medium'), ('Cabai Merah'), ('Cabai Rawit'),
('Bawang Merah'), ('Bawang Putih'), ('Minyak Goreng'), ('Gula Pasir'),
('Daging Sapi'), ('Daging Ayam'), ('Telur Ayam'), ('Ikan Tongkol');

-- Insert Default Admin User
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert Sample UPTD Users
INSERT INTO users (username, email, password, full_name, role, market_assigned) VALUES
('uptd_tugu', 'uptd.tugu@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Tugu', 'uptd', 'Pasar Tugu'),
('uptd_bambu', 'uptd.bambu@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Bambu Kuning', 'uptd', 'Pasar Bambu Kuning'),
('uptd_smep', 'uptd.smep@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Smep', 'uptd', 'Pasar Smep'),
('uptd_kangkung', 'uptd.kangkung@siagabapok.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UPTD Pasar Kangkung', 'uptd', 'Pasar Kangkung');

-- Insert Sample Masyarakat User
INSERT INTO users (username, email, password, full_name, role) VALUES
('masyarakat1', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Masyarakat User', 'masyarakat');

-- Generate Sample Price Data
DELIMITER //

CREATE PROCEDURE GenerateSamplePrices()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_id INT;
    DECLARE market VARCHAR(100);
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
            FETCH uptd_cursor INTO user_id, market;
            IF done THEN
                LEAVE uptd_loop;
            END IF;
            
            -- Insert sample prices for main commodities
            INSERT INTO prices (commodity_name, price, market_name, uptd_user_id, status, approved_by, approved_at, created_at) VALUES
            ('Beras Premium', 15000 + (RAND() * 3000), market, user_id, 'approved', 1, current_date, current_date),
            ('Beras Medium', 12000 + (RAND() * 2000), market, user_id, 'approved', 1, current_date, current_date),
            ('Cabai Merah', 25000 + (RAND() * 20000), market, user_id, 'approved', 1, current_date, current_date),
            ('Cabai Rawit', 30000 + (RAND() * 20000), market, user_id, 'approved', 1, current_date, current_date),
            ('Bawang Merah', 18000 + (RAND() * 7000), market, user_id, 'approved', 1, current_date, current_date),
            ('Bawang Putih', 22000 + (RAND() * 6000), market, user_id, 'approved', 1, current_date, current_date),
            ('Minyak Goreng', 16000 + (RAND() * 2000), market, user_id, 'approved', 1, current_date, current_date),
            ('Gula Pasir', 13000 + (RAND() * 2000), market, user_id, 'approved', 1, current_date, current_date),
            ('Daging Sapi', 120000 + (RAND() * 20000), market, user_id, 'approved', 1, current_date, current_date),
            ('Daging Ayam', 28000 + (RAND() * 7000), market, user_id, 'approved', 1, current_date, current_date),
            ('Telur Ayam', 24000 + (RAND() * 4000), market, user_id, 'approved', 1, current_date, current_date),
            ('Ikan Tongkol', 20000 + (RAND() * 5000), market, user_id, 'approved', 1, current_date, current_date);
            
        END LOOP;
        CLOSE uptd_cursor;
        
        SET day_counter = day_counter + 1;
    END WHILE;
    
    -- Insert some pending data for demo
    INSERT INTO prices (commodity_name, price, market_name, uptd_user_id, status, created_at) VALUES
    ('Beras Premium', 16500, 'Pasar Tugu', 2, 'pending', NOW()),
    ('Cabai Merah', 35000, 'Pasar Bambu Kuning', 3, 'pending', NOW()),
    ('Minyak Goreng', 17500, 'Pasar Smep', 4, 'pending', NOW());
END //

DELIMITER ;

-- Execute the procedure
CALL GenerateSamplePrices();
DROP PROCEDURE GenerateSamplePrices;

-- Create useful views
CREATE VIEW view_approved_prices AS
SELECT 
    p.*,
    u.full_name as uptd_name,
    admin.full_name as approved_by_name
FROM prices p
JOIN users u ON p.uptd_user_id = u.id
LEFT JOIN users admin ON p.approved_by = admin.id
WHERE p.status = 'approved';

CREATE VIEW view_latest_prices AS
SELECT 
    commodity_name,
    market_name,
    price,
    uptd_user_id,
    created_at,
    ROW_NUMBER() OVER (PARTITION BY commodity_name, market_name ORDER BY created_at DESC) as rn
FROM prices 
WHERE status = 'approved';

CREATE VIEW view_price_trends AS
SELECT 
    commodity_name,
    DATE(created_at) as price_date,
    AVG(price) as avg_price,
    MIN(price) as min_price,
    MAX(price) as max_price,
    COUNT(*) as market_count
FROM prices 
WHERE status = 'approved'
GROUP BY commodity_name, DATE(created_at)
ORDER BY commodity_name, price_date DESC;

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