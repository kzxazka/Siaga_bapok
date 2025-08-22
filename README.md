# Siaga Bapok - Sistem Informasi Harga Bahan Pokok (Versi MVC)

Sistem informasi multi-user untuk memantau harga bahan pokok di Kota Bandar Lampung dengan arsitektur MVC dan role-based access control.

## 🚀 Fitur Utama

### 👥 Sistem Multi-User
- **Admin**: Mengelola user, approve/reject data harga, oversight sistem
- **UPTD**: Input data harga komoditas dengan validasi ketat
- **Masyarakat**: Akses publik untuk melihat data dan grafik

### 📊 Visualisasi Data Interaktif
- Grafik interaktif dengan filter periode (1 hari, 7 hari, 30 hari)
- Top komoditas dengan kenaikan harga tertinggi
- Tabel harga terbaru per pasar
- Dashboard analytics real-time

### 🔒 Keamanan & Validasi
- Role-based authentication dengan session management
- Validasi input harga (maksimal 5 digit)
- Password hashing dengan bcrypt
- CSRF protection dan input sanitization

## 🏗️ Arsitektur MVC

```
/siagabapok/
├── /public/                    → Entry point aplikasi
│   ├── index.php              → Halaman publik dengan grafik interaktif
│   ├── login.php              → Halaman login multi-role
│   ├── logout.php             → Handler logout
│   ├── /admin/                → Dashboard admin
│   └── /uptd/                 → Dashboard UPTD
│       └── dashboard.php      → Form input harga + validasi
│
├── /src/
│   ├── /models/               → Data layer
│   │   ├── User.php          → Model user & authentication
│   │   └── Price.php         → Model harga komoditas
│   ├── /controllers/          → Business logic
│   │   └── AuthController.php → Controller authentication
│   └── /views/               → Presentation layer (akan dikembangkan)
│
├── /config/
│   └── database.php          → Konfigurasi database & helper functions
│
├── /assets/                   → Static assets
│   ├── /css/
│   ├── /js/
│   └── /img/
│
├── database.sql               → Schema database + data sample
└── README.md                  → Dokumentasi
```

## 🗄️ Database Schema

### Tabel `users`
```sql
- id (PK)
- username (unique)
- email (unique) 
- password (hashed)
- full_name
- role (admin|uptd|masyarakat)
- market_assigned (untuk UPTD)
- is_active
- created_at, updated_at
```

### Tabel `prices`
```sql
- id (PK)
- commodity_name
- price (decimal 10,2)
- market_name
- uptd_user_id (FK)
- status (pending|approved|rejected)
- approved_by (FK)
- approved_at
- notes
- created_at, updated_at
```

### Tabel `user_sessions`
```sql
- id (PK)
- user_id (FK)
- session_token
- expires_at
- created_at
```

## 🚀 Instalasi & Setup

### Prasyarat
- **Laragon** (atau XAMPP/WAMP)
- **PHP 7.4+** dengan PDO extension
- **MySQL 5.7+**
- **Web browser** modern

### Langkah Instalasi

1. **Setup Project**
   ```bash
   # Copy ke direktori Laragon
   # Lokasi: C:\laragon\www\siagabapok\
   ```

2. **Import Database**
   ```bash
   # Buka phpMyAdmin
   # Import file: database.sql
   # Atau via command line:
   mysql -u root -p < database.sql
   ```

3. **Konfigurasi Database**
   ```php
   // Edit config/database.php jika perlu
   $host = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'siagabapok_db';
   ```

4. **Jalankan Aplikasi**
   ```
   # Start Laragon
   # Akses: http://localhost/siagabapok/public/
   ```

## 👥 Login Credentials

| Role | Username | Password | Akses |
|------|----------|----------|--------|
| **Admin** | `admin` | `password` | Full system access |
| **UPTD** | `uptd_tugu` | `password` | Input data Pasar Tugu |
| **UPTD** | `uptd_bambu` | `password` | Input data Pasar Bambu Kuning |
| **Masyarakat** | `masyarakat1` | `password` | View-only access |

## 📱 Fitur per Role

### 🔧 Admin
- ✅ Dashboard dengan statistik lengkap
- ✅ Approve/reject data harga dari UPTD
- ✅ Manajemen user (create, edit, delete)
- ✅ Monitor seluruh sistem
- ✅ Export data dan laporan

### 📝 UPTD (Petugas Pasar)
- ✅ **Form input harga** dengan validasi ketat
- ✅ **Validasi harga**: hanya angka, maksimal 5 digit (1-99999)
- ✅ Riwayat input data dengan status
- ✅ Dashboard statistik personal
- ✅ Notifikasi approval/rejection

### 👁️ Masyarakat Umum
- ✅ **Grafik interaktif** dengan filter periode
- ✅ **Tren harga** 1 hari, 7 hari, 30 hari terakhir
- ✅ Top komoditas kenaikan harga
- ✅ Tabel harga terbaru per pasar
- ✅ Akses tanpa login (publik)

## 📊 Grafik & Visualisasi

### Chart.js Integration
```javascript
// Grafik dengan 3 periode filter
- 1 Hari Terakhir: Tren harga hari ini
- 7 Hari Terakhir: Tren mingguan  
- 30 Hari Terakhir: Tren bulanan

// Fitur interaktif:
- Hover untuk detail harga
- Legend toggle per komoditas
- Responsive design
- Smooth animations
```

### Data Visualization Features
- **Line Chart**: Pergerakan harga multi-komoditas
- **Statistics Cards**: Summary data real-time
- **Top Trending**: Komoditas kenaikan tertinggi
- **Latest Prices Table**: 20 data terbaru

## 🔒 Validasi & Keamanan

### Input Validation
```php
// Validasi harga UPTD
function validatePrice($price) {
    if (!is_numeric($price)) return false;
    $price = (float) $price;
    if ($price <= 0 || $price > 99999) return false;
    return true;
}
```

### Security Features
- **Password Hashing**: bcrypt dengan cost 10
- **Session Management**: Token-based dengan expiry
- **Input Sanitization**: htmlspecialchars + strip_tags
- **SQL Injection Prevention**: Prepared statements
- **CSRF Protection**: Session tokens
- **Role-based Access**: Middleware authentication

## 🎯 Workflow Sistem

### Data Input Flow
```
1. UPTD Login → Dashboard
2. Input Form → Validasi (max 5 digit)
3. Submit → Status: "Pending"
4. Admin Review → Approve/Reject
5. Status Update → Notifikasi UPTD
6. Approved Data → Tampil di Public
```

### Public Access Flow
```
1. Visitor → index.php (tanpa login)
2. View Grafik → Filter periode
3. Browse Data → Tabel harga terbaru
4. Optional Login → Role-based redirect
```

## 🔧 Customization

### Menambah Komoditas Baru
```php
// Edit: public/uptd/dashboard.php
<option value="Komoditas Baru">Komoditas Baru</option>
```

### Mengubah Validasi Harga
```php
// Edit: config/database.php
function validatePrice($price) {
    // Ubah batas maksimal di sini
    if ($price <= 0 || $price > 999999) return false; // 6 digit
    return true;
}
```

### Menambah Pasar Baru
```sql
-- Insert UPTD user baru
INSERT INTO users (username, email, password, full_name, role, market_assigned) 
VALUES ('uptd_baru', 'uptd.baru@siagabapok.com', '$2y$10$...', 'UPTD Pasar Baru', 'uptd', 'Pasar Baru');
```

## 📈 Performance & Monitoring

### Database Optimization
- **Indexes**: commodity_name, market_name, created_at, status
- **Views**: Pre-computed queries untuk performa
- **Connection Pooling**: PDO dengan persistent connection

### Monitoring Features
- Real-time statistics dashboard
- User activity tracking
- Data approval workflow
- Error logging & handling

## 🐛 Troubleshooting

### Database Connection Error
```bash
# Cek MySQL service
# Verify config/database.php credentials
# Ensure database 'siagabapok_db' exists
```

### Login Issues
```bash
# Clear browser cookies
# Check user is_active status
# Verify password hash in database
```

### Chart Not Loading
```bash
# Check internet connection (CDN Chart.js)
# Verify data exists in database
# Check browser console for JS errors
```

## 🚀 Production Deployment

### Security Checklist
- [ ] Change default passwords
- [ ] Enable HTTPS
- [ ] Configure proper file permissions
- [ ] Set up regular database backups
- [ ] Enable error logging
- [ ] Configure firewall rules

### Performance Optimization
- [ ] Enable gzip compression
- [ ] Set up database query caching
- [ ] Optimize images and assets
- [ ] Configure CDN for static files
- [ ] Set up monitoring alerts

## 📞 Support & Maintenance

### Regular Tasks
- **Daily**: Monitor pending approvals
- **Weekly**: Database backup
- **Monthly**: User access review
- **Quarterly**: Security audit

### Contact Information
- **Email**: admin@siagabapok.com
- **Phone**: (0721) 123456
- **Support**: Senin-Jumat 08:00-17:00 WIB

---

**Siaga Bapok v2.0** - Multi-User Price Monitoring System  
Dikembangkan dengan ❤️ untuk transparansi harga bahan pokok Kota Bandar Lampung