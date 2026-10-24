/* OH6KW Jäsenrekisteri – hallinnan skriptit */
(function ($) {
    'use strict';

    // Varmistuskysymys linkeille, painikkeille ja lomakkeille
    $(document).on('click', '[data-confirm]', function (e) {
        if ($(this).is('.jasrek-run')) return; // ajoon oma käsittely
        if ($(this).closest('.jasrek-bulk').find('#jasrek-mbulk').val() === 'tarrat') return; // tarrojen tulostus ei muuta tietoja
        if (!window.confirm($(this).data('confirm'))) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });

    // Tulosta-painike ja automaattisesti lähetettävä valinta (esim. vuosiraportin vuosi)
    $(document).on('click', '[data-jasrek-print]', function () { window.print(); });
    $(document).on('change', 'select[data-jasrek-autosubmit]', function () { this.form.submit(); });

    // Haku + tilasuodatin taulukoille ja listoille
    var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ''); };
    var state = {};
    function applyFilter(target) {
        var st = state[target] || {};
        var q = norm(st.q), status = st.status || '';
        // Tiliotteella viite voi olla etunollilla täytetty (esim. 00000000000000100010)
        if (/^\d+$/.test(q)) q = q.replace(/^0+/, '');
        $(target).find('[data-search]').each(function () {
            var $el = $(this);
            var okQ = !q || String($el.data('search')).indexOf(q) !== -1;
            var okS = !status || (' ' + ($el.data('status') || '') + ' ').indexOf(' ' + status + ' ') !== -1;
            $el.toggle(okQ && okS);
        });
    }
    $(document).on('input', '[data-jasrek-filter]', function () {
        var target = $(this).data('jasrek-filter');
        state[target] = $.extend(state[target] || {}, { q: $(this).val() });
        applyFilter(target);
    });
    $(document).on('click', '[data-jasrek-status-for] a', function (e) {
        e.preventDefault();
        var $ul = $(this).closest('[data-jasrek-status-for]'), target = $ul.data('jasrek-status-for');
        $ul.find('a').removeClass('current');
        $(this).addClass('current');
        state[target] = $.extend(state[target] || {}, { status: $(this).data('status') });
        applyFilter(target);
    });

    // Valitse kaikki (vain näkyvät rivit)
    $(document).on('change', '.jasrek-check-all', function () {
        var checked = this.checked;
        $($(this).data('target')).find('input[type=checkbox]').not(this).each(function () {
            var $row = $(this).closest('tr, label');
            if ($row.is(':visible')) this.checked = checked;
        });
    });

    // Vastaanottajavalinta uudessa laskussa
    $(document).on('change', 'input[name=vastaanottajat]', function () {
        $('#jasrek-member-pick').prop('hidden', $('input[name=vastaanottajat]:checked').val() !== 'valitut');
    });

    // Jäsenlajien lisäys ja poisto asetuksissa
    $(document).on('click', '#jasrek-laji-add', function () {
        var tpl = document.getElementById('jasrek-laji-tpl');
        var $row = $(tpl.innerHTML);
        $('#jasrek-lajit tbody').append($row);
        $row.find('input[type=text]').first().trigger('focus');
    });
    $(document).on('click', '.jasrek-laji-del', function () {
        var $tr = $(this).closest('tr');
        var nimi = $tr.find('input[name="laji_nimi[]"]').val();
        if (!nimi || window.confirm('Poistetaanko jäsenluokka "' + nimi + '"? Muutos tallentuu, kun tallennat asetukset.')) $tr.remove();
    });

    // Yleinen taulukkorivin lisäys/poisto (lisäkentät asetuksissa)
    $(document).on('click', '.jasrek-row-add', function () {
        var $row = $(document.querySelector($(this).data('tpl')).innerHTML);
        $($(this).data('table')).find('tbody').append($row);
        $row.find('input[type=text]').first().trigger('focus');
    });
    $(document).on('click', '.jasrek-row-del', function () {
        var $tr = $(this).closest('tr');
        var nimi = $tr.find('input[type=text]').first().val();
        var msg = $(this).data('confirm-del') || 'Poistetaanko rivi?';
        if (!nimi || window.confirm('"' + nimi + '": ' + msg + ' Muutos tallentuu, kun tallennat asetukset.')) $tr.remove();
    });

    // Jäsenlomake: eroamispäivä näkyy, kun jäsenyys on "Eronnut"
    $(document).on('change', '#jasrek-tila', function () {
        $('.jasrek-ero').prop('hidden', $(this).val() !== 'eronnut');
    });

    // Jäsenlomake: laskulle lisättävän summan päivitys jäsenluokan mukaan (ellei summaa ole muutettu käsin)
    $(document).on('input', '.jasrek-laskulle-rivi input[type=text]', function () { $(this).data('touched', true); });
    $(document).on('change', '#jasrek-laji', function () {
        var laji = $(this).val();
        $('.jasrek-laskulle-rivi').each(function () {
            var map = $(this).data('hinnat') || {};
            var $in = $(this).find('input[type=text]');
            if ($in.data('touched')) return;
            var v = Object.prototype.hasOwnProperty.call(map, laji) ? map[laji] : map[''];
            $in.val(Number(v || 0).toFixed(2).replace('.', ','));
        });
    });

    // Klikattavat paikkamerkit: lisätään kohdistimen kohdalle (tai valitun tekstin tilalle)
    $(document).on('click', '.jasrek-ph', function (e) {
        e.preventDefault();
        var el = $($(this).data('target')).get(0);
        if (!el) return;
        var ph = String($(this).data('ph'));
        var start = typeof el.selectionStart === 'number' ? el.selectionStart : el.value.length;
        var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : el.value.length;
        el.focus();
        if (typeof el.setRangeText === 'function') {
            el.setRangeText(ph, start, end, 'end');
        } else {
            el.value = el.value.slice(0, start) + ph + el.value.slice(end);
            el.selectionStart = el.selectionEnd = start + ph.length;
        }
        $(el).trigger('input');
        var $b = $(this).addClass('jasrek-ph-ok');
        setTimeout(function () { $b.removeClass('jasrek-ph-ok'); }, 400);
    });

    // Tunnukset isoilla kirjaimilla
    $(document).on('input', '.jasrek-upper', function () {
        var p = this.selectionStart;
        this.value = this.value.toUpperCase();
        try { this.setSelectionRange(p, p); } catch (err) {}
    });

    // Erälähetys AJAXilla edistymispalkin kanssa
    function run($btn) {
        if ($btn.data('running')) return;
        $btn.data('running', true);
        var $panel = $btn.closest('.jasrek-panel');
        var $prog = $panel.find('.jasrek-progress').prop('hidden', false);
        var $bar = $prog.find('.jasrek-progress-bar span'), $txt = $prog.find('.jasrek-progress-text');
        var params = $.extend({}, $btn.data('params'));
        var tot = { sent: 0, failed: 0, errors: [] };
        $panel.find('.jasrek-run, .button').prop('disabled', true).addClass('disabled');
        $(window).on('beforeunload.jr', function () { return 'Lähetys on kesken.'; });

        function finish(msg, isError) {
            $(window).off('beforeunload.jr');
            $txt.html('');
            $txt.append($('<strong>').text(msg));
            if (tot.errors.length) {
                var $ul = $('<ul class="jasrek-errors">');
                tot.errors.forEach(function (er) { $ul.append($('<li>').text(er)); });
                $txt.append($ul);
            }
            $prog.toggleClass('is-error', !!isError || tot.failed > 0);
            var $again = $('<a class="button button-primary">').text('Päivitä näkymä').attr('href', $btn.data('done-url') || window.location.href.replace(/[?&]jasrek_msg=[^&]*/, ''));
            $txt.append($('<p>').append($again));
        }

        function step() {
            $.post(JASREK.ajax, $.extend({ action: $btn.data('action') }, params)).done(function (res) {
                if (!res || !res.success) { finish('Virhe: ' + (res && res.data ? res.data : 'tuntematon virhe'), true); return; }
                var d = res.data;
                tot.sent += d.sent; tot.failed += d.failed;
                tot.errors = tot.errors.concat(d.errors || []);
                if (typeof d.offset !== 'undefined') params.offset = d.offset;
                var done = tot.sent + tot.failed, all = done + d.remaining;
                var pct = all ? Math.round(done / all * 100) : 100;
                $bar.css('width', pct + '%');
                $txt.text('Lähetetty ' + tot.sent + ' / ' + all + (tot.failed ? ', epäonnistui ' + tot.failed : '') + '…');
                if (d.remaining > 0 && (d.sent + d.failed) > 0) {
                    step();
                } else {
                    $bar.css('width', '100%');
                    finish('Valmis! Lähetetty ' + tot.sent + ' viestiä' + (tot.failed ? ', epäonnistui ' + tot.failed + '.' : '.'));
                }
            }).fail(function (xhr) {
                finish(xhr.status === 403
                    ? 'Lähetys pysäytettiin: kirjautuminen on vanhentunut tai tiedot on sillä välin palautettu varmuuskopiosta. Päivitä sivu ja tarkista tilanne ennen jatkamista – jo lähetettyjä ei lähetetä uudelleen.'
                    : 'Yhteysvirhe (' + xhr.status + '). Voit jatkaa lähetystä päivittämällä sivun – jo lähetettyjä ei lähetetä uudelleen.', true);
            });
        }
        $txt.text('Aloitetaan…');
        step();
    }

    $(document).on('click', '.jasrek-run', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.data('confirm') && !window.confirm($btn.data('confirm'))) return;
        run($btn);
    });
    $(function () {
        $('.jasrek-run[data-autostart]').each(function () { run($(this)); });
    });

    // Asetussivun välilehdet: kaikki kentät ovat samassa lomakkeessa, joten tallennus koskee aina kaikkia.
    // Ilman JavaScriptiä kaikki välilehdet näkyvät allekkain.
    (function () {
        var $nav = $('.jasrek-settabs');
        if (!$nav.length) return;
        var KEY = 'jasrek_asetus_tab';
        function show(k, keepHash) {
            if (!$nav.find('a[data-tab="' + k + '"]').length) k = $nav.find('a').first().data('tab');
            $('.jasrek-tab').each(function () { $(this).prop('hidden', $(this).data('tab') !== k); });
            $nav.find('a').removeClass('nav-tab-active').filter('[data-tab="' + k + '"]').addClass('nav-tab-active');
            try { window.localStorage.setItem(KEY, k); } catch (err) { /* ei tallennusta */ }
            if (!keepHash && window.history && history.replaceState) history.replaceState(null, '', '#' + k);
        }
        // Avaa välilehti, jolla annettu elementti on (esim. linkki …#jasrek-paperi-asetukset)
        function showFor(id) {
            var $el = id ? $(document.getElementById(id)) : $();
            if (!$el.length) return false;
            var $tab = $el.closest('.jasrek-tab');
            if (!$tab.length) return false;
            show($tab.data('tab'), true);
            setTimeout(function () { $el[0].scrollIntoView({ block: 'start' }); }, 0);
            return true;
        }
        var hash = window.location.hash.replace('#', '');
        if (!showFor(hash)) {
            var k = hash;
            if (!$nav.find('a[data-tab="' + k + '"]').length) {
                try { k = window.localStorage.getItem(KEY) || ''; } catch (err) { k = ''; }
            }
            show(k, !hash);
        }
        $nav.on('click', 'a', function (e) { e.preventDefault(); show($(this).data('tab')); });
        $(window).on('hashchange', function () {
            var h = window.location.hash.replace('#', '');
            if (!showFor(h) && $nav.find('a[data-tab="' + h + '"]').length) show(h, true);
        });
        $(document).on('click', 'a[href^="#jasrek-"]', function (e) {
            if (showFor(this.getAttribute('href').slice(1))) e.preventDefault();
        });
        // Virheellinen kenttä piilotetulla välilehdellä: näytetään se, jotta selain voi kertoa virheestä
        document.addEventListener('invalid', function (e) {
            var tab = $(e.target).closest('.jasrek-tab');
            if (tab.length && tab.prop('hidden')) show(tab.data('tab'));
        }, true);
    })();

    // Sähköpostin esikatselu: kehys yhtä korkeaksi kuin viesti
    function fitPreview(frame) {
        try {
            var doc = frame.contentDocument;
            if (doc && doc.body) frame.style.height = (doc.documentElement.scrollHeight + 4) + 'px';
        } catch (err) { /* ei saatavilla – oletuskorkeus jää */ }
    }
    $('.jasrek-preview-frame').each(function () {
        var f = this;
        $(f).on('load', function () { fitPreview(f); });
        fitPreview(f);
    });
})(jQuery);
