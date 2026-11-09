<?php
// admin/index.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['koordinator']);

$sql = "SELECT s.*, u.ad_soyad, u.email, u.ogrenci_no 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.id DESC";
$ogrenciler = $db->query($sql)->fetchAll();

$bekleyen_ders = $db->query("SELECT COUNT(*) FROM student_courses WHERE onay_durumu = 'bekliyor'")->fetchColumn();
$bekleyen_belge = $db->query("SELECT COUNT(*) FROM student_documents WHERE onay_durumu = 'bekliyor'")->fetchColumn();
$okunmamis_mesaj = $db->query("SELECT COUNT(*) FROM messages WHERE alici_user_id = " . (int)$_SESSION['user_id'] . " AND okundu_mu = 0")->fetchColumn();

require_once '../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-primary text-white">
            <h6 class="small text-uppercase mb-1">Kayıtlı Öğrenci</h6>
            <h3 class="fw-bold mb-0"><?= count($ogrenciler) ?> Öğrenci</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-warning text-dark">
            <h6 class="small text-uppercase mb-1">Onay Bekleyen Ders</h6>
            <h3 class="fw-bold mb-0"><?= $bekleyen_ders ?> Kayıt</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-info text-dark">
            <h6 class="small text-uppercase mb-1">Onay Bekleyen Belge</h6>
            <h3 class="fw-bold mb-0"><?= $bekleyen_belge ?> Belge</h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-3 bg-dark text-white">
            <h6 class="small text-uppercase mb-1">Yeni Öğrenci Mesajı</h6>
            <h3 class="fw-bold mb-0"><?= $okunmamis_mesaj ?> Mesaj</h3>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Bölüm ÇAP & YDP Öğrenci Listesi</h4>
    <div>
        <a href="kurallar.php" class="btn btn-outline-dark btn-sm me-2">
            <i class="bi bi-sliders"></i> Yönerge & Katalog Ayarları
        </a>
        <a href="ogrenci_ekle.php" class="btn btn-success btn-sm fw-bold">
            <i class="bi bi-person-plus-fill"></i> Yeni / Mevcut Öğrenci Ekle
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Öğrenci No</th>
                    <th>Ad Soyad</th>
                    <th>Program</th>
                    <th>Anadal Bölümü</th>
                    <th>Anadal GANO</th>
                    <th>Tamamlanan / Kalan AKTS</th>
                    <th>Yönerge Durumu / Uyarı</th>
                    <th>Kayıt Durumu</th>
                    <th class="text-end">Yönetim</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ogrenciler as $o): 
                    $analiz = degerlendir_ogrenci_kurallari($db, $o);
                ?>
                    <tr>
                        <td class="fw-bold"><?= e($o['ogrenci_no']) ?></td>
                        <td><?= e($o['ad_soyad']) ?></td>
                        <td><span class="badge bg-<?= $o['program_turu'] === 'CAP' ? 'primary' : 'warning text-dark' ?>"><?= e($o['program_turu']) ?></span></td>
                        <td><?= e($o['anadal_bolum']) ?></td>
                        <td><b><?= e($o['anadal_gano']) ?></b> <br><span class="text-muted" style="font-size:11px;"><?= e($o['anadal_gano_kaynak']) ?></span></td>
                        <td>
                            <div class="fw-bold text-success">Geçilen: <?= $analiz['tamamlanan_akts'] ?> / <?= $analiz['hedef_akts'] ?> AKTS</div>
                            <small class="text-danger">Kalan: <?= $analiz['kalan_akts'] ?> AKTS</small> | <small class="text-muted">Ort: <?= $analiz['bolum_ortalamasi'] ?></small>
                        </td>
                        <td style="max-width: 260px;">
                            <?php if (empty($analiz['uyarilar'])): ?>
                                <span class="badge bg-light text-success border border-success">Yönergeye Uygun</span>
                            <?php else: foreach ($analiz['uyarilar'] as $u): ?>
                                <div class="badge bg-<?= $u['tip'] ?> text-wrap text-start mb-1 d-block"><?= e($u['mesaj']) ?></div>
                            <?php endforeach; endif; ?>
                        </td>
                        <td><span class="badge bg-dark"><?= strtoupper(e($o['durum'])) ?></span></td>
                        <td class="text-end">
                            <a href="ogrenci_detay.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-primary fw-bold">
                                Öğrenci Kartı & Notlar &rarr;
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
