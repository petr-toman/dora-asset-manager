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

SQLite modely/databáze jsou uložené v persistentním adresáři:

```text
./data/models/*.sqlite
./data/current_model.txt
./data/deleted/*.sqlite
```

Při prvním spuštění prázdné datové složky se vytvoří ukázkový model `demo.sqlite` se stejnými demo daty jako starší prototyp. Pokud aplikace najde starší `./data/assets.sqlite` a adresář modelů je prázdný, zkopíruje jej do `./data/models/assets.sqlite` jako běžný model a zároveň vytvoří `demo.sqlite`.

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
- více modelů/projektů jako samostatné SQLite dokumenty
- nový prázdný model, kopie aktuálního modelu, přepnutí modelu, bezpečné smazání do koše
- stažení/nahrání SQLite DB souboru pro výměnu modelů mezi instancemi aplikace
- excel-like tabulka assetů s editací buněk, filtrováním, sortováním a copy/paste přes TSV
- import assetů z CSV s preview a validací před zápisem do DB
- export assetů do CSV jako šablona nebo přenosový formát
- excel-like tabulka vazeb s editací buněk, filtrováním, sortováním a copy/paste přes TSV
- tisknutelný report: `/report.php`
- heatmapa rizik v reportu
- change log pro auditní stopu a budoucí undo/redo


## CSV import assetů

V pohledu **Assety tabulka** jsou tlačítka **Import CSV** a **Export CSV**.

Doporučený postup:

1. Připrav Excel ve struktuře assetové tabulky aplikace.
2. Ulož list jako **CSV UTF-8**.
3. V aplikaci otevři **Assety tabulka** a klikni na **Import CSV**.
4. Zkontroluj preview a případné červené validační chyby.
5. Pokud je CSV validní, potvrď import.

Výchozí import ignoruje sloupec `ID` a vkládá assety jako nové záznamy. Volitelně lze zapnout režim **Aktualizovat existující assety podle ID**. V takovém případě musí ID v CSV existovat v aktuálním modelu.

Podporované oddělovače: středník, čárka a tabulátor. CSV může obsahovat UTF-8 BOM. Import vazeb z CSV zatím není implementován; vazby lze hromadně spravovat přes tabulku vazeb nebo copy/paste z Excelu.

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



## Změny v6

- Tlačítka `Uložit view`, `Nový view z aktuálního` a `Smazat view` jsou přesunuta z hlavní lišty do panelu `Pohled`.
- Hlavní lišta obsahuje jen globální navigaci a akce aplikace.


## Verze v10

- Upravený layout detailní karty assetu.
- Přidáno pole Vendor / manufacturer.
- Přidány tooltipy k CIA, RTO, RPO, MTD a dalším metodickým polím.

## v12 poznámka

Grafové view obsahuje volbu **Snap to grid** v panelu Pohled. Při zapnutí se uzly po puštění myši zarovnají na nastavenou mřížku. Tlačítko **Zarovnat aktuální view** zarovná všechny viditelné uzly aktuálního pohledu a pozice uloží.


## Reporty

HTML/PDF report otevřeš tlačítkem `Report / PDF`. V reportu je tlačítko `Export DOCX / Word`, případně lze přímo použít `/report_docx.php`. DOCX export vyžaduje PHP rozšíření `zip`, které Dockerfile v této verzi instaluje.


## v14 - Modely / projekty jako SQLite dokumenty

Aplikace nyní podporuje více samostatných modelů/projektů. Každý model je jeden SQLite soubor v `./data/models`. Aktuální model je uložen v `./data/current_model.txt`. Pokud tento soubor chybí nebo obsahuje neplatný/neexistující model, aplikace otevře nejmladší model, který se nejmenuje `demo.sqlite`; pokud žádný takový neexistuje, otevře `demo.sqlite`.

V levém panelu je sekce **Model / projekt**:

- `Nový prázdný` vytvoří nový prázdný SQLite model bez demo dat.
- `Kopie aktuálního` vytvoří kopii aktuálního modelu a přepne se na ni. Defaultní název je původní název + `kopie` + časové razítko.
- `Smazat model` přesune model do `./data/deleted`, fyzicky jej okamžitě nemaže. Nelze smazat poslední existující model; `demo.sqlite` lze smazat, pokud existuje jiný model.
- `Stáhnout DB` stáhne aktuální SQLite soubor.
- `Nahrát DB` nahraje SQLite soubor do `./data/models`, zkontroluje duplicitu názvu a přepne se na importovaný model.

Tento režim je záměrně podobný práci s dokumentem ve Wordu/Excelu: aplikace je editor, SQLite soubor je dokument.


## v15 - Modely jako dokumenty + sklápěcí panel

- Při prvním spuštění prázdné složky `./data/models` se vytvoří `demo.sqlite` s ukázkovými daty.
- Nový model vytvořený z UI je prázdný, bez template/demo záznamů.
- Pokud `current_model.txt` chybí nebo odkazuje na neexistující soubor, aplikace vybere nejmladší model kromě `demo.sqlite`; pokud jiný model není k dispozici, otevře `demo.sqlite`.
- Přepnutí modelu provede reload celé stránky, aby se bezpečně překreslil graf, tabulky, views i reportové zdroje.
- Levý panel lze zasunout tlačítkem `‹/›`; stav se ukládá do `localStorage`.

## v16 - Projektová dokumentace

Verze v16 doplňuje soubory:

- `PROJECT_STATE.md` — aktuální stav architektury a funkcí,
- `CHANGELOG.md` — zpětný changelog iterací,
- `AI-PROMPT.md` — prompt/specifikace pro znovuvytvoření aplikace od začátku.

Funkční kód aplikace vychází z verze v15.

## Verze v17 - technická a výkonová vylepšení

Verze v17 zapracovává code review zaměřené na správnost a výkon:

- dynamické grafové pohledy filtrují vazby podle explicitně povolených typů hran,
- tabulka vazeb validuje uzly proti celé DB, ne proti aktuálně viditelnému grafu,
- hromadné ukládání pozic používá endpoint `save_positions`,
- pozice se zapisují a logují jen při skutečné změně,
- hromadné změny pozic se logují agregovaně jako `move_nodes_batch`,
- `save_position` / `save_positions` validují existenci view a uzlů,
- nevyplněná pravděpodobnost nebo dopad rizika se označí jako `Nehodnoceno`, nikoli jako nízké riziko,
- HTML i DOCX reporty zobrazují nehodnocená aktiva zvlášť mimo heatmapu,
- kopie/download aktuálního SQLite modelu kontroluje výsledek WAL checkpointu,
- change log se automaticky čistí podle retence.

Konfigurace retence change logu přes environment variables:

```text
DORA_CHANGE_LOG_RETENTION_DAYS=90
DORA_CHANGE_LOG_MAX_RECORDS=5000
```
