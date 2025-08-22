# SIAGA BAPOK - Sistem Informasi Harga Bahan Pokok

[![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-green.svg)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-blue.svg)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**SIAGA BAPOK** adalah sistem informasi untuk mencatat dan membandingkan harga bahan pokok di berbagai pasar. Sistem ini dirancang dengan arsitektur multi-user role untuk memudahkan pengelolaan data harga komoditas secara real-time.

## 🚀 Fitur Utama

### 👥 Multi-User Role System
- **Admin**: Manajemen user, approval data harga, monitoring sistem
- **UPTD**: Input data harga, monitoring pasar yang ditugaskan
- **Masyarakat**: Akses informasi harga publik, perbandingan antar pasar

### 📊 Dashboard & Analytics
- Dashboard real-time dengan grafik tren harga
- Perbandingan harga antar pasar dan periode
- Analisis perubahan harga dengan persentase
- Sparkline charts untuk visualisasi tren
- AI-powered insights untuk analisis harga

### 🏪 Manajemen Data
- Input dan approval data harga komoditas
- Manajemen pasar dan komoditas
- Riwayat perubahan harga
- Export data untuk analisis lanjutan

### 📱 Responsive Design
- Fully responsive menggunakan Bootstrap 5
- Optimized untuk desktop, tablet, dan mobile
- Modern UI/UX dengan gradient design
- Sidebar navigation yang user-friendly

## 🗄️ Database Schema

### Tabel Utama

#### `users`
```sql
- id (PK)
- username, email, password
- full_name, role (admin/uptd/masyarakat)
- market_assigned (FK ke pasar.id_pasar)
- is_active, created_at, updated_at
```

#### `pasar`
```sql
- id_pasar (PK)
- nama_pasar, alamat, keterangan
- created_at, updated_at
```

#### `commodities`
```sql
- id (PK)
- name, unit, kategori
- created_at
```

#### `prices`
```sql
- id (PK)
- commodity_id (FK ke commodities.id)
- price, market_id (FK ke pasar.id_pasar)
- uptd_user_id (FK ke users.id)
- status (pending/approved/rejected)
- approved_by, approved_at, notes
- created_at, updated_at
```

#### `user_sessions`
```sql
- id (PK)
- user_id (FK ke users.id)
- session_token, expires_at
- created_at
```

### Relasi Database
- **prices.commodity_id** → **commodities.id**
- **prices.market_id** → **pasar.id_pasar**
- **prices.uptd_user_id** → **users.id**
- **users.market_assigned** → **pasar.id_pasar**

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP 8.0+, PDO, OOP
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.3
- **Charts**: Chart.js
- **Icons**: Bootstrap Icons
- **Security**: Password hashing, Session management

## 📋 Persyaratan Sistem

### Server Requirements
- PHP 8.0 atau lebih tinggi
- MySQL 8.0 atau lebih tinggi
- Apache/Nginx web server
- Extensions: PDO, PDO_MySQL, JSON

### Development Environment
- XAMPP 8.0+ atau Laragon
- Git untuk version control
- Code editor (VS Code, PHPStorm, dll)

## 🚀 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/username/siagabapok.git
cd siagabapok
```

### 2. Setup Database
```bash
# Import database schema
mysql -u root -p < database.sql

# Atau gunakan phpMyAdmin untuk import file database.sql
```

### 3. Konfigurasi Database
Edit file `config/database.php`:
```php
private $host = 'localhost';
private $username = 'root';
private $password = '';
private $database = 'siagabapok_db';
```

### 4. Setup Web Server
- Copy semua file ke folder web server (htdocs untuk XAMPP)
- Pastikan folder memiliki permission yang tepat
- Akses melalui browser: `http://localhost/siagabapok`

### 5. Generate Sample Data (Optional)
```bash
# Akses untuk generate dummy data
http://localhost/siagabapok/public/generate_dummy_data.php
```

## 🔐 Default Login Credentials

### Admin
- **Username**: `admin`
- **Password**: `password`
- **Role**: Administrator

### UPTD Users
- **Username**: `uptd_tugu` / **Password**: `password`
- **Username**: `uptd_bambu` / **Password**: `password`
- **Username**: `uptd_smep` / **Password**: `password`
- **Username**: `uptd_kangkung` / **Password**: `password`

### Masyarakat
- **Username**: `masyarakat1` / **Password**: `password`

## 📁 Struktur Project

```
siagabapok/
├── config/
│   ├── database.php          # Database configuration
│   └── ai_config.php         # AI insights configuration
├── src/
│   ├── models/
│   │   ├── User.php          # User model
│   │   └── Price.php         # Price model
│   └── controllers/
│       ├── AuthController.php # Authentication controller
│       └── ApiAuthController.php # API authentication
├── public/
│   ├── admin/                # Admin panel
│   ├── uptd/                 # UPTD panel
│   ├── api/                  # REST API endpoints
│   ├── assets/               # CSS, JS, images
│   └── index.php             # Main dashboard
├── database/
│   ├── database.sql          # Database schema
│   └── migrations/           # Database migrations
└── README.md                 # This file
```

## 🔧 Konfigurasi

### Database Connection
File `config/database.php` berisi konfigurasi database utama:
- Host, username, password
- Database name: `siagabapok_db`
- PDO options untuk security dan performance

### AI Configuration
File `config/ai_config.php` berisi konfigurasi untuk fitur AI insights:
- API keys (jika diperlukan)
- Model parameters
- Analysis thresholds

## 📱 Responsive Features

### Bootstrap 5 Grid System
- **Extra Small** (<576px): Mobile phones
- **Small** (≥576px): Large phones
- **Medium** (≥768px): Tablets
- **Large** (≥992px): Desktops
- **Extra Large** (≥1200px): Large desktops

### Mobile-First Design
- Sidebar collapse pada mobile
- Table responsive dengan horizontal scroll
- Touch-friendly buttons dan forms
- Optimized typography untuk readability

## 🔒 Security Features

- **Password Hashing**: Menggunakan `password_hash()` dan `password_verify()`
- **Session Management**: Secure session handling dengan token
- **SQL Injection Prevention**: Prepared statements dengan PDO
- **XSS Protection**: Input sanitization dan output escaping
- **CSRF Protection**: Session-based token validation

## 📊 API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/verify` - Verify token

### Prices
- `GET /api/prices` - Get price data
- `POST /api/prices` - Create new price
- `PUT /api/prices/{id}` - Update price
- `DELETE /api/prices/{id}` - Delete price

### Dashboard
- `GET /api/dashboard/stats` - Get dashboard statistics
- `GET /api/dashboard/trends` - Get price trends
- `GET /api/dashboard/insights` - Get AI insights

## 🧪 Testing

### Manual Testing
1. **Login Test**: Test semua role user
2. **CRUD Operations**: Test create, read, update, delete
3. **Responsive Test**: Test pada berbagai device sizes
4. **Security Test**: Test SQL injection, XSS prevention

### Automated Testing
```bash
# Run PHP unit tests (if available)
php vendor/bin/phpunit

# Run database tests
php test_database.php
```

## 🚀 Deployment

### Production Server
1. **Upload Files**: Upload semua file ke server
2. **Database Setup**: Import database schema
3. **Configuration**: Update database credentials
4. **Permissions**: Set proper file permissions
5. **SSL**: Enable HTTPS for security

### Environment Variables
```bash
# Production environment
DB_HOST=production_host
DB_NAME=production_db
DB_USER=production_user
DB_PASS=production_password
```

## 📈 Performance Optimization

### Database Optimization
- Proper indexing pada foreign keys
- Query optimization dengan JOINs
- Connection pooling
- Query caching

### Frontend Optimization
- Minified CSS/JS
- Image optimization
- Lazy loading untuk charts
- CDN untuk external libraries

## 🔮 Roadmap & Future Improvements

### Short Term (1-3 months)
- [ ] Chart optimization untuk data besar
- [ ] Real-time notifications
- [ ] Mobile app development
- [ ] Advanced filtering dan search

### Medium Term (3-6 months)
- [ ] Machine learning price predictions
- [ ] Integration dengan external APIs
- [ ] Advanced reporting system
- [ ] Multi-language support

### Long Term (6+ months)
- [ ] Blockchain integration
- [ ] IoT sensor integration
- [ ] Advanced analytics dashboard
- [ ] API marketplace

## 🐛 Troubleshooting

### Common Issues

#### Database Connection Error
```bash
# Check database service
sudo systemctl status mysql

# Check database credentials
mysql -u root -p
```

#### Permission Denied
```bash
# Set proper permissions
chmod 755 -R /var/www/html/siagabapok
chown www-data:www-data -R /var/www/html/siagabapok
```

#### Session Issues
```bash
# Check PHP session configuration
php -i | grep session

# Clear browser cookies and cache
```

### Debug Mode
Enable debug mode in `config/database.php`:
```php
// Add this line for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

### Coding Standards
- PSR-12 coding standards
- Proper documentation
- Unit tests for new features
- Follow existing code structure

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Authors

- **Main Developer** - [Your Name](https://github.com/username)
- **Contributors** - See [Contributors](https://github.com/username/siagabapok/graphs/contributors)

## 🙏 Acknowledgments

- Bootstrap team untuk framework CSS
- Chart.js team untuk charting library
- PHP community untuk best practices
- All contributors dan testers

## 📞 Support

- **Email**: support@siagabapok.com
- **Documentation**: [Wiki](https://github.com/username/siagabapok/wiki)
- **Issues**: [GitHub Issues](https://github.com/username/siagabapok/issues)
- **Discussions**: [GitHub Discussions](https://github.com/username/siagabapok/discussions)

---

**SIAGA BAPOK** - Empowering communities with real-time commodity price information.

*Made with ❤️ for better price transparency*