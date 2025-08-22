<?php
session_start();
require_once __DIR__ . '/../../src/controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

$auth = new AuthController();
$user = $auth->requireRole('uptd');

$db = new Database();
$pdo = $db->getConnection();

// Ambil list komoditas dan pasar
$komoditas = $pdo->query("SELECT id, name, unit FROM commodities ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$pasar = $pdo->query("SELECT id_pasar, nama_pasar FROM pasar ORDER BY nama_pasar ASC")->fetchAll(PDO::FETCH_ASSOC);

// Proses simpan harga
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commodity_id = (int) $_POST['commodity_id'];
    $price = (float) $_POST['price'];
    $market_id = (int) $_POST['market_id'];
    $notes = trim($_POST['notes']) ?? null;

    $stmt = $pdo->prepare("INSERT INTO prices (commodity_id, price, market_id, uptd_user_id, notes, status) 
                           VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$commodity_id, $price, $market_id, $user['id'], $notes]);

    $_SESSION['success'] = "Data harga berhasil dikirim, menunggu persetujuan admin.";
    header("Location: form_uploadHarga.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Harga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <h3 class="mb-4">Input Harga Komoditas</h3>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">

                <div class="mb-3">
                    <label for="commodity_id" class="form-label">Komoditas</label>
                    <select name="commodity_id" id="commodity_id" class="form-select" required>
                        <option value="">-- Pilih Komoditas --</option>
                        <?php foreach ($komoditas as $k): ?>
                            <option value="<?= $k['id'] ?>">
                                <?= htmlspecialchars($k['name']) ?> (<?= htmlspecialchars($k['unit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="price" class="form-label">Harga (Rp)</label>
                    <input type="number" class="form-control" name="price" id="price" required min="1">
                </div>

                <div class="mb-3">
                    <label for="market_id" class="form-label">Pasar</label>
                    <select name="market_id" id="market_id" class="form-select" required>
                        <option value="">-- Pilih Pasar --</option>
                        <?php foreach ($pasar as $p): ?>
                            <option value="<?= $p['id_pasar'] ?>">
                                <?= htmlspecialchars($p['nama_pasar']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Catatan (opsional)</label>
                    <textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
