# CHANGELOG.md

## Evidence IT aktiv / DORA Asset Map

Tento changelog byl zpětně zrekonstruován z iterací vývoje v chatu. Verze v16 nemění funkčnost aplikace, ale doplňuje dokumentaci projektu.

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
