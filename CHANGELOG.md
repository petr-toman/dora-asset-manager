# CHANGELOG.md

## v25 - Edge table asset picker

- Added a double-click asset picker for `source_node_id` and `target_node_id` cells in the Edges table.
- Users can still directly type numeric asset IDs and paste TSV/Excel rows unchanged.
- The picker searches all nodes in the current model and displays options as `Name (type) · ID`.
- Selecting an asset fills the numeric ID and refreshes the read-only source/target name in the table row.
- Updated edge-table UX documentation.



## v23 - Demo seed data refactor

- Demo seed data was replaced by the updated exported JSON model supplied on 2026-06-28.
- `seed_demo_data()` was moved out of `app/db.php` into `app/db_seed_data.php`.
- The actual demo dataset is stored in `app/demo_seed_data.json`.
- `db.php` now loads the seed module only when a new demo model is initialized, reducing normal request parse/load cost and keeping database bootstrap code cleaner.
- Seed import excludes `change_log`; demo data contains nodes, edges, views and view node positions only.

## Evidence IT aktiv / DORA Asset Map

Tento changelog byl zpětně zrekonstruován z iterací vývoje v chatu a je dále průběžně udržován v projektu.


## v21 - Asset relationship table visual alignment

Datum: 2026-06-28

### Změněno

- Sekce **Vazby assetu** na detailní kartě assetu byla vizuálně sjednocena se zbytkem formuláře.
- Pole v tabulce vazeb nyní používají menší písmo, nižší výšku, stejné zaoblení, podobné ohraničení a focus styl jako běžná formulářová pole na kartě.
- Hlavičky tabulky vazeb jsou méně dominantní a už nepůsobí jako samostatná velká tabulka mimo formulář.
- Read-only aktuální asset v tabulce vazeb je zobrazen jako decentní badge se stejnou výškou jako input/select pole.
- Tlačítko smazání vazby je méně agresivní; červená se objeví až při hoveru.

### Poznámka

- Jde čistě o UI/design iteraci. Funkce, API a datový model zůstávají stejné jako ve v20.

## v18-csv-import

Datum: 2026-06-28

### Přidáno

- Import assetů z CSV přímo z obrazovky **Assety tabulka**.
- CSV preview před zápisem do databáze.
- Validace celého CSV před importem; do DB se ukládá pouze tehdy, pokud je celý soubor validní.
- Detekce oddělovače CSV (`;`, `,`, tab) a podpora UTF-8 BOM.
- Mapování českých hlaviček z tabulky aplikace i interních názvů polí.
- Režim výchozího importu „vložit jako nové assety“, kdy se CSV `ID` ignoruje.
- Volitelný režim „aktualizovat podle ID“ pro aktualizaci existujících assetů.
- Kontrola, že při aktualizaci podle ID cílový asset v aktuálním modelu existuje.
- Export assetové tabulky do CSV jako šablona nebo jako přenosový formát z aktuálního modelu.

### Změněno

- README, PROJECT_STATE a AI-PROMPT rozšířeny o CSV import/export workflow.
- CSV import používá stejnou sadu validačních pravidel jako tabulkový editor assetů: `Typ` a `Název` jsou povinné, číselníky a číselná pole se validují před uložením.

### Poznámka

- Import vazeb z CSV zatím není součástí v18. Vazby lze nadále hromadně spravovat přes tabulkový editor vazeb nebo copy/paste z Excelu.

## v17-tech-performance

Datum: 2026-06-28

### Opraveno

- Dynamické pohledy (`hardware`, `data`, `process`, `supplier`, `impact`) nyní skutečně respektují povolené typy vazeb pro daný režim a finálně filtrují vazby podle vypočteného `keepEdgeIds`.
- Tabulka vazeb validuje `source_node_id` a `target_node_id` proti kompletnímu seznamu uzlů z aktuální databáze, ne proti aktuálně zobrazené podmnožině grafu.
- `save_position` nyní validuje existenci `view_id` a `node_id` a vrací řízené JSON chyby 400/404 místo nekontrolované SQLite/FK chyby.
- Rizikové položky bez vyplněné pravděpodobnosti nebo dopadu už nejsou převáděny na `1 × 1`; jsou označeny jako `unrated` / nehodnoceno.
- HTML a DOCX reporty rozlišují nehodnocená aktiva a neumisťují je do heatmapy nízkého rizika.
- Kopie a download aktuálního SQLite modelu kontrolují výsledek WAL checkpointu a v případě `busy` stavu vrátí řízenou chybu.

### Přidáno

- Endpoint `get_node_lookup` pro kompletní seznam uzlů používaný tabulkovým editorem vazeb.
- Endpoint `save_positions` pro dávkové ukládání pozic uzlů.
- Agregovaný auditní záznam `move_nodes_batch` pro hromadný přesun/zarovnání pozic.
- Konfigurace retence change logu:
  - `DORA_CHANGE_LOG_RETENTION_DAYS`, default 90 dní,
  - `DORA_CHANGE_LOG_MAX_RECORDS`, default 5000 záznamů.
- Automatické čištění `change_log` podle stáří a maximálního počtu záznamů.

### Výkon

- Hromadné ukládání pozic posílá jeden HTTP request místo jednoho requestu za každý uzel.
- Pozice se zapisují a logují jen tehdy, když se reálně změnily.
- Hromadné přesuny se v auditní stopě zapisují agregovaně, aby se SQLite databáze zbytečně nenafukovala.

## v16-changelog

Datum: 2026-06-26

### Přidáno

- `PROJECT_STATE.md` — souhrn aktuální architektury, funkcí, datového modelu a rozhodnutí.
- `CHANGELOG.md` — zpětný changelog iterací v1–v16.
- `AI-PROMPT.md` — prompt/specifikace pro AI, podle kterého lze aktuální aplikaci znovu vytvořit od začátku.

### Změněno

- Funkční kód aplikace zůstává vycházet z verze v15.

## v15-models-sidebar

### Přidáno

- `demo.sqlite` jako ukázkový model při inicializaci prázdné datové složky.
- Výběr modelu při startu podle transparentních pravidel:
  1. platný `current_model.txt`,
  2. nejmladší model kromě `demo.sqlite`,
  3. `demo.sqlite`.
- Levý panel je zasouvací.
- Stav zasunutí sidebaru se ukládá do `localStorage`.

### Změněno

- Nový model z UI je prázdný, bez demo/template dat.
- Přepnutí modelu provede reload celé stránky, aby se bezpečně překreslil graf, tabulky, views a reportové zdroje.
- `demo.sqlite` lze smazat, pokud existuje jiný model.
- Poslední existující model nelze smazat.

## v14-models

### Přidáno

- Podpora více modelů/projektů jako samostatných SQLite souborů v `/data/models`.
- Soubor `/data/current_model.txt` pro uchování aktuálního modelu.
- Adresář `/data/deleted` jako koš pro smazané modely.
- Sekce **Model / projekt** v levém panelu.
- Funkce:
  - nový prázdný model,
  - kopie aktuálního modelu,
  - přepnutí modelu,
  - smazání modelu do koše,
  - stažení aktuální DB,
  - nahrání SQLite DB.

### Změněno

- Aplikace se posunula do dokumentového režimu: editor + vyměnitelné SQLite soubory.
- Původní `/data/assets.sqlite` se při přechodu zkopíruje do `/data/models/assets.sqlite`.

## v13-docx-report

### Přidáno

- Export reportu do DOCX / MS Word.
- Tlačítko **Report DOCX** v UI.
- Tlačítko **Export DOCX / Word** v HTML reportu.
- Endpoint `/report_docx.php`.
- Generování `.docx` bez Composeru přes PHP `ZipArchive`.
- Dockerfile instaluje `libzip-dev` a PHP rozšíření `zip`.

## v12-snap-grid

### Přidáno

- Funkce **Snap to grid** v grafovém view.
- Nastavitelná velikost mřížky v px.
- Tlačítko **Zarovnat aktuální view**.
- Uložení snap nastavení do `localStorage`.

### Změněno

- Při zapnutém snapu se uzel po puštění myši zarovná na mřížku a uloží se zarovnaná pozice.

## v11-asset-card-align

### Opraveno

- Zarovnání polí `Technical owner` a `Vendor / manufacturer` v detailní kartě assetu.
- Vyšší textarea `Popis` již neroztahuje grid tak, aby pravá pole ujížděla dolů.

### Změněno

- `Popis` je mírně snížený.
- Owner/Business owner a Technical owner/Vendor jsou v samostatném pravém sub-gridu.

## v10-asset-card

### Přidáno

- Nový layout detailní karty assetu se sekcemi:
  - Základní informace,
  - Klasifikace a odolnost,
  - Data, lokalita a revize,
  - Hrozby a rizika.
- Pole `Vendor / manufacturer`.
- Tooltipy `ⓘ` k metodickým polím.
- `vendor_manufacturer` ve schématu DB, migraci, API, tabulce assetů a reportu.

### Změněno

- `Důvěrnost (CIA)`, `Integrita (CIA)`, `Dostupnost (CIA)` jsou seskupené.
- `RTO [h]`, `RPO [h]`, `MTD [h]` jsou seskupené.
- `Technical owner` je pod `Owner`.
- `Vendor / manufacturer` je pod `Business owner`.

## v9-light-tech

### Změněno

- Alternativní světlejší technický design.
- Graf má jemnou technickou mřížku.
- Uzly mají čistší CMDB/architecture card styl.
- Sidebar, tabulky a detailní karta jsou světlejší a čitelnější.
- Hrany mají decentnější barvy a lepší čitelnost popisků.

## v8-sidebar-order

### Změněno

- Sekce **Pohled** v levém panelu přesunuta pod **Legendu**.
- Pořadí levého panelu:
  - Filtr v UI,
  - Výběr,
  - Legenda,
  - Pohled.

## v7-design

### Změněno

- První výraznější designová iterace ve stylu dark/cyber/GRC.
- Tmavší pracovní plocha.
- Graf s gridovým pozadím.
- Uzly jako výraznější karty s glow efektem.
- Barvy uzlů podle typu assetu.
- Hrany barevně odlišené podle typu vazby.
- Editační karta jako tmavý glass modal.

## v6-view-buttons

### Změněno

- Tlačítka `Uložit view`, `Nový view z aktuálního` a `Smazat view` přesunuta z hlavní lišty do levého panelu do sekce **Pohled**.
- Hlavní lišta zůstává pro globální navigaci a aplikační akce.

## v5-table-save

### Přidáno

- Tabulkové views fungují jako staging editor.
- Tlačítka:
  - `+ Řádek`,
  - `Smazat řádku`,
  - `Save`.
- Hromadné vložení z Excelu přes TSV/clipboard.
- Automatické přidání řádků při vložení většího bloku.
- Validace před uložením.
- Červené zvýraznění neplatných buněk.

### Změněno

- Změny v tabulkách se neukládají okamžitě po editaci buňky.
- Asset musí mít `type` a `name`.
- Vazba musí mít `source_node_id`, `target_node_id` a `type`.

## v4-views

### Přidáno

- Oprava konceptu uložených views.
- `Uložit view` ukládá aktuální view.
- `Nový view z aktuálního` vytvoří kopii aktuálního view včetně pozic.
- `Smazat view` smaže view a jeho pozice.
- Ochrana výchozího view `Celková mapa`.

### Opraveno

- Select views již nemá přepisovat starý view novým názvem.

## v3-tables

### Přidáno

- Tlačítko **Assety tabulka**.
- Tlačítko **Vazby tabulka**.
- Excel-like tabulky s filtrováním a sortováním.
- Copy/paste přes TSV.
- Přímá editace buněk.

### Změněno

- Docker compose port změněn z `8080` na `8888`.

## v2-report

### Přidáno

- Tisknutelný HTML report.
- Tlačítko **Report / PDF**.
- Report obsahuje:
  - manažerské shrnutí,
  - počty assetů podle typů,
  - heatmapu rizik,
  - nejkritičtější assety,
  - detailní seznam assetů,
  - atributy,
  - odchozí a příchozí vazby,
  - metodickou poznámku.
- Rizikové atributy assetů:
  - hrozby,
  - rizikové scénáře,
  - pravděpodobnost,
  - dopad,
  - opatření/kontroly,
  - reziduální riziko.

### Opraveno

- Po přidání assetu se graf automaticky znovu načte a nový uzel se vybere.

## v1 základní prototyp

### Přidáno

- První funkční prototyp webové aplikace.
- Dockerfile a `docker-compose.yml`.
- PHP 8.3 + Apache.
- SQLite databáze.
- Cytoscape.js z CDN.
- Základní graf uzlů a vazeb.
- Drag & drop uzlů.
- Ukládání pozic.
- Doubleclick na uzel otevře detail.
- Editace DORA atributů.
- Vytváření/editace/smazání vazeb.
- Typ vazby `contains` pro hierarchii.
- Views.
- Dynamické režimy:
  - hardware,
  - data,
  - process,
  - supplier,
  - critical,
  - personal data,
  - impact.
- Export JSON.
- `change_log` pro auditní stopu a budoucí undo/redo.
- Demo data:
  - SAP ECC,
  - ALICE,
  - server,
  - pojistné smlouvy,
  - proces,
  - dodavatel.

## v0 návrhová fáze

### Rozhodnutí

- Použít PHP místo Perlu.
- Použít SQLite jako dokumentový backend.
- Použít Cytoscape.js pro graf.
- Modelovat aplikaci jako graf uzlů a hran.
- Nedělat `asset_components`; hierarchii řešit vazbou `contains`.
- Dodavatele a procesy modelovat jako samostatné uzly.
- Připravit DORA atributy včetně CIA, RTO, RPO, MTD, revizí a provozních metadat.
- Navrhnout reportování a později risk register.

## v19 - Asset card edge editor

- Added an editable relationship table directly to the asset detail card in graph view.
- The relationship table shows directed edges as `Asset A -> relationship type -> Asset B`.
- The currently opened asset is kept read-only on the left or right side according to the real stored edge direction.
- Existing relationships can be edited from the asset card: relationship type, other asset, criticality and description.
- Relationships can be deleted from the asset card using a trash button.
- New outgoing and incoming relationships can be added from the asset card.
- Asset detail modal now has Save/Close buttons in the header as well as the existing bottom action bar, because the card has grown and may require scrolling.
- Bottom action bar is sticky inside the modal so Save remains easier to reach.
- Added API endpoint `get_node_edges` to load all incoming/outgoing relationships for a selected node from the full DB, independent of the currently visible graph view.

## v20 - Compact asset relationship table design

- Improved the visual design of the **Vazby assetu** section in the asset detail card.
- The relationship editor now uses a more compact technical table layout with fixed column widths.
- Current asset cells are displayed as compact read-only badges instead of large table text.
- Relationship type, other asset, criticality and description fields are visually smaller and better aligned.
- The delete action is now a smaller, less visually dominant icon button.
- The section header combines the explanation and action buttons more cleanly.
- No data model or API behavior was changed in this iteration.


## v24 - Demo seed loader bug fix

- Fixed a runtime error in `app/db_seed_data.php` where the seed importer called an unavailable helper `nullable_int()`.
- Added a self-contained `seed_nullable_int()` helper in the seed module, so demo initialization no longer depends on request/API helpers.
- The demo seed remains externalized in `app/demo_seed_data.json` and still imports nodes, edges, views and positions, but not `change_log`.
- This version keeps v22 as the functional/UI baseline and v23 seed refactor, with only the seed runtime bug corrected.
