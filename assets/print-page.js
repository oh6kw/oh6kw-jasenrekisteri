/* OH6KW Jäsenrekisteri – tulostettavien sivujen (paperilaskut, äänioikeusluettelo, tietosuojaseloste) painikkeet */
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-jasrek-print], [data-jasrek-confirm], [data-jasrek-copy]') : null;
        if (!el) return;
        if (el.hasAttribute('data-jasrek-copy')) {
            var src = document.querySelector(el.getAttribute('data-jasrek-copy'));
            var label = el.textContent;
            var ok = function () { el.textContent = '✓ Kopioitu'; setTimeout(function () { el.textContent = label; }, 2000); };
            if (src && navigator.clipboard) navigator.clipboard.writeText(src.innerText).then(ok);
            return;
        }
        if (el.hasAttribute('data-jasrek-print')) {
            window.print();
        } else if (!window.confirm(el.getAttribute('data-jasrek-confirm'))) {
            e.preventDefault();
        }
    });
    window.addEventListener('load', function () {
        // Osoitetarrat: pitkä nimi tai osoite pienennetään mahtumaan tarraan (enintään 6,5 pt:iin)
        Array.prototype.forEach.call(document.querySelectorAll('.tarra .rivit'), function (r) {
            var pt = 10, yli = function () {
                if (r.scrollHeight > r.parentNode.clientHeight) return true;
                for (var i = 0; i < r.children.length; i++) if (r.children[i].scrollWidth > r.children[i].clientWidth + 1) return true;
                return false;
            };
            while (yli() && pt > 6.5) { pt -= 0.5; r.style.fontSize = pt + 'pt'; }
        });
        // Tilisiirtomalli: jos saate ei mahdu lomakkeen yläpuolelle, tekstiä pienennetään (enintään 8,5 pt:iin)
        // ja jos se ei vieläkään mahdu, yläpalkissa näytetään varoitus.
        var yli = [];
        Array.prototype.forEach.call(document.querySelectorAll('.ts-keski'), function (k) {
            var saate = k.querySelector('.saate'), pt = 10.5;
            while (k.scrollHeight > k.clientHeight + 1 && saate && pt > 8.5) {
                pt -= 0.5;
                saate.style.fontSize = pt + 'pt';
                saate.style.lineHeight = '1.35';
            }
            if (k.scrollHeight > k.clientHeight + 1) {
                k.parentNode.classList.add('ts-ylivuoto');
                var n = k.querySelector('.vastaanottaja strong');
                yli.push(n ? n.textContent : '?');
            }
        });
        // Kuoren ikkuna: palautusosoite enintään kaksi riviä (fontti pienenee 6 pt:iin asti),
        // eikä vastaanottajan osoite saa jatkua ikkunan alle.
        var ikkuna = [];
        Array.prototype.forEach.call(document.querySelectorAll('.ikkuna'), function (ik) {
            var p = ik.querySelector('.palautus'), pt = 7.5, ok = true;
            while (p && p.scrollHeight > p.clientHeight + 1 && pt > 6) {
                pt -= 0.5;
                p.style.fontSize = pt + 'pt';
            }
            if (p && p.scrollHeight > p.clientHeight + 1) ok = false;
            if (ik.scrollHeight > ik.clientHeight + 1) ok = false;
            if (!ok) {
                var n = ik.querySelector('.vastaanottaja strong');
                ikkuna.push(n ? n.textContent : '?');
            }
        });
        // Tilisiirtolomakkeen Saaja: pitkä osoite pienennetään 7 pt:iin, jotta se mahtuu kiinteän korkuiseen kenttään
        Array.prototype.forEach.call(document.querySelectorAll('.ts-saaja, .ts-maksaja-os'), function (o) {
            var pt = parseFloat(getComputedStyle(o).fontSize) * 0.75;
            while (o.scrollHeight > o.clientHeight + 1 && pt > 7) {
                pt -= 0.5;
                o.style.fontSize = pt + 'pt';
                o.style.lineHeight = '1.15';
            }
        });
        var wi = document.getElementById('jasrek-ikkuna-yli');
        if (wi && ikkuna.length) { wi.querySelector('span').textContent = ikkuna.join(', '); wi.hidden = false; }
        var w = document.getElementById('jasrek-ylivuoto');
        if (w && yli.length) { w.querySelector('span').textContent = yli.join(', '); w.hidden = false; }
        if (document.body.getAttribute('data-jasrek-autoprint') === '1') window.print();
    });
})();
