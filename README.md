# Evidence IT aktiv / DORA asset map

PHP + SQLite webová aplikace pro jednoduchou grafovou evidenci ICT a informačních aktiv, vazeb, DORA atributů a základního rizikového hodnocení.

## Spuštění

```bash
docker compose up --build
```

Aplikace poběží na:

```text
http://localhost:8888
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
- nový view z aktuálního včetně zkopírování pozic
- smazání view s ochranou výchozího view `Celková mapa`
- export JSON
- excel-like tabulka assetů s editací buněk, filtrováním, sortováním a copy/paste přes TSV
- excel-like tabulka vazeb s editací buněk, filtrováním, sortováním a copy/paste přes TSV
- tisknutelný report: `/report.php`
- heatmapa rizik v reportu
- change log pro auditní stopu a budoucí undo/redo

## Report / PDF

V aplikaci klikni na **Report / PDF**. Otevře se HTML report. Tlačítko **Tisk / uložit jako PDF** použije tiskovou funkci prohlížeče, kde lze zvolit „Save as PDF“ / „Uložit jako PDF“.

## Poznámka k SQLite

Prototyp je navržený pro single-user režim. Pro víceuživatelský provoz bude vhodné migrovat SQLite na MariaDB/PostgreSQL, ale datový model je k tomu připravený.

## v5 - tabulkový staging editor

Tabulkové pohledy **Assety tabulka** a **Vazby tabulka** nyní fungují jako staging editor:

- změny se neukládají po opuštění buňky,
- nové řádky se přidávají tlačítkem `+ Řádek`,
- řádky se mažou checkboxem vlevo a tlačítkem `Smazat řádku`,
- změny se zapisují do SQLite až tlačítkem `Save`,
- před uložením proběhne validace povinných polí a číselníků,
- neplatné buňky se zvýrazní červeně,
- asset musí mít vyplněný `type` a `name`,
- vazba musí mít `source_node_id`, `target_node_id` a `type`,
- vložení z Excelu funguje přes TSV/clipboard; pokud vložený blok přesahuje aktuální počet řádků, tabulka automaticky přidá nové řádky.

