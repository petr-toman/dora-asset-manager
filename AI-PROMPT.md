# AI-PROMPT.md

## Prompt pro znovuvytvoření aktuální aplikace od začátku

Tento soubor obsahuje zadání pro ChatGPT nebo jinou AI, podle kterého má být možné znovu vytvořit aplikaci **Evidence IT aktiv / DORA Asset Map** ve stavu odpovídajícím verzi v28.

---

Jsi seniorní full-stack vývojář. Vytvoř kompletní spustitelnou webovou aplikaci **Evidence IT aktiv / DORA Asset Map** jako ZIP projekt. Aplikace má sloužit jako single-user editor/modelář ICT a informačních aktiv podle metodiky DORA. Má se chovat podobně jako Word/Excel dokumentový editor: aplikace běží v Dockeru a jednotlivé modely/projekty jsou samostatné SQLite soubory.

## 1. Technický stack

Použij:

- PHP 8.3
- Apache
- SQLite přes PDO
- HTML5
- CSS
- vanilla JavaScript
- Cytoscape.js z CDN
- Docker
- docker compose

Nepoužívej:

- Composer
- framework typu Laravel/Symfony
- databázový server
- login/autentizaci
- multiuser režim
- React/Vue/Angular

Aplikace má být jednoduchá, samostatná, přenositelná a spustitelná pomocí:

```bash
docker compose up --build
```

A má běžet na:

```text
http://localhost:8888
```

## 2. Struktura projektu

Vytvoř projektovou strukturu:

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

`docker-compose.yml` musí mapovat port `8888:80` a používat persistentní bind mount `./data:/data` přes explicitní syntaxi:

```yaml
ports:
  - "8888:80"
volumes:
  - type: bind
    source: ./data
    target: /data
```

Bind mount je záměrný: SQLite modely mají být běžné soubory v projektu a mají přežít i `docker compose down -v`. README má uvádět i full rebuild variantu:

```bash
docker compose down -v && docker compose build
docker compose up -d
```

`Dockerfile` musí instalovat podporu pro SQLite a ZIP:

- `libsqlite3-dev`
- `sqlite3`
- `libzip-dev`
- PHP rozšíření `pdo`, `pdo_sqlite`, `zip`

## 3. Koncept aplikace

Aplikace eviduje graf DORA aktiv:

- uzly = assety/entity,
- hrany = orientované typované vazby,
- views = různé uložené mapy/rozložení nad stejnými daty,
- model/projekt = samostatný SQLite soubor.

Nepoužívej samostatnou tabulku `asset_components`. Hierarchii aktiv modeluj jako hranu typu `contains`.

## 4. Modely/projekty jako SQLite dokumenty

Aplikace musí podporovat více modelů/projektů jako samostatné SQLite soubory.

Runtime adresáře:

```text
/data/
  current_model.txt
  models/
    demo.sqlite
    *.sqlite
  deleted/
    *.sqlite
```

### Inicializace modelů

Při startu:

1. vytvoř `/data`, `/data/models` a `/data/deleted`, pokud neexistují,
2. pokud existuje legacy `/data/assets.sqlite` a `/data/models` je prázdný, zkopíruj jej do `/data/models/assets.sqlite`,
3. pokud je `/data/models` prázdný, vytvoř `demo.sqlite`,
4. `demo.sqlite` musí obsahovat ukázková data podobná původnímu prototypu:
   - SAP ECC,
   - ALICE,
   - server,
   - pojistné smlouvy,
   - proces správy smluv,
   - dodavatel,
   - vazby mezi nimi,
5. nový model vytvořený přes UI musí být prázdný, bez demo/template dat,
6. pokud `current_model.txt` obsahuje platný existující model, otevři jej,
7. jinak otevři nejmladší model kromě `demo.sqlite`,
8. pokud jiný model není, otevři `demo.sqlite`.

### UI sekce Model / projekt

V levém sidebaru přidej sekci **Model / projekt** s prvky:

- select aktuálního modelu,
- tlačítko `Nový prázdný`,
- tlačítko `Kopie aktuálního`,
- tlačítko `Smazat model`,
- tlačítko/odkaz `Stáhnout DB`,
- input file + tlačítko `Nahrát DB`.

### Modelové operace

Implementuj API akce:

- `get_models`,
- `create_model`,
- `copy_model`,
- `switch_model`,
- `delete_model`,
- `upload_model`,
- `download_model`.

Pravidla:

- názvy SQLite souborů validuj, aby neumožňovaly path traversal,
- povol pouze bezpečné názvy končící `.sqlite`,
- při duplicitě názvu vytvoř unikátní variantu,
- `Kopie aktuálního` vytvoří název podle originálu + `kopie` + timestamp,
- po vytvoření/přepnutí/importu modelu udělej reload celé stránky,
- `Smazat model` nepálí soubor fyzicky, ale přesune ho do `/data/deleted`,
- poslední existující model nesmí jít smazat,
- `demo.sqlite` lze smazat, pokud existuje jiný model.

## 5. SQLite schéma

Vytvoř `schema.sql` s tabulkami:

### `nodes`

Pole:

- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `type TEXT NOT NULL DEFAULT 'software'`
- `name TEXT NOT NULL`
- `description TEXT`
- `owner TEXT`
- `business_owner TEXT`
- `technical_owner TEXT`
- `vendor_manufacturer TEXT`
- `criticality TEXT`
- `confidentiality TEXT`
- `integrity_level TEXT`
- `availability TEXT`
- `rto_hours INTEGER`
- `rpo_hours INTEGER`
- `mtd_hours INTEGER`
- `data_sensitivity TEXT`
- `data_categories TEXT`
- `environment TEXT`
- `location TEXT`
- `status TEXT`
- `lifecycle_state TEXT`
- `good_to_know TEXT`
- `last_reviewed_at TEXT`
- `review_frequency_months INTEGER`
- `threats TEXT`
- `risk_scenarios TEXT`
- `risk_likelihood INTEGER`
- `risk_impact INTEGER`
- `risk_controls TEXT`
- `residual_risk TEXT`
- `created_at TEXT NOT NULL`
- `updated_at TEXT NOT NULL`

### `edges`

Pole:

- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `source_node_id INTEGER NOT NULL`
- `target_node_id INTEGER NOT NULL`
- `type TEXT NOT NULL`
- `description TEXT`
- `criticality TEXT`
- `created_at TEXT NOT NULL`
- `updated_at TEXT NOT NULL`

Hrany mají foreign key na `nodes` a `ON DELETE CASCADE`.

### `views`

Pole:

- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `name TEXT NOT NULL UNIQUE`
- `description TEXT`
- `filter_json TEXT`
- `created_at TEXT NOT NULL`
- `updated_at TEXT NOT NULL`

Default view:

```text
id = 1
name = Celková mapa
```

Tento view nesmí jít smazat.

### `view_node_positions`

Pole:

- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `view_id INTEGER NOT NULL`
- `node_id INTEGER NOT NULL`
- `x REAL NOT NULL`
- `y REAL NOT NULL`
- `width REAL`
- `height REAL`
- `visible INTEGER DEFAULT 1`
- `collapsed INTEGER DEFAULT 0`

Unikátní klíč:

```text
UNIQUE(view_id, node_id)
```

### `change_log`

Pole:

- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `action TEXT NOT NULL`
- `entity_type TEXT NOT NULL`
- `entity_id INTEGER`
- `before_json TEXT`
- `after_json TEXT`
- `created_at TEXT NOT NULL`
- `created_by TEXT`

Použij jej jako auditní stopu a základ pro budoucí undo/redo.

## 6. Číselníky

Typy uzlů:

- `hardware`
- `software`
- `data`
- `process`
- `business_function`
- `supplier`
- `provider`
- `manufacturer`
- `network`
- `location`
- `documentation`
- `ict_service`

Typy vazeb:

- `contains`
- `hosts`
- `runs_on`
- `stores`
- `processes_data`
- `uses_data`
- `supports_process`
- `supports_function`
- `depends_on`
- `provided_by`
- `supplied_by`
- `manufactured_by`
- `connected_to`
- `backed_up_by`
- `monitored_by`
- `administered_by`
- `integrates_with`
- `authenticates_via`

Kritičnost/CIA hodnoty:

- `low`
- `medium`
- `high`
- `critical`

Citlivost dat:

- `public`
- `private`
- `secret`

Kategorie dat:

- osobní,
- firemní-interní,
- obecná,
- finanční,
- obchodní,
- správní,
- technologická.

Prostředí:

- `prod`
- `test`
- `dev`
- `archive`

Stav:

- `active`
- `planned`
- `retired`
- `unknown`

Lifecycle:

- `production`
- `test`
- `development`
- `archived`
- `retired`

## 7. API

Implementuj `api.php` s JSON odpověďmi.

Minimální akce:

- `get_graph`
- `get_node`
- `save_node`
- `delete_node`
- `save_edge`
- `delete_edge`
- `save_position`
- `get_views`
- `save_view`
- `copy_view`
- `delete_view`
- `export_json`
- `get_table_assets`
- `save_table_assets`
- `get_table_edges`
- `save_table_edges`
- `get_models`
- `create_model`
- `copy_model`
- `switch_model`
- `delete_model`
- `upload_model`
- `download_model`

Všechny akce musí pracovat nad aktuálně vybraným SQLite modelem.

## 8. Grafové UI

V `main.php` vytvoř aplikaci se:

- horní lištou,
- levým sidebarem,
- hlavní grafovou plochou,
- detailním modalem/drawerem assetu,
- modalem/formulářem vazby,
- tabulkovými pohledy.

Horní lišta:

- `Graf`
- `Assety tabulka`
- `Vazby tabulka`
- `+ Uzel`
- `+ Vazba`
- `Export JSON`
- `Report / PDF`
- `Report DOCX`

Levý sidebar obsahuje sekce:

1. Filtr v UI
2. Výběr
3. Legenda
4. Pohled
5. Model / projekt

Sidebar má být zasouvací tlačítkem `‹ / ›`; stav ukládej do `localStorage`.

Graf:

- použij Cytoscape.js,
- uzly zobraz jako obdélníkové technické karty,
- hrany jako směrové čáry s popisky,
- doubleclick na uzel otevře detail,
- drag & drop ukládá pozice,
- click vybírá uzel/hranu.

Podporuj dynamické režimy grafu:

- all,
- hardware,
- data,
- process,
- supplier,
- critical,
- personal_data,
- impact.

## 9. Views

V sekci **Pohled**:

- select uložených views,
- `Uložit view`,
- `Nový view z aktuálního`,
- `Smazat view`,
- `Snap to grid`,
- velikost gridu,
- `Zarovnat aktuální view`.

`Nový view z aktuálního` zkopíruje aktuální view včetně pozic.

`Smazat view` nesmí smazat view s ID 1.

Snap to grid:

- nastavení ukládej do `localStorage`,
- při zapnutí zarovnej uzel po puštění myši na mřížku,
- tlačítko `Zarovnat aktuální view` zarovná všechny viditelné uzly a uloží pozice.

## 10. Detailní karta assetu

Detailní karta má být světlá technická karta/modal. Sekce:

1. Základní informace
2. Klasifikace a odolnost
3. Data, lokalita a revize
4. Hrozby a rizika

Layout:

- `Popis` jako textarea s rozumnou výškou.
- Vpravo od popisu:
  - `Owner`, `Business owner`,
  - pod nimi `Technical owner`, `Vendor / manufacturer`.
- `Důvěrnost (CIA)`, `Integrita (CIA)`, `Dostupnost (CIA)` vedle sebe.
- `RTO [h]`, `RPO [h]`, `MTD [h]` vedle sebe.

Tooltipy `ⓘ` doplň k:

- Kritičnost,
- Prostředí,
- Owner,
- Business owner,
- Technical owner,
- Vendor / manufacturer,
- CIA,
- RTO,
- RPO,
- MTD,
- Citlivost dat,
- Revize.

Vysvětlení:

- RTO = Recovery Time Objective, cílová doba obnovy.
- RPO = Recovery Point Objective, přípustná ztráta dat v hodinách.
- MTD = Maximum Tolerable Downtime, maximálně tolerovatelný výpadek.
- CIA = Confidentiality, Integrity, Availability.

## 11. Excel-like tabulky

Přidej dva pohledy:

- `Assety tabulka`,
- `Vazby tabulka`.

Vlastnosti:

- zobrazit všechna hlavní pole,
- editace v buňkách,
- přidat řádek,
- označit řádky checkboxem,
- smazat označené řádky,
- změny ukládat až tlačítkem `Save`,
- validovat až při `Save`,
- neplatné buňky zvýraznit červeně,
- sortování klikem na hlavičku,
- filtrování,
- Ctrl+C/Ctrl+V přes TSV kompatibilní s Excelem.

Povinná pole assetu:

- `type`,
- `name`.

Povinná pole vazby:

- `source_node_id`,
- `target_node_id`,
- `type`.

U vazeb zobraz read-only názvy zdrojového a cílového uzlu, ale editovat se mají ID.

## 12. Report / PDF

Vytvoř `/report.php` jako tisknutelný HTML report.

Obsah:

- manažerské shrnutí,
- počty assetů podle typu,
- heatmapa rizik,
- nejkritičtější assety,
- detailní seznam assetů,
- atributy assetů,
- odchozí vazby,
- příchozí vazby,
- metodická poznámka.

V HTML reportu přidej tlačítka:

- `Tisk / uložit jako PDF`,
- `Export DOCX / Word`.

## 13. DOCX report

Vytvoř `/report_docx.php`, který vygeneruje `.docx` soubor.

Nepoužívej Composer ani PHPWord. Vygeneruj minimální validní DOCX jako ZIP s částmi:

- `[Content_Types].xml`,
- `_rels/.rels`,
- `word/document.xml`,
- případně `word/styles.xml`.

DOCX obsahuje podobné sekce jako HTML report:

- manažerské shrnutí,
- počty assetů podle typů,
- heatmapu rizik jako Word tabulku,
- top kritická aktiva,
- detailní seznam assetů,
- vazby,
- metodickou poznámku.

## 14. Risk scoring

U assetu eviduj:

- hrozby,
- rizikové scénáře,
- pravděpodobnost 1–5,
- dopad 1–5,
- opatření/kontroly,
- reziduální riziko.

Použij jednoduché pracovní skóre:

```text
risk_likelihood × risk_impact
+ váha criticality
+ váha nejvyšší hodnoty CIA
+ váha RTO
```

RTO váha má zvýšit skóre, pokud je RTO krátké.

## 15. Design

Použij světlejší technický design:

- čistý CMDB/architecture styl,
- světlé panely,
- jemná technická mřížka v grafu,
- uzly jako technické karty,
- barevné akcenty podle typu uzlu,
- decentní hrany podle typu vazby,
- dobře čitelný sidebar,
- moderní modal detailu,
- tabulky podobné Excelu.

Barvy uzlů podle typu:

- hardware: šedá/modrošedá,
- software: modrá,
- data: zelená,
- process: oranžová,
- supplier/provider/manufacturer: fialová,
- ostatní: neutrální.

Hrany:

- `contains` čárkovaně,
- data vazby zeleně,
- hosting modře,
- procesní vazby oranžově,
- dodavatelské vazby fialově,
- ostatní neutrálně.

## 16. Dokumentace

Do ZIPu přidej:

- `README.md` s návodem ke spuštění a shrnutím funkcí,
- `PROJECT_STATE.md` s popisem aktuální architektury a stavu,
- `CHANGELOG.md` se seznamem iterací,
- `AI-PROMPT.md` s tímto promptem.

## 17. Kvalita a kontroly

- PHP soubory musí projít `php -l`.
- JavaScript musí být syntakticky validní.
- Aplikace má fungovat bez internetu kromě načtení Cytoscape.js z CDN; pro offline variantu lze později přibalit lokální soubor.
- Aplikace je určena pro single-user provoz.
- Přechod na MariaDB/PostgreSQL není součástí této verze.
- Nepřidávej login.
- Nepřidávej merge modelů.
- Nepřidávej porovnání modelů.

Výstupem má být ZIP s celým projektem.

---

## Dodatek pro verzi v17 — technická a výkonová vylepšení

Při implementaci musí být součástí aplikace také následující technické požadavky:

### Dynamické pohledy

Dynamické grafové views (`hardware`, `data`, `process`, `supplier`, `critical`, `personal_data`, `impact`) nesmí pouze rozšířit okolí vybraných uzlů libovolnými hranami. Každý režim musí mít explicitní seznam povolených typů hran. Při průchodu grafem se smí zahrnout pouze hrany z tohoto seznamu a finální filtrování hran musí používat vypočtený seznam `keepEdgeIds`.

### Validace tabulky vazeb

Excel-like tabulka vazeb musí validovat `source_node_id` a `target_node_id` proti kompletnímu seznamu uzlů v aktuální SQLite databázi, nikoli proti uzlům právě viditelným v grafu. Pro tento účel má existovat endpoint například:

```text
/api.php?action=get_node_lookup
```

### Ukládání pozic

Kromě single endpointu `save_position` musí existovat batch endpoint:

```text
/api.php?action=save_positions
```

Frontend ho použije pro hromadné uložení pozic, například při `Zarovnat aktuální view`. Backend musí ověřit existenci `view_id` i všech `node_id`, zapisovat pouze reálně změněné pozice a v audit logu hromadnou změnu uložit agregovaně jako `move_nodes_batch`.

### Validace API

Endpointy pro ukládání pozic musí vracet řízené JSON chyby 400/404, pokud `view_id` nebo `node_id` neexistují. Nemají spoléhat na SQLite foreign key exception a HTTP 500.

### Change log retention

Change log se nesmí neomezeně nafukovat. Aplikace má mít konfigurovatelnou retenci:

```text
DORA_CHANGE_LOG_RETENTION_DAYS = 90
DORA_CHANGE_LOG_MAX_RECORDS = 5000
```

Po zápisu do change logu se má provádět lehké čištění podle stáří a maximálního počtu záznamů.

### Rizikové skóre

Nevyplněná pravděpodobnost nebo dopad se nesmí interpretovat jako `1 × 1`. Pokud `risk_likelihood` nebo `risk_impact` chybí, asset má mít stav `unrated` / nehodnoceno, jeho skóre má být `null` a v reportech se má zobrazit zvlášť mimo heatmapu.

### SQLite kopie modelu

Před kopírováním nebo stažením aktuálního SQLite modelu má aplikace zavolat `PRAGMA wal_checkpoint(FULL)` a zkontrolovat návratový stav. Pokud je checkpoint `busy`, operace má vrátit řízenou chybu a nemá kopírovat pouze hlavní `.sqlite` soubor.


## CSV import/export požadavek

Implementuj v pohledu **Assety tabulka** funkce:

- `Import CSV` — upload CSV souboru s assety, preview před zápisem, validace celého souboru, zápis jen pokud nejsou chyby.
- `Export CSV` — stažení aktuální assetové tabulky v CSV UTF-8 se středníkem jako oddělovačem.
- CSV import má podporovat hlavičky shodné s UI tabulkou: `ID`, `Typ`, `Název`, `Popis`, `Owner`, `Business owner`, `Technical owner`, `Vendor/manufacturer`, `Kritičnost`, `Důvěrnost`, `Integrita`, `Dostupnost`, `RTO h`, `RPO h`, `MTD h`, `Citlivost dat`, `Kategorie dat`, `Prostředí`, `Lokalita`, `Stav`, `Lifecycle`, `Poslední revize`, `Revize měs.`, `Hrozby`, `Rizikové scénáře`, `Pravděp. 1-5`, `Dopad 1-5`, `Kontroly`, `Reziduální riziko`, `Good-to-know`.
- Výchozí import ignoruje `ID` a vkládá řádky jako nové assety.
- Volitelný režim aktualizace podle ID musí ověřit, že ID v aktuální DB existuje.
- Validuj povinné `Typ` a `Název`, číselníky, čísla a rizikové hodnoty 1–5.
- Import vazeb z CSV není součástí tohoto požadavku.

### v19 UI requirement: relationship editor in asset card

In the graph asset detail modal, add a final section called `Vazby assetu`. This section must display all relationships where the opened asset is either source or target. Render it as a table with the conceptual columns `Asset A`, `Typ vazby`, `Asset B`, `Kritičnost`, `Popis`, and delete action. Relationships are directed and active, so the current asset must appear read-only on the correct side: left if it is `source_node_id`, right if it is `target_node_id`. The other asset must be editable via a select populated from all nodes in the current model. The relationship type must be editable via the edge type dictionary. Allow adding outgoing and incoming relationships separately, deleting relationships with a trash button, and saving relationship changes together with the asset card. Add an API endpoint that loads all incoming/outgoing edges for a node from the full DB, not just from the currently visible graph. Also provide Save/Close buttons in the modal header and keep the bottom action bar sticky for long asset cards.


## v20 UI/design requirement

When recreating or extending the app, keep the asset-card relationship editor compact. It should look like a technical relationship table rather than a large form: fixed column widths, small selects/inputs, read-only badge for the current asset, subtle delete icon, and a concise header toolbar.


## v21 UI/design requirement

When rendering the asset-card relationship editor, keep the relationship table visually aligned with the rest of the asset form. Relationship table controls must not look larger or heavier than normal form fields. Use compact 12px text, 30px-high inputs/selects, 10px border radius, subtle borders, form-like focus styling, understated headers, a neutral read-only badge for the current asset, and a delete button that is visually calm by default and becomes red only on hover. This is a pure UI refinement over v20; do not change APIs or persistence behavior.


## UI detail v22

V sekci Vazby assetu na detailu assetu musí inputy/selecty používat stejný vizuální styl jako běžná formulářová pole karty assetu: stejná výška, padding, font, border radius, pozadí a focus state. Vazbová tabulka nesmí působit typograficky větší nebo dominantnější než zbytek asset formuláře.


## v23 seed requirement

Keep demo seed data outside the core DB helper. Implement `app/db_seed_data.php` with `seed_demo_data(PDO $pdo)` and store the demo dataset in `app/demo_seed_data.json`. Load this seed module from `db.php` only when initializing a new demo model. Do not seed `change_log`; seed only nodes, edges, views and view positions.


## v24 seed implementation detail

When implementing the external demo seed loader, make `app/db_seed_data.php` self-contained for seed-only helper conversions. Do not call request/API helper functions such as `nullable_int()` unless they are guaranteed to be loaded. Define a local helper such as `seed_nullable_int()` inside the seed module and use it for nullable numeric fields.


## Additional requirement from v25

In the Edges table view, keep `source_node_id` and `target_node_id` as directly editable numeric cells to preserve Excel/TSV copy-paste workflows. Add a double-click asset picker on these cells. The picker must search all nodes in the current model and display each option as `Name (type) · ID`. Selecting an option writes the numeric ID into the cell and updates the corresponding read-only source/target name field in the same table row.


## Additional requirement from v26

In the Assets table view, keep all cells directly editable to preserve Excel/TSV copy-paste workflows. For stable enum fields, add a double-click picker that opens a small list of allowed values and writes the selected raw value into the cell.

Fields requiring a picker:

- `type`
- `criticality`
- `environment`
- `status`
- `confidentiality`
- `integrity_level`
- `availability`
- `lifecycle_state`
- `data_sensitivity`

Do not replace cells with permanent `<select>` elements; direct editing must remain available. Use the picker only as a helper on double-click.

Consolidate third-party node types: replace separate `supplier`, `provider` and `manufacturer` node types with one raw type `third_party`, displayed as `3. strana (dodavatel)`. Existing older models should be normalized by converting nodes with type `supplier`, `provider` or `manufacturer` to `third_party` during schema initialization. Demo seed data should also use `third_party`.

## Additional requirement from v27

In the **Assety tabulka** view, improve usability for very wide node tables without replacing the existing spreadsheet-like editor.

Implement sticky left columns for:

- row delete/select checkbox,
- `ID`,
- `Typ`,
- `Název`.

These columns must remain visible while horizontally scrolling to the right side of the table. The solution should be lightweight, preferably CSS `position: sticky`, and must not break:

- direct cell editing,
- TSV/Excel copy-paste,
- sorting/filtering,
- validation,
- v26 double-click enum pickers.

Add a toolbar toggle for row display mode:

- compact mode: uniform row height, long values visually hidden with CSS ellipsis, no data truncation,
- full mode: previous wrapping behavior with row height based on content.

Persist the compact/full preference in `localStorage`. Do not implement manual column resizing in this iteration.

## Additional requirement from v28

Add repository hygiene files:

- `LICENSE` with MIT license text,
- `.gitignore`,
- `.gitkeep` placeholders in `data/`, `data/models/` and `data/deleted/`.

The `.gitignore` should exclude runtime SQLite data and local noise, especially:

```gitignore
data/models/*.sqlite
data/models/*.sqlite-*
data/deleted/*.sqlite
data/deleted/*.sqlite-*
data/assets.sqlite
data/assets.sqlite-*
data/current_model.txt
.env
.env.local
*.log
*.tmp
*.bak
.DS_Store
**/.DS_Store
Thumbs.db
.idea/
.vscode/
*.zip
```

Do not commit real SQLite model data. Keep only the runtime directory structure through `.gitkeep`.
