CREATE DATABASE IF NOT EXISTS cap_yandal_db CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE cap_yandal_db;

-- 1. KULLANICILAR TABLOSU
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ogrenci_no VARCHAR(20) UNIQUE NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(100) NOT NULL,
    role ENUM('koordinator', 'ogrenci', 'bolum_baskani', 'ogrenci_isleri') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 2. ÖĞRENCİ AKADEMİK KARTI
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    program_turu ENUM('CAP', 'YDP') NOT NULL,
    anadal_bolum VARCHAR(100) NOT NULL,
    kabul_donemi VARCHAR(20) NOT NULL,
    kayitli_donem_sayisi INT DEFAULT 1,
    anadal_gano DECIMAL(3,2) DEFAULT 0.00,
    anadal_gano_kaynak VARCHAR(100) DEFAULT 'Resmi Transkript',
    gano_dusme_sayisi INT DEFAULT 0,
    cap_ort_dusme_sayisi INT DEFAULT 0,
    ust_uste_ders_almama INT DEFAULT 0,
    koordinator_ozel_notu TEXT NULL,
    durum ENUM('aktif', 'mezun', 'sertifika_aldi', 'kaydi_silindi') DEFAULT 'aktif',
    durum_gerekcesi TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 3. BİLGİSAYAR MÜHENDİSLİĞİ DERS KATALOĞU
CREATE TABLE IF NOT EXISTS course_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ders_kodu VARCHAR(20) NOT NULL,
    ders_adi VARCHAR(150) NOT NULL,
    akts INT NOT NULL,
    kredi INT NOT NULL,
    muafiyet_aciklama TEXT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 4. ÖĞRENCİ DERS VE NOT TAKİBİ (Notları Yalnızca Koordinatör Düzenler)
CREATE TABLE IF NOT EXISTS student_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    akademik_yil VARCHAR(15) NOT NULL,
    donem ENUM('Guz', 'Bahar', 'Yaz') NOT NULL,
    deneme_sayisi INT DEFAULT 1,
    vize_notu DECIMAL(5,2) NULL,
    final_notu DECIMAL(5,2) NULL,
    sayisal_ortalama DECIMAL(5,2) NULL,
    harf_notu VARCHAR(5) NULL,
    durum ENUM('planlandi', 'alindi', 'gecti', 'kaldi', 'muaf', 'cikarildi') DEFAULT 'planlandi',
    onay_durumu ENUM('bekliyor', 'onaylandi', 'reddedildi', 'iptal') DEFAULT 'bekliyor',
    degisiklik_gerekcesi TEXT NULL,
    islem_yapan_id INT NULL,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES course_catalog(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 5. BELGE VE TRANSKRİPT ARŞİVİ (Silinemez, Versiyonlu)
CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    yukleyen_user_id INT NOT NULL,
    belge_turu ENUM('imzali_kabul_protokolu', 'imzali_ders_kayit', 'cap_ydp_transkript', 'anadal_transkript', 'muafiyet_formu', 'yonetim_kurulu_karari', 'ek_belge') NOT NULL,
    akademik_yil VARCHAR(15) NOT NULL,
    donem ENUM('Guz', 'Bahar', 'Yaz') NOT NULL,
    orijinal_dosya_adi VARCHAR(255) NOT NULL,
    sunucu_dosya_yolu VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    dosya_boyutu INT NOT NULL,
    versiyon INT DEFAULT 1,
    onay_durumu ENUM('bekliyor', 'onaylandi', 'reddedildi') DEFAULT 'bekliyor',
    red_veya_talep_notu TEXT NULL,
    yukleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 6. SİSTEM İÇİ KALICI MESAJLAŞMA VE BELGE TALEPLERİ
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    gonderen_user_id INT NOT NULL,
    alici_user_id INT NOT NULL,
    konu VARCHAR(150) NOT NULL,
    mesaj TEXT NOT NULL,
    ekli_dosya_yolu VARCHAR(255) NULL,
    belge_talebi_mi TINYINT(1) DEFAULT 0,
    talep_durumu ENUM('yok', 'bekliyor', 'yuklendi', 'onaylandi', 'reddedildi') DEFAULT 'yok',
    okundu_mu TINYINT(1) DEFAULT 0,
    gonderim_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 7. DİNAMİK YÖNERGE KURALLARI (Versiyonlu, Eski Sürümler Korunur)
CREATE TABLE IF NOT EXISTS regulation_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_turu ENUM('CAP', 'YDP') NOT NULL,
    kural_kodu VARCHAR(50) NOT NULL,
    kural_adi VARCHAR(150) NOT NULL,
    deger DECIMAL(6,2) NOT NULL,
    birim VARCHAR(20) NOT NULL,
    versiyon INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    guncelleyen_id INT NULL,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- 8. DEĞİŞTİRİLEMEZ DENETİM KAYITLARI (AUDIT LOG)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    islem_turu VARCHAR(50) NOT NULL,
    tablo_adi VARCHAR(50) NOT NULL,
    kayit_id INT NOT NULL,
    eski_deger TEXT NULL,
    yeni_deger TEXT NULL,
    aciklama TEXT NOT NULL,
    ip_adresi VARCHAR(45) NOT NULL,
    islem_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- TEST KULLANICILARI (Tüm Şifreler: 123456)
INSERT INTO users (ogrenci_no, email, password_hash, ad_soyad, role) VALUES
(NULL, 'koordinator@selcuk.edu.tr', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFvA/fF6K47v8y5Z0uD9sC9nE8yQ0g0m', 'Doç. Dr. İlker Ali Özkan (Koordinatör)', 'koordinator'),
(NULL, 'bolumbaskani@selcuk.edu.tr', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFvA/fF6K47v8y5Z0uD9sC9nE8yQ0g0m', 'Prof. Dr. Bölüm Başkanı', 'bolum_baskani'),
(NULL, 'ogrenciisleri@selcuk.edu.tr', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFvA/fF6K47v8y5Z0uD9sC9nE8yQ0g0m', 'Teknoloji Fak. Öğrenci İşleri', 'ogrenci_isleri'),
('203311041', '203311041@ogr.selcuk.edu.tr', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFvA/fF6K47v8y5Z0uD9sC9nE8yQ0g0m', 'Sentetik Test Öğrencisi (ÇAP)', 'ogrenci'),
('223301099', '223301099@ogr.selcuk.edu.tr', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFvA/fF6K47v8y5Z0uD9sC9nE8yQ0g0m', 'Ayşe Yılmaz (Sentetik YDP Öğrencisi)', 'ogrenci');

-- SENTETİK ÖĞRENCİ KARTLARI
INSERT INTO students (user_id, program_turu, anadal_bolum, kabul_donemi, kayitli_donem_sayisi, anadal_gano, anadal_gano_kaynak, koordinator_ozel_notu) VALUES
(4, 'CAP', 'Elektrik-Elektronik Mühendisliği', '2025-2026 Güz', 2, 3.15, 'Öğrenci İşleri Resmi Transkript', 'Fakülte Yönetim Kurulu 2025/14 sayılı kararı ile ÇAP programına kabul edildi.'),
(5, 'YDP', 'Makine Mühendisliği', '2025-2026 Güz', 2, 2.85, 'Öğrenci İşleri Resmi Transkript', 'Yandal öğrencisi. İlk dönem derslerini başarıyla tamamladı.');

-- RESMİ PROTOKOL DERS KATALOĞU
INSERT INTO course_catalog (ders_kodu, ders_adi, akts, kredi, muafiyet_aciklama) VALUES
('3301156', 'BİLGİSAYAR MÜHENDİSLİĞİNE GİRİŞ', 4, 3, NULL),
('3301351', 'NESNEYE YÖNELİK PROG. DİLİ', 4, 3, NULL),
('3301352', 'İŞLETİM SİSTEMLERİ', 4, 3, NULL),
('3301354', 'VERİ YAPILARI VE UYGULAMALARI', 4, 3, 'EEM 3302458 Veri Yapıları dersi alınırsa muaf sayılır.'),
('3301452', 'PROGRAMLAMA DİLİ-2', 4, 3, NULL),
('3301454', 'VERİ TABANI YÖNETİM SİSTEMLERİ', 5, 4, NULL),
('3301455', 'YAZILIM MÜHENDİSLİĞİ', 4, 3, NULL),
('3301456', 'MOBİL PROGRAMLAMA', 4, 3, NULL),
('3301552', 'PROGRAMLAMA DİLİ-3', 5, 4, NULL),
('3301553', 'VERİ HABERLEŞMESİ VE AĞLAR', 5, 4, NULL),
('3301651', 'YAPAY ZEKA', 4, 3, 'EEM 3302561 Makine Öğrenmesi dersi alınırsa muaf sayılır.'),
('3301652', 'OTOMATA TEORİSİ VE BİÇİMSEL DİLLER', 4, 3, NULL),
('3301653', 'GÖRÜNTÜ İŞLEME', 4, 3, 'EEM 3302659 Görüntü İşleme dersi alınırsa muaf sayılır.'),
('3301654', 'MÜHENDİSLİK TASARIMI', 6, 4, NULL),
('3301751', 'BİLGİSAYAR MÜHENDİSLİĞİ UYGULAMALARI', 4, 3, NULL),
('3301752', 'BİLGİSAYAR MİMARİSİ VE ORGANİZASYONU', 4, 3, NULL),
('3301753', 'VERİ BİLİMİ', 4, 3, NULL),
('3301851', 'İŞLETMEDE MESLEKİ EĞİTİM', 20, 10, NULL),
('3301852', 'STAJ 1', 5, 0, NULL),
('3301853', 'STAJ 2', 5, 0, NULL);

-- DİNAMİK YÖNERGE KURALLARI
INSERT INTO regulation_rules (program_turu, kural_kodu, kural_adi, deger, birim, versiyon) VALUES
('CAP', 'CAP_MIN_ANADAL_GANO', 'ÇAP Anadal Asgari Mezuniyet/Devam GANO Eşiği', 2.72, 'GANO', 1),
('CAP', 'CAP_TEK_SEFER_GANO_ALT_LIMIT', 'ÇAP Anadal Bir Defaya Mahsus Düşebilecek Alt GANO', 2.50, 'GANO', 1),
('CAP', 'CAP_MIN_MEZUNIYET_ORT', 'ÇAP İkinci Anadal Mezuniyet Ortalaması (100 üzerinden)', 70.00, 'Puan', 1),
('CAP', 'CAP_TEK_SEFER_ORT_ALT_LIMIT', 'ÇAP İkinci Anadal Bir Defaya Mahsus Düşebilecek Ort.', 65.00, 'Puan', 1),
('CAP', 'CAP_PROTOKOL_TOPLAM_AKTS', 'ÇAP Tamamlanması Gereken Protokol AKTS', 135.00, 'AKTS', 1),
('CAP', 'CAP_AZAMI_YIL', 'ÇAP Azami Öğrenim Süresi', 7.00, 'Yıl', 1),
('YDP', 'YDP_MIN_ANADAL_GANO', 'Yandal Anadal Asgari Devam GANO Eşiği', 2.30, 'GANO', 1),
('YDP', 'YDP_MIN_SERTIFIKA_GANO', 'Yandal Sertifika ve Devam Ortalaması Eşiği', 2.00, 'GANO', 1),
('YDP', 'YDP_MIN_AKTS', 'Yandal Sertifikası İçin Asgari AKTS', 30.00, 'AKTS', 1),
('YDP', 'YDP_MIN_DERS_SAYISI', 'Yandal Sertifikası İçin Asgari Ders Sayısı', 6.00, 'Ders', 1);

-- ÖRNEK DERS VE NOT KAYITLARI
INSERT INTO student_courses (student_id, course_id, akademik_yil, donem, deneme_sayisi, vize_notu, final_notu, sayisal_ortalama, harf_notu, durum, onay_durumu, degisiklik_gerekcesi, islem_yapan_id) VALUES
(1, 1, '2025-2026', 'Guz', 1, 80, 85, 83.00, 'BA', 'gecti', 'onaylandi', 'Dönem sonu transkript kontrolü ile işlendi.', 1),
(1, 2, '2025-2026', 'Guz', 1, 70, 75, 73.00, 'BB', 'gecti', 'onaylandi', 'Dönem sonu transkript kontrolü ile işlendi.', 1),
(1, 4, '2025-2026', 'Guz', 1, NULL, NULL, NULL, 'MUAF', 'muaf', 'onaylandi', 'Özel şartlı ders muafiyet formu ile muaf sayıldı.', 1),
(1, 6, '2026-2027', 'Guz', 1, NULL, NULL, NULL, NULL, 'alindi', 'bekliyor', 'Öğrenci dönem başı ders seçimi.', 4),
(2, 1, '2025-2026', 'Guz', 1, 85, 90, 88.00, 'AA', 'gecti', 'onaylandi', 'Yandal dönem sonu transkripti ile işlendi.', 1);

-- ÖRNEK MESAJ KAYDI
INSERT INTO messages (student_id, gonderen_user_id, alici_user_id, konu, mesaj, belge_talebi_mi, talep_durumu) VALUES
(1, 1, 4, '2026-2027 Güz Dönemi Ders Kayıt Formu Hk.', 'Lütfen anadal danışmanınıza imzalattığınız Ders Kayıt Formunu belge yükleme alanından sisteme yükleyiniz.', 1, 'bekliyor');

-- ÖRNEK DENETİM KAYDI
INSERT INTO audit_logs (user_id, islem_turu, tablo_adi, kayit_id, eski_deger, yeni_deger, aciklama, ip_adresi) VALUES
(1, 'SISTEM_KURULUMU', 'students', 1, NULL, '{"ogrenci_no":"203311041","program":"CAP"}', 'Sentetik ÇAP öğrencisi ve başlangıç ders verileri tanımlandı.', '127.0.0.1');
