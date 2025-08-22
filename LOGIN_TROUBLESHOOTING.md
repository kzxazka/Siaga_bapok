# LOGIN TROUBLESHOOTING GUIDE - SIAGA BAPOK

## 🔧 AuthController.php - SUDAH DIPERBAIKI

File `src/controllers/AuthController.php` telah dibuat ulang dengan fitur:

### ✅ **Fitur Utama:**
- **Login dengan validasi lengkap**: Username, password, dan status aktif
- **Role-based redirect**: Admin → admin/dashboard.php, UPTD → uptd/dashboard.php
- **Session management**: Session + cookie untuk persistent login
- **Logout bersih**: Hapus session dan cookie
- **Error handling**: Pesan error yang jelas

### ✅ **Validasi Login:**
1. **Username kosong/password kosong** → "Username dan password harus diisi."
2. **Username tidak ditemukan** → "Username atau password salah."
3. **User tidak aktif (is_active = 0)** → "Akun Anda belum disetujui oleh Admin."
4. **Password salah** → "Username atau password salah."
5. **Login berhasil** → Redirect sesuai role

### ✅ **Redirect Berdasarkan Role:**
- **Admin** → `admin/dashboard.php`
- **UPTD** → `uptd/dashboard.php`
- **Masyarakat** → `index.php`

## 🧪 CARA TESTING

### 1. **Test Database & Authentication**
Akses: `http://localhost/siagabapok/debug_auth.php`
- Cek koneksi database
- Cek tabel users
- Cek password hash
- Cek AuthController

### 2. **Test Login Interaktif**
Akses: `http://localhost/siagabapok/test_auth.php`
- Test login dengan berbagai akun
- Lihat session data
- Test logout

### 3. **Login Normal**
Akses: `http://localhost/siagabapok/public/login.php`

## 🔑 DEFAULT LOGIN CREDENTIALS

| Role | Username | Password | Full Name |
|------|----------|----------|-----------|
| Admin | `admin` | `password` | Administrator |
| UPTD | `uptd_tugu` | `password` | UPTD Pasar Tugu |
| UPTD | `uptd_bambu` | `password` | UPTD Pasar Bambu Kuning |
| UPTD | `uptd_smep` | `password` | UPTD Pasar Smep |
| UPTD | `uptd_kangkung` | `password` | UPTD Pasar Kangkung |
| Masyarakat | `masyarakat1` | `password` | Masyarakat User |

## 🔍 TROUBLESHOOTING STEPS

### 1. **Database Issues**
```sql
-- Cek apakah database exists
SHOW DATABASES LIKE 'siagabapok_db';

-- Cek tabel users
USE siagabapok_db;
SHOW TABLES LIKE 'users';

-- Cek data users
SELECT username, full_name, role, is_active FROM users;

-- Cek password hash admin
SELECT username, password FROM users WHERE username = 'admin';
```

### 2. **Import Database**
```bash
# Import database.sql
mysql -u root -p siagabapok_db < database.sql
```

### 3. **File Permissions**
```bash
# Set proper permissions
chmod 755 public/
chmod 644 public/*.php
chmod 755 src/
chmod 644 src/controllers/*.php
chmod 644 src/models/*.php
```

### 4. **PHP Session Configuration**
Pastikan di `php.ini`:
```ini
session.save_path = "/tmp"
session.use_cookies = 1
session.cookie_httponly = 1
```

### 5. **Check Error Logs**
- Apache error log: `/var/log/apache2/error.log`
- PHP error log: Check `error_log` setting in php.ini

## 🚨 COMMON ISSUES & SOLUTIONS

### Issue: "Database connection failed"
**Solution:** 
- Pastikan MySQL running
- Cek kredensial database di `config/database.php`
- Import `database.sql`

### Issue: "Username atau password salah" (padahal benar)
**Solution:**
- Cek password hash di database
- Pastikan `is_active = 1`
- Test dengan `debug_auth.php`

### Issue: "Akun Anda belum disetujui oleh Admin"
**Solution:**
```sql
UPDATE users SET is_active = 1 WHERE username = 'admin';
```

### Issue: Login berhasil tapi tidak redirect
**Solution:**
- Cek apakah folder `public/admin/` dan `public/uptd/` ada
- Cek file `dashboard.php` di folder tersebut
- Cek PHP output buffering

### Issue: Session tidak tersimpan
**Solution:**
- Pastikan `session_start()` dipanggil
- Cek session permissions
- Restart web server

## 📁 STRUKTUR FILE YANG DIPERLUKAN

```
siagabapok/
├── config/database.php ✅
├── src/
│   ├── controllers/AuthController.php ✅
│   └── models/User.php ✅
├── public/
│   ├── login.php ✅
│   ├── index.php ✅
│   ├── admin/
│   │   └── dashboard.php ✅
│   └── uptd/
│       └── dashboard.php ✅
├── debug_auth.php ✅
├── test_auth.php ✅
└── database.sql ✅
```

## 🎯 NEXT STEPS

Jika masih ada masalah:

1. **Jalankan debug_auth.php** - Lihat mana yang error
2. **Jalankan test_auth.php** - Test login interaktif
3. **Cek database** - Pastikan data user ada dan aktif
4. **Cek file permissions** - Pastikan web server bisa akses
5. **Cek error logs** - Lihat pesan error detail

---

**AuthController.php sudah diperbaiki dengan:**
- ✅ Validasi input lengkap
- ✅ Error handling yang tepat
- ✅ Session management yang aman
- ✅ Role-based redirect
- ✅ Password verification yang benar
- ✅ Status aktif checking