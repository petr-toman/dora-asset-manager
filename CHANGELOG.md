# CHANGELOG

## v31 - Detail card, table filters and report UIX

- Detailní karta assetu má sticky záhlaví a sticky zápatí, aby akce **Uložit**, **Zavřít** a **Smazat asset** zůstaly dostupné i při scrollu dlouhého formuláře.
- Záhlaví detailu zobrazuje přímo `Název (typ)` a drobný řádek `Asset ID · skóre rizika`; prefix `Uzel:` a samostatné tlačítko `×` byly odstraněny.
- Číselníkové hodnoty v detailu i v editorech vazeb se v UI zobrazují jako uživatelské popisky bez interních DB hodnot v hranatých závorkách. DB hodnoty zůstávají interně ukládané beze změny.
- Sekce **Vazby assetu** na kartě assetu má jemnější typografii, sjednocené nadpisy sekcí a původní směrový popis byl přesunut do tooltipu. Výběry assetů v této sekci zobrazují jen název a typ, ne ID.
- Tabulka assetů má užší sticky sloupce `checkbox`, `ID`, `Typ` a `Název`.
- Tabulky mají řádek textových filtrů pod hlavičkou; filtrování skrývá řádky přes `display:none`, takže řádky zůstávají v DOM.
- Tabulka vazeb má double-click výběr z číselníků pro `Typ vazby` a `Kritičnost`, konzistentně s asset kartou.
- HTML/PDF report dostal kartový design s KPI boxy, čistšími tabulkami a print CSS proti zalomení heatmapy přes více stran.
- Aktualizovány `README.md`, `PROJECT_STATE.md` a `AI-PROMPT.md` na stav v31.

## v30 - Third-party naming and documentation cleanup

- Sjednoceno aktuální názvosloví třetích stran v dokumentaci: primární raw typ uzlu je `third_party`, zobrazovaný jako `3. strana (dodavatel)`.
- Dynamický grafový režim třetích stran používá v HTML/PHP primárně hodnotu `third_party` místo historického `supplier`.
- V API zůstal zachován pouze zpětně kompatibilní alias režimu `supplier` -> `third_party` pro případ starších volání.
- Vyčištěny reference v JS/CSS, které už stylovaly historické node typy `supplier`, `provider` a `manufacturer` jako aktuální typy.
- Oddělena normalizace typu vazby od normalizace typu uzlu; ukládání vazeb už nepoužívá node-type normalizátor pro edge typy.
- Aktualizovány `README.md`, `PROJECT_STATE.md` a `AI-PROMPT.md` na stav v30.
- `CHANGELOG.md` byl srovnán do jedné chronologické sekvence v30 -> v0 bez duplicitních bloků.

## v29 - Named Docker data volume semantics

- Changed `docker-compose.yml` from a bind mount to a Docker named volume `dora_assets_data` for `/data`.
- Data now survives ordinary rebuilds and `docker compose down`, while `docker compose down -v` intentionally removes the volume and resets the application data.
- Updated README to describe the difference between a normal rebuild and a full reinstall/reset.
- Kept `.gitignore` rules for local `./data` SQLite files for optional local development or manual bind-mount scenarios.
- No application runtime logic or UI behavior changed.

## v28 - License, gitignore and persistent data mount

- Added `LICENSE` file from the provided MIT license text.
- Added `.gitignore` for runtime SQLite model files, current model state, SQLite WAL/SHM/journal files, local env/log/temp files, OS/editor metadata and generated ZIP archives.
- Added `.gitkeep` placeholders for `data/`, `data/models/` and `data/deleted/` so the runtime directory structure can be committed without real SQLite data.
- Updated `docker-compose.yml` in v28 to make `/data` persistence explicit. This was refined in v29 from bind mount to named volume so `docker compose down -v` can intentionally reset data.
- Updated README with standard background start and full rebuild commands.
- Updated project documentation to describe repository hygiene and runtime data handling.

## v27 - Assets table usability

- Added sticky left columns in **Assety tabulka** for the row selector, `ID`, `Typ` and `Název`.
- Horizontal scrolling no longer hides the asset identity while editing attributes on the right side of the wide table.
- Added a toolbar toggle for compact/full row display.
- Compact mode keeps rows at a consistent height and visually truncates long text with ellipsis without changing the stored value.
- Full mode keeps the previous wrapping behavior with row height based on content.
- The compact/full preference is stored in `localStorage`.
- Preserved direct cell editing, TSV/Excel copy-paste, validation and v26 double-click value pickers.

## v26 - Node table enum pickers and third-party type consolidation

- Added double-click pickers in **Assety tabulka** for stable enum fields: `type`, `criticality`, `environment`, `status`, `confidentiality`, `integrity_level`, `availability`, `lifecycle_state` and `data_sensitivity`.
- Direct raw-value editing and TSV/Excel copy-paste remain available.
- Consolidated node types `supplier`, `provider` and `manufacturer` into the single node type `third_party`, displayed as `3. strana (dodavatel)`.
- Added automatic normalization of older SQLite models from the former third-party types to `third_party`.
- Demo seed data was updated to use `third_party`.

## v25 - Edge table asset picker

- Added a double-click asset picker for `source_node_id` and `target_node_id` cells in the Edges table.
- Users can still directly type numeric asset IDs and paste TSV/Excel rows unchanged.
- The picker searches all nodes in the current model and displays options as `Name (type) · ID`.
- Selecting an asset fills the numeric ID and refreshes the read-only source/target name in the table row.
- Updated edge-table UX documentation.

## v24 - Demo seed loader bug fix

- Fixed a runtime error in `app/db_seed_data.php` where the seed importer called an unavailable helper `nullable_int()`.
- Added a self-contained `seed_nullable_int()` helper in the seed module, so demo initialization no longer depends on request/API helpers.
- The demo seed remains externalized in `app/demo_seed_data.json` and still imports nodes, edges, views and positions, but not `change_log`.
- This version keeps v22 as the functional/UI baseline and v23 seed refactor, with only the seed runtime bug corrected.

## v23 - Demo seed data refactor

- Demo seed data was replaced by the updated exported JSON model supplied on 2026-06-28.
- `seed_demo_data()` was moved out of `app/db.php` into `app/db_seed_data.php`.
- The actual demo dataset is stored in `app/demo_seed_data.json`.
- `db.php` now loads the seed module only when a new demo model is initialized, reducing normal request parse/load cost and keeping database bootstrap code cleaner.
- Seed import excludes `change_log`; demo data contains nodes, edges, views and view node positions only.

## v22 - Asset relationship field consistency

- Sjednocen vzhled polí v tabulce **Vazby assetu** se standardními formulářovými poli na kartě assetu.
- Vstupy/selecty v relationship editoru používají kompaktní, formulářově konzistentní rozměry a focus styl.
- Jde o UI refinement navazující na v21 bez změny datového modelu nebo API.

## v21 - Asset relationship table visual alignment

- Sekce **Vazby assetu** na detailní kartě assetu byla vizuálně sjednocena se zbytkem formuláře.
- Pole v tabulce vazeb používají menší písmo, nižší výšku, stejné zaoblení, podobné ohraničení a focus styl jako běžná formulářová pole na kartě.
- Hlavičky tabulky vazeb jsou méně dominantní a už nepůsobí jako samostatná velká tabulka mimo formulář.
- Read-only aktuální asset v tabulce vazeb je zobrazen jako decentní badge se stejnou výškou jako input/select pole.
- Tlačítko smazání vazby je méně agresivní; červená se objeví až při hoveru.
- Jde čistě o UI/design iteraci. Funkce, API a datový model zůstávají stejné jako ve v20.

## v20 - Compact asset relationship table design

- Improved the visual design of the **Vazby assetu** section in the asset detail card.
- The relationship editor now uses a more compact technical table layout with fixed column widths.
- Current asset cells are displayed as compact read-only badges instead of large table text.
- Relationship type, other asset, criticality and description fields are visually smaller and better aligned.
- The delete action is now a smaller, less visually dominant icon button.
- The section header combines the explanation and action buttons more cleanly.
- No data model or API behavior was changed in this iteration.

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

## v18 - CSV import/export

- Import assetů z CSV přímo z obrazovky **Assety tabulka**.
- CSV preview před zápisem do databáze.
- Validace celého CSV před importem; do DB se ukládá pouze tehdy, pokud je celý soubor validní.
- Detekce oddělovače CSV (`;`, `,`, tab) a podpora UTF-8 BOM.
- Mapování českých hlaviček z tabulky aplikace i interních názvů polí.
- Režim výchozího importu „vložit jako nové assety“, kdy se CSV `ID` ignoruje.
- Volitelný režim „aktualizovat podle ID“ pro aktualizaci existujících assetů.
- Kontrola, že při aktualizaci podle ID cílový asset v aktuálním modelu existuje.
- Export assetové tabulky do CSV jako šablona nebo jako přenosový formát z aktuálního modelu.
- README, PROJECT_STATE a AI-PROMPT rozšířeny o CSV import/export workflow.
- Import vazeb z CSV zatím není součástí v18.

## v17 - Technical correctness and performance hardening

- Dynamické pohledy (`hardware`, `data`, `process`, `supplier`, `impact`) začaly respektovat povolené typy vazeb pro daný režim a finálně filtrovat vazby podle vypočteného `keepEdgeIds`.
- Tabulka vazeb validuje `source_node_id` a `target_node_id` proti kompletnímu seznamu uzlů z aktuální databáze, ne proti aktuálně zobrazené podmnožině grafu.
- `save_position` validuje existenci `view_id` a `node_id` a vrací řízené JSON chyby 400/404 místo nekontrolované SQLite/FK chyby.
- Rizikové položky bez vyplněné pravděpodobnosti nebo dopadu už nejsou převáděny na `1 × 1`; jsou označeny jako `unrated` / nehodnoceno.
- HTML a DOCX reporty rozlišují nehodnocená aktiva a neumisťují je do heatmapy nízkého rizika.
- Kopie a download aktuálního SQLite modelu kontrolují výsledek WAL checkpointu a v případě `busy` stavu vrátí řízenou chybu.
- Přidán endpoint `get_node_lookup` pro kompletní seznam uzlů používaný tabulkovým editorem vazeb.
- Přidán endpoint `save_positions` pro dávkové ukládání pozic uzlů a agregovaný auditní záznam `move_nodes_batch`.
- Přidána retence change logu přes `DORA_CHANGE_LOG_RETENTION_DAYS` a `DORA_CHANGE_LOG_MAX_RECORDS`.

## v16 - Documentation baseline

- Přidán `PROJECT_STATE.md` se souhrnem aktuální architektury, funkcí, datového modelu a rozhodnutí.
- Přidán `CHANGELOG.md` jako zpětný changelog iterací v1-v16.
- Přidán `AI-PROMPT.md` jako prompt/specifikace pro AI, podle kterého lze aktuální aplikaci znovu vytvořit od začátku.
- Funkční kód aplikace zůstává vycházet z verze v15.

## v15 - Models sidebar

- `demo.sqlite` jako ukázkový model při inicializaci prázdné datové složky.
- Výběr modelu při startu podle transparentních pravidel: platný `current_model.txt`, nejmladší model kromě `demo.sqlite`, jinak `demo.sqlite`.
- Levý panel je zasouvací.
- Stav zasunutí sidebaru se ukládá do `localStorage`.
- Nový model z UI je prázdný, bez demo/template dat.
- Přepnutí modelu provede reload celé stránky, aby se bezpečně překreslil graf, tabulky, views a reportové zdroje.
- `demo.sqlite` lze smazat, pokud existuje jiný model.
- Poslední existující model nelze smazat.

## v14 - Models as SQLite documents

- Podpora více modelů/projektů jako samostatných SQLite souborů v `/data/models`.
- Soubor `/data/current_model.txt` pro uchování aktuálního modelu.
- Adresář `/data/deleted` jako koš pro smazané modely.
- Sekce **Model / projekt** v levém panelu.
- Funkce: nový prázdný model, kopie aktuálního modelu, přepnutí modelu, smazání modelu do koše, stažení aktuální DB, nahrání SQLite DB.
- Aplikace se posunula do dokumentového režimu: editor + vyměnitelné SQLite soubory.
- Původní `/data/assets.sqlite` se při přechodu zkopíruje do `/data/models/assets.sqlite`.

## v13 - DOCX report

- Export reportu do DOCX / MS Word.
- Tlačítko **Report DOCX** v UI.
- Tlačítko **Export DOCX / Word** v HTML reportu.
- Endpoint `/report_docx.php`.
- Generování `.docx` bez Composeru přes PHP `ZipArchive`.
- Dockerfile instaluje `libzip-dev` a PHP rozšíření `zip`.

## v12 - Snap to grid

- Funkce **Snap to grid** v grafovém view.
- Nastavitelná velikost mřížky v px.
- Tlačítko **Zarovnat aktuální view**.
- Uložení snap nastavení do `localStorage`.
- Při zapnutém snapu se uzel po puštění myši zarovná na mřížku a uloží se zarovnaná pozice.

## v11 - Asset card alignment

- Opraveno zarovnání polí `Technical owner` a `Vendor / manufacturer` v detailní kartě assetu.
- Vyšší textarea `Popis` již neroztahuje grid tak, aby pravá pole ujížděla dolů.
- `Popis` je mírně snížený.
- Owner/Business owner a Technical owner/Vendor jsou v samostatném pravém sub-gridu.

## v10 - Asset card redesign

- Nový layout detailní karty assetu se sekcemi: Základní informace, Klasifikace a odolnost, Data, lokalita a revize, Hrozby a rizika.
- Přidáno pole `Vendor / manufacturer`.
- Přidány tooltipy `ⓘ` k metodickým polím.
- Přidán sloupec `vendor_manufacturer` ve schématu DB, migraci, API, tabulce assetů a reportu.
- `Důvěrnost (CIA)`, `Integrita (CIA)`, `Dostupnost (CIA)` jsou seskupené.
- `RTO [h]`, `RPO [h]`, `MTD [h]` jsou seskupené.
- `Technical owner` je pod `Owner` a `Vendor / manufacturer` pod `Business owner`.

## v9 - Light technical design

- Alternativní světlejší technický design.
- Graf má jemnou technickou mřížku.
- Uzly mají čistší CMDB/architecture card styl.
- Sidebar, tabulky a detailní karta jsou světlejší a čitelnější.
- Hrany mají decentnější barvy a lepší čitelnost popisků.

## v8 - Sidebar order

- Sekce **Pohled** v levém panelu přesunuta pod **Legendu**.
- Pořadí levého panelu: Filtr v UI, Výběr, Výběr, Legenda, Pohled.

## v7 - Design iteration

- První výraznější designová iterace ve stylu dark/cyber/GRC.
- Tmavší pracovní plocha.
- Graf s gridovým pozadím.
- Uzly jako výraznější karty s glow efektem.
- Barvy uzlů podle typu assetu.
- Hrany barevně odlišené podle typu vazby.
- Editační karta jako tmavý glass modal.

## v6 - View buttons relocation

- Tlačítka `Uložit view`, `Nový view z aktuálního` a `Smazat view` přesunuta z hlavní lišty do levého panelu do sekce **Pohled**.
- Hlavní lišta zůstává pro globální navigaci a aplikační akce.

## v5 - Table staging save

- Tabulkové views fungují jako staging editor.
- Přidána tlačítka `+ Řádek`, `Smazat řádku`, `Save`.
- Hromadné vložení z Excelu přes TSV/clipboard.
- Automatické přidání řádků při vložení většího bloku.
- Validace před uložením.
- Červené zvýraznění neplatných buněk.
- Změny v tabulkách se neukládají okamžitě po editaci buňky.
- Asset musí mít `type` a `name`; vazba musí mít `source_node_id`, `target_node_id` a `type`.

## v4 - Views correction

- Oprava konceptu uložených views.
- `Uložit view` ukládá aktuální view.
- `Nový view z aktuálního` vytvoří kopii aktuálního view včetně pozic.
- `Smazat view` smaže view a jeho pozice.
- Ochrana výchozího view `Celková mapa`.
- Select views již nemá přepisovat starý view novým názvem.

## v3 - Table views

- Přidána tlačítka **Assety tabulka** a **Vazby tabulka**.
- Excel-like tabulky s filtrováním a sortováním.
- Copy/paste přes TSV.
- Přímá editace buněk.
- Docker compose port změněn z `8080` na `8888`.

## v2 - Report

- Tisknutelný HTML report.
- Tlačítko **Report / PDF**.
- Report obsahuje manažerské shrnutí, počty assetů podle typů, heatmapu rizik, nejkritičtější assety, detailní seznam assetů, atributy, odchozí/příchozí vazby a metodickou poznámku.
- Přidány rizikové atributy assetů: hrozby, rizikové scénáře, pravděpodobnost, dopad, opatření/kontroly a reziduální riziko.
- Po přidání assetu se graf automaticky znovu načte a nový uzel se vybere.

## v1 - Základní prototyp

- První funkční prototyp webové aplikace.
- Dockerfile a `docker-compose.yml`.
- PHP 8.3 + Apache.
- SQLite databáze.
- Cytoscape.js z CDN.
- Základní graf uzlů a vazeb.
- Drag & drop uzlů a ukládání pozic.
- Doubleclick na uzel otevře detail.
- Editace DORA atributů.
- Vytváření/editace/smazání vazeb.
- Typ vazby `contains` pro hierarchii.
- Views.
- Dynamické režimy: hardware, data, process, supplier, critical, personal data, impact.
- Export JSON.
- `change_log` pro auditní stopu a budoucí undo/redo.
- Demo data: SAP ECC, ALICE, server, pojistné smlouvy, proces, dodavatel.

## v0 - Návrhová fáze

- Použít PHP místo Perlu.
- Použít SQLite jako dokumentový backend.
- Použít Cytoscape.js pro graf.
- Modelovat aplikaci jako graf uzlů a hran.
- Nedělat `asset_components`; hierarchii řešit vazbou `contains`.
- Dodavatele a procesy modelovat jako samostatné uzly.
- Připravit DORA atributy včetně CIA, RTO, RPO, MTD, revizí a provozních metadat.
- Navrhnout reportování a později risk register.
