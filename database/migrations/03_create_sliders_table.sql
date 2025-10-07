-- Create sliders table
CREATE TABLE IF NOT EXISTS `sliders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some default data
INSERT INTO `sliders` (`image_path`, `title`, `description`, `is_active`) VALUES
('/assets/img/slider/slider1.jpg', 'Selamat Datang', 'Sistem Informasi Harga Bahan Pokok', 1),
('/assets/img/slider/slider2.jpg', 'Harga Terkini', 'Update harga bahan pokok setiap hari', 1),
('/assets/img/slider/slider3.jpg', 'Info Pasar', 'Informasi pasar terdekat', 1);
