# Muutosloki – Jäsenrekisteri

Tekijä: Jarno Jaskari (oh6kw). Versiosta 3.0 alkaen lisäosaa on kehitetty yhdessä Claude-tekoälyn (Anthropic) kanssa.

## Tulossa seuraavaan versioon

### Tekstit
- wordpress.orgin kuvaus: osoitetarrojen usein kysytyssä kysymyksessä painikkeen ja sivun nimet englanniksi ja suomenkielinen nimi suluissa, kuten asennusohjeessa (*Print address labels (Tulosta osoitetarrat) on the Members page (Jäsenet)*).

## 4.8.6 (2026-10-18)

### Uutta: Osoitetarrat ja lähettäjätarrat
- Jäsenet-sivun yläreunaan, *Tulosta jäsenluettelo* -painikkeen viereen, uusi painike **Tulosta osoitetarrat**. Lisäksi jäsenlistan joukkotoiminnoissa on *Tulosta → Osoitetarrat valituille*.
- **Kenelle:** paperilaskun saajat (oletus), kaikki voimassa olevat, jäsenluokka, eronneet, kaikki (myös eronneet) tai valitut jäsenet. Jos jäsenlistassa on auki jäsenluokan tai eronneiden välilehti, tarrasivu avautuu sen mukaisena.
- **Lähettäjätarrat:** yhdistyksen nimi ja palautusosoite (tai yhdistyksen osoite) haluttu määrä kertaa, esim. koko arkillinen kuorien taakse.
- **Tarra-arkki:** valmiit yleiset A4-arkit (24 tarraa 70 × 36 mm, 24 tarraa 70 × 37 mm, 21 tarraa 70 × 42,3 mm, 27 tarraa 70 × 32 mm, 14 tarraa 105 × 42,3 mm) tai **oma arkki** millimetreinä (sarakkeet, rivit, tarran koko, reunat ja välit).
- **Aloita tarrasta nro:** osittain käytetyn arkin voi käyttää loppuun. Käytetyt paikat näkyvät esikatselussa raidallisina.
- **Hienosäätö** oikealle/alas millimetreinä tulostimen heiton korjaamiseen. Arkin valinta, omat mitat ja hienosäätö muistetaan seuraavalla kerralla.
- **Tarran sisältö:** nimi, katuosoite sekä postinumero ja postitoimipaikka isoilla kirjaimilla Postin suosituksen mukaan (60100 SEINÄJOKI). Osoitteen pilkut erottavat rivit (esim. c/o-rivi). Pitkä nimi tai osoite pienenee automaattisesti mahtumaan tarraan.
- Jäsenet, joilta osoite puuttuu, luetellaan työkalurivillä, eikä heille tulosteta tyhjää tarraa.
- Ohje työkalurivillä: marginaalit ei mitään, skaalaus 100 %, kokeilu ensin tavalliselle paperille.
- Tulostus kirjataan muutoslokiin.

### Tekstit ja kuvat
- README ja wordpress.orgin kuvaus: uusi kohta *Tulosteet ja viennit* (osoitetarrat, jäsenluettelo, kaikki tiedot Exceliin), osoitetarrat mainitaan esittelyssä, ja wordpress.orgiin uusi usein kysytty kysymys osoitetarroista.
- Uudet kuvakaappaukset: osoitetarrat (README 27, wordpress.org 13) ja jäsenluettelo (README 28, wordpress.org 14).

### Julkaisuautomaatio
- GitHubin julkaisu vie version nyt myös WordPress.orgiin (trunk, tags/VERSIO ja assets), kun repositorioon on asetettu salaisuudet `SVN_USERNAME` ja `SVN_PASSWORD`. SVN-komentoja ei tarvitse enää ajaa käsin. Jo WordPress.orgissa oleva versio ohitetaan.
- Ajoon asennetaan Subversion (GitHubin uusissa Ubuntu-koneissa sitä ei ole valmiina).
- WordPress.orgin assets-kansioon viedään vain kuvat ja valmis `blueprint.json`, ei esikatselun lähdetiedostoja.
- Ei vaikuta lisäosaan.

## 4.8.5 (2026-10-17)

### Uutta: Tulosta jäsenluettelo
- Jäsenet-sivun yläreunaan, *Vie kaikki tiedot Exceliin* -painikkeen viereen, uusi painike **Tulosta jäsenluettelo**. Se avaa uuteen välilehteen siistin, vaakasuuntaisen A4-luettelon paperille tai PDF:ksi.
- Sarakkeet: järjestysnumero, nimi (sukunimi etunimi), tunnus (esim. kutsumerkki), jäsenluokka, valitut omat lisäkentät (esim. kerhon avain), sähköposti, puhelin, osoite, liittymispäivä ja syntymävuosi/-päivä. Eronneita tulostettaessa myös eroamispäivä.
- Työkalurivillä valitaan: voimassa olevat / eronneet / kaikki, jäsenluokka, järjestys (sukunimi tai tunnus) ja näytettävät sarakkeet (jäsenluokka ja jokainen oma lisäkenttä). Oletuksena näytetään lisäkentät, jotka näkyvät myös jäsenlistassa. Jos jäsenlistassa on auki jäsenluokan tai eronneiden välilehti, tuloste avautuu sen mukaisena.
- Ohje samaan tapaan kuin muissa tulosteissa: *Tulosta tämä paperille tai tallenna PDF:nä (tulostusikkunassa tulostimeksi Tallenna PDF-tiedostona).* Otsikkorivi toistuu jokaisella sivulla.
- Alareunassa muistutus: luettelo sisältää henkilötietoja, säilytä huolellisesti ja hävitä tarpeeton tuloste.
- Tulostus kirjataan muutoslokiin.

### Muuta
- Äänioikeusluettelon työkalurivillä on nyt sama tulostus- ja PDF-ohje kuin muissa tulosteissa.

## 4.8.4 (2026-10-17)

### Uutta: Vie kaikki tiedot Exceliin
- Jäsenet-sivun yläreunaan, *Lisää uusi jäsen* -painikkeen viereen, uusi painike **Vie kaikki tiedot Exceliin**. Se tekee koko rekisteristä yhden Excel-tiedoston samalla tyylillä kuin laskujen Excel-raportti (siniset otsikot, kiinnitetty otsikkorivi, suodattimet, eurot ja päivämäärät oikeassa muodossa).
- Välilehdet:
  - **Yhteenveto:** yhdistys, vientiaika, jäsenmäärät jäsenluokittain, eronneet, laskut, avoimet maksut ja niiden summa.
  - **Jäsenet:** kaikki jäsenet (myös eronneet) kaikkine tietoineen, omat lisäkentät omina sarakkeinaan.
  - **Jokainen oma lisäkenttä omalla välilehdellään** (esim. *Kerhon avain*): vain jäsenet, joilla kentässä on arvo, järjestettynä arvon mukaan (8.2 ennen 8.10), sekä jäsenyys ja yhteystiedot.
  - **Jäsenluokat:** jäsenmaksu sekä voimassa olevat ja eronneet jäsenet luokittain.
  - **Laskut:** jokaisen laskun laskutettu, postimaksut, maksettu ja avoinna, yhteensä-rivi.
  - **Laskurivit:** jokainen jäsenelle lähetetty lasku: laskutapa (sähköposti/paperi), viite, summa, eräpäivä, tila, maksupäivä, maksettu summa, maksaja, arkistotunnus, lähetys ja muistutukset.
  - **Avoimet maksut:** maksamattomat laskut kaikista laskuista.
  - **Muutosloki:** kaikki tapahtumat muutoksineen.
- Vienti kirjataan muutoslokiin. Tiedosto on luettavaksi ja arkistoon; tietojen palauttamiseen käytetään edelleen varmuuskopiota.

## 4.8.3 (2026-10-17)

### Korjattu
- **Jäsenten tuonti CSV:stä:** kaksi eri henkilöä, joilla on sama sähköpostiosoite (esim. vanhempi ja nuorisojäsen), yhdistyivät tuonnissa yhdeksi jäseneksi, kun valittuna oli *Päivitä*, tai jälkimmäinen ohitettiin, kun valittuna oli *Ohita*. Nyt pelkkä sähköposti tunnistaa jäsenen vain, jos myös etunimi on sama (esim. sukunimi on vaihtunut). Tuontisivun teksti kertoo säännön: *sama nimi, tai sama sähköposti ja etunimi*.
- **Jäsenten tuonti CSV:stä:** samannimiset eri henkilöt yhdistyivät, vaikka heillä oli eri tunnus (esim. kutsumerkki tai jäsennumero). Nyt eri tunnus tarkoittaa aina eri henkilöä, kun jäsentä etsitään nimen tai sähköpostin perusteella. Jos rekisterissä on useampi samanniminen, tiedoston tunnus valitsee oikean.

## 4.8.2 (2026-10-17)

### Uutta: Jäsenmaksut kirjanpitoon
- Uusi välilehti **Vuosiraportti → Jäsenmaksut kirjanpitoon**: raportti rahastonhoitajalle ja kirjanpitäjälle. Lisäosa ei tee kirjanpitoa, vaan kokoaa jäsenmaksuista sen, mitä kirjanpitoon tarvitaan, käytti kirjanpitäjä mitä ohjelmaa tahansa.
- **Aikaväli** vapaasti (alkaen–päättyen) tai pikavalinnoilla: tilikausi, edellinen kuukausi, kuluva kuukausi. Oletuksena kuluva tilikausi (tammi–helmikuussa edellinen).
- **Vientiehdotus** kahdenkertaiseen kirjanpitoon: *debet Pankkitili / kredit Jäsenmaksutuotot*, maksupäivittäin (oletus, täsmää tiliotteeseen) tai kuukausittain (päivänä kuun viimeinen päivä). Selitteessä maksujen määrä ja laskun nimi. Paperilaskujen postimaksut omana vientinään.
- **Tilit** asetetaan kohdassa *Asetukset → Jäsenmaksut kirjanpitoon* (esim. *1910 Pankkitili*, *3000 Jäsenmaksutuotot*). Tyhjänä käytetään tilin nimeä. Postimaksuille voi antaa oman tilin tai saman kuin jäsenmaksuille.
- **Jäsenluokittain:** maksut, jäsenmaksut ja postimaksut luokittain (luokka laskutushetken mukaan).
- **Poikkeavat maksut:** maksut, joiden summa poikkeaa laskusta (yli- ja alimaksut), maksajan nimi ja erotus. Vientiehdotuksessa on todellinen maksettu summa.
- **Avoimet jäsenmaksut aikavälin lopussa** laskuittain (tilinpäätöksen saamisia varten).
- **Tulosta** (sama tulostettava sivu kuin vuosiraportissa) ja **Vie Exceliin**: välilehdet *Vientiehdotus*, *Maksut* (jokainen maksu jäsenittäin: maksupäivä, jäsen, lasku, viite, jäsenluokka, laskutettu, maksettu, postimaksu, maksaja), *Jäsenluokittain*, *Poikkeavat maksut* ja *Avoimet* jäsenittäin. Excel-vienti kirjataan muutoslokiin.
- Summat on tarkistettu tietokannan maksuja vasten; maksupäivittäinen ja kuukausittainen vientiehdotus antavat saman loppusumman.

### Tekstit ja kuvat
- **Toimii vain hallintapaneelissa:** README, wordpress.orgin kuvaus ja Tietoja-sivu kertovat, ettei lisäosa julkaise sivustolle mitään (ei sivuja, lomakkeita, lyhytkoodeja eikä lohkoja) ja että WordPress hoitaa kirjautumisen, käyttäjät, automaattiset päivitykset sekä webhotellin varmuuskopiot ja HTTPS:n.
- wordpress.orgin kuvaus jäsennelty uudelleen: sähköposti- ja paperilasku omina kohtinaan, *Raportit vuosikokoukseen ja kirjanpitäjälle*, *Tietosuoja, varmuuskopio ja siirto*. Uudet usein kysytyt kysymykset: näkyykö sivustolla jotain, ja tekeekö lisäosa kirjanpidon.
- Uusi kuvakaappaus *Jäsenmaksut kirjanpitoon* (README ja wordpress.org, kuva 12).
- Uusi wordpress.orgin ikoni ja banneri, joissa pieni Suomen lippu (lähteet `docs/brand/`).
- Kuvat 02, 05 ja 11 otettu uudelleen: päivämäärät suomalaisessa muodossa ja jäsenlomake nykyisen näköisenä.
- Vanhentunut ohje päivityksestä versiosta 4.6.4 tai vanhemmasta poistettu.

### Muuta
- GitHubin julkaisuautomaatio: käsin tehty julkaisu ei enää käynnistä automaatiota kahdesti (aiemmin toinen ajo näkyi epäonnistuneena), ja työkalut päivitetty (Node.js 20 -varoitus poistuu). Ei vaikuta lisäosaan.

## 4.8.1 (2026-10-17)

### Sähköpostilasku: maksukortti tilisiirtolomakkeen tilalle
- Sähköpostin **tilisiirtolomakemalli poistettiin**. HTML-lomakkeen viivat eivät kohdistuneet eri sähköpostiohjelmissa, lomake näytti kapealta, eikä se toiminut puhelimessa. Sähköpostista maksetaan kopioimalla tiedot tai skannaamalla koodi, ei lomakkeelta.
- Tilalle tuli **Maksukortti** (uusi oletus): saate, laskun nimi ja jäsenen tiedot, kortissa maksettava summa ja eräpäivä isolla sekä allekkain *Saaja, Saajan tilinumero (IBAN), BIC, Viitenumero, Eräpäivä, Summa* – helppo valita ja kopioida myös puhelimessa. Lisäksi QR-koodi (kohdan *Maksukoodi laskussa* mukaan, tekstinä ohje skannata se puhelimella, kun viestiä luetaan tietokoneella) ja virtuaaliviivakoodi.
- Pankkiviivakoodia ei näytetä maksukortissa, koska sitä ei voi lukea näytöltä. Tulostettava lasku viivakoodeineen tulee PDF-liitteenä, jos se on valittu; silloin viestissä mainitaan liitteestä.
- Toimii yhtenä palstana kaikilla näytöillä (inline-tyylit, ei mediakyselyjä).
- *Perusmalli* säilyy valittavana ennallaan.
- Päivitys: jos asetuksena oli tilisiirtolomake, käytetään automaattisesti maksukorttia.

### Laskun rivilista: Tulosta-painike PDF-painikkeen tilalle
- Sähköpostilaskun saajan rivillä on nyt **Tulosta** (erääntyneelle maksamattomalle **Tulosta muistutus**). Se avaa saman tulostusnäkymän kuin paperilaskut, yläpalkkeineen. Laskun voi tulostaa tai tallentaa PDF:nä selaimen tulostusikkunasta ja lähettää jäsenelle omasta sähköpostista.
- Näkymän otsikko on esim. *Lasku: Jäsenmaksu 2027 – Anna Aaltonen*, eikä siinä ole *Merkitse postitetuksi* -painiketta: tulostaminen ei muuta sähköpostilaskun tilaa.
- 4.8.0:n PDF-latauspainike poistettiin. PDF-liite sähköpostissa toimii ennallaan.

### Live-esikatselu wordpress.orgissa (WordPress Playground)
- Uusi `.wordpress-org/blueprints/blueprint.json` (SVN:ssä `assets/blueprints/blueprint.json`). Se asentaa lisäosan wordpress.orgista kokeiluympäristöön, vaihtaa WordPressin suomeksi, luo keksityt esimerkkijäsenet (12 kpl, kolme jäsenluokkaa, yksi paperilaskun saaja) ja yhden laskun, joka on osittain lähetetty ja maksettu, ja avaa jäsenlistan.
- Esikatselussa jäsenrekisterin sivuilla näkyy ilmoitus, että kyse on kokeiluympäristöstä. Sähköposteja ei yritetä lähettää.
- Lähdetiedostot `demo-seed.php` ja `preview-notice.php`; blueprint.json tehdään niistä komennolla `python3 .wordpress-org/blueprints/build.py`. Blueprint on tarkistettu Playgroundin virallista skeemaa vasten, ja demoaineiston luonti on testattu tyhjällä SQLite-asennuksella (kuten Playgroundissa).

### Kuvakaappaukset
- Uudet kuvat (README ja wordpress.org): laskun sivu Tulosta-painikkeineen, sähköpostilasku maksukorttina tietokoneella ja puhelimessa, paperilaskut sekä tulostettava paperilasku tilisiirtolomakkeella (oletusmalli). Vanhat kuvat perusmallin paperilaskusta ja sähköpostin tilisiirtolomakkeesta poistettiin.

## 4.8.0 (2026-10-17)

### Uutta: PDF-lasku
- **Sähköpostilasku PDF-liitteenä** (valinnainen): *Asetukset → Sähköpostilaskun malli → Liitä lasku PDF:nä*. Oletuksena pois päältä.
  - PDF on sama A4-lasku kuin paperilaskun tilisiirtolomakemalli: lähettäjä, ikkunakuoren osoite (ja palautusosoite), laskun tiedot, saate, maksuohje, QR-koodi sekä tilisiirtolomake pankkiviivakoodeineen. Jäsen voi tulostaa ja arkistoida laskun, ja tulostettuna viivakoodi toimii.
  - Koskee laskuja, muistutuksia (otsikko *MAKSUMUISTUTUS*, muistutusteksti) ja testilaskuja. Liitteen nimi on esim. `Jasenmaksu-2027-200017.pdf`.
  - Liite on noin 5–10 kt. Jos PDF:n tekeminen jostain syystä epäonnistuu, lasku lähtee silti ilman liitettä.
  - QR-koodi ja pankkiviivakoodi piirretään PDF:ään vektoreina, joten ne ovat terävät millä tahansa tulostimella. Molemmat on tarkistettu viivakoodinlukijalla (QR: EPC-tiedot, viivakoodi: virtuaaliviivakoodi).
  - Ääkköset ja €-merkki toimivat. Muut erikoismerkit (esim. Ł, č) muutetaan lähimmäksi kirjaimeksi.
- **PDF-linkki laskun rivilistassa:** jokaisen sähköpostilaskun saajan kohdalla on *PDF*-painike, joka avaa jäsenen laskun PDF:nä. Erääntyneelle maksamattomalle laskulle painike on *PDF (muistutus)*. Rahastonhoitaja voi lähettää PDF:n jäsenelle omasta sähköpostistaan, jos jäsen on hukannut laskunsa. Lataus kirjataan muutoslokiin.
- PDF tehdään FPDF-kirjastolla (versio 1.86, vapaa ja GPL-yhteensopiva lisenssi). Kirjaston luokka on nimetty uudelleen, jotta se ei törmää muiden lisäosien FPDF:ään, ja siitä on poistettu kuvatiedostojen lukeminen (ei tiedostojärjestelmän käsittelyä). Ei vaadi PHP:n lisälaajennuksia (toimii myös ilman mbstring- ja GD-laajennuksia).

### Jäsenten lisätunniste ylemmäs
- *Jäsenten lisätunniste* -asetukset (käytössä, kentän nimi, isot kirjaimet) siirrettiin omasta osiostaan *Asetukset → Yhdistys ja tilitiedot* -kohtaan. Kentän nimen esimerkkeinä *Jäsennumero, Tunnus, Kutsutunnus*.
- Jäsenen lomakkeella lisätunnisteen vieressä on linkki *muuta nimeä*, joka vie suoraan asetukseen.

## 4.7.2 (2026-10-17)

### Yhdistyksen osoite ja palautusosoite erikseen
- **Uusi kenttä *Yhdistyksen osoite*** kohdassa *Asetukset → Yhdistys ja tilitiedot*: yhdistyksen virallinen osoite. Se näkyy tilisiirtolomakkeen Saaja-kentässä (paperi- ja sähköpostilasku) ja tietosuojaselosteen rekisterinpitäjän tiedoissa.
- **Palautusosoite (kuoren ikkuna)** pysyy omana kenttänään kohdassa *Paperilaskut*, ja sitä käytetään vain kuoren ikkunan palautusosoitteessa. Se voi olla eri kuin yhdistyksen osoite, esim. rahastonhoitajan osoite, jos yhdistyksellä ei ole postilaatikkoa.
- Päivitettäessä mikään ei muutu itsestään: aiemmin annettu osoite säilyy palautusosoitteena. Niin kauan kuin yhdistyksen osoite on tyhjä, Saaja-kentässä on pelkkä yhdistyksen nimi ja tietosuojaseloste käyttää palautusosoitetta.
- Uusi asetus on mukana varmuuskopiossa ja muutoslokissa.

### Korjaukset paperilaskuun
- **Tilisiirtolomake leikkautui alareunasta** joillakin tulostimilla: lomakkeen alaosa (ehdot ja *PANKKI BANKEN*) jatkui noin 1,5 mm varatun alueen ali. Lomakkeen rivejä tiivistettiin hieman (yläreunan katkoviiva, tilinumero- ja saajarivi), joten koko lomake mahtuu nyt 88 mm:n alueeseen 6 mm sivun reunasta.
- **Saaja- ja maksajakentät eivät enää venytä lomaketta:** kentillä on kiinteä korkeus. Saajassa on enintään kolme riviä (ylimenevät yhdistetään viimeiselle riville), ja pitkä teksti pienennetään tarvittaessa 7 pt:iin.
- **Palautusosoite ikkunassa:** näkyy nyt kokonaan enintään kahdella rivillä (aiemmin yksi rivi, joka katkesi). Pitkä osoite pienennetään tarvittaessa 6 pt:iin. Jos palautusosoite tai vastaanottajan osoite ei silti mahdu kuoren ikkunaan, tulostussivun yläpalkissa näytetään varoitus ja niiden jäsenten nimet.
- Asetuksissa ohje palautusosoitteen pituudesta (noin 110 merkkiä yhdistyksen nimi mukaan lukien).

### Muuta
- GitHub-repon nimi on nyt `oh6kw/oh6kw-jasenrekisteri`; linkit päivitetty (Plugin URI, README, readme.txt, Tietoja-sivu).

## 4.7.1 (2026-10-15)

### Sähköpostilasku tilisiirtolomakkeella
- **Pankkiviivakoodi mukaan:** tilisiirtomallin sähköpostilasku noudattaa nyt samaa asetusta *Maksukoodi laskussa* kuin paperilasku ja perusmalli. Oletuksella (vain pankkiviivakoodi) viivakoodi on lomakkeen alla, ja QR-koodi tulee lomakkeen yläpuolelle, jos se on valittu. Kuvat upotetaan viestiin samalla tavalla kuin perusmallissa. Virtuaaliviivakoodi on edelleen myös tekstinä.
- Erillinen ruksi *QR-koodi sähköpostilaskussa* poistettiin, koska maksukoodiasetus hoitaa sen.
- **Leveämpi:** tilisiirtomallin sähköpostilasku on 720 px leveä (aiemmin 600 px), joten lomake mahtuu väljemmin. Puhelimessa lomake asettuu edelleen kahteen osaan allekkain.

## 4.7.0 (2026-10-15)

### Uutta: varmuuskopio ja siirto
- **Asetukset → Varmuuskopio ja siirto:** koko jäsenrekisteri yhteen tiedostoon ja takaisin. Käyttötarkoituksia:
  - rekisterin siirto toiselle sivustolle (esim. kun yhdistys vaihtaa sivustoa tai rekisteri siirtyy uudelle rahastonhoitajalle)
  - turvakopio esimerkiksi ennen laskutusta tai massamuutoksia
  - Koko sivuston siirrossa (siirtolisäosalla tai webhotellin työkalulla) rekisteri siirtyy jo muun tietokannan mukana.
- **Lataa varmuuskopio:**
  - Tiedostossa ovat jäsenet (myös eronneet), laskut, laskurivit maksutietoineen ja asetukset (jäsenluokat, lisäkentät, saatetekstit ym.). Muutoslokin voi ottaa mukaan tai jättää pois.
  - Muoto on JSON. Tiedosto ladataan pakattuna (`.json.gz`, noin kymmenesosa koosta), jotta suurikin rekisteri mahtuu sivustojen tavalliseen 2 Mt:n latausrajaan. Sen voi purkaa tavalliseksi JSONiksi esim. 7-Zipillä.
  - Rivit tallennetaan tunnisteineen, joten laskurivit pysyvät oikeiden jäsenten kohdalla ja viitenumerot ja maksut toimivat palautuksen jälkeen.
  - Lataus kirjataan muutoslokiin, ja sivulla muistutetaan, että tiedosto sisältää henkilötietoja.
- **Palauta varmuuskopiosta:**
  - Kelpaa `.json` tai `.json.gz`.
  - Tiedosto tarkistetaan kokonaan ennen kuin mitään muutetaan: muoto, versio, pakolliset taulut, tunnisteet (ei puuttuvia eikä kahdentuneita) ja arvojen tyypit. Virheellinen tiedosto hylätään selkeällä virheilmoituksella.
  - Uudemmalla lisäosan versiolla tehty varmuuskopio hylätään, ja ohjeeksi annetaan lisäosan päivitys ensin. Vanhemman version varmuuskopio päivitetään palautuksessa nykyiseen muotoon.
  - Ennen palautusta näytetään yhteenveto: yhdistys, sivusto, tekoaika ja versio sekä jäsenten, laskujen, laskurivien ja lokimerkintöjen määrät rinnakkain nykyisten kanssa. Palautus pitää vahvistaa erikseen.
  - Palautus korvaa kaikki jäsenet, laskut, laskurivit ja asetukset. Muutosloki korvataan vain, jos se on varmuuskopiossa, muuten nykyinen loki säilyy. Tämän sivuston valinta *Poista tiedot lisäosan poistossa* säilyy aina.
  - Tunnistamattomat sarakkeet (esim. uudemmasta versiosta) ohitetaan, eikä tiedoston sisältö pääse SQL-kyselyihin sarakkeiden niminä.
  - Palautus tehdään tietokantatransaktiona, kun tietokanta tukee sitä.
- **Palautuksen voi perua:** nykyinen tilanne tallennetaan automaattisesti ennen palautusta. Asetuksissa näkyy silloin laatikko, jossa on valinnat *Peru palautus*, *Lataa tiedostona* ja *Poista*. Myös perumisen voi perua. Tallennettu tilanne sisältää henkilötietoja, joten sivu kehottaa poistamaan sen, kun palautus on todettu onnistuneeksi.
- Palautus ja perumiset kirjataan muutoslokiin (uusi toiminto *Varmuuskopio palautettu*).
- Toimii myös ilman PHP:n mbstring-laajennusta. Ilman zlibiä tiedosto ladataan pakkaamattomana JSONina.
- **Turvallisuus ja varmuus:**
  - **Palautus pysäyttää keskeneräiset toiminnot:** maksujen tuonnin esikatselu, jäsenten CSV-tuonnin esikatselu sekä kesken oleva laskujen tai tiedotteen lähetys. Ne viittaavat vanhoihin rivitunnisteisiin, ja ilman tätä esimerkiksi maksu olisi voinut kirjautua väärälle laskuriville. Tiedoilla on versio, joka vaihtuu palautuksessa. Esikatselu pyytää aloittamaan tuonnin uudelleen, ja lähetys pysähtyy selkeään ilmoitukseen ("päivitä sivu ja tarkista tilanne").
  - **Perumismahdollisuus varmistetaan ennen palautusta.** Jos nykyistä tilannetta ei voida tallentaa (esim. tietokannan kokoraja), palautusta ei tehdä, ja käyttäjää neuvotaan lataamaan ensin varmuuskopio.
  - **Luettu varmuuskopio odottaa vahvistusta tavallisena tietokanta-asetuksena eikä transientina.** Sivustoilla, joilla on ulkoinen välimuisti (Redis, Memcached), transient olisi voinut kadota kokorajan takia. Odottava tiedosto vanhenee tunnissa, ja vanhat siivotaan vuorokauden välein.
  - **Asetukset tarkistetaan palautuksessa:** vain tunnetut asetukset ja oikeat tyypit hyväksytään, ja virheellinen arvo korvataan nykyisellä. Jäsenluokat ja lisäkentät siistitään (tuntematon kenttätyyppi → teksti, virheelliset rivit pois).
  - **Palautusta edeltänyt tilanne poistetaan automaattisesti 30 päivän kuluttua,** koska se sisältää henkilötietoja. Poistopäivä näkyy asetuksissa.
  - **Vahvistussivu kertoo,** jos aiemman palautuksen peruttava tilanne korvautuu uudella (tallessa on aina vain yksi).
  - **Purettu varmuuskopio saa olla enintään 50 Mt,** jottei rikkinäinen tai valtava tiedosto kaada sivua muistin loppumiseen. Rikkinäinen pakattu tiedosto ei enää kirjoita PHP-varoitusta lokiin.
  - Jos tietokantataulut ovat vanhaa MyISAM-tyyppiä, transaktio ei ole käytössä. Keskeytynyt palautus korjataan silloin perumalla.

### Uutta: paperilasku tilisiirtolomakkeella
- **Asetukset → Paperilaskut → Laskun malli:** perusmallin rinnalle tuli *Tilisiirtolomake*, perinteinen suomalainen pankkisiirtolomake (tilisiirto / girering) sivun alareunaan. Tilisiirtolomake on oletus (ks. *Uudet oletukset*).
- **Sivun rakenne:**
  - Ylhäällä lähettäjä, ikkunakuoren osoite ja laskun tiedot, täsmälleen samassa kohdassa kuin perusmallissa. Osoite osuu C5- ja DL-kuorten ikkunaan, ja testissä sen paikka oli molemmissa malleissa 20 mm vasemmalta ja 27,1 mm ylhäältä.
  - Keskellä saateteksti.
  - Lomakkeen yläpuolella *Maksaminen*-ohje viitenumeroineen, virtuaaliviivakoodi ja QR-koodi (jos valittu).
  - **Tilisiirtolomake aina sivun alareunassa:**
    - saajan tilinumero (IBAN) ja BIC, saaja osoitteineen ja maksaja osoitteineen
    - allekirjoitus, tililtä nro ja viesti
    - viitenumero, eräpäivä ja summa euroina
    - pankkiviivakoodi ja pankin vakioteksti (suomeksi ja ruotsiksi)
    - leikkausviiva ✂
- Jos saate jää lyhyeksi, väliin jää tyhjää ja lomake pysyy alareunassa. Erittely ja yhteensä-laatikko jätetään tästä mallista pois, koska summat ovat jo tietolaatikossa ja lomakkeessa. Saatteelle jää noin puolet sivusta.
- **Liian pitkä saate:** tulostussivu pienentää saatteen tekstiä vähitellen (enintään 8,5 pt:iin). Jos teksti ei vieläkään mahdu, yläpalkissa näytetään varoitus jäsenten nimillä. Mitään ei jää lomakkeen alle piiloon.
- QR-koodi ja pankkiviivakoodi tulostuvat kohdan *Maksukoodi* mukaan (QR, viivakoodi, molemmat tai ei kumpaakaan).
- Sivu on tulosteessa koko A4:n korkuinen ja tekstikoko sama kuin esikatselussa (10,5 pt). Aiemman 4.6.4-perusmallin pienennetyt koot eivät koske tätä mallia.
- Tulostus: A4, reunukset *Oletus* tai *Ei mitään*, mittakaava 100 %. Testattu Chromen PDF-tulostuksella: yksi sivu laskua kohden, lomake 6 mm alareunasta.
- Perusmalli toimii kuten ennenkin. Sen yläosa ja saate tulevat nyt samasta koodista kuin tilisiirtomallissa.

### Uutta: sähköpostilasku tilisiirtolomakkeella
- **Asetukset → Yhdistys ja tilitiedot → Sähköpostilaskun malli:** *Perusmalli* (kuten ennen) tai *Tilisiirtolomake*. Tilisiirtolomake on sama ulkoasu kuin paperilaskussa.
- Lomake on HTML-taulukko inline-tyyleillä, joten se näkyy Gmailissa, Outlookissa ja puhelimen postiohjelmissa ilman kuvia. Tilinumeron, viitteen ja virtuaaliviivakoodin voi kopioida verkkopankkiin.
- **Viestissä ei ole liitteitä eikä kuvia.** Pankkiviivakoodia ei lähetetä kuvana, koska omalta näytöltä ei voi skannata. Virtuaaliviivakoodi on viestissä tekstinä.
- **QR-koodi on valinnainen:** ruksi *Lisää tilisiirtolomakkeen yläpuolelle myös QR-koodi* (oletuksena pois päältä). QR on upotettu kuva, ja osa sähköpostiohjelmista näyttää sen myös liitteenä.
- **Puhelimessa** lomakkeen vasen puolisko (tilinumero, saaja, maksaja) ja oikea puolisko (viite, eräpäivä, summa) asettuvat allekkain. Leveällä näytöllä ne ovat vierekkäin kuten paperilla. Toimii ilman mediakyselyjä, myös Gmailissa ja Outlookissa.
- Laskun esikatselu, testilasku, lähetys, muistutukset ja automaattiset muistutukset käyttävät valittua mallia.
- Viestin pelkkä tekstiversio (roskapostisuodattimia varten) muotoilee nyt myös otsikkosolut oikein.

### Uutta: tietosuoja (GDPR)
- **Jäsenrekisterin tietosuojaselosteen pohja** (Tietoja-sivu → *Jäsenrekisterin tietosuojaselosteen pohja*): yhdistyksen tiedoilla esitäytetty seloste tietosuoja-asetuksen 13 ja 14 artiklan mukaan. Se sisältää:
  - rekisterinpitäjä ja yhteyshenkilö
  - käsittelyn tarkoitus ja oikeusperuste (yhdistyslain 11 §:n jäsenluettelo, jäsenyys, ja syntymävuodelle suostumus, jos merkkipäivät ovat käytössä)
  - tietosisältö: käytössä olevat kentät, lisätunniste ja omat lisäkentät nimeltä
  - tietolähteet, luovutukset ja käsittelijät, siirrot EU:n ulkopuolelle
  - säilytysajat (kirjanpito 6 vuotta, muutosloki asetuksen mukaan), suojaus ja rekisteröidyn oikeudet
  - Täydennettävät kohdat on merkitty keltaisella *[täydennä]*.
  - Tekstiä voi muokata suoraan sivulla, ja sen voi tulostaa tai kopioida (*Kopioi teksti*) esimerkiksi yhdistyksen verkkosivuille.
  - Sivulla muistutetaan, että pohja ei ole oikeudellinen neuvo.
- **WordPressin henkilötietotyökalut** (*Työkalut → Vie henkilötiedot / Poista henkilötiedot*) tunnistavat nyt jäsenen sähköpostiosoitteella:
  - **Vienti:** jäsentiedot (myös lisäkentät) ja kaikki jäsenmaksut (lasku, viite, summa, eräpäivä, lähetys- ja maksupäivä, maksaja).
  - **Poisto nimettömöi jäsenen:**
    - Nimi, osoite, puhelin, sähköposti, lisätunniste, syntymävuosi, lisätiedot ja lisäkentät poistetaan, ja jäsen merkitään eronneeksi.
    - Laskuriveiltä poistetaan nimi, sähköposti ja maksaja.
    - Jäsenmaksujen summat ja päivät säilyvät kirjanpitoa ja vuosiraporttia varten.
    - Jäsenen muutoslokimerkinnät poistetaan, ja hänen nimensä korvataan muista merkinnöistä tekstillä *[poistettu]*.
    - Poisto kirjataan lokiin.
- **Ehdotus sivuston tietosuojaselosteeseen** (*Asetukset → Tietosuoja → Opas*): lisäosa kertoo, mitä tietoja se käsittelee. Se ei kerää tietoja sivuston kävijöistä eikä käytä evästeitä.

### Uudet oletukset
- **Uudessa asennuksessa** paperilasku ja sähköpostilasku käyttävät tilisiirtolomaketta, ja maksukoodina on vain pankkiviivakoodi (ei QR-koodia).
  - Paperilaskussa pankkiviivakoodi on lomakkeen alla.
  - Sähköpostilaskussa on virtuaaliviivakoodi tekstinä, joten viestissä ei ole liitteitä eikä kuvia.
- **Päivitettäessä** aiemman version maksukoodin valinta säilyy, jos asetukset on joskus tallennettu. Laskun mallit ovat uusia asetuksia, joten myös päivitetyllä sivustolla paperi- ja sähköpostilaskut tulevat tilisiirtolomakkeella. Perusmallin saa takaisin asetuksista.

### Muut muutokset
- **Käytöstä poiston kysely** suosittelee nyt koko varmuuskopiota: linkki *Lataa ensin varmuuskopio* lataa kaikki tiedot muutoslokeineen. Jäsenluettelon CSV on toissijaisena vaihtoehtona Exceliä varten.
- *Lisäosan poistaminen* -kohta asetuksissa ohjaa ottamaan varmuuskopion.
- Lisäosan poisto (kun tietojen poisto on valittu) poistaa myös tallessa olevan palautusta edeltäneen tilanteen ja vahvistusta odottavat varmuuskopiot.
- **Tekijä:** lisäosan otsakkeessa on nyt *Jarno Jaskari (oh6kw)*. Clauden (Anthropic) osuus kerrotaan README:ssä, readme.txt:ssä, CHANGELOGissa ja Tietoja-sivulla.
- **Palautelomakkeen** ruksi *Liitä mukaan versiotiedot (… sivuston osoite)* on nyt oletuksena pois päältä. Tietoja lähtee vain, jos käyttäjä itse valitsee.

## 4.6.5 (2026-10-15)

### Uutta: äänioikeusluettelo vuosikokoukseen
- Vuosiraportin painikkeiden (*Tulosta*, *Vie Exceliin*) jatkona on painike **🗳 Äänioikeusluettelo**. Se avaa tulostettavan A4-luettelon voimassa olevista jäsenistä aakkosjärjestyksessä. Sarakkeet: numero, nimi, lisätunniste (esim. jäsennumero), jäsenluokka, jäsenmaksun tila, äänioikeus (✓) ja allekirjoitus.
- **Äänioikeus:** jäsenmaksu valitulta laskulta on maksettu, tai jäsenluokan jäsenmaksu on 0 € (esim. kunniajäsen). Muiden tilaksi näytetään *Maksamatta* tai *Ei laskulla*.
- Luettelon yläpalkissa valitaan kokouksen nimi (oletuksena ”Vuosikokous” ja raporttia seuraava vuosi), päivä ja lasku (oletuksena uusin). Valinnalla *Vain äänioikeutetut* luettelo näyttää vain äänioikeutetut. Lopussa on äänioikeutettujen määrä.
- Monisivuinen luettelo toistaa otsikkorivin joka sivulla. Luettelon avaaminen kirjataan muutoslokiin, koska se sisältää henkilötietoja.

### Uutta: merkkipäivät
- Jäsenen tietoihin tuli vapaaehtoinen kenttä **Syntymävuosi tai -päivä** (liittymispäivän viereen). Se näkyy jäsenen lomakkeella vain, kun merkkipäivät ovat käytössä.
  - Kelpaa pelkkä vuosi (esim. 1976) tai päivämäärä (12.3.1976 tai 1976-03-12). Kaikki eivät halua ikäänsä julki, joten vuosi riittää: merkkipäivä näkyy silloin vuosiraportissa ja Excelissä muodossa *vuoden aikana* ja listan lopussa, mutta ei jäsenlistan 60 päivän kortissa.
  - Tyhjä kenttä tarkoittaa, ettei syntymävuotta tiedetä. Virheellinen tai tuleva vuosi/päivä ei tallennu, ja selain huomauttaa väärästä muodosta.
  - Kun merkkipäivät kytketään pois, kenttä piilotetaan, mutta jo tallennetut syntymävuodet säilyvät.
  - Kenttä on mukana CSV-viennissä (sarake *Syntynyt*), CSV-tuonnissa (tunnistaa otsikot *Syntynyt*, *Syntymävuosi*, *Syntymäaika*, *Syntymäpäivä*), CSV-mallissa ja muutoslokissa.
  - Tietokanta päivittyy automaattisesti (versio 10: jäsentauluun sarake *syntynyt*).
- Lisäkenttiin tuli uusi tyyppi **Päivämäärä**. Jäsenen lomakkeella se on päivämääräkenttä. CSV-viennissä se on muodossa p.k.vvvv, ja tuonnissa kelpaavat sekä p.k.vvvv että vvvv-kk-pp.
- Asetuksissa on uusi kohta **Merkkipäivät**: *Täytettävät vuodet* (oletuksena 50, 60, 70, 75, 80, 85, 90, 95 ja 100) ja *Jäsenyysvuodet* (oletuksena 10, 20, 25, 30, 40, 50 ja 60). Vuodet voi muuttaa vapaasti, ja tyhjä kenttä poistaa ne käytöstä.
- **Vuosiraportissa** on osio *Merkkipäivät* (päivä, jäsen, merkkipäivä, jäsenluokka), ja se on myös Excel-raportissa omana välilehtenään.
- **Jäsenlistassa** on kortti, joka näyttää seuraavan 60 päivän merkkipäivät ja ensimmäisen niistä, myös vuodenvaihteen yli. Kortista pääsee vuosiraportin merkkipäiviin.
- Karkauspäivänä syntyneen merkkipäivä on muina vuosina 28.2. Mukana ovat vain voimassa olevat jäsenet.
- **Merkkipäivät voi kytkeä päälle ja pois** asetuksista (*Asetukset → Merkkipäivät → Merkkipäivät käytössä*, oletuksena päällä). Pois päältä merkkipäiviä ei näytetä jäsenlistan kortissa, vuosiraportissa eikä Excel-raportissa, ja asetetut vuodet säilyvät.

### Nimi muuttui: OH6KW Jäsenrekisteri (WordPress.orgin tarkastus)
- WordPress.orgin tarkastus piti nimeä *Jäsenrekisteri* liian yleisenä. Lisäosan nimi on nyt **OH6KW Jäsenrekisteri** ja tunniste (slug, kansio ja Text Domain) **`oh6kw-jasenrekisteri`**. Pääsivun tiedosto on `oh6kw-jasenrekisteri.php`. Hallinnan valikossa nimi on edelleen *Jäsenrekisteri*.
- Uudet bannerit, joissa on uusi nimi. GitHubin julkaisu tekee nyt tiedoston `oh6kw-jasenrekisteri.zip`, ja WordPress.org-vienti käyttää uutta slugia.
- **Päivitys vanhasta versiosta:** kansio vaihtui, joten WordPress asentaa 4.6.5:n uutena lisäosana. Tiedot (jäsenet, laskut, asetukset) säilyvät, koska ne ovat samoissa tauluissa ja asetuksissa. Ohje on README:ssä: poista vanha käytöstä (*Säilytä tiedot*), asenna ja ota käyttöön uusi, ja poista vanha vasta sitten.
- Jos vanha ja uusi ovat yhtä aikaa käytössä, uusi ei lataudu toista kertaa (ei virhettä), vaan näyttää ilmoituksen vanhan poistamisesta käytöstä. Siksi pääsivun koodi siirtyi tiedostoon `includes/main.php`.

### Korjaukset WordPress.orgin tarkastuksesta
- **Tyylit ja skriptit WordPressin omilla funktioilla:**
  - Tulostettavien sivujen (paperilaskut ja äänioikeusluettelo) `<style>`-lohkot siirtyivät tiedostoihin `assets/print.css` ja `assets/voters.css`. Ne rekisteröidään `wp_register_style`-funktiolla ja tulostetaan `wp_print_styles`-funktiolla.
  - Sivujen `onclick`-, `onload`- ja `onchange`-käsittelijät siirtyivät skripteihin: tulostettavilla sivuilla `assets/print-page.js`, hallinnassa `admin.js`. Niitä käyttävät *Tulosta*-painikkeet, postitusmerkinnän vahvistus, automaattinen tulostus ja vuosiraportin vuoden valinta.
  - Käytöstä poiston kyselyn tyylit siirtyivät WordPressin `common`-tyylistä omaan tiedostoonsa `assets/deactivate.css`.
- **Toimii ilman PHP:n mbstring-laajennusta:**
  - Jäsenlista kutsui `mb_strtolower`-funktiota ehdoitta, ja se olisi kaatanut sivun palvelimella, jossa mbstringiä ei ole.
  - Nyt kirjainkoon muunnokset kulkevat apufunktioiden `jasrek_lower` ja `jasrek_upper` kautta. Ne toimivat myös ilman mbstringiä, myös å-, ä- ja ö-kirjaimilla.
  - Pankki- ja CSV-tiedostojen merkistömuunnos (Windows-1252 → UTF-8) toimii myös ilman mbstringiä ja iconvia.
- **Tyylit vain omilla sivuilla:**
  - Hallinnan CSS ja JS ladataan vain lisäosan omille sivuille, ja sivut tunnistetaan niiden rekisteröinnissä saaduista tunnisteista.
  - Vuosiraportin tulostustyylit (WordPressin valikon piilotus) on rajattu luokkaan `body.jasrek-screen`, joten ne eivät vaikuta WordPressin muihin sivuihin.
- Kaikkien lomakkeiden ja AJAX-kutsujen käsittelijät tarkistettiin: jokainen tarkistaa noncen ja käyttöoikeuden (`manage_options`).
- Testattu puhtaassa WordPressissä (WP_DEBUG päällä): aktivointi ilman virheitä ja tulosteita, kaikki sivut ilman varoituksia, myös PHP:llä ilman mbstringiä. Plugin Check: 0 virhettä, 0 varoitusta.

### Muut muutokset
- Jäsenyyden ehdot yhtenäistetty: jäseniksi lasketaan kaikkialla vain voimassa olevat jäsenet (aiemmin ”ei eronnut”). Tämä vaikuttaa jäsenmäärien, tiedotteiden, asetusten ja joukkotoimintojen ehtoihin.
- Lisäosa toimii edelleen vain WordPressin hallintapaneelissa: sivustolle ei tule lomakkeita eikä lyhytkoodeja. Kokeiluversion 4.7.0 liittymislomake jätettiin pois (liittymiseen kannattaa käyttää esim. Contact Form 7:ää).
- Kokeiluversion 4.7.0 lisäkenttätyyppi *Syntymäaika* poistui. Jos sellainen kenttä oli käytössä, sen arvot siirtyvät päivityksessä automaattisesti jäsenen Syntymävuosi tai -päivä -kenttään ja lisäkenttä poistetaan asetuksista. Siirto kirjataan muutoslokiin.
- Jos 4.7.0-kokeiluversiolla ehti tulla liittymishakemuksia, ne näkyvät jäsenlistan välilehdellä *Eronneet* merkinnällä *liittymishakemus*. Niitä ei laskuteta eikä lasketa jäseniksi. Hakemuksen voi palauttaa jäseneksi (joukkotoiminto *Palauta jäseneksi* tai jäsenen lomakkeella) tai poistaa.

## 4.6.4 (2026-10-14)

### Korjaus: tyhjä toinen sivu paperilaskun PDF:ssä
- Versiossa 4.6.3 lasku mahtui jo yhdelle sivulle, mutta joillakin koneilla PDF:n perään tuli edelleen tyhjä sivu. Syy: maksuosa työnnettiin sivun alalaitaan. Kun selaimen tulostusalue oli pienempi (oletusreunukset tai Letter-paperikoko), maksuosa tai sivun tyhjä alalaita valui toiselle sivulle.
- Tulosteessa sivu on nyt vain sisällön korkuinen, ja maksuosa tulee heti erittelyn perään. Sivunvaihto tehdään vain laskujen väliin, ei koskaan viimeisen perään.
- Tiiviimpi maksuosa: QR-koodi 25 mm, pankkiviivakoodin korkeus 11 mm, pienemmät välit ja ohjetekstit, tulosteen fonttikoko 9,5 pt. Pitkälläkin saatetekstillä lasku on noin 250 mm korkea.
- Testattu Chromen PDF-tulostuksella viidellä yhdistelmällä: A4 ilman reunuksia, A4 10 mm ja 15 mm reunuksilla sekä Letter 10 mm ja 15 mm reunuksilla. Jokaisessa yksi lasku vie yhden sivun, ja QR-koodi ja pankkiviivakoodi lukeutuvat tulosteesta.
- Postimaksun selitysrivin ja saatteen väliin lisättiin pieni väli.

## 4.6.3 (2026-10-14)

### Korjaus: paperilasku tulostui kahdelle sivulle
- Tulostettaessa laskun perään tuli tyhjä toinen sivu. Syy: sivun korkeus oli tasan A4 (297 mm), ja kun selain käyttää omia tulostusreunuksiaan (Chromessa ”Reunukset: Oletus”), sivu ei mahtunut arkille. Nyt tulostettava sivu on enintään 270 mm korkea, joten se mahtuu yhdelle arkille reunusasetuksesta riippumatta. Jos saateteksti on poikkeuksellisen pitkä, maksuosa siirtyy kokonaisena seuraavalle sivulle eikä leikkaudu pois.
- Testattu Chromen PDF-tulostuksella sekä ilman reunuksia että 10 mm reunuksilla: yksi lasku = yksi sivu, ja QR-koodi ja pankkiviivakoodi lukeutuvat tulosteesta.

### Tiiviimpi lasku
- Oikean yläkulman tietolaatikko (päiväys, tunnus, viite, eräpäivä, jäsenmaksu, postimaksu, maksettava) on tiiviimpi: rivivälit ovat pienemmät, summat on tasattu oikealle ja *Maksettava*-rivin yläpuolella on viiva.
- Yläosan, saatteen, erittelyn ja maksettava-laatikon välit ovat pienemmät, ja tulosteen fonttikoko on 10 pt. Osoite osuu edelleen ikkunakuoren ikkunaan.

## 4.6.2 (2026-10-13)

### Korjaus: postimaksun erittely paperilaskussa
- Paperilaskussa luki saatteessa virheellisesti, että jäsenmaksu on 9,00 €, vaikka jäsenmaksu oli 5,00 € ja postimaksu 4,00 €. Syy: paikkamerkki `{summa}` tarkoitti maksettavaa kokonaissummaa, ja saatteissa sitä käytetään yleensä jäsenmaksun kohdalla.
- **`{summa}` on nyt jäsenmaksu ilman postimaksua**, kuten saatteissa on tapana kirjoittaa (”Jäsenmaksu tässä luokassa: {summa}”). Sähköpostilaskuissa ei ole postimaksua, joten niissä mikään ei muutu.
- Uusi paikkamerkki **`{maksettava}`**: maksettava yhteensä (paperilaskussa jäsenmaksu + postimaksu). `{postimaksu}` toimii edelleen. Vuoden 4.6.1 `{jasenmaksu}` toimii edelleen samana kuin `{summa}`.
- Paperilaskun **yläosan tiedoissa** näkyvät nyt erikseen *Jäsenmaksu*, *Postimaksu* ja *Maksettava* (aiemmin vain ”Summa”).
- Jos saate ei mainitse postimaksua (`{postimaksu}` tai `{maksettava}`), saatteen perään lisätään automaattisesti lihavoitu rivi: ”Paperilaskun postimaksu 4,00 € on lisätty jäsenmaksuun 5,00 €. Maksettava yhteensä 9,00 €.”
- Ikkunakuoren ikkunassa ei enää toistu yhdistyksen nimi kahteen kertaan. Asetuksiin (*Paperilaskut*) tuli kenttä **Yhdistyksen postiosoite**, joka tulostetaan pienellä palautusosoitteeksi vastaanottajan yläpuolelle. Tyhjänä palautusriviä ei tulosteta.

### WordPress.orgin lisäosahakemistoa varten
- **Englanninkielinen `readme.txt`** WordPress.orgin muodossa: kuvaus, asennus, usein kysytyt kysymykset, kuvakaappaukset ja muutokset. Readme on vaatinut englantia heinäkuusta 2025.
- **Yksilöllinen etuliite `jasrek_`** kaikille funktioille, vakioille (`JASREK_`), luokille, asetuksille, välimuistille, ajastuksille, lomakkeiden toiminnoille ja CSS-luokille (aiemmin `jr_`). **Asetukset siirtyvät automaattisesti**: vanhat `jr_settings` ja `jr_db_version` kopioidaan uusille nimille ja poistetaan, ja vanha muistutusajastus korvataan uudella. Hallintasivujen osoitteet muuttuivat (esim. `page=jasrek-laskutus`), joten vanhat kirjanmerkit pitää päivittää.
- **”Update URI” poistettu** lisäosan otsakkeesta. WordPress.orgin lisäosat päivittyvät vain WordPress.orgin kautta.
- **Vähimmäisversio WordPress 6.2** (aiemmin 5.8). Tarvitaan SQL-tunnisteiden turvalliseen sitomiseen (`%i`). ”Tested up to: 7.1”.
- **[Plugin Check](https://wordpress.org/plugins/plugin-check/) menee läpi ilman virheitä ja varoituksia.** Ennen korjauksia löydöksiä oli 191 virhettä ja 421 varoitusta. Korjaukset:
  - Kaikki tulostettava tieto escapataan (`esc_html`, `wp_kses_post`).
  - Kaikki SQL-kyselyt kulkevat `$wpdb->prepare()`-kutsun kautta. Taulujen nimet luetaan `$wpdb`-olion ominaisuuksista (kuten WordPressin omat taulut), IN-listat sidotaan paikkamerkeillä, ja dynaamiset WHERE-ehdot kootaan SQL-literaaleista, joiden arvot sidotaan erikseen. Laskurivikyselyt kulkevat yhden apufunktion (`jasrek_get_rivit`) kautta.
  - Pyyntöparametrit (`$_GET`, `$_POST`, `$_FILES`) luetaan keskitetyillä apufunktioilla, jotka sanitoivat arvot. Lomakkeiden nonce-tarkistus tehdään ennen tietojen käsittelyä.
  - `date()` → `gmdate()`. `utf8_encode()` (vanhentunut PHP 8.2:ssa) korvattu `mb_convert_encoding`/`iconv`-muunnoksella.
  - QR-koodikirjastosta poistettu käyttämättömät kuva-, HTML- ja SVG-tulostusfunktiot. Virheet nostetaan poikkeuksina, ja laskussa on aina vähintään virtuaaliviivakoodi.
- **Hakemiston kuvat** kansiossa `.wordpress-org`: ikoni, banneri ja 8 kuvakaappausta.

## 4.6.1 (2026-10-13)

### Uutta: postimaksu paperilaskuihin
- Asetuksissa on uusi kohta **Paperilaskut → Postimaksu (€)**. Postimaksu lisätään jäsenmaksuun **vain paperilaskun saajille** eli jäsenille, jotka haluavat laskun paperilla tai joilla ei ole sähköpostia. Sähköpostilaskuihin sitä ei lisätä. Oletus on 0 € (ei postimaksua).
- Uuden laskun lomakkeessa on kenttä *Postimaksu paperilaskuihin*. Se esitäytetään asetuksista, ja sitä voi muuttaa laskukohtaisesti. Lomake kertoo, moneenko laskuun postimaksu tulee.
- Laskussa (tuloste ja sähköpostiversio) postimaksu näkyy **omana rivinään**: jäsenmaksu, postimaksu ja maksettava yhteensä. QR-koodi, pankkiviivakoodi ja virtuaaliviivakoodi sisältävät kokonaissumman, joten pankkitiedoston tuonti kohdistaa maksun oikein.
- Laskun sivulla summan alla lukee ”sis. postimaksun 2,50 €”. Laskun kortissa ja laskulistassa näytetään jäsenmaksut ilman postimaksua.
- Jos jäsen vaihtaa paperilaskuun laskun teon jälkeen, laskun Paperilaskut-osio huomauttaa puuttuvasta postimaksusta, ja painike **Lisää postimaksu** lisää sen kaikkiin postittamattomiin ja maksamattomiin paperilaskuihin.
- Laskurivien joukkotoimintoihin tuli **Lisää postimaksu** ja **Poista postimaksu** (vain maksamattomat). Jokainen muutos kirjataan muutoslokiin uuden summan kanssa.
- Myöhemmin laskulle lisätty jäsen (jäsenlomake tai *Lisää laskulle uudet jäsenet*) saa laskun postimaksun, jos hän saa paperilaskun.
- **Uudet paikkamerkit:** `{jasenmaksu}` (jäsenmaksu ilman postimaksua) ja `{postimaksu}`. `{summa}` on maksettava kokonaissumma. Jos saatteessa lukee esimerkiksi ”Jäsenmaksu tässä luokassa: {summa}”, vaihda se muotoon `{jasenmaksu}`, jotta paperilaskun saaja näkee jäsenmaksun ja postimaksun erikseen.
- **Vuosiraportti** erittelee jäsenmaksukertymästä postimaksut ja jäsenmaksut omiin sarakkeisiinsa (myös Excelissä), jos postimaksuja on kertynyt.
- Tietokantaversio 9: sarake `postimaksu` laskuille ja laskuriveille. Päivitys tapahtuu automaattisesti, ja aiemmilla laskuilla postimaksu on 0.

## 4.6.0 (2026-10-13)

### Uutta: paperilaskut
- Jäsenen tiedoissa on valinta **”Haluaa laskun paperilla”**. Laskua ei silloin lähetetä sähköpostilla, vaan se tulostetaan. Jäsenet, joilla ei ole sähköpostia, saavat laskun paperilla aina.
- Laskun sivulla on oma osio **Paperilaskut**: jäsen, postiosoite, viite, summa, postitustila ja maksutilanne. Jokaisella rivillä on painikkeet *Näytä lasku* ja *Tulosta*, ja ylhäällä *Tulosta postittamattomat*, *Tulosta muistutukset erääntyneille* ja *Näytä kaikki paperilaskut*.
- **Tulostettava lasku** on A4-sivu, joka sopii ikkunakuoreen (C5 tai DL, ikkuna vasemmalla). Nimi ja osoite osuvat ikkunaan, ja reunassa on taittomerkit. Laskussa ovat saateteksti, erittely, maksutiedot, EPC QR -koodi, pankkiviivakoodi ja virtuaaliviivakoodi. Osoite jaetaan riveiksi pilkkujen kohdalta. Jos osoite puuttuu, siitä varoitetaan.
- Tulostussivulla on painike **Merkitse postitetuksi**. Postituspäivä näkyy laskun rivillä kohdassa ”Postitettu”. Paperiset muistutukset kirjataan muistutuksiksi kuten sähköpostimuistutukset.
- Sähköpostilähetys, muistutukset (käsin ja automaattiset) ja kesken kauden liittyvän jäsenen ”lähetä heti” ohittavat paperilaskun valinneet. Laskun taulukossa on suodatin *Paperilaskut*, ja uuden laskun sivu kertoo, moniko saa laskun paperilla.
- Jäsenlistassa on merkki *paperilasku* ja kortti ”Saa laskun paperilla”. Valinnan voi asettaa myös joukkotoiminnolla, ja se kulkee CSV-viennissä ja -tuonnissa (sarake *Paperilasku*).

### Uutta: omat lisäkentät
- Asetuksissa voi luoda jäsenille omia kenttiä, esim. **Avain** (kerhotalon avaimen numero, kuten 8.59) tai **Talkoolistalla**.
- Kenttä on joko **teksti** (tyhjä = ei arvoa) tai **valintaruutu** (kyllä/ei). Kentän voi näyttää jäsenlistassa omana sarakkeenaan.
- Kentät näkyvät jäsenlomakkeella, ovat mukana CSV-viennissä ja tunnistetaan CSV-tuonnissa sarakeotsikosta.
- Jäsenlistan haku löytää kentän nimellä (esim. ”avain”) kaikki, joilla kenttä on täytetty, ja tekstikentän arvolla (esim. ”8.59”) oikean jäsenen.
- CSV-viennissä arvot kuten 8.12 suojataan, jottei Excel muuta niitä päivämääriksi.

### Uutta: muutosloki
- Uusi sivu **Jäsenrekisteri → Muutosloki**: kuka muutti mitä ja milloin.
- Kirjataan:
  - jäsenen lisäys, muutokset (jokainen muuttunut kenttä vanha → uusi) ja poisto
  - joukkomuutokset ja CSV-tuonnit
  - maksumerkinnät ja niiden peruminen (käsin, joukkona tai pankkitiedostosta)
  - laskun lähetys ja muistutus, paperilaskun postitus ja laskulta poisto
  - laskun luonti, muokkaus ja poisto, sekä uusien jäsenten lisäys laskulle
  - sähköpostilähetykset (yksi merkintä per lähetysajo vastaanottajineen) ja automaattiset muistutukset
  - asetusmuutokset, tiedotteet sekä tuonnit ja viennit (CSV ja Excel)
- Suodatus aiheen mukaan, haku nimellä, käyttäjällä tai muutoksella, sekä sivutus.
- Jäsenen sivulla on oma **Muutoshistoria**, jossa ovat hänen viimeisimmät merkintänsä, ja linkki kaikkiin merkintöihin.
- Asetuksissa valitaan **säilytysaika** (1–10 vuotta tai pysyvästi, oletus 2 vuotta). Vanhemmat merkinnät poistetaan automaattisesti, koska loki sisältää henkilötietoja.

### Uutta: vuosiraportti vuosikokoukseen
- Uusi sivu **Jäsenrekisteri → Vuosiraportti** vuosivalinnalla:
  - **Jäsenmäärä jäsenluokittain:** jäseniä edellisen vuoden lopussa, liittyneet, eronneet, jäseniä vuoden lopussa (kuluvana vuonna tänään) ja muutos.
  - **Jäsenmaksut:** vuoden laskut (laskuja, maksettu kpl, laskutettu, maksettu €, avoinna, josta erääntynyt) sekä sama jäsenluokittain.
  - **Jäsenmaksukertymä:** vuoden aikana maksupäivän mukaan kirjatut maksut, myös aiempien vuosien laskuista. Luku on vertailukelpoinen kirjanpidon kanssa.
- Painikkeet **Tulosta** (sivu tulostuu ilman WordPressin valikoita) ja **Vie Exceliin** (neljä välilehteä, yhteensä-rivit kaavoina).
- Raportti kertoo, jos eronneilta puuttuu eroamispäivä tai jäseniltä liittymispäivä.

### Muut muutokset
- **Eroamispäivä:** kun jäsen merkitään eronneeksi (lomakkeella, joukkona tai CSV-tuonnissa), eroamispäivä tallentuu. Lomakkeella päivää voi muuttaa, ja se tyhjenee, jos jäsen palautetaan. CSV-vienti ja -tuonti tuntevat sarakkeen *Eronnut*.
- **Jäsenluokka laskutushetkellä:** laskuriville tallennetaan jäsenen luokka laskua tehtäessä. Laskussa, Excel-raportissa ja vuosiraportissa näkyy siksi luokka, jonka mukaan maksu on laskutettu, vaikka jäsenen luokka myöhemmin vaihtuisi. Vanhoille riveille päivitys asettaa nykyisen luokan.
- Laskun muokkaus ei enää nollaa laskun automaattimuistutusvalintaa, jos automaattimuistutus on asetuksissa pois päältä.
- Jäsen-CSV:n mallitiedostossa on uudet sarakkeet (Eronnut, Paperilasku ja omat lisäkentät).
- Tietokantaversio 8: uudet sarakkeet `paperilasku`, `lisakentat` ja `eronnut_pvm` jäsenille, `jasenlaji` laskuriveille ja uusi taulu `jasenrekisteri_loki`. Päivitys tapahtuu automaattisesti, ja lisäosan poisto (jos tietojen poisto on valittu) poistaa myös lokitaulun.

## 4.5.0 (2026-10-13)

### Uutta: laskut Exceliin
- Laskutus-sivulla on painike **Vie kaikki laskut Exceliin**. Se tekee yhden .xlsx-tiedoston, jossa on:
  - **Yhteenveto:** jokaisesta laskusta laskujen, maksettujen, avoimien ja erääntyneiden määrä sekä laskutettu, maksettu ja avoinna oleva summa. Yhteensä-rivillä on kaavat.
  - **Avoimet maksut:** kaikki maksamattomat kaikilta laskuilta yhdellä välilehdellä yhteystietoineen, muistutusmäärineen ja eräpäivineen.
  - **Jokainen lasku omalla välilehdellään:** jäsen, jäsenluokka, viite, summa, eräpäivä, tila (Maksettu / Avoin / Erääntynyt / Maksettu eri summalla, värikoodattuina), maksupäivä, maksettu summa, maksaja, lähetyspäivä ja muistutukset.
- Otsikkorivit on kiinnitetty, sarakkeissa on suodattimet, ja päivämäärät ja eurot ovat oikeassa muodossa, joten niillä voi laskea. Tulostettaessa taulukko sovitetaan sivun leveyteen.
- Yksittäisen laskun sivulla on lisäksi painike **Vie Exceliin**. CSV-vienti on edelleen käytettävissä.
- Excel-tiedosto tehdään ilman ulkoisia kirjastoja tai PHP:n zip-laajennusta, joten vienti toimii kaikilla palvelimilla.

### Yleiskäyttöisyys
- Radioamatööriviittaukset poistettu lisäosasta, README:stä ja kuvista. Esimerkkinä on nyt kuvitteellinen Esimerkkiyhdistys ry jäsennumeroineen.
- Jäsentuonti tunnistaa sarakkeen ”Jäsentunnus”. Tuonti löytää edelleen sarakkeen, jonka nimi on sama kuin asetuksissa annettu lisätunnisteen nimi.

### Korjaukset (koodikatselmointi)
1. **Viitenumeroiden törmäys suurilla jäsen-ID:illä:** kun jäsenten ID:t ylittävät 9999, kahdelle eri laskuriville saattoi syntyä sama viite (esim. lasku 1 + jäsen 12345 ja lasku 11 + jäsen 2345). Tällöin jäsenelle ei syntynyt laskua. Nyt viite tarkistetaan, ja törmäyksessä perään lisätään juokseva numero ennen tarkistetta. Tavallisissa tapauksissa viitteet ovat ennallaan.
2. **Pankin CSV:n summat:** muoto 1,234.50 (pilkku tuhaterottimena) tulkittiin väärin. Desimaalierotin tunnistetaan nyt viimeisestä erottimesta, ja €-merkki ohitetaan.
3. **Tietosuoja:** keskeneräisen jäsen- tai maksutuonnin tiedot (henkilötietoja) poistetaan tietokannasta heti, kun tuonti perutaan. Aiemmin ne vanhenivat tunnin kuluttua.


## 4.4.0 (2026-10-13)

### Uutta: klikattavat paikkamerkit
- Tekstikenttien alla olevat paikkamerkit, kuten `{etunimi}`, `{jasenluokka}` ja `{summa}`, ovat nyt **painikkeita**. Klikkaus lisää merkin tekstiin kohdistimen kohdalle tai valitun tekstin tilalle, ja kohdistin siirtyy merkin perään.
- Käytössä kaikissa viestikentissä: uuden laskun saateteksti, laskun muokkaus, tiedote sekä asetusten oletussaate ja muistutusteksti. Oletussaatteella on nyt oma paikkamerkkirivi, eikä se enää jaa riviä muistutustekstin kanssa.
- Painikkeen päälle vietäessä näytetään selite (esim. ”Summa, esim. 25,00 €”). Paikkamerkit näkyvät nyt myös laskun muokkauskentässä (Muokkaa laskun tietoja).
- Lisätunnisteen paikkamerkki `{tunnus}` näytetään vain, jos lisätunniste on käytössä asetuksissa.


## 4.3.0 (2026-10-13)

### Uutta: kesken kauden liittyvä jäsen
- Jäsenlomakkeessa on uusi kohta **”Jäsenmaksu jo tehdyltä laskulta”**. Uuden tai olemassa olevan jäsenen voi lisätä jo lähetetylle laskulle, esim. ”Jäsenmaksu 2027”. Summa tulee jäsenluokasta ja päivittyy, kun luokka vaihdetaan. Summaa voi muuttaa, esim. osakauden jäsenmaksuksi.
- **Jäsenkohtainen eräpäivä:** laskulle myöhemmin lisätty jäsen saa eräpäivän vähintään kolmen viikon päähän, jottei lasku ole erääntynyt jo lähtiessään. Eräpäivä näkyy laskussa, QR- ja viivakoodeissa, laskun rivillä (”eräp. …”), erääntymisissä ja automaattisissa muistutuksissa.
- Valinta **”Lähetä lasku heti sähköpostilla”**: lasku lähtee tallennettaessa.
- Laskun sivun painike *Lisää laskulle uudet jäsenet* antaa myöhemmin lisätyille jäsenille saman jäsenkohtaisen eräpäivän, jos laskun eräpäivä on jo lähellä tai mennyt.
- Laskun kortissa näkyy ”Avoinna, josta erääntynyt …”, ja laskulistassa erääntyneiden määrä jäsenkohtaisten eräpäivien mukaan.

### Uutta: Tietoja ja palaute
- Uusi sivu **Jäsenrekisteri → Tietoja ja palaute**: tekijät, yhteystieto (jarno.jaskari@gmail.com), linkit GitHubiin, muutoslokiin ja vikailmoituksiin.
- **Palautelomake:** kehitysehdotus, virheilmoitus tai kysymys lähtee kehittäjälle sähköpostilla. Vastausosoitteena on lähettäjän oma osoite, ja versiotiedot liitetään mukaan halutessa. Jäsenrekisterin tietoja ei lähetetä.
- **Ko-fi:** ”Tarjoa kahvit” -linkki Tietoja-sivulla, Lisäosat-listassa ja GitHubissa (Sponsor-painike).
- Lisäosat-listaan linkit: GitHub, Palaute ja kehitysehdotukset, Tarjoa kahvit.

### Lisenssi ja tekijät
- Lisenssiksi vaihdettu **GNU Affero General Public License v3.0** (AGPL-3.0-or-later).
- Tekijätiedot: Jarno Jaskari OH6KW (alkuperäinen lisäosa ja ylläpito) ja Claude (Anthropic) jatkokehityksessä.

### Muuta
- Laskun CSV-vientiin lisätty Eräpäivä-sarake.
- README: 0 €:n jäsenluokkia ei yleistetä kunniajäseniin.
- Tietokantaversio 7: laskuriveille sarake `erapaiva`.


## 4.2.0 – ensimmäinen julkinen versio GitHubissa (2026-10-13)
- Julkaistu GitHubissa: README ja kuvakaappaukset.
- Lisäosan otsakkeeseen lisätty Plugin URI, lisenssitiedot ja `Update URI`, jottei WordPress.orgin samanniminen lisäosa voi koskaan korvata tätä päivityksenä.
- Julkaisun zip rakennetaan automaattisesti (GitHub Actions), ja kansion nimi on aina `jasenrekisteri`.


## 4.2 (2026-10-13)

### Uutta: tietojen poisto lisäosan poiston yhteydessä
- Kun lisäosa poistetaan käytöstä Lisäosat-sivulla, avautuu kysely: **säilytetäänkö vai poistetaanko** jäsenrekisterin tiedot, jos lisäosa myöhemmin poistetaan kokonaan. Poistovalinnan kohdalla näytetään varoitus ja linkki CSV-vientiin.
- Sama valinta löytyy myös kohdasta **Asetukset → Lisäosan poistaminen**. Oletuksena tiedot säilyvät.
- Uusi `uninstall.php`: jos poisto on valittu, poistetaan kaikki kolme taulua, asetukset ja väliaikaiset tiedot. Toimii myös monisivustoasennuksessa. Automaattisten muistutusten ajastus poistetaan aina.
- Kysely näytetään käytöstä poistettaessa, koska WordPress ei lataa käytöstä poistettua lisäosaa enää varsinaisessa poistovaiheessa.

### Korjaukset
1. **Puhelinnumeron etunolla katosi**, kun vientitiedosto avattiin Excelissä (0401234567 → 401234567), ja +358-numerot muuttuivat luvuiksi. Vienti merkitsee nyt etunollalliset ja +-alkuiset numerot tekstiksi, joten ne näkyvät oikein Excelissä ja LibreOfficessa.
2. **Tuonti korjaa rikkoutuneet numerot:** 401234567 → 0401234567 ja 358401234567 → +358401234567. Tuonti tunnistaa myös oman vientitiedoston tekstimerkinnät, joten vienti → tuonti säilyttää numerot ennallaan.
3. CSV-viennin kaavasuojaus kattaa nyt myös + ja - -merkillä alkavat arvot.

### Parannukset
- Laskussa näkyy oma rivi **Jäsenluokka** (esim. ”Nuorisojäsen”), kun jäsenellä on luokka.
- Paikkamerkki `{jasenluokka}` näyttää ”Jäsen”, jos jäsenellä ei ole luokkaa, joten tekstiin ei jää tyhjää kohtaa.
- `{jasenluokka}` toimii myös tiedotteissa.


## 4.1 (2026-10-13)

### Jäsenluokat välilehdiksi
- Jäsenlistan välilehdet muodostuvat nyt **omista jäsenluokista** (esim. Kaikki jäsenet · Jäsen · Nuorisojäsen · Kunniajäsen · Eronneet). Vanhat välilehdet ”Jäsenet / Maksuvapaat jäsenet / Kaikki” poistuivat.
- ”Ilman jäsenluokkaa” -välilehti ja vinkki näkyvät vain, jos jollakin jäsenellä ei ole luokkaa.
- Jos luokkia ei ole vielä määritelty, listassa näytetään vinkki ja linkki asetuksiin.
- ”Jäsenlaji” on nimetty kaikkialla **”Jäsenluokaksi”**.

### Jäsenmaksu 0 € korvaa maksuvapaan tilan
- Jäsenluokan jäsenmaksuksi voi asettaa **0 €**. Silloin luokan jäsenille ei tehdä laskua eikä heitä muistuteta, mutta he saavat tiedotteet. Nollahintaiset luokat näkyvät violettina merkkinä, ja jäsenmaksusarakkeessa lukee ”ei jäsenmaksua”.
- Erillinen jäsenyyden tila ”maksuvapaa” poistettiin. Jäsenyys on nyt joko **Voimassa** tai **Eronnut**.
- **Automaattinen muunnos:** jos rekisterissä oli maksuvapaiksi merkittyjä, heidät siirretään luokkaan ”Kunniajäsen” (0 €). Luokka luodaan, jos 0 €:n luokkaa ei vielä ole.

### Muut parannukset
- **Joukkotoiminnot jäsenlistassa:** valitse jäsenet ruuduista ja aseta heille jäsenluokka, merkitse eronneiksi tai palauta jäseniksi kerralla.
- Jäsenlomakkeessa jäsenluokka on ensimmäisenä, ja hinta tai ”ei jäsenmaksua” näkyy valinnassa. Kun uusi jäsen lisätään luokan välilehdeltä, se luokka on valmiiksi valittuna.
- **Tiedotteen voi lähettää vain yhdelle jäsenluokalle** (esim. vain nuorisojäsenille).
- Uuden laskun lomakkeessa näkyy luokittain jäsenmäärä ja merkintä ”ei laskua” 0 €:n luokille. Rivi ”Ilman jäsenluokkaa” näkyy vain tarvittaessa.
- CSV-tuonti tunnistaa sarakkeet ”Jäsenluokka” ja ”Luokka”. Jos Jäsenyys-sarakkeessa on luokan nimi (esim. ”Kunniajäsen”), jäsen asetetaan siihen luokkaan. Malli-CSV päivitetty.
- Uusi paikkamerkki `{jasenluokka}` (vanha `{jasenlaji}` toimii edelleen).

### Korjaukset
1. Laskun ”Lisää laskulle uudet jäsenet” -painike laski mukaan myös jäsenet, joille ei kuulu laskua. Nyt mukana ovat vain ne, joiden jäsenmaksu on yli 0 €.
2. Jos jäsenluokka lisättiin laskun luomisen jälkeen, uusille jäsenille käytettiin laskun oletussummaa. Nyt käytetään luokan hintaa.

### Tietokanta
- Tietokantaversio 6 (vain tietojen muunnos, ei uusia sarakkeita).


## 4.0 (2026-10-12)

### Uutta: maksujen tuonti pankista
- **Laskutus → Tuo maksut pankista**: lataa verkkopankista maksutiedosto, ja maksut kohdistetaan laskuihin viitteen perusteella. Maksajan nimellä ei ole väliä, joten esim. puolison maksama lasku kohdistuu oikein.
- Tuetut muodot: **KTL-viitesiirtoaineisto** (.txt), **camt.054** ja **camt.053** (XML), sekä **pankkien CSV-tiliotteet** (sarakkeet tunnistetaan otsikoista, esim. OP ja Nordea). Merkistö tunnistetaan automaattisesti.
- Ennen tallennusta näytetään esikatselu. Täsmäävät maksut on valittu valmiiksi. Jos summa poikkeaa (esim. osamaksu), rivi pitää valita itse.
- Lisäksi tunnistetaan: jo maksetut, tiedostossa toistuvat viitteet, oikaisut ja veloitukset sekä viitteet, joiden tarkiste ei täsmää (näppäilyvirhe).
- **Tuplakirjauksen esto:** pankin arkistointitunnus tallennetaan, joten saman tiedoston voi ladata uudelleen ilman, että mitään kirjataan kahdesti.
- RF-viitteet (kansainvälinen viite) kohdistetaan samaan laskuun kuin kotimainen viite.
- Laskulle tallennetaan maksupäivä, maksaja ja maksettu summa. Laskunäkymässä näkyy ”Maksaja: …”, ja poikkeava summa näytetään oranssilla.

### Uutta: jäsenlajit
- Asetuksissa voi määritellä vapaasti **jäsenlajeja omilla jäsenmaksuillaan** (esim. varsinainen jäsen, opiskelija, perhejäsen). Lajeja voi lisätä, nimetä uudelleen ja poistaa.
- Jäsenelle valitaan laji jäsenlomakkeessa. Laji näkyy jäsenlistassa merkkinä, ja sillä voi hakea.
- Uutta laskua tehdessä summat näytetään lajeittain jäsenmäärineen, ja niitä voi muuttaa kyseiselle laskulle. Jos summa on 0, sen ryhmän jäsenille ei tehdä laskua.
- Laskulistassa ja laskunäkymässä näytetään summaväli (esim. 12,50–25,00 €).
- Uusi paikkamerkki `{jasenlaji}` saatteisiin.

### Uutta: jäsenten tuonti CSV-tiedostosta
- **Jäsenet → Tuo CSV**: sarakkeet tunnistetaan otsikoista (esim. Nimi, Kutsutunnus, Sähköposti, Postinro, Liittymisvuosi, Laji, Jäsenyys), ja ne voi tarkistaa ja vaihtaa ennen tuontia.
- Tukee koko nimeä yhdessä sarakkeessa (Etunimi Sukunimi tai Sukunimi Etunimi), osoitteen yhdistämistä postinumerosta ja -toimipaikasta, pelkkää liittymisvuotta sekä Excelin Windows-merkistöä.
- Jäsenyys tunnistetaan tekstistä (esim. ”Kunniajäsen” → maksuvapaa, ”Eronnut” → eronnut).
- Jos jäsen on jo rekisterissä, hänet voi joko ohittaa tai päivittää. Tyhjät kentät eivät korvaa nykyisiä tietoja. Tunnistus tehdään ensin nimen ja sähköpostin perusteella yhdessä, sitten nimen ja lopuksi sähköpostin perusteella. Pariskunnan yhteinen sähköpostiosoite ei sekoita henkilöitä.
- Malli-CSV ladattavissa. Jäsenrekisterin oma CSV-vienti kelpaa tuontiin sellaisenaan.

### Uutta: automaattinen maksumuistutus (valinnainen)
- Asetuksista päälle ja pois. Muistutus lähtee valitun päivämäärän kuluttua eräpäivästä (oletus 14 päivää), ja niitä lähetetään enintään valittu määrä (oletus 1). Seuraava muistutus lähtee aina saman välin jälkeen.
- Automaattisen muistutuksen voi kytkeä pois myös yksittäiseltä laskulta (Muokkaa laskun tietoja).
- Muistutuksen saavat vain jäsenet, joille lasku on lähetetty ja joilla on sähköposti. Maksuvapaiksi tai eronneiksi merkityt eivät saa muistutusta.
- Rahastonhoitajalle lähetetään valinnaisesti yhteenveto lähetetyistä muistutuksista.
- Laskunäkymässä näkyy, milloin ensimmäinen automaattinen muistutus lähtee.

### Uutta: QR-koodi ja pankkiviivakoodi laskuun
- **EPC QR -koodi** (Euroopan maksuneuvoston standardi): pankkisovellus täyttää saajan, tilinumeron, summan ja viitteen. Viite on QR-koodissa RF-muodossa, jonka pankit kohdistavat samaan viitteeseen.
- **Pankkiviivakoodi** (Code 128 C, versio 4) kuvana.
- Asetuksista valitaan: QR ja viivakoodi, vain QR, vain viivakoodi tai ei kuvaa. Virtuaaliviivakoodi tekstinä on laskussa aina.
- Kuvat upotetaan sähköpostiin liitteinä (cid), joten ne näkyvät ilman ulkoisia latauksia. Kuvat tehdään puhtaalla PHP:llä, eikä GD-laajennusta tarvita.
- Koodit on tarkistettu koneellisesti lukemalla ne sekä tavallisella että tarkalla näytöllä.

### Korjaukset
1. Jos oletustekstin (saate tai muistutus) tallensi tyhjänä, lasku lähti ilman saatetta. Nyt tyhjän tilalla käytetään oletustekstiä.
2. Maksumerkinnän peruminen (”Peru maksu”) tyhjentää nyt myös maksajan, maksetun summan ja arkistointitunnuksen.

### Tekninen
- Tietokantaversio 5: jäsenille `jasenlaji`, laskuille `hinnat` ja `auto_muistutus`, laskuriveille `maksettu_summa`, `maksaja` ja `arkistotunnus`. Päivitys tapahtuu automaattisesti.
- QR-kirjasto: kazuhikoarase/qrcode-generator (MIT-lisenssi, `includes/lib/LICENSE-qrcode.txt`). Luokkien nimet on etuliitetty, jotta ne eivät törmää muiden lisäosien kanssa.
- Lisäosan poistaminen käytöstä pysäyttää automaattisten muistutusten ajastuksen.


## 3.3 (2026-10-12) – katselmointi

### Korjaukset
1. **Tiedotteen lähetys saattoi jäädä alkamatta.** Lähetyksen tunnuksessa oli isoja kirjaimia, mutta WordPress muuttaa sen pieniksi luettaessa. Toimi vain, jos tietokanta vertaa kirjainkoosta välittämättä. Nyt tunnus on aina pienillä kirjaimilla.
2. **Viitehaku ei löytänyt tiliotteen etunollilla täytettyä viitettä** (esim. `00000000000000100010`). Nyt etunollat ohitetaan.
3. **Eräpäivä saattoi olla ennen laskun päiväystä.** Nyt se estetään, ja summan yläraja on tarkistettu (virtuaaliviivakoodin maksimi 999 999,99 €).
4. Jos jollekin jäsenelle ei voitu muodostaa laskua (viitenumero jo käytössä), asiasta ei kerrottu. Nyt näytetään varoitus ja määrä.
5. Tiedotetta ei voi enää ”lähettää” joukolle, jossa ei ole yhtään sähköpostiosoitetta. Näytetään ilmoitus.
6. Laskun CSV-viennissä tunnisteen sarake nimetään asetusten mukaan.

### Parannukset
- **Sähköposteihin lisätty tekstiversio** HTML:n rinnalle (multipart/alternative). Pelkkä HTML-viesti päätyy herkemmin roskapostiin, ja tekstiversio näkyy myös tekstipohjaisissa sähköpostiohjelmissa.
- **Jäsenen sivulla näkyy kaikki hänen laskunsa ja maksunsa**: viite, summa, eräpäivä, maksupäivä ja muistutukset, sekä linkki laskuun.
- **Tuplakirjauksen varoitus:** jos rekisterissä on jo jäsen samalla nimellä tai sähköpostiosoitteella, tallennuksen jälkeen näytetään varoitus.
- Hakemistoihin lisätty tyhjät `index.php`-tiedostot (estää hakemistolistauksen palvelimilla, joissa se on päällä).

### Tarkistettu – kunnossa
- Kaikki tietokantakyselyt käyttävät valmisteltuja lauseita tai kokonaislukuja. Lajittelusarakkeet ovat sallittujen listalla.
- Kaikki tallentavat toiminnot vaativat ylläpitäjän oikeudet ja nonce-tunnisteen (CSRF-suojaus).
- Kaikki tulostettava tieto escapataan (XSS-suojaus), myös JavaScriptissä.
- Sähköpostin otsakkeisiin ei voi syöttää rivinvaihtoja (otsakeinjektio).
- CSV-vienti estää kaavojen suorittamisen Excelissä.
- Tiedostoja ei voi avata suoraan selaimella (ABSPATH-tarkistus).


## 3.2 (2026-10-12)

### Muistutukset
- Muistutukset lasketaan. Laskun rivillä näkyy **oranssi merkki** ”Muistutettu 12.10.2026”, ja useamman muistutuksen jälkeen ”Muistutettu 2× · viimeksi 12.10.2026”.
- Samana päivänä jo muistutetulle ei lähetetä toista muistutusta. Tämä estää vahingossa tehdyn tuplapainalluksen. Kun kaikki maksamattomat on muistutettu, painikkeen tilalla lukee ”Maksamattomia muistutettu tänään”.
- Ennen muistutuksen lähettämistä varmistuskysymys kertoo, moniko vastaanottajista on jo muistutettu aiemmin.
- CSV-viennissä muistutuksista kerrotaan määrä ja viimeisin päivä.

### Lisätunniste
- Uusissa asennuksissa kentän oletusnimi on **Jäsennumero**, eikä isoiksi kirjaimiksi muuntaminen ole oletuksena päällä. Nimen voi vaihtaa asetuksista, esim. ”Tunnus”.
- Asetuksista on poistettu radioamatööriviittaus (”esim. OH6ABC”).
- Päivityksessä olemassa olevat asennukset säilyttävät nimen ”Tunnus” ja isot kirjaimet, ellei asetusta ole jo tallennettu.

### Korjaukset
1. **”73” näkyi yhä oletusteksteissä.** Teksti oli tallentunut tietokantaan asetusten tallennuksen yhteydessä, ja lisäksi jo luodulla laskulla on oma saateteksti. Päivitys vaihtaa allekirjoituksen ”73, {yhdistys}” muotoon ”Ystävällisin terveisin {yhdistys}” sekä asetuksissa että jo luoduissa laskuissa. Muuhun tekstiin ei kosketa.
2. Välilehden nimi ”Maksuvapaat” muutettu muotoon **”Maksuvapaat jäsenet”**.

### Tietokanta
- Laskuriveille on lisätty sarake `muistutuksia` (tietokantaversio 4). Aiemmin lähetetyt muistutukset lasketaan yhdeksi.

## 3.1 (2026-10-12)

### Uutta
- **Kolme jäsentilaa:** *Jäsen*, *Jäsen, ei jäsenmaksua (esim. kunniajäsen)* ja *Eronnut*. Maksuvapaille ei tehdä laskua, mutta he saavat tiedotteet kuten muut jäsenet. Jäsenlistassa on violetti ”maksuvapaa”-merkki ja oma välilehti.
- **Lisätunniste asetuksiin:** ”Tunnus”-kentän nimen voi vaihtaa (esim. Kutsutunnus, Jäsennumero), muunnon isoiksi kirjaimiksi voi kytkeä pois, ja kentän voi piilottaa kokonaan. Plugin sopii näin muillekin yhdistyksille kuin radiokerhoille.
- **Testiviestin osoite näkyy ja on muokattavissa** sekä laskuissa että tiedotteissa. Oletuksena on kirjautuneen WP-käyttäjän osoite, ja käyttäjän nimi näytetään. Laskusta voi testata myös muistutuksen.
- Ilmoitus kertoo, mihin osoitteeseen testiviesti lähti.

### Muutokset
- Oletussaatteissa radioamatöörien ”73” on vaihdettu muotoon ”Ystävällisin terveisin” (yleiskäyttöisyys). Jo tallennetut tekstit eivät muutu.
- Laskun luonnissa kerrotaan, montako maksuvapaata jäsentä jätetään pois.
- Tiedotteen vastaanottajavalinta ”Jäsenet” sisältää myös maksuvapaat.
- Jäsenlistan välilehdillä näkyvät lukumäärät. Haku löytää nimen kummassakin järjestyksessä (etunimi sukunimi / sukunimi etunimi).
- Laskun rivien hakukentän ohjeteksti mainitsee viitteen ensimmäisenä: viitteellä löytyy oikea jäsen, vaikka maksajan nimi olisi eri.
- CSV-viennissä tunnisteen sarake on nimetty asetusten mukaan, ja jäsenyyden tila on kirjoitettu auki.
- Pluginin kuvaus muutettu yleiseksi (”Yhdistyksen jäsenrekisteri”).

## 3.0 (2026-10-12)

### Uutta: jäsenmaksulaskutus
- Uusi **Laskutus**-sivu. Ylläpitäjä syöttää laskun nimen (esim. ”Jäsenmaksu 2027”), summan, laskun päiväyksen, eräpäivän ja saatetekstin.
- Ohjelma luo jokaiselle aktiiviselle jäsenelle (tai valituille jäsenille) oman laskun **yksilöllisellä suomalaisella viitenumerolla** (laskun nro + jäsenen ID + 7-3-1-tarkiste).
- Laskut lähetetään sähköpostilla siistinä HTML-laskuna: saaja, IBAN, BIC, viite, eräpäivä, summa ja **virtuaaliviivakoodi** (pankkiviivakoodi v4), jonka jäsen voi liittää pankkisovellukseen.
- Lähetys tapahtuu pienissä erissä edistymispalkin kanssa – ei aikakatkaisuja. Jo lähetettyjä ei lähetetä uudelleen, vaikka lähetys keskeytyisi.
- Testilasku omaan sähköpostiin ja laskun esikatselu selaimessa ennen lähetystä.
- Maksujen seuranta: merkitse maksetuksi / peru, joukkomerkintä valitulla päivämäärällä, haku nimellä tai viitteellä (tiliotteen täsmäytys).
- **Maksumuistutus** maksamattomille yhdellä painikkeella (oma muistutusteksti asetuksissa).
- Yhteenvetokortit: lähetetty, maksettu, kertynyt ja avoinna oleva summa; erääntyneet merkitään punaisella.
- Laskulle voi jälkikäteen lisätä uudet jäsenet tai poistaa jäsenen (esim. kunniajäsen).
- Laskun nimeä, eräpäivää ja saatetekstiä voi muokata.
- Laskun rivit CSV-tiedostona (Excel-yhteensopiva).
- Jäsentaulukossa näkyy viimeisimmän laskun maksutilanne jokaiselle jäsenelle.

### Uutta: asetukset
- Yhdistyksen nimi, IBAN (tarkistus), BIC, Y-tunnus, viestien alalaidan yhteystiedot.
- Lähettäjän nimi ja osoite, vastausosoite (Reply-To).
- Oletussaateteksti ja muistutusteksti paikkamerkeillä ({etunimi}, {tunnus}, {viite}, {summa}, {erapaiva} …).

### Parannukset: jäsenet
- Uudet kentät: **liittymispäivä**, **jäsenyyden tila** (aktiivinen / eronnut) ja **lisätiedot**. Eronneita ei laskuteta.
- Jäsenlomake ja lista omissa näkymissään; lisäys- ja muokkauslomake ei enää vie tilaa listan yläpuolelta.
- Pikahaku (nimi, tunnus, sähköposti, puhelin), välilehdet aktiiviset / eronneet / kaikki.
- Lajittelu nuolilla, oletuksena sukunimen mukaan.
- Yhteenvetokortit: aktiiviset, eronneet, ilman sähköpostia olevat.
- Jäsenrekisterin vienti CSV-tiedostoon.
- Kutsutunnus muutetaan automaattisesti isoiksi kirjaimiksi.
- Poisto kysyy varmistuksen.

### Parannukset: sähköposti
- Viestit lähtevät yhtenäisellä, puhelimellakin toimivalla HTML-pohjalla.
- Rivinvaihdot säilyvät (aiemmin koko viesti tuli yhtenä rivinä).
- Paikkamerkit {etunimi}, {sukunimi}, {tunnus} – jokainen saa henkilökohtaisen viestin.
- Vastaanottajiksi aktiiviset, kaikki tai viimeisimmän laskun maksamattomat.
- Testiviesti itselle, erälähetys edistymispalkilla, tyhjät osoitteet ohitetaan.

### Korjaukset
1. **SQL-injektio** lajittelussa (`order_by` meni suoraan SQL-lauseeseen) – nyt vain sallitut sarakkeet.
2. **CSRF-suojaus puuttui** kaikista lomakkeista – lisätty nonce-tarkistukset ja oikeustarkistukset.
3. **Jäsenen poisto numeroi kaikkien jäsenten ID:t uudelleen.** Tämä olisi rikkonut viitenumerot ja maksuhistorian – poistettu. ID:t pysyvät pysyvinä.
4. **Sähköpostin lähetyksen jälkeen jäsenlista näkyi tyhjänä**, koska lähetyskoodi korvasi `$members`-muuttujan. Korjattu.
5. Sähköposti lähetettiin HTML-muodossa mutta tekstinä, jolloin rivinvaihdot katosivat. Korjattu.
6. Sähköpostia yritettiin lähettää myös jäsenille, joilla ei ole osoitetta. Korjattu.
7. Sivun päivitys (F5) lähetti lomakkeen uudelleen (esim. tuplajäsenet tai tuplasähköpostit) – nyt käytetään Post/Redirect/Get-mallia.
8. Tietokannan rakenne ei päivittynyt pluginia päivitettäessä (vain aktivoinnissa) – nyt päivitys tapahtuu automaattisesti.
9. Lomakkeiden `label for` -viittaukset eivät osuneet kenttiin (saavutettavuus) – korjattu.
10. Tunnus-kenttä pidennetty 10 → 20 merkkiin.

### Yhteensopivuus
- Olemassa oleva jäsentaulu (`wp_jasenrekisteri`) ja sen tiedot säilyvät; uudet sarakkeet lisätään automaattisesti ja nykyiset jäsenet merkitään aktiivisiksi.
- Vaatii WordPress 5.8+ ja PHP 7.4+.

## 2.6
- Alkuperäinen versio: jäsenten lisäys, muokkaus, poisto ja sähköposti kaikille.
