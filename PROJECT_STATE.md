# PROJECT_STATE.md

## Evidence IT aktiv / DORA Asset Map — stav projektu ve verzi v16

Tento dokument zachycuje aktuální stav aplikace po 15 iteracích vývoje a slouží jako rychlá orientace pro další vývoj nebo pro navázání v novém AI vlákně.

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

- Aktuální iterace: `v16-changelog`
- Poslední funkční základ: `v15-models-sidebar`
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

Persistentní data jsou v bind mountu:

```text
./data:/data
```

## 5. Modely/projekty jako dokumenty

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

## 6. Datový model

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
