if (!function_exists('degerlendir_ogrenci_kurallari')) {
    function degerlendir_ogrenci_kurallari($db, $student_arg) {
        $sonuc = [
            'tamamlanan_akts'  => 0,
            'hedef_akts'       => 135,
            'kalan_akts'       => 135,
            'bolum_ortalamasi' => 0.00,
            'durum'            => 'Uygun',
            'mesaj'            => 'Yönergeye Uygun',
            'uyarilar'         => []
        ];

        try {
            $student_id = null;
            $gano = 0.0;
            $prog = 'CAP';

            // Parametre ister ID (sayı) ister öğrenci dizisi gelsin, verileri güvenle çöz
            if (is_array($student_arg)) {
                $student_id = $student_arg['id'] ?? null;
                $gano = (float)($student_arg['anadal_gano'] ?? 0);
                $prog = strtoupper($student_arg['program_turu'] ?? 'CAP');
            } else {
                $student_id = $student_arg;
                $stStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
                $stStmt->execute([$student_id]);
                $stData = $stStmt->fetch();
                if ($stData) {
                    $gano = (float)($stData['anadal_gano'] ?? 0);
                    $prog = strtoupper($stData['program_turu'] ?? 'CAP');
                }
            }

            // Veritabanındaki yonerge_kurallari tablosundan dinamik eşikleri al
            $kurallar = [];
            $kQuery = $db->query("SELECT kural_adi, deger FROM yonerge_kurallari");
            while ($row = $kQuery->fetch()) {
                $kurallar[$row['kural_adi']] = (float)$row['deger'];
            }

            // Dinamik GANO eşik kontrolü
            if ($prog === 'CAP') {
                $asgariGano = $kurallar['CAP Anadal Asgari Mezuniyet/Devam GANO Eşiği'] ?? 2.72;
                if ($gano < $asgariGano) {
                    $sonuc['durum'] = 'Uygun Değil';
                    $sonuc['mesaj'] = 'Anadal GANO Asgari Eşiğin Altında!';
                    $sonuc['uyarilar'][] = [
                        'tip' => 'danger',
                        'mesaj' => "Dikkat: Öğrencinin Anadal GANO'su ($gano), yönerge eşiği olan $asgariGano değerinin altında!"
                    ];
                }
            } elseif ($prog === 'YDP') {
                $asgariGano = $kurallar['Yandal Anadal Asgari Devam GANO Eşiği'] ?? 2.30;
                if ($gano < $asgariGano) {
                    $sonuc['durum'] = 'Uygun Değil';
                    $sonuc['mesaj'] = 'Anadal GANO Asgari Eşiğin Altında!';
                    $sonuc['uyarilar'][] = [
                        'tip' => 'danger',
                        'mesaj' => "Dikkat: Yandal öğrencisinin Anadal GANO'su ($gano), yönerge eşiği olan $asgariGano değerinin altında!"
                    ];
                }
            }

            if ($student_id) {
                // Tamamlanan AKTS'leri veritabanından hesapla
                $stmt = $db->prepare("
                    SELECT SUM(COALESCE(sc.akts, cc.akts, 0)) as toplam 
                    FROM student_courses sc 
                    LEFT JOIN course_catalog cc ON sc.course_id = cc.id 
                    WHERE sc.student_id = ? AND (sc.durum = 'Geçti' OR sc.durum = 'GEÇTİ' OR sc.onay_durumu = 'Onaylı' OR sc.harf_notu IN ('AA','BA','BB','CB','CC'))
                ");
                $stmt->execute([$student_id]);
                $row = $stmt->fetch();
                if ($row && isset($row['toplam'])) {
                    $sonuc['tamamlanan_akts'] = (int)$row['toplam'];
                }

                $sonuc['kalan_akts'] = max(0, $sonuc['hedef_akts'] - $sonuc['tamamlanan_akts']);

                // Bölüm ortalamasını hesapla
                $stmt2 = $db->prepare("
                    SELECT AVG(sayisal_ortalama) as ort 
                    FROM student_courses 
                    WHERE student_id = ? AND sayisal_ortalama > 0
                ");
                $stmt2->execute([$student_id]);
                $row2 = $stmt2->fetch();
                if ($row2 && isset($row2['ort'])) {
                    $sonuc['bolum_ortalamasi'] = round((float)$row2['ort'], 2);
                }
            }
        } catch (Exception $e) {}

        return $sonuc;
    }
}
