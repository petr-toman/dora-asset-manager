# Evidence IT aktiv / DORA asset map

Jednoduchá webová aplikace v PHP + SQLite pro grafovou evidenci ICT/informačních aktiv, vazeb a pohledů.

## Spuštění

```bash
docker compose up --build
```

Pak otevři:

```text
http://localhost:8080
```

SQLite databáze bude uložena v:

```text
./data/assets.sqlite
```

## Funkce první verze

- uzly: hardware, software, data, procesy, business funkce, dodavatelé, poskytovatelé, výrobci, síť, lokalita, dokumentace, ICT služba
- hrany/vazby: contains, hosts, processes_data, supports_process, depends_on atd.
- graf v Cytoscape.js
- drag & drop uzlů
- uložení pozic
- doubleclick na uzel otevře detail
- editace atributů uzlu
- vytváření vazeb mezi vybranými uzly
- uložené views
- dynamické views: hardware, data, process, supplier, impact
- export JSON
- change_log jako auditní stopa a základ pro undo/redo

## Poznámka

Login a multiuser režim záměrně nejsou implementovány. Aplikace je navržena jako single-user dokumentový nástroj se SQLite databází.
