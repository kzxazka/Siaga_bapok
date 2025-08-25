# Fitur Tabel Harga Komoditas - Siaga Bapok

## Deskripsi
Fitur ini menampilkan tabel harga komoditas dengan perbandingan harga H-N (H-1, H-7, H-30) dan mini grafik trend harga menggunakan Chart.js.

## Fitur Utama

### 1. Tabel Responsif
- **Format Kolom**: Jenis Komoditas | Satuan | Harga (tanggal dipilih) | Harga (H-N) | % H-N | Grafik
- **Responsif**: Menggunakan Bootstrap 5 untuk tampilan yang responsif di berbagai device
- **Print-friendly**: Tombol print untuk mencetak tabel

### 2. Filter dan Kontrol
- **Input Tanggal**: Pilih tanggal untuk melihat harga (default: hari ini)
- **Dropdown Perbandingan**: Pilih periode perbandingan (H-1, H-7, H-30)
- **Update Real-time**: Tabel diupdate secara otomatis tanpa reload halaman

### 3. Perhitungan Otomatis
- **Persentase Perubahan**: `((harga_tgl - harga_HN) / harga_HN) * 100`
- **Kategori Perubahan**: 
  - Naik >5%: Merah (↑)
  - Naik 0-5%: Kuning (↑)
  - Tetap: Abu-abu (→)
  - Turun 0-5%: Biru (↓)
  - Turun >5%: Hijau (↓)

### 4. Mini Grafik (Sparkline)
- **Chart.js Integration**: Menggunakan Chart.js untuk mini grafik
- **Ukuran**: 200x60px per baris komoditas
- **Data**: Rentang dari H-N sampai tanggal yang dipilih
- **Warna Dinamis**: 
  - Merah: Trend naik
  - Hijau: Trend turun
  - Biru: Trend stabil

## Struktur Database

### Query Utama
```sql
SELECT 
    c.id, c.name AS commodity_name, c.unit,
    -- Harga pada tanggal yang dipilih
    (SELECT AVG(p1.price) FROM prices p1 
     WHERE p1.commodity_id = c.id 
     AND DATE(p1.created_at) = ? 
     AND p1.status = 'approved') as selected_date_price,
    
    -- Harga pada H-N
    (SELECT AVG(p2.price) FROM prices p2 
     WHERE p2.commodity_id = c.id 
     AND DATE(p2.created_at) = ? 
     AND p2.status = 'approved') as comparison_date_price,
    
    -- Data untuk grafik
    (SELECT GROUP_CONCAT(...) FROM prices p3 ...) as chart_data
FROM commodities c
WHERE c.id IN (SELECT DISTINCT commodity_id FROM prices WHERE status = 'approved')
```

## File yang Dimodifikasi

### 1. `src/models/Price.php`
- **Method Baru**: `getCommodityPriceComparison()`
- **Fungsi**: Mengambil data harga dengan perbandingan dan data grafik

### 2. `public/index.php`
- **Section Baru**: Tabel Harga Komoditas
- **JavaScript**: Fungsi untuk update tabel dan inisialisasi sparkline
- **CSS**: Styling untuk tabel dan print styles

### 3. `public/get_commodity_price_comparison.php` (Baru)
- **API Endpoint**: Untuk update data tabel secara dinamis
- **Response**: JSON dengan data harga dan metadata

## Cara Penggunaan

### 1. Akses Tabel
- Buka halaman utama (`index.php`)
- Scroll ke section "Tabel Harga Komoditas"

### 2. Filter Data
- Pilih tanggal dari input date picker
- Pilih periode perbandingan dari dropdown (H-1, H-7, H-30)
- Tabel akan update otomatis

### 3. Print Tabel
- Klik tombol printer (ikon printer)
- Browser akan membuka dialog print

## Fitur Responsif

### Mobile
- Tabel scroll horizontal
- Ukuran font dan padding disesuaikan
- Touch-friendly controls

### Desktop
- Tabel full-width
- Hover effects pada baris
- Tooltip pada grafik

## Print Styles

### CSS Print
```css
@media print {
    .btn, .form-select, .form-control { display: none !important; }
    .sparkline-container { height: 40px !important; width: 150px !important; }
    .commodity-price-table { page-break-inside: avoid; }
}
```

## Dependencies

### Frontend
- Bootstrap 5.3.2
- Chart.js
- Bootstrap Icons
- jQuery (untuk DataTables)

### Backend
- PHP 7.4+
- MySQL/MariaDB
- Custom Database class

## Keamanan

### Input Validation
- Sanitasi input tanggal
- Validasi periode perbandingan (1, 7, 30 hari)
- Escape HTML output

### Access Control
- Filter UPTD berdasarkan user role
- Hanya data approved yang ditampilkan

## Performance

### Optimization
- Query dengan JOIN yang efisien
- Lazy loading untuk grafik
- Chart cleanup untuk mencegah memory leak

### Caching
- Data di-cache di JavaScript global variable
- Update incremental tanpa reload halaman

## Troubleshooting

### Grafik Tidak Muncul
1. Pastikan Chart.js sudah dimuat
2. Cek console browser untuk error JavaScript
3. Pastikan data chart_data_formatted tersedia

### Data Tidak Update
1. Cek network tab untuk API calls
2. Pastikan endpoint `get_commodity_price_comparison.php` bisa diakses
3. Cek error log PHP

### Print Issues
1. Pastikan CSS print styles sudah dimuat
2. Cek browser print settings
3. Pastikan tidak ada JavaScript error sebelum print

## Future Enhancements

### Fitur yang Bisa Ditambahkan
1. Export ke Excel/PDF
2. Filter berdasarkan komoditas tertentu
3. Perbandingan antar pasar
4. Notifikasi perubahan harga signifikan
5. Dashboard analytics yang lebih detail