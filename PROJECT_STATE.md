# PROJECT_STATE.md

## Evidence IT aktiv / DORA Asset Map — stav projektu ve verzi v28

Tento dokument zachycuje aktuální stav aplikace po 18 iteracích vývoje a slouží jako rychlá orientace pro další vývoj nebo pro navázání v novém AI vlákně.

## 1. Účel aplikace

Aplikace je single-user webový editor pro evidenci ICT a informačních aktiv podle metodiky DORA. Je navržena jako praktický interní nástroj pro:

- evidenci ICT aktiv a informačních aktiv,
- modelování vazeb mezi aktivy jako graf,
- evidenci DORA atributů aktiv,
- základní rizikové hodnocení,
- reportování do HTML/PDF a DOCX,
- práci s více oddělenými modely/projekty jako samostatnými SQLite soubory.

Mentální model aplikace je: **webová aplikace jako editor, SQLite soubor jako dokument**. Tedy podobně jako Word nebo Excel, ale pro model DORA aktiv.

## 2. Aktuální verze

- Aktuální iterace: `v28-repository-hygiene`
- Poslední funkční základ: `v27-assets-table-usability` + repozitářová/provozní hygiena ve v28
- Aplikace běží na portu: `8888`
- URL: `http://localhost:8888`

## 3. Technologický stack

- Backend: PHP 8.3
- Webserver: Apache v Docker kontejneru
- Databáze: SQLite
- Frontend: HTML5, CSS, vanilla JavaScript
- Graf: Cytoscape.js z CDN
- Report DOCX: vlastní generování `.docx` balíčku přes PHP `ZipArchive`, bez Composeru a bez PHPWord
- Kontejner: Docker + docker compose

## 4. Docker a adresářová struktura

Aktuální základní struktura projektu:

```text
 dora-assets/
 ├── Dockerfile
 ├── docker-compose.yml
 ├── README.md
 ├── PROJECT_STATE.md
 ├── CHANGELOG.md
 ├── AI-PROMPT.md
 ├── LICENSE
 ├── .gitignore
 ├── app/
 │   ├── index.php
 │   ├── api.php
 │   ├── db.php
 │   ├── config.php
 │   ├── schema.sql
 │   ├── report.php
 │   ├── report_docx.php
 │   ├── views/
 │   │   └── main.php
 │   └── assets/
 │       ├── app.js
 │       └── style.css
 └── data/
```

`docker-compose.yml` mapuje port:

```yaml
ports:
  - "8888:80"
```

Persistentní data jsou v explicitním bind mountu:

```yaml
volumes:
  - type: bind
    source: ./data
    target: /data
```

Bind mount je zvolen záměrně místo Docker named volume: SQLite modely jsou běžné soubory viditelné v `./data`, lze je zálohovat, stáhnout nebo přenášet a nepřijdou se smazáním Docker volumes přes `docker compose down -v`.

## 5. Licence, gitignore a runtime data

Projekt obsahuje MIT licenci v souboru `LICENSE`.

Soubor `.gitignore` ignoruje lokální runtime data, zejména:

- `data/models/*.sqlite`,
- `data/deleted/*.sqlite`,
- `data/current_model.txt`,
- starší `data/assets.sqlite`,
- SQLite sidecar soubory `*.sqlite-wal`, `*.sqlite-shm`, `*.sqlite-journal`,
- lokální `.env`, logy, dočasné soubory, OS/editor metadata a generované ZIP archivy.

Adresáře `data/`, `data/models/` a `data/deleted/` zůstávají v repozitáři jako prázdná struktura pomocí `.gitkeep`, ale skutečná SQLite data se necommitují.

## 6. Modely/projekty jako dokumenty

Aplikace podporuje více samostatných modelů/projektů. Každý model je jeden SQLite soubor.

Datová struktura runtime adresáře:

```text
/data/
  current_model.txt
  models/
    demo.sqlite
    svp-dora.sqlite
    sap-model.sqlite
  deleted/
    old-model.deleted-YYYYMMDD-HHMMSS.sqlite
```

### Chování při startu

1. Aplikace zkontroluje `/data/models`.
2. Pokud existuje starší `/data/assets.sqlite` a modely jsou prázdné, zkopíruje se jako běžný model `assets.sqlite`.
3. Při prvním spuštění prázdného modelového adresáře se vytvoří `demo.sqlite` s ukázkovými daty.
4. Pokud `current_model.txt` obsahuje platný existující model, otevře se tento model.
5. Pokud `current_model.txt` chybí nebo je neplatný, otevře se nejmladší model kromě `demo.sqlite`.
6. Pokud jiný model není, otevře se `demo.sqlite`.

### Modelové operace v UI

V levém panelu je sekce **Model / projekt**:

- výběr aktuálního modelu,
- `Nový prázdný`,
- `Kopie aktuálního`,
- `Smazat model`,
- `Stáhnout DB`,
- `Nahrát DB`.

### Nový model

Nový model je vždy prázdný. Neobsahuje demo ani template data.

### Kopie aktuálního modelu

Funguje jako „Uložit jako“. Výchozí název kopie je odvozen z původního názvu a timestampu.

### Smazání modelu

Smazání neodstraňuje SQLite soubor fyzicky. Soubor se přesune do `/data/deleted`. Poslední existující model nelze smazat. `demo.sqlite` lze smazat, pokud existuje jiný model.

### Import/export DB

- `Stáhnout DB` stáhne aktuální SQLite soubor.
- `Nahrát DB` nahraje SQLite soubor do `/data/models`, zkontroluje duplicitu názvu a přepne aplikaci na importovaný model.
- Merge modelů se nedělá; případné slučování dat se řeší přes excel-like tabulky assetů a vazeb.

## 7. Datový model

Datový model je obecný graf:

- `nodes` = uzly / aktiva / entity,
- `edges` = orientované typované vazby,
- `views` = uložené pohledy nad grafem,
- `view_node_positions` = pozice uzlů per view,
- `change_log` = auditní stopa změn.

### Hlavní tabulka `nodes`

Uzel může být například:

- hardware,
- software,
- data,
- process,
- business_function,
- supplier,
- provider,
- manufacturer,
- network,
- location,
- documentation,
- ict_service.

Hlavní atributy uzlu:

- `id`,
- `type`,
- `name`,
- `description`,
- `owner`,
- `business_owner`,
- `technical_owner`,
- `vendor_manufacturer`,
- `criticality`,
- `confidentiality`,
- `integrity_level`,
- `availability`,
- `rto_hours`,
- `rpo_hours`,
- `mtd_hours`,
- `data_sensitivity`,
- `data_categories`,
- `environment`,
- `location`,
- `status`,
- `lifecycle_state`,
- `good_to_know`,
- `last_reviewed_at`,
- `review_frequency_months`,
- `threats`,
- `risk_scenarios`,
- `risk_likelihood`,
- `risk_impact`,
- `risk_controls`,
- `residual_risk`,
- `created_at`,
- `updated_at`.

### Hlavní tabulka `edges`

Vazba je orientovaná hrana:

- `source_node_id`,
- `target_node_id`,
- `type`,
- `description`,
- `criticality`.

Typy vazeb zahrnují zejména:

- `contains`,
- `hosts`,
- `runs_on`,
- `stores`,
- `processes_data`,
- `uses_data`,
- `supports_process`,
- `supports_function`,
- `depends_on`,
- `provided_by`,
- `supplied_by`,
- `manufactured_by`,
- `connected_to`,
- `backed_up_by`,
- `monitored_by`,
- `administered_by`,
- `integrates_with`,
- `authenticates_via`.

Hierarchie aktiv se neřeší zvláštní tabulkou `asset_components`; modeluje se vazbou `contains`.

Příklad:

```text
SAP ECC -> contains -> ALICE
SAP ECC -> processes_data -> Pojistné smlouvy
Server -> hosts -> SAP ECC
SAP ECC -> supports_process -> Správa smluv
```

## 7. Views / pohledy

Aplikace podporuje uložené views nad stejnými aktivy a vazbami.

Princip:

```text
Assety a vazby = společná data
Views = různé mapy/rozložení nad stejnými daty
```

Funkce:

- uložit aktuální view,
- vytvořit nový view z aktuálního včetně pozic,
- smazat view,
- chránit výchozí view `Celková mapa`,
- uchovávat pozice uzlů per view.

Tlačítka pro view jsou v levém panelu v sekci **Pohled**.

## 8. Grafové UI

Grafové UI používá Cytoscape.js.

Funkce:

- uzly jako karty/obdélníky,
- hrany jako směrové čáry s popisky,
- drag & drop uzlů,
- uložení pozic,
- doubleclick na uzel otevře detailní kartu,
- single click vybírá uzel/hranu,
- vytvoření a mazání uzlů a vazeb,
- filtry podle typu, kritičnosti a dalších pohledů,
- dynamické views/režimy: hardware, data, process, supplier, critical, personal data, impact,
- snap to grid.

### Snap to grid

V panelu **Pohled** je:

- přepínač `Snap to grid`,
- velikost mřížky v px,
- tlačítko `Zarovnat aktuální view`.

Nastavení se ukládá do `localStorage`.

## 9. Detailní karta assetu

Detail uzlu je moderní světlý technický modal/drawer.

Sekce:

1. Základní informace
2. Klasifikace a odolnost
3. Data, lokalita a revize
4. Hrozby a rizika

Důležité layoutové zásady:

- `Popis` má textarea s rozumnou výškou.
- `Technical owner` je pod `Owner`.
- `Vendor / manufacturer` je pod `Business owner`.
- `Důvěrnost (CIA)`, `Integrita (CIA)`, `Dostupnost (CIA)` jsou vedle sebe.
- `RTO [h]`, `RPO [h]`, `MTD [h]` jsou vedle sebe.
- U zkratek jsou tooltipy `ⓘ`.

## 10. Excel-like tabulky

Aplikace obsahuje dvě tabulkové obrazovky:

- `Assety tabulka`,
- `Vazby tabulka`.

Vlastnosti:

- editace buněk,
- přidání řádku,
- smazání označeného řádku,
- staging režim — změny se neukládají okamžitě,
- tlačítko `Save`,
- validace před uložením,
- neplatné hodnoty se zvýrazní červeně,
- sortování podle sloupců,
- filtrování,
- copy/paste přes TSV z Excelu/LibreOffice.

V **Assety tabulka** jsou ve v27 zafixované vlevo identifikační sloupce `checkbox`, `ID`, `Typ` a `Název`, aby při horizontálním scrollování zůstal vidět kontext editovaného assetu. Tabulka má také přepínač **Kompaktní zobrazení řádků / Plné zobrazení řádků**. Kompaktní režim používá jednotnou výšku řádků a ellipsis pro dlouhé texty; plný režim zachovává zalamování a výšku podle obsahu. Nastavení se ukládá do `localStorage`.

Povinná pole assetu:

- `type`,
- `name`.

Povinná pole vazby:

- `source_node_id`,
- `target_node_id`,
- `type`.

Textové názvy source/target u vazeb jsou read-only; editují se ID.

## 11. Reporty

Aplikace generuje:

- HTML report vhodný pro tisk/PDF,
- DOCX report pro MS Word.

### HTML/PDF report

Endpoint:

```text
/report.php
```

V UI tlačítko:

```text
Report / PDF
```

Report lze uložit do PDF přes tiskovou funkci prohlížeče.

### DOCX report

Endpoint:

```text
/report_docx.php
```

V UI tlačítko:

```text
Report DOCX
```

DOCX se generuje bez Composeru jako vlastní `.docx` ZIP balíček pomocí `ZipArchive`. Dockerfile proto instaluje PHP rozšíření `zip`.

Report obsahuje:

- manažerské shrnutí,
- počty assetů podle typů,
- heatmapu rizik,
- seznam nejkritičtějších assetů,
- detailní seznam assetů,
- odchozí/příchozí vazby,
- metodickou poznámku ke skóre.

## 12. Rizikové hodnocení

U assetu lze evidovat:

- hrozby,
- rizikové scénáře,
- pravděpodobnost 1–5,
- dopad 1–5,
- opatření/kontroly,
- reziduální riziko.

Skóre kritičnosti v reportu je zjednodušené pracovní skóre:

```text
pravděpodobnost × dopad
+ váha deklarované kritičnosti
+ váha nejvyšší hodnoty CIA
+ váha RTO
```

Krátké RTO zvyšuje skóre, protože nízká tolerance výpadku znamená vyšší provozní důležitost.

## 13. Design

Aktuálně použitá varianta je světlejší technický design:

- světlé technické UI,
- jemná gridová plocha grafu,
- uzly jako čisté CMDB/architecture karty,
- barvy podle typu assetu,
- decentní hrany a popisky,
- světlý technický modal detailu,
- zasouvací levý panel.

Levý panel lze zasunout tlačítkem `‹ / ›`. Stav je v `localStorage`.

## 14. Známá rozhodnutí

- Nepoužívat login.
- Nepoužívat multiuser režim.
- Nepoužívat MariaDB/PostgreSQL v aktuální fázi.
- Nepoužívat `project_id` v jedné databázi.
- Nepoužívat samostatnou tabulku `asset_components`.
- Modelovat vše jako graf uzlů a hran.
- Pro různé firmy/úseky/projekty používat samostatné SQLite soubory.
- Merge modelů neřešit programově; lze řešit přes tabulky assetů a vazeb.
- Porovnání modelů není požadováno.

## 15. Doporučený další vývoj

Možné budoucí funkce:

- undo/redo nad `change_log`,
- validace struktur vazeb podle typů uzlů,
- lepší importní mapování Excel sloupců,
- export grafu jako PNG/SVG,
- detailní DORA gap report,
- automatická kontrola nevyplněných polí,
- rozšířené risk registry,
- šablony reportů,
- volitelný přechod na MariaDB/PostgreSQL pro multiuser provoz.

## 14. Technické a výkonové zpevnění ve v17

Verze v17 zapracovává code review zaměřené na správnost dynamických pohledů, validaci a výkon.

### Dynamické views

Dynamické grafové režimy mají explicitní politiku povolených typů vazeb. Například hardware/data/process/supplier pohled už netahá libovolné okolní vazby, ale pouze vazby relevantní pro daný režim. Finální seznam hran je filtrován podle vypočteného `keepEdgeIds`.

### Tabulka vazeb

Editor vazeb validuje zdrojový a cílový uzel proti kompletnímu seznamu uzlů v aktuální SQLite DB, nikoli proti aktuálně zobrazenému grafu. To je důležité, protože graf může být omezen dynamickým view nebo filtrem.

### Pozice uzlů

Pozice uzlů lze ukládat dávkově přes endpoint `save_positions`. Hromadné zarovnání view nebo uložení většího počtu pozic tak neposílá jeden HTTP request na každý uzel. Pozice se zapisují jen při reálné změně a audit se ukládá agregovaně jako `move_nodes_batch`.

### Change log

Change log je automaticky udržovaný podle konfigurace:

```text
DORA_CHANGE_LOG_RETENTION_DAYS = 90
DORA_CHANGE_LOG_MAX_RECORDS = 5000
```

Tím se zabraňuje nekontrolovanému růstu SQLite souboru kvůli velkému počtu technických změn.

### Rizika

Prázdná pravděpodobnost nebo dopad už neznamená nízké riziko `1 × 1`. Takový asset je označen jako `unrated` / nehodnoceno a reporty ho uvádějí zvlášť mimo heatmapu.

### SQLite model copy/download

Před kopírováním nebo stažením aktuálního SQLite modelu se kontroluje výsledek WAL checkpointu. Pokud je checkpoint busy, aplikace vrátí řízenou chybu, protože kopírovat jen hlavní `.sqlite` soubor by nemuselo být bezpečné.


## CSV import/export assetů

Ve verzi v18 aplikace podporuje import assetů z CSV přímo z tabulkového pohledu **Assety tabulka**. CSV soubor má mít hlavičky odpovídající tabulce aplikace, například:

```text
ID;Typ;Název;Popis;Owner;Business owner;Technical owner;Vendor/manufacturer;Kritičnost;Důvěrnost;Integrita;Dostupnost;RTO h;RPO h;MTD h;Citlivost dat;Kategorie dat;Prostředí;Lokalita;Stav;Lifecycle;Poslední revize;Revize měs.;Hrozby;Rizikové scénáře;Pravděp. 1-5;Dopad 1-5;Kontroly;Reziduální riziko;Good-to-know
```

Workflow:

1. Uživatel otevře **Assety tabulka**.
2. Klikne na **Import CSV**.
3. Vybere CSV soubor exportovaný z Excelu.
4. Aplikace zobrazí preview, počet řádků, ignorované sloupce a validační chyby.
5. Import do DB je povolen pouze tehdy, když CSV neobsahuje validační chyby.
6. Výchozí režim ignoruje CSV `ID` a vkládá všechny řádky jako nové assety.
7. Volitelně lze zapnout aktualizaci podle ID existujících assetů.

Aplikace také umí exportovat aktuální assetovou tabulku do CSV přes tlačítko **Export CSV**. Tento export slouží jako šablona, přenosový formát a podklad pro úpravy v Excelu.

CSV import se týká pouze assetů. Vazby se zatím importují přes tabulkový editor vazeb nebo copy/paste.

## Current addition in v19

The asset detail card in graph view includes an embedded relationship editor. It loads all incoming and outgoing edges for the opened asset using `get_node_edges`. The table keeps relationship direction explicit by showing the current asset on the left for outgoing edges and on the right for incoming edges. The current asset side is read-only, while the other asset, edge type, criticality and description can be edited. New incoming/outgoing relationships can be added and existing relationships can be marked for deletion. Relationship changes are saved through the existing batch edge API when the asset card is saved.

The asset modal now also has Save/Close actions in the header, plus a sticky bottom action bar, so users do not have to scroll to the bottom of a large card just to save changes.


## v20 UI state

Aktuální karta assetu ve view Graf obsahuje sekci **Vazby assetu** jako kompaktní editovatelnou tabulku. Vztahy se zobrazují ve směru `Asset A -> typ vazby -> Asset B`; aktuálně otevřený asset je read-only badge vlevo nebo vpravo podle skutečného směru. Ostatní pole vztahu jsou editovatelná, ale vizuálně zmenšená, aby sekce nepůsobila jako samostatný velký formulář.


## v21 UI state

Sekce **Vazby assetu** v detailní kartě assetu je ve v21 vizuálně sjednocená s ostatními formulářovými poli. Cílem bylo odstranit dojem, že vazbová tabulka používá jiné měřítko než zbytek karty. Selecty, inputy, read-only badge aktuálního assetu a delete tlačítko mají nyní kompaktnější styl, menší písmo a stejné zaoblení jako pole typu CIA/RTO/RPO na kartě assetu.

Tato iterace nemění datový model ani endpointy z v19/v20.


## v23 seed data organization

Demo initialization is now separated from the core database bootstrap code. The updated demo model is stored as `app/demo_seed_data.json`; `app/db_seed_data.php` contains the importer function `seed_demo_data(PDO $pdo)`. `app/db.php` requires this file only when creating a new demo SQLite model. This keeps ordinary application requests lighter and makes future demo-data replacement possible without editing the core DB helper. The seed intentionally imports nodes, edges, views and node positions, but not `change_log`.


## v24 seed loader correction

The active codebase includes the v23 demo seed refactor plus a v24 fix. `app/db_seed_data.php` is self-contained for nullable integer conversion via `seed_nullable_int()`. This prevents the seed importer from failing during first initialization with `Call to undefined function nullable_int()`. Demo data is still stored in `app/demo_seed_data.json` and loaded only when initializing a new demo model.


## v25-edge-asset-picker

Aktuální navazující iterace přidává do tabulkového pohledu vazeb doubleclick picker pro výběr assetu ve sloupcích `source_node_id` a `target_node_id`. Přímá editace ID i TSV/Excel paste zůstávají zachovány; picker je pouze pomocná nadstavba pro snazší zjištění, které ID patří kterému assetu.


## v26-node-field-pickers

Verze v26 doplňuje do **Assety tabulka** pomocné doubleclick výběry pro stabilní číselníková pole. Uživatel může stále psát raw hodnotu přímo do buňky nebo vkládat blok dat z Excelu/TSV, ale u vybraných polí může dvojklikem otevřít seznam povolených hodnot a vybrat správnou hodnotu bez znalosti interního kódu.

Pole s výběrem:

- `type`
- `criticality`
- `environment`
- `status`
- `confidentiality`
- `integrity_level`
- `availability`
- `lifecycle_state`
- `data_sensitivity`

Typy třetích stran byly zjednodušeny: původní `supplier`, `provider` a `manufacturer` jsou nyní jeden typ `third_party` s UI popiskem **3. strana (dodavatel)**. Starší SQLite modely se při inicializaci automaticky normalizují, takže existující dodavatelé/poskytovatelé/výrobci zůstanou v datech, ale budou mít nový společný typ.

## v27-assets-table-usability

Verze v27 zlepšuje práci s širokou obrazovkou **Assety tabulka**. První identifikační sloupce — checkbox, `ID`, `Typ` a `Název` — jsou sticky vlevo, takže při editaci vzdálených atributů zůstává zřejmé, který asset uživatel upravuje.

Do toolbaru assetové tabulky byl přidán přepínač kompaktního/plného zobrazení řádků. Kompaktní režim drží řádky ve stejné výšce a dlouhé texty zobrazuje zkráceně přes `...`; uložená hodnota se nezkracuje. Plný režim nechává texty zalamovat a řádky rostou podle obsahu.

Změna je implementována převážně přes CSS a třídy v renderu tabulky; nemění datový model, API ani ukládání tabulky. Zachována je přímá editace, TSV/Excel copy-paste i doubleclick pickery z v26.

## v28 doplnění

Verze v28 je technická/provozní iterace bez změny aplikační funkcionality. Přidává `LICENSE`, `.gitignore`, `.gitkeep` placeholdery runtime adresářů a zpřesňuje Docker Compose persistentní data přes explicitní bind mount. README nově obsahuje i full rebuild postup.

