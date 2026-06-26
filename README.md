# Evidence IT aktiv / DORA asset map

PHP + SQLite webová aplikace pro jednoduchou grafovou evidenci ICT a informačních aktiv, vazeb, DORA atributů a základního rizikového hodnocení.

## Spuštění

```bash
docker compose up --build
```

Aplikace poběží na:

```text
http://localhost:8080
```

SQLite databáze je uložená v persistentním adresáři:

```text
./data/assets.sqlite
```

## Funkce prototypu

- evidence uzlů: hardware, software, data, procesy, business funkce, dodavatelé, poskytovatelé, výrobci, sítě, lokality, dokumentace, ICT služby
- evidence vazeb: contains, hosts, processes_data, supports_process, depends_on atd.
- hierarchie aktiv přes vazbu `contains`
- drag & drop graf v Cytoscape.js
- uložení pozic uzlů per view
- detail uzlu po doubleclick
- editace DORA atributů: kritičnost, CIA, RTO/RPO/MTD, citlivost dat, kategorie dat, revize
- základní rizikové atributy: hrozby, scénáře, pravděpodobnost, dopad, kontroly, reziduální riziko
- views a dynamické pohledy
- export JSON
- tisknutelný report: `/report.php`
- heatmapa rizik v reportu
- change log pro auditní stopu a budoucí undo/redo

## Report / PDF

V aplikaci klikni na **Report / PDF**. Otevře se HTML report. Tlačítko **Tisk / uložit jako PDF** použije tiskovou funkci prohlížeče, kde lze zvolit „Save as PDF“ / „Uložit jako PDF“.

## Poznámka k SQLite

Prototyp je navržený pro single-user režim. Pro víceuživatelský provoz bude vhodné migrovat SQLite na MariaDB/PostgreSQL, ale datový model je k tomu připravený.
