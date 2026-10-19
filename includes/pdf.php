<?php
/**
 * PDF-lasku: sama ulkoasu kuin paperilaskun tilisiirtolomakemallissa (A4, ikkunakuoren osoite,
 * saate, maksuohje ja QR-koodi sekä tilisiirtolomake pankkiviivakoodeineen sivun alareunassa).
 *
 * Piirretään millimetreinä FPDF-kirjastolla (includes/lib/jasrek-fpdf.php), joten lomakkeen viivat
 * osuvat aina kohdalleen. QR-koodi ja pankkiviivakoodi piirretään vektoreina (terävät tulosteessa).
 * Käyttö: sähköpostilaskun liite (Asetukset → Sähköpostilaskun malli) ja laskun rivilistan PDF-linkki.
 */
if (!defined('ABSPATH')) exit;

require_once JASREK_DIR . 'includes/lib/jasrek-fpdf.php';

/** FPDF + kiertoteksti (tilisiirtolomakkeen pystyteksti). */
class JASREK_Invoice_PDF extends JASREK_FPDF {
    public function jasrek_rotated_text($x, $y, $txt, $angle) {
        $a = deg2rad($angle);
        $c = cos($a);
        $s = sin($a);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        $this->Text($x, $y, $txt);
        $this->_out('Q');
    }
}

/**
 * UTF-8 → Windows-1252 (FPDF:n perusfontit). Suomen ja ruotsin merkit sekä € säilyvät;
 * muut merkit muutetaan lähimmäksi ASCII-merkiksi (esim. č → c) tai kysymysmerkiksi.
 * Ei vaadi mbstring- eikä iconv-laajennusta.
 */
function jasrek_pdf_txt($s) {
    static $map = array(
        0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85, 0x2020 => 0x86, 0x2021 => 0x87,
        0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A, 0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91,
        0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97, 0x02DC => 0x98,
        0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C, 0x017E => 0x9E, 0x0178 => 0x9F,
        0x202F => 0x20, 0x2009 => 0x20, 0x2212 => 0x2D, 0x2010 => 0x2D, 0x2011 => 0x2D,
    );
    $s = (string) $s;
    if (!preg_match('/[\x80-\xff]/', $s)) return $s;
    if (!preg_match_all('/./us', $s, $m)) return preg_replace('/[\x80-\xff]/', '?', $s); // virheellinen UTF-8
    $out = '';
    foreach ($m[0] as $ch) {
        $b = array_values(unpack('C*', $ch));
        $n = count($b);
        if ($n === 1) { $out .= $ch; continue; }
        if ($n === 2) $cp = (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F);
        elseif ($n === 3) $cp = (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F);
        else $cp = (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F);
        if ($cp >= 0xA0 && $cp <= 0xFF) {
            $out .= chr($cp);
        } elseif (isset($map[$cp])) {
            $out .= chr($map[$cp]);
        } else {
            $a = remove_accents($ch);
            $out .= preg_match('/^[\x20-\x7e]+$/', $a) ? $a : '?';
        }
    }
    return $out;
}

/** Pt → mm. */
function jasrek_pdf_pt($pt) {
    return $pt * 25.4 / 72;
}

/** Rivittää tekstin (UTF-8) annettuun leveyteen nykyisellä fontilla. Palauttaa Windows-1252-rivit. */
function jasrek_pdf_wrap($pdf, $text, $w) {
    $lines = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $para) {
        $para = jasrek_pdf_txt(rtrim($para));
        if ($para === '') { $lines[] = ''; continue; }
        $line = '';
        foreach (preg_split('/ +/', $para) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($pdf->GetStringWidth($try) <= $w) { $line = $try; continue; }
            if ($line !== '') $lines[] = $line;
            // Liian pitkä sana katkaistaan merkeittäin
            while ($pdf->GetStringWidth($word) > $w && strlen($word) > 1) {
                $i = strlen($word) - 1;
                while ($i > 1 && $pdf->GetStringWidth(substr($word, 0, $i)) > $w) $i--;
                $lines[] = substr($word, 0, $i);
                $word = substr($word, $i);
            }
            $line = $word;
        }
        $lines[] = $line;
    }
    return $lines;
}

/** Tekstirivit: $lines Windows-1252, $x/$y vasen yläkulma, $lh rivikorkeus mm, $align L/R/C leveydellä $w. */
function jasrek_pdf_lines($pdf, $lines, $x, $y, $lh, $w = 0, $align = 'L') {
    foreach ($lines as $i => $l) {
        $pdf->SetXY($x, $y + $i * $lh);
        $pdf->Cell($w, $lh, $l, 0, 0, $align);
    }
    return $y + count($lines) * $lh;
}

/** Fontti ja väri. */
function jasrek_pdf_font($pdf, $size, $style = '', $rgb = array(31, 41, 51), $family = 'Helvetica') {
    $pdf->SetFont($family, $style, $size);
    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
}

/** Yksi lasku PDF-sivuksi. */
function jasrek_pdf_page($pdf, $lasku, $r, $reminder) {
    $s    = jasrek_settings();
    $vars = array_map(function ($v) { return html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8'); }, jasrek_invoice_vars($lasku, $r));
    $fill = function ($t) use ($vars) {
        foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', $v, $t);
        return $t;
    };
    $navy = array(29, 53, 87);
    $grey = array(85, 85, 85);
    $black = array(0, 0, 0);
    $pdf->AddPage();
    $pdf->SetDrawColor(0, 0, 0);

    // Taittomerkit 99, 148,5 ja 198 mm
    $pdf->SetLineWidth(0.1);
    $pdf->SetDrawColor(153, 153, 153);
    foreach (array(99, 148.5, 198) as $ty) $pdf->Line(4, $ty, 8, $ty);

    // Lähettäjä
    jasrek_pdf_font($pdf, 12, 'B', $navy);
    $pdf->SetXY(20, 8);
    $pdf->Cell(95, 6, jasrek_pdf_txt($s['yhdistys']));

    // Ikkunakuoren ikkuna (20 mm vasemmalta, osoite ikkunan alueella 15–60 mm ylhäältä)
    $y = 19;
    $postiosoite = trim((string) $s['postiosoite']);
    if ($postiosoite !== '') {
        $palautus = $s['yhdistys'] . ', ' . preg_replace('/\s*\n\s*/', ', ', $postiosoite);
        $pt = 7.5;
        while (true) {
            jasrek_pdf_font($pdf, $pt, '', $grey);
            $pl = jasrek_pdf_wrap($pdf, $palautus, 85);
            if (count($pl) <= 2 || $pt <= 6) break;
            $pt -= 0.5;
        }
        $y = jasrek_pdf_lines($pdf, array_slice($pl, 0, 2), 20, $y, jasrek_pdf_pt($pt * 1.2), 85);
        $pdf->SetLineWidth(0.14);
        $pdf->Line(20, $y + 1, 105, $y + 1);
        $y += 3.5;
    }
    $addr = jasrek_address_lines($r);
    $lh = jasrek_pdf_pt(11 * 1.35);
    foreach ($addr as $i => $l) {
        jasrek_pdf_font($pdf, 11, $i === 0 ? 'B' : '', array(31, 41, 51));
        $pdf->SetXY(20, $y);
        $pdf->Cell(85, $lh, jasrek_pdf_txt($l));
        $y += $lh;
    }

    // Laskun tiedot oikealla
    $tx = 123;
    $tw = 72;
    jasrek_pdf_font($pdf, 15, 'B', $navy);
    $pdf->SetXY($tx, 10);
    $pdf->Cell($tw, 6.4, $reminder ? 'MAKSUMUISTUTUS' : 'LASKU');
    $rows = array(array('Päiväys', $reminder ? jasrek_date(current_time('Y-m-d')) : $vars['laskupvm'], '', false));
    if ($reminder) $rows[] = array('Alkuperäinen lasku', $vars['laskupvm'], '', false);
    if (jasrek_tunnus_on() && $vars['tunnus'] !== '') $rows[] = array(jasrek_tunnus_nimi(), $vars['tunnus'], '', false);
    $rows[] = array('Viitenumero', $vars['viite'], 'mono', false);
    $rows[] = array('Eräpäivä', $vars['erapaiva'], 'B', false);
    if (jasrek_rivi_postimaksu($r) > 0) {
        $rows[] = array('Jäsenmaksu', $vars['jasenmaksu'], '', false);
        $rows[] = array('Postimaksu', $vars['postimaksu'], '', false);
        $rows[] = array('Maksettava', $vars['maksettava'], 'B', true);
    } else {
        $rows[] = array('Summa', $vars['maksettava'], 'B', false);
    }
    $y = 18;
    $rh = 4.7;
    foreach ($rows as $row) {
        if ($row[3]) {
            $pdf->SetDrawColor(153, 153, 153);
            $pdf->SetLineWidth(0.14);
            $pdf->Line($tx, $y + 0.3, $tx + $tw, $y + 0.3);
            $y += 0.6;
        }
        jasrek_pdf_font($pdf, 9, '', $grey);
        $pdf->SetXY($tx, $y);
        $pdf->Cell($tw / 2, $rh, jasrek_pdf_txt($row[0]));
        if ($row[2] === 'mono') jasrek_pdf_font($pdf, 9.5, 'B', array(31, 41, 51), 'Courier');
        else jasrek_pdf_font($pdf, 9, $row[2], array(31, 41, 51));
        $pdf->SetXY($tx + $tw / 2, $y);
        $pdf->Cell($tw / 2, $rh, jasrek_pdf_txt($row[1]), 0, 0, 'R');
        $y += $rh;
    }

    // Saate (58–169 mm). Pitkä teksti pienennetään 8,5 pt:iin; jos ei silti mahdu, loput jätetään pois.
    $text = $fill(jasrek_saate_omat_tiedot($reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']), $r, 'teksti'));
    $note = jasrek_postimaksu_note($reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']), $r, $vars);
    $top = 58;
    $bottom = 169;
    for ($pt = 10.5; $pt >= 8.5; $pt -= 0.5) {
        jasrek_pdf_font($pdf, $pt, '', array(31, 41, 51));
        $lines = jasrek_pdf_wrap($pdf, $text, 175);
        $lh = jasrek_pdf_pt($pt * 1.45);
        $need = count($lines) * $lh;
        if ($note !== '') {
            jasrek_pdf_font($pdf, $pt, 'B', array(31, 41, 51));
            $nlines = jasrek_pdf_wrap($pdf, $note, 175);
            $need += 3 + count($nlines) * $lh;
        }
        if ($top + $need <= $bottom) break;
    }
    $pt = max(8.5, $pt);
    jasrek_pdf_font($pdf, $pt, '', array(31, 41, 51));
    $max = (int) floor(($bottom - $top) / $lh);
    $y = jasrek_pdf_lines($pdf, array_slice($lines, 0, $max), 20, $top, $lh, 175);
    if ($note !== '' && $y + 3 + count($nlines) * $lh <= $bottom) {
        jasrek_pdf_font($pdf, $pt, 'B', array(31, 41, 51));
        jasrek_pdf_lines($pdf, $nlines, 20, $y + 3, $lh, 175);
    }

    // Lomakkeen yläpuolella: maksuohje ja QR-koodi, alareuna 203 mm
    $images_mode = $s['maksukoodi'];
    $qr = ($images_mode === 'qr' || $images_mode === 'molemmat') ? jasrek_qr_matrix(jasrek_epc_payload($lasku, $r)) : array();
    $vvk = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    $yt = trim(preg_replace('/\s*\n\s*/', ' · ', (string) $s['yhteystiedot']));
    $foot = ($yt !== '' ? $yt : $s['yhdistys']) . ($s['ytunnus'] !== '' ? ' · Y-tunnus ' . $s['ytunnus'] : '');
    $ow = $qr ? 139 : 175;
    jasrek_pdf_font($pdf, 9, '', array(31, 41, 51));
    $ohje = jasrek_pdf_wrap($pdf, 'Maksa verkkopankissa tai mobiilisovelluksessa tilisiirtolomakkeen tiedoilla. Käytä viitenumeroa ' . $vars['viite'] . ', jotta maksu kohdistuu sinulle.', $ow);
    jasrek_pdf_font($pdf, 7.5, '', array(68, 68, 68));
    $vl = $vvk !== '' ? jasrek_pdf_wrap($pdf, 'Virtuaaliviivakoodi: ' . $vvk, $ow) : array();
    $fl = jasrek_pdf_wrap($pdf, $foot, $ow);
    $l9 = jasrek_pdf_pt(9 * 1.4);
    $l75 = jasrek_pdf_pt(7.5 * 1.3);
    $h = 5 + count($ohje) * $l9 + ($vl ? 1.5 + count($vl) * $l75 : 0) + 3.5 + count($fl) * $l75;
    $y = 203 - $h;
    jasrek_pdf_font($pdf, 10, 'B', $navy);
    $pdf->SetXY(20, $y);
    $pdf->Cell($ow, 5, 'Maksaminen');
    jasrek_pdf_font($pdf, 9, '', array(31, 41, 51));
    $y = jasrek_pdf_lines($pdf, $ohje, 20, $y + 5, $l9, $ow);
    if ($vl) {
        jasrek_pdf_font($pdf, 7.5, '', array(68, 68, 68));
        $y = jasrek_pdf_lines($pdf, $vl, 20, $y + 1.5, $l75, $ow);
    }
    $pdf->SetDrawColor(204, 204, 204);
    $pdf->SetLineWidth(0.1);
    $pdf->Line(20, $y + 1.5, 20 + $ow, $y + 1.5);
    jasrek_pdf_font($pdf, 7.5, '', $grey);
    jasrek_pdf_lines($pdf, $fl, 20, $y + 3.5, $l75, $ow);
    if ($qr) {
        $n = count($qr);
        $size = 28;
        $mod = $size / ($n + 8); // 4 moduulin hiljainen alue kuten kuvassa
        $qx = 166 + 4 * $mod;
        $qy = 203 - 4.5 - $size + 4 * $mod;
        $pdf->SetFillColor(0, 0, 0);
        foreach ($qr as $yy => $row) {
            $run = -1;
            foreach ($row as $xx => $dark) {
                if ($dark && $run < 0) $run = $xx;
                if ((!$dark || $xx === $n - 1) && $run >= 0) {
                    $end = $dark ? $xx + 1 : $xx;
                    $pdf->Rect($qx + $run * $mod, $qy + $yy * $mod, ($end - $run) * $mod, $mod + 0.01, 'F');
                    $run = -1;
                }
            }
        }
        jasrek_pdf_font($pdf, 7, '', $grey);
        $pdf->SetXY(165, 203 - 4);
        $pdf->Cell(30, 3.5, 'Skannaa pankkisovelluksella', 0, 0, 'C');
    }

    // Tilisiirtolomake: 8–202 mm, 203–291 mm
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    for ($dx = 8; $dx < 202; $dx += 2.5) $pdf->Line($dx, 204, min($dx + 1.3, 202), 204);
    $X = array(8, 30, 112, 127, 165, 177, 202);
    $Y = array(208.3, 220.3, 234.3, 251.3, 260.3, 269.3);
    $thick = function ($x1, $y1, $x2, $y2) use ($pdf) { $pdf->SetLineWidth(0.6); $pdf->Line($x1, $y1, $x2, $y2); };
    $thin  = function ($x1, $y1, $x2, $y2) use ($pdf) { $pdf->SetLineWidth(0.3); $pdf->Line($x1, $y1, $x2, $y2); };
    $thick($X[0], $Y[1], $X[6], $Y[1]);
    $thick($X[0], $Y[2], $X[2], $Y[2]);
    $thick($X[2], $Y[3], $X[6], $Y[3]);
    $thick($X[0], $Y[4], $X[6], $Y[4]);
    $thick($X[0], $Y[5], $X[6], $Y[5]);
    $thick($X[1], $Y[0], $X[1], $Y[5]);
    $thick($X[2], $Y[0], $X[2], $Y[5]);
    $thin($X[3], $Y[3], $X[3], $Y[5]);
    $thin($X[4], $Y[4], $X[4], $Y[5]);
    $thin($X[5], $Y[4], $X[5], $Y[5]);
    $thin($X[1] + 4, $Y[4] - 4, $X[2] - 8, $Y[4] - 4); // allekirjoitus

    $l7 = jasrek_pdf_pt(7 * 1.2);
    $th = function ($lines, $x1, $x2, $y, $valign = 'top', $y2 = 0) use ($pdf, $l7, $black) {
        jasrek_pdf_font($pdf, 7, '', $black);
        $lines = array_map('jasrek_pdf_txt', $lines);
        if ($valign === 'middle') $y = $y + (($y2 - $y) - count($lines) * $l7) / 2;
        jasrek_pdf_lines($pdf, $lines, $x1 + 1.6, $y, $l7, $x2 - $x1 - 3.2, 'R');
    };
    $small = function ($t, $x, $y) use ($pdf, $black) {
        jasrek_pdf_font($pdf, 7, '', $black);
        $pdf->SetXY($x + 1.6, $y + 1);
        $pdf->Cell(40, jasrek_pdf_pt(7 * 1.2), jasrek_pdf_txt($t));
    };
    $arvo = function ($t, $x1, $x2, $y, $align = 'L') use ($pdf, $black) {
        jasrek_pdf_font($pdf, 11, '', $black);
        $pdf->SetXY($x1 + 1.6, $y);
        $pdf->Cell($x2 - $x1 - 3.2, 5, jasrek_pdf_txt($t), 0, 0, $align);
    };
    $osoite = function ($lines, $x1, $x2, $y, $maxh, $isot) use ($pdf, $black) {
        for ($pt = count($lines) > $isot ? 8.5 : 10; $pt >= 7; $pt -= 0.5) {
            jasrek_pdf_font($pdf, $pt, '', $black);
            $wr = array();
            foreach ($lines as $l) $wr = array_merge($wr, jasrek_pdf_wrap($pdf, $l, $x2 - $x1 - 3.2));
            $lh = jasrek_pdf_pt($pt * ($pt < 10 ? 1.2 : 1.3));
            if (count($wr) * $lh <= $maxh) break;
        }
        $wr = array_slice($wr, 0, (int) floor($maxh / $lh));
        jasrek_pdf_lines($pdf, $wr, $x1 + 1.6, $y, $lh, $x2 - $x1 - 3.2);
    };

    // Rivi 1: tilinumero ja BIC
    $th(array('Saajan', 'tilinumero', 'Mottagarens', 'kontonummer'), $X[0], $X[1], $Y[0] + 0.3);
    $small('IBAN', $X[1], $Y[0]);
    $arvo($vars['iban'], $X[1], $X[2], $Y[0] + 4.6);
    $small('BIC', $X[2], $Y[0]);
    $arvo($s['bic'], $X[2], $X[6], $Y[0] + 4.6);
    // Rivi 2: saaja ja viesti
    $th(array('Saaja', 'Mottagare'), $X[0], $X[1], $Y[1] + 1);
    $osoite(jasrek_saaja_rivit($s), $X[1], $X[2], $Y[1] + 1.2, 11.6, 2);
    $small('Viesti / Meddelande', $X[2], $Y[1]);
    // Rivit 3–4: maksaja
    $th(array('Maksaja', 'Betalare'), $X[0], $X[1], $Y[2] + 1);
    $th(array('Allekirjoitus', 'Underskrift'), $X[0], $X[1], $Y[4] - 1.5 - 2 * $l7);
    jasrek_pdf_font($pdf, 5.8, 'B', $black);
    $pdf->jasrek_rotated_text($X[0] + 2.6, $Y[4] - 1, 'TILISIIRTO GIRERING', 90);
    $osoite(jasrek_address_lines($r), $X[1], $X[2], $Y[2] + 1.2, 19, 4);
    // Rivi 4: viitenumero
    $th(array('Viitenro', 'Ref.nr'), $X[2], $X[3], $Y[3], 'middle', $Y[4]);
    $arvo($vars['viite'], $X[3], $X[6], $Y[3] + 2);
    // Rivi 5: tililtä, eräpäivä, summa
    $th(array('Tililtä nro', 'Från konto nr'), $X[0], $X[1], $Y[4], 'middle', $Y[5]);
    $th(array('Eräpäivä', 'Förf.dag'), $X[2], $X[3], $Y[4], 'middle', $Y[5]);
    $arvo($vars['erapaiva'], $X[3], $X[4], $Y[4] + 2);
    $th(array('Summa'), $X[4], $X[5], $Y[4], 'middle', $Y[5]);
    $arvo(jasrek_print_eur($r->summa), $X[5] - 8, $X[6], $Y[4] + 2, 'R');

    // Pankkiviivakoodi ja ehdot lomakkeen alla
    $bar = ($images_mode === 'viivakoodi' || $images_mode === 'molemmat') ? jasrek_barcode_modules($vvk) : array();
    if ($bar) {
        $mw = min(0.3, 106 / count($bar));
        $pdf->SetFillColor(0, 0, 0);
        $run = -1;
        $cnt = count($bar);
        foreach ($bar as $i => $dark) {
            if ($dark && $run < 0) $run = $i;
            if ((!$dark || $i === $cnt - 1) && $run >= 0) {
                $end = $dark ? $i + 1 : $i;
                $pdf->Rect(12 + $run * $mw, $Y[5] + 2.3, ($end - $run) * $mw, 12, 'F');
                $run = -1;
            }
        }
    }
    jasrek_pdf_font($pdf, 6.3, '', $black);
    $el = array_merge(
        jasrek_pdf_wrap($pdf, 'Maksu välitetään saajalle maksujen välityksen ehtojen mukaisesti ja vain maksajan ilmoittaman tilinumeron perusteella.', 78),
        jasrek_pdf_wrap($pdf, 'Betalningen förmedlas till mottagaren enligt villkoren för betalningsförmedling och endast till det kontonummer som betalaren angivit.', 78)
    );
    $y = jasrek_pdf_lines($pdf, $el, 124, $Y[5] + 2.3, jasrek_pdf_pt(6.3 * 1.3), 78);
    jasrek_pdf_font($pdf, 7, 'B', $black);
    $pdf->SetXY(124, $y + 1);
    $pdf->Cell(78, 3, 'PANKKI BANKEN', 0, 0, 'R');
}

/** Laskut PDF:nä (merkkijono). $rows: laskurivit; yksi sivu per rivi. */
function jasrek_invoice_pdf($lasku, $rows, $reminder = false) {
    $pdf = new JASREK_Invoice_PDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetTitle(jasrek_pdf_txt($lasku->nimi));
    $pdf->SetAuthor(jasrek_pdf_txt(jasrek_settings()['yhdistys']));
    $pdf->SetCreator('OH6KW Jasenrekisteri');
    foreach ($rows as $r) jasrek_pdf_page($pdf, $lasku, $r, $reminder);
    return $pdf->Output('S');
}

/** Liitetiedoston nimi, esim. Jasenmaksu-2027-200046.pdf. */
function jasrek_invoice_pdf_name($lasku, $r, $reminder = false) {
    $base = sanitize_file_name(remove_accents(($reminder ? 'Muistutus-' : '') . $lasku->nimi . '-' . preg_replace('/\D/', '', (string) $r->viite)));
    return ($base !== '' ? $base : 'lasku') . '.pdf';
}
