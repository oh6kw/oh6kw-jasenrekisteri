<?php
if (!defined('ABSPATH')) exit;

/** Yhteinen sähköpostipohja (inline-tyylit, toimii myös Outlookissa ja puhelimella). */
function jasrek_email_layout($title, $inner, $width = 600) {
    $s = jasrek_settings();
    $footer = $s['yhteystiedot'] !== '' ? nl2br(esc_html($s['yhteystiedot'])) : esc_html($s['yhdistys']);
    if ($s['ytunnus'] !== '') $footer .= '<br>Y-tunnus ' . esc_html($s['ytunnus']);

    return '<!DOCTYPE html><html lang="fi"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#eef1f5;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:' . (int) $width . 'px;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#1f2933;font-size:15px;line-height:1.55;">'
        . '<tr><td style="background:#1d3557;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:bold;">' . esc_html($s['yhdistys']) . '</td></tr>'
        . '<tr><td style="padding:28px;">' . $inner . '</td></tr>'
        . '<tr><td style="background:#f6f8fa;color:#6b7785;padding:16px 28px;font-size:12px;line-height:1.5;">' . $footer . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Laskurivin henkilötiedot: ensisijaisesti jäsenrekisterin nykyiset tiedot, muuten laskulle tallennetut. */
function jasrek_rivi_person($r) {
    $etu  = isset($r->etunimi) && $r->etunimi !== null ? $r->etunimi : strtok((string) $r->nimi, ' ');
    $suku = isset($r->sukunimi) && $r->sukunimi !== null ? $r->sukunimi : trim(substr((string) $r->nimi, strlen((string) $etu)));
    return array(
        'etunimi'  => (string) $etu,
        'sukunimi' => (string) $suku,
        'nimi'     => trim($etu . ' ' . $suku),
        'tunnus'   => isset($r->tunnus) ? (string) $r->tunnus : '',
        'jasenlaji'=> isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '',
        'jasenluokka' => (isset($r->jasenlaji) && jasrek_laji_nimi((string) $r->jasenlaji) !== '') ? jasrek_laji_nimi((string) $r->jasenlaji) : 'Jäsen',
    );
}

function jasrek_invoice_vars($lasku, $r) {
    $s = jasrek_settings();
    $p = jasrek_rivi_person($r);
    return array_merge($p, array(
        'laskun_nimi' => $lasku->nimi,
        // {summa} = jäsenmaksu (ilman paperilaskun postimaksua), {maksettava} = maksettava yhteensä
        'summa'       => jasrek_money(jasrek_rivi_jasenmaksu($r)),
        'maksettava'  => jasrek_money($r->summa),
        'jasenmaksu'  => jasrek_money(jasrek_rivi_jasenmaksu($r)),
        'postimaksu'  => jasrek_money(jasrek_rivi_postimaksu($r)),
        'viite'       => jasrek_viite_format($r->viite),
        'erapaiva'    => jasrek_date(jasrek_rivi_era($lasku, $r)),
        'laskupvm'    => jasrek_date($lasku->laskupvm),
        'iban'        => jasrek_iban_format($s['iban']),
        'saaja'       => $s['yhdistys'],
        'yhdistys'    => $s['yhdistys'],
    ));
}

/**
 * Postimaksun selite saatteen perään, jos laskussa on postimaksu eikä saate mainitse sitä
 * paikkamerkeillä {postimaksu} tai {maksettava}.
 */
function jasrek_postimaksu_note($text, $r, $vars) {
    if (jasrek_rivi_postimaksu($r) <= 0) return '';
    if (strpos($text, '{postimaksu}') !== false || strpos($text, '{maksettava}') !== false) return '';
    return sprintf('Paperilaskun postimaksu %s on lisätty jäsenmaksuun %s. Maksettava yhteensä %s.', $vars['postimaksu'], $vars['jasenmaksu'], $vars['maksettava']);
}

/**
 * @param string $img 'cid' = kuvat upotetaan sähköpostin liitteinä, 'data' = esikatselu selaimessa.
 */
function jasrek_invoice_html($lasku, $r, $reminder = false, $img = 'cid') {
    $s    = jasrek_settings();
    if ($s['sposti_malli'] === 'maksukortti') return jasrek_invoice_html_maksukortti($lasku, $r, $reminder, $img);
    $vars = jasrek_invoice_vars($lasku, $r);
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);

    $row = function ($label, $value, $strong = false) {
        return '<tr><td style="padding:8px 0;color:#6b7785;border-bottom:1px solid #e6e9ee;width:42%;">' . esc_html($label) . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #e6e9ee;' . ($strong ? 'font-weight:bold;' : '') . '">' . $value . '</td></tr>';
    };

    $jasen = esc_html($vars['nimi']) . (jasrek_tunnus_on() && $vars['tunnus'] !== '' ? ' (' . esc_html($vars['tunnus']) . ')' : '');

    $html  = '<div style="margin:0 0 20px;">' . jasrek_saate_omat_tiedot(jasrek_fill($text, $vars), $r, 'html') . '</div>';
    $note  = jasrek_postimaksu_note($text, $r, $vars);
    if ($note !== '') $html .= '<p style="margin:0 0 20px;font-weight:bold;">' . esc_html($note) . '</p>';
    $html .= '<div style="font-size:12px;letter-spacing:.08em;color:#6b7785;text-transform:uppercase;margin:0 0 4px;">'
           . ($reminder ? 'Maksumuistutus' : 'Lasku') . '</div>';
    $html .= '<div style="font-size:20px;font-weight:bold;margin:0 0 12px;">' . esc_html($lasku->nimi) . '</div>';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">';
    $html .= $row('Jäsen', $jasen);
    $luokka = isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '';
    if ($luokka !== '') $html .= $row('Jäsenluokka', esc_html($luokka));
    $html .= $row('Laskun päiväys', esc_html($vars['laskupvm']));
    $html .= $row('Saaja', esc_html($s['yhdistys']));
    $html .= $row('Saajan tilinumero (IBAN)', '<span style="font-family:Consolas,Menlo,monospace;">' . esc_html($vars['iban']) . '</span>', true);
    if ($s['bic'] !== '') $html .= $row('BIC', esc_html($s['bic']));
    $html .= $row('Viitenumero', '<span style="font-family:Consolas,Menlo,monospace;">' . esc_html($vars['viite']) . '</span>', true);
    $html .= $row('Eräpäivä', esc_html($vars['erapaiva']), true);
    if (jasrek_rivi_postimaksu($r) > 0) {
        $html .= $row('Jäsenmaksu', esc_html($vars['jasenmaksu']));
        $html .= $row('Postimaksu (paperilasku)', esc_html($vars['postimaksu']));
    }
    $html .= '</table>';

    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0;background:#eaf2fb;border-radius:6px;">'
           . '<tr><td style="padding:14px 18px;font-size:15px;">Maksettava</td>'
           . '<td align="right" style="padding:14px 18px;font-size:22px;font-weight:bold;color:#1d3557;">' . esc_html($vars['maksettava']) . '</td></tr></table>';

    // QR-koodi ja/tai pankkiviivakoodi asetusten mukaan
    $images = jasrek_invoice_images($lasku, $r);
    $src = function ($cid) use ($images, $img) {
        return $img === 'data' ? 'data:image/png;base64,' . base64_encode($images[$cid]) : 'cid:' . $cid;
    };
    if (isset($images['jrqr'])) {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 0;"><tr>'
               . '<td style="padding:0 16px 0 0;vertical-align:middle;"><img src="' . esc_attr($src('jrqr')) . '" width="150" height="150" alt="Maksun QR-koodi" style="display:block;width:150px;height:150px;"></td>'
               . '<td style="vertical-align:middle;font-size:13px;color:#6b7785;">Maksa helposti: avaa pankkisovellus ja skannaa QR-koodi. Saaja, tilinumero, summa ja viite täyttyvät valmiiksi.</td>'
               . '</tr></table>';
    }
    if (isset($images['jrbar'])) {
        // Kuva on piirretty 2× tarkkuudella; näytetään puolikkaana, jotta palkit ovat teräviä (skaalaus 2:1).
        $bw = (int) (unpack('N', substr($images['jrbar'], 16, 4))[1] / 2);
        $html .= '<div style="margin:18px 0 0;"><img src="' . esc_attr($src('jrbar')) . '" width="' . $bw . '" height="50" alt="Pankkiviivakoodi" style="display:block;width:' . $bw . 'px;height:50px;"></div>';
    }

    $vvk = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    if ($vvk !== '') {
        $html .= '<div style="margin:22px 0 0;padding:14px 16px;border:1px dashed #b8c2cc;border-radius:6px;">'
               . '<div style="font-size:13px;color:#6b7785;margin:0 0 6px;">Virtuaaliviivakoodi – kopioi pankkisovellukseen, niin maksun tiedot täyttyvät valmiiksi:</div>'
               . '<div style="font-family:Consolas,Menlo,monospace;font-size:14px;word-break:break-all;">' . esc_html($vvk) . '</div></div>';
    }

    $html .= '<p style="margin:20px 0 0;font-size:13px;color:#6b7785;">Käytäthän maksaessasi viitenumeroa (ei viestiä), jotta maksu kohdistuu sinulle.</p>';

    return jasrek_email_layout($lasku->nimi, $html);
}

/** Sähköpostilaskun kuvat valitun mallin mukaan (upotetaan viestiin cid-kuvina). */
function jasrek_invoice_email_images($lasku, $r) {
    $s = jasrek_settings();
    $images = jasrek_invoice_images($lasku, $r);
    // Maksukortissa ei pankkiviivakoodia: sitä ei voi lukea näytöltä (tulostettava lasku viivakoodeineen on PDF-liitteessä)
    if ($s['sposti_malli'] === 'maksukortti') unset($images['jrbar']);
    return $images;
}

/**
 * Sähköpostilasku "maksukorttina": yksi palsta, joka toimii puhelimessa ja tietokoneella. Maksettava summa ja eräpäivä
 * isolla, maksun tiedot allekkain helposti kopioitavina, QR-koodi asetuksen mukaan ja virtuaaliviivakoodi.
 * Inline-tyylit ja taulukot, ei mediakyselyjä – toimii Gmailissa, Outlookissa ja puhelinten postisovelluksissa.
 */
function jasrek_invoice_html_maksukortti($lasku, $r, $reminder = false, $img = 'cid') {
    $s    = jasrek_settings();
    $vars = array_map(function ($v) { return html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8'); }, jasrek_invoice_vars($lasku, $r));
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);
    $images = jasrek_invoice_email_images($lasku, $r);
    $vvk  = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    $mono = 'font-family:Consolas,Menlo,Monaco,monospace;';
    $grey = '#6b7785';
    $navy = '#1d3557';
    $line = '#e3e8ee';

    $html  = '<div style="margin:0 0 22px;">' . jasrek_saate_omat_tiedot(jasrek_fill($text, jasrek_invoice_vars($lasku, $r)), $r, 'html') . '</div>';
    $note  = jasrek_postimaksu_note($text, $r, jasrek_invoice_vars($lasku, $r));
    if ($note !== '') $html .= '<p style="margin:0 0 22px;font-weight:bold;">' . esc_html($note) . '</p>';

    // Laskun otsikko ja jäsenen tiedot
    $html .= '<div style="font-size:12px;letter-spacing:.08em;color:' . $grey . ';text-transform:uppercase;margin:0 0 2px;">' . ($reminder ? 'Maksumuistutus' : 'Lasku') . '</div>';
    $html .= '<div style="font-size:20px;font-weight:bold;line-height:1.3;margin:0 0 4px;">' . esc_html($lasku->nimi) . '</div>';
    $jasen = array($vars['nimi']);
    if (jasrek_tunnus_on() && $vars['tunnus'] !== '') $jasen[] = $vars['tunnus'];
    $luokka = isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '';
    if ($luokka !== '') $jasen[] = $luokka;
    $pvm = $reminder ? 'Muistutus ' . jasrek_date(current_time('Y-m-d')) . ' · lasku ' . $vars['laskupvm'] : 'Päiväys ' . $vars['laskupvm'];
    $html .= '<div style="font-size:14px;color:' . $grey . ';margin:0 0 16px;">' . esc_html(implode(' · ', $jasen)) . '<br>' . esc_html($pvm) . '</div>';

    // Kortti: summa ja eräpäivä
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #c9d6e3;border-radius:8px;border-collapse:separate;">';
    $html .= '<tr><td style="background:#eaf2fb;padding:16px 18px;border-radius:8px 8px 0 0;">'
           . '<div style="font-size:13px;color:' . $grey . ';">Maksettava</div>'
           . '<div style="font-size:30px;font-weight:bold;line-height:1.2;color:' . $navy . ';white-space:nowrap;">' . esc_html($vars['maksettava']) . '</div>'
           . '<div style="font-size:15px;margin:4px 0 0;">Eräpäivä <strong>' . esc_html($vars['erapaiva']) . '</strong></div>'
           . (jasrek_rivi_postimaksu($r) > 0 ? '<div style="font-size:13px;color:' . $grey . ';margin:4px 0 0;">Jäsenmaksu ' . esc_html($vars['jasenmaksu']) . ' + postimaksu ' . esc_html($vars['postimaksu']) . '</div>' : '')
           . '</td></tr>';

    // Maksun tiedot allekkain: nimi pienellä, arvo isolla – helppo valita ja kopioida myös puhelimessa
    $field = function ($label, $value, $is_mono = false, $last = false) use ($grey, $line, $mono) {
        return '<tr><td style="padding:12px 18px;' . ($last ? '' : 'border-bottom:1px solid ' . $line . ';') . '">'
             . '<div style="font-size:12px;color:' . $grey . ';margin:0 0 2px;">' . esc_html($label) . '</div>'
             . '<div style="font-size:17px;font-weight:bold;' . ($is_mono ? $mono . 'letter-spacing:.02em;' : '') . 'word-break:break-word;">' . esc_html($value) . '</div></td></tr>';
    };
    $html .= $field('Saaja', $s['yhdistys']);
    $html .= $field('Saajan tilinumero (IBAN)', $vars['iban'], true);
    if ($s['bic'] !== '') $html .= $field('BIC', $s['bic'], true);
    $html .= $field('Viitenumero', $vars['viite'], true);
    $html .= $field('Eräpäivä', $vars['erapaiva']);
    $html .= $field('Summa', $vars['maksettava'], false, true);
    $html .= '</table>';

    // QR-koodi (asetuksen mukaan): kun sähköposti on auki tietokoneella, maksun voi skannata puhelimen pankkisovelluksella
    if (isset($images['jrqr'])) {
        $src = $img === 'data' ? 'data:image/png;base64,' . base64_encode($images['jrqr']) : 'cid:jrqr';
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0 0;"><tr>'
               . '<td style="padding:0 16px 0 0;vertical-align:middle;"><img src="' . esc_attr($src) . '" width="140" height="140" alt="Maksun QR-koodi" style="display:block;width:140px;height:140px;"></td>'
               . '<td style="vertical-align:middle;font-size:14px;color:' . $grey . ';">Luetko tätä tietokoneella? Avaa puhelimen pankkisovellus ja skannaa QR-koodi, niin saaja, tilinumero, summa ja viite täyttyvät valmiiksi.</td>'
               . '</tr></table>';
    }

    // Virtuaaliviivakoodi
    if ($vvk !== '') {
        $html .= '<div style="margin:20px 0 0;padding:14px 16px;border:1px dashed #b8c2cc;border-radius:8px;">'
               . '<div style="font-size:13px;color:' . $grey . ';margin:0 0 6px;">Virtuaaliviivakoodi – kopioi se verkkopankin tai pankkisovelluksen viivakoodikenttään, niin kaikki maksun tiedot täyttyvät kerralla:</div>'
               . '<div style="' . $mono . 'font-size:15px;word-break:break-all;">' . esc_html($vvk) . '</div></div>';
    }
    if ($s['sposti_pdf'] === '1') {
        $html .= '<p style="margin:18px 0 0;font-size:14px;">📎 Lasku on myös liitteenä PDF-tiedostona, jos haluat tulostaa sen.</p>';
    }
    $html .= '<p style="margin:16px 0 0;font-size:13px;color:' . $grey . ';">Käytäthän maksaessasi viitenumeroa (ei viestiä), jotta maksu kohdistuu sinulle.</p>';
    return jasrek_email_layout($lasku->nimi, $html);
}
