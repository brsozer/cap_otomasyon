<?php
// ogrenci/index.php
require_once '../config/db.php';
require_once '../includes/auth_check.php';
check_access(['ogrenci']);

$stmt = $db->prepare("SELECT s.*, u.ad_soyad, u.ogrenci_no FROM students s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) { die("Öğrenci kaydı bulunamadı."); }

$db->prepare("UPDATE messages SET okundu_mu = 1 WHERE student_id = ? AND alici_user_id = ?")->execute([$student['id'], $_SESSION['user_id']]);

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'ders_sec') {
        $course_id = (int)$_POST['course_id'];
        $yil       = trim($_POST['akademik_yil']);
        $donem     = $_POST['donem'];

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND course_id = ?");
        $cntStmt->execute([$student['id'], $course_id]);
        $deneme = (int)$cntStmt->fetchColumn() + 1;

        $ins = $db->prepare("INSERT INTO student_courses (student_id, course_id, akademik_yil, donem, deneme_sayisi, durum, onay_durumu, degisiklik_gerekcesi, islem_yapan_id) VALUES (?, ?, ?, ?, ?, 'alindi', 'bekliyor', 'Öğrenci dönemlik ders seçimi', ?)");
        $ins->execute([$student['id'], $course_id, $yil, $donem, $deneme, $_SESSION['user_id']]);

        log_islem($db, 'OGRENCI_DERS_SECIMI', 'student_courses', $db->lastInsertId(), "Öğrenci katalogdan ders seçip onaya gönderdi.");
        $msg = "Ders seçiminiz 'Onay Bekliyor' durumunda koordinatöre iletildi.";
    }

    if ($action === 'belge_yukle' && isset($_FILES['dosya'])) {
        $file = $_FILES['dosya'];
        $tur  = $_POST['belge_turu'];
        $yil  = trim($_POST['akademik_yil']);
        $donem= $_POST['donem'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowed_mimes)) {
                $error = "Güvenlik Engeli: Yalnızca gerçek PDF, JPG veya PNG dosyaları yüklenebilir.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = "Dosya boyutu 5 MB sınırını aşamaz.";
            } else {
                $vStmt = $db->prepare("SELECT COALESCE(MAX(versiyon), 0) + 1 FROM student_documents WHERE student_id = ? AND belge_turu = ? AND akademik_yil = ? AND donem = ?");
                $vStmt->execute([$student['id'], $tur, $yil, $donem]);
                $versiyon = (int)$vStmt->fetchColumn();

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safeName = "doc_" . $student['ogrenci_no'] . "_" . $tur . "_v" . $versiyon . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                if (!is_dir('../uploads')) { mkdir('../uploads', 0755, true); }
                $target = "../uploads/" . $safeName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $ins = $db->prepare("INSERT INTO student_documents (student_id, yukleyen_user_id, belge_turu, akademik_yil, donem, orijinal_dosya_adi, sunucu_dosya_yolu, mime_type, dosya_boyutu, versiyon, onay_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor')");
                    $ins->execute([$student['id'], $_SESSION['user_id'], $tur, $yil, $donem, $file['name'], $target, $mime, $file['size'], $versiyon]);

                    $db->prepare("UPDATE messages SET talep_durumu = 'yuklendi' WHERE student_id = ? AND belge_talebi_mi = 1 AND talep_durumu = 'bekliyor'")->execute([$student['id']]);

                    log_islem($db, 'BELGE_YUKLEME', 'student_documents', $db->lastInsertId(), "Öğrenci belge yükledi: $tur (v$versiyon)");
                    $msg = "Belgeniz (Sürüm v$versiyon) arşivlendi ve koordinatör onayına sunuldu.";
                }
            }
        }
    }

    if ($action === 'mesaj_gonder') {
        $konu  = trim($_POST['konu']);
        $mesaj = trim($_POST['mesaj']);
        $koord_id = $db->query("SELECT id FROM users WHERE role = 'koordinator' LIMIT 1")->fetchColumn();
        $ek_yol = null;

        if (isset($_FILES['ek_dosya']) && $_FILES['ek_dosya']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['ek_dosya'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png']) && $file['size'] <= 5*1024*1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $hedef = "../uploads/msg_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], $hedef)) { $ek_yol = $hedef; }
            }
        }

        $ins = $db->prepare("INSERT INTO messages (student_id, gonderen_user_id, alici_user_id, konu, mesaj, ekli_dosya_yolu) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$student['id'], $_SESSION['user_id'], $koord_id, $konu, $mesaj, $ek_yol]);
        log_islem($db, 'OGRENCI_MESAJ', 'messages', $db->lastInsertId(), "Öğrenci koordinatöre mesaj gönderdi: $konu");
        $msg = "Mesajınız koordinatöre iletildi.";
    }
}

$analiz = degerlendir_ogrenci_kurallari($db, $student);
$katalog = $db->query("SELECT * FROM course_catalog WHERE is_active = 1 ORDER BY ders_kodu")->fetchAll();

$dersler = $db->prepare("SELECT sc.*, cc.ders_kodu, cc.ders_adi, cc.akts FROM student_courses sc JOIN course_catalog cc ON sc.course_id = cc.id WHERE sc.student_id = ? ORDER BY sc.akademik_yil DESC, sc.id DESC");
$dersler->execute([$student['id']]);
$alinan_dersler = $dersler->fetchAll();

$belgeler = $db->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY id DESC");
$belgeler->execute([$student['id']]);
$belge_listesi = $belgeler->fetchAll();

$mesajlar = $db->prepare("SELECT m.*, u.ad_soyad FROM messages m JOIN users u ON m.gonderen_user_id = u.id WHERE m.student_id = ? ORDER BY m.id DESC");
$mesajlar->execute([$student['id']]);
$mesaj_listesi = $mesajlar->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= e($student['ad_soyad']) ?> (<?= e($student['ogrenci_no']) ?>)</h4>
        <span class="badge bg-primary"><?= e($student['program_turu']) ?> Öğrencisi</span>
        <span class="text-muted small ms-2">Anadal: <?= e($student['anadal_bolum']) ?> | Anadal GANO: <b><?= e($student['anadal_gano']) ?></b></span>
    </div>
    <div>
        <span class="badge bg-success fs-6">Tamamlanan: <?= $analiz['tamamlanan_akts'] ?> / <?= $analiz['hedef_akts'] ?> AKTS</span>
        <span class="badge bg-danger fs-6 ms-1">Kalan: <?= $analiz['kalan_akts'] ?> AKTS</span>
        <span class="badge bg-dark fs-6 ms-1">ÇAP/YDP Ort: <?= $analiz['bolum_ortalamasi'] ?></span>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 small"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>

<?php foreach ($analiz['uyarilar'] as $u): ?>
    <div class="alert alert-<?= $u['tip'] ?> py-2 small fw-bold"><?= e($u['mesaj']) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-2">
                <h6 class="mb-0 small fw-bold">1. Katalogdan Dönem Dersi Seç</h6>
            </div>
            <div class="card-body small">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="ders_sec">
                    <div class="mb-2">
                        <label class="fw-bold">Ders Kataloğu (Kod veya Ad ile) *</label>
                        <select name="course_id" class="form-select form-select-sm" required>
                            <?php foreach ($katalog as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= e($k['ders_kodu']) ?> - <?= e($k['ders_adi']) ?> (<?= $k['akts'] ?> AKTS)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1">Katalogda olmayan ders için sağ alttaki mesaj kutusundan koordinatöre talep gönderiniz.</small>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input type="text" name="akademik_yil" class="form-control form-control-sm" value="2026-2027" required>
                        </div>
                        <div class="col-6">
                            <select name="donem" class="form-select form-select-sm">
                                <option value="Guz">Güz</option>
                                <option value="Bahar">Bahar</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Dersi Onaya Gönder</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white py-2">
                <h6 class="mb-0 small fw-bold">2. Resmi Form & Transkript Yükle</h6>
            </div>
            <div class="card-body small">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="belge_yukle">
                    <div class="mb-2">
                        <label class="fw-bold">Belge Türü *</label>
                        <select name="belge_turu" class="form-select form-select-sm">
                            <option value="cap_ydp_transkript">Dönem Sonu ÇAP/YDP Transkripti (Zorunlu)</option>
                            <option value="imzali_kabul_protokolu">İmzalı Kabul ve Ders Protokol Formu</option>
                            <option value="imzali_ders_kayit">İmzalı Dönem Ders Kayıt Formu</option>
                            <option value="muafiyet_formu">Özel Şartlı Ders Muafiyet Formu</option>
                            <option value="anadal_transkript">Anadal Transkripti (İsteğe Bağlı)</option>
                            <option value="ek_belge">Koordinatörce İstenen Ek Belge</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" name="akademik_yil" class="form-control form-control-sm" value="2026-2027" required></div>
                        <div class="col-6">
                            <select name="donem" class="form-select form-select-sm">
                                <option value="Guz">Güz</option>
                                <option value="Bahar">Bahar</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="file" name="dosya" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Yüklenen belgeler silinemez; yenisi yüklendiğinde yeni versiyon olarak saklanır.</small>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm w-100 fw-bold">Belgeyi Yükle</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">Bilgisayar Mühendisliği ÇAP / YDP Ders ve Not Durumu</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Ders Kodu & Adı</th>
                            <th>Dönem</th>
                            <th>AKTS</th>
                            <th>Vize / Final</th>
                            <th>Harf (Koordinatör Girer)</th>
                            <th>Durum</th>
                            <th>Onay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alinan_dersler as $d): ?>
                            <tr>
                                <td>
                                    <b><?= e($d['ders_kodu']) ?></b> - <?= e($d['ders_adi']) ?>
                                    <span class="badge bg-light text-dark border"><?= $d['deneme_sayisi'] ?>. Deneme</span>
                                </td>
                                <td><?= e($d['akademik_yil']) ?> <?= e($d['donem']) ?></td>
                                <td><?= $d['akts'] ?></td>
                                <td><?= $d['vize_notu'] ?? '—' ?> / <?= $d['final_notu'] ?? '—' ?></td>
                                <td><span class="badge bg-dark"><?= e($d['harf_notu'] ?: '—') ?></span></td>
                                <td><span class="badge bg-<?= $d['durum']==='gecti'?'success':($d['durum']==='kaldi'?'danger':'secondary') ?>"><?= strtoupper(e($d['durum'])) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= $d['onay_durumu']==='onaylandi'?'success':($d['onay_durumu']==='reddedildi'?'danger':'warning text-dark') ?>">
                                        <?= $d['onay_durumu']==='onaylandi' ? 'ONAYLI (Kilitli)' : strtoupper(e($d['onay_durumu'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">Yüklediğim Belgeler ve Transkript Arşivi</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Belge Türü</th><th>Dönem & Versiyon</th><th>Durum</th><th>Koordinatör Notu</th><th>Dosya</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($belge_listesi)): ?>
                            <tr><td colspan="5" class="text-center py-2 text-muted">Henüz yüklediğiniz belge bulunmuyor.</td></tr>
                        <?php else: foreach ($belge_listesi as $b): ?>
                            <tr>
                                <td><?= strtoupper(str_replace('_', ' ', e($b['belge_turu']))) ?></td>
                                <td><?= e($b['akademik_yil']) ?> <?= e($b['donem']) ?> (v<?= $b['versiyon'] ?>)</td>
                                <td><span class="badge bg-<?= $b['onay_durumu']==='onaylandi'?'success':($b['onay_durumu']==='reddedildi'?'danger':'warning text-dark') ?>"><?= strtoupper(e($b['onay_durumu'])) ?></span></td>
                                <td><?= e($b['red_veya_talep_notu'] ?: '—') ?></td>
                                <td><a href="<?= e($b['sunucu_dosya_yolu']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0">Gör</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold">Koordinatör İle Yazışma & Katalog Dışı Ders Talebi</h6>
            </div>
            <div class="card-body small">
                <form method="POST" enctype="multipart/form-data" class="mb-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="mesaj_gonder">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <input type="text" name="konu" class="form-control form-control-sm" placeholder="Konu (Örn: Katalogda Olmayan Ders Talebi)" required>
                        </div>
                        <div class="col-md-4">
                            <input type="file" name="ek_dosya" class="form-control form-control-sm" accept=".pdf,.jpg,.png">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm w-100 fw-bold" type="submit">Gönder</button>
                        </div>
                    </div>
                    <textarea name="mesaj" class="form-control form-control-sm" rows="2" placeholder="Mesajınızı yazın..." required></textarea>
                </form>

                <?php foreach ($mesaj_listesi as $m): ?>
                    <div class="p-2 mb-2 rounded bg-light border">
                        <div class="d-flex justify-content-between">
                            <strong><?= e($m['ad_soyad']) ?>: <?= e($m['konu']) ?></strong>
                            <span class="text-muted"><?= e($m['gonderim_tarihi']) ?></span>
                        </div>
                        <div><?= e($m['mesaj']) ?></div>
                        <?php if ($m['ekli_dosya_yolu']): ?>
                            <a href="<?= e($m['ekli_dosya_yolu']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-1"><i class="bi bi-paperclip"></i> Ekli Dosya</a>
                        <?php endif; ?>
                        <?php if ($m['belge_talebi_mi']): ?>
                            <span class="badge bg-warning text-dark mt-1">Belge Talebi: <?= strtoupper(e($m['talep_durumu'])) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
