# Billetterie API

Billetterie je REST API za upravljanje događajima i prodaju ulaznica. Aplikacija omogućava javni pregled događaja i dostupnih tipova karata, administratorsko upravljanje ponudom, kreiranje porudžbina sa redom čekanja, kontrolisane promene statusa porudžbine, CSV izvoz događaja i preuzimanje podataka o događajima iz javnog Wikidata servisa.

## Tehnologije

- **PHP 8.2+** – programski jezik na kojem je aplikacija zasnovana.
- **Laravel 12** – serverski framework koji obezbeđuje rutiranje, validaciju, kontrolere, middleware i rad sa bazom.
- **MySQL** – relaciona baza podataka za korisnike, događaje, tipove karata, porudžbine i API tokene.
- **Eloquent ORM** – mapiranje modela i njihovih relacija na tabele baze podataka.
- **Laravel Sanctum 4** – autentifikacija pomoću Bearer API tokena.
- **L5-Swagger / OpenAPI** – generisanje interaktivne dokumentacije svih API ruta.
- **Laravel HTTP Client** – komunikacija sa javnim Wikidata Query servisom, bez API ključa.
- **Migrations, Factories i Seeders** – verzionisanje strukture baze i generisanje početnih i testnih podataka.
- **Pest** – automatizovano testiranje Laravel aplikacije.
- **Vite, Tailwind CSS i Axios** – razvojne frontend zavisnosti koje dolaze sa Laravel projektom; nisu neophodne za korišćenje samog JSON API-ja.

## Glavne funkcionalnosti

### Autentifikacija i uloge

Korisnik može da se registruje i prijavi, nakon čega dobija Sanctum Bearer token. Postoje dve uloge: `admin` i `user`. Novoregistrovani nalog uvek dobija ulogu `user`, dok administratorski nalozi nastaju kroz kontrolisano unošenje podataka, odnosno kroz seeder u razvojnom okruženju.

### Događaji

Javne rute omogućavaju pregled svih događaja i jednog izabranog događaja. Lista podržava tekstualnu pretragu, filtriranje prema lokaciji i datumu početka, sortiranje u oba smera i paginaciju. Administrator može da kreira, menja i briše događaje. Događaj koji je počeo, kao ni događaj za koji postoje porudžbine, nije moguće menjati ili obrisati.

### Tipovi karata

Svaki događaj može imati više tipova karata sa zasebnim nazivom, cenom, ukupnom i dostupnom količinom i maksimalnim brojem karata po porudžbini. Tipovi karata jednog događaja dostupni su preko nested rute. Administrator može da ih kreira, menja i briše samo pre početka događaja, a izmena i brisanje nisu dozvoljeni ako za dati tip već postoje porudžbine.

### Porudžbine i red čekanja

Samo prijavljeni korisnik sa ulogom `user` može da kreira porudžbinu. Nova porudžbina dobija status `queued` i jedinstveni `queue_number`, koji određuje njen položaj u redu čekanja. Promene statusa su ograničene unapred definisanim prelazima i zavise od uloge korisnika. Administrator vidi sve porudžbine, dok običan korisnik vidi samo svoje. Brisanje porudžbine nije podržano.

Prilikom prelaska porudžbine u status `pending` rezervisana količina se oduzima od raspoloživih karata. Ako se takva porudžbina otkaže ili obrada ne uspe, količina se vraća. Prelazak u `paid` beleži vreme kupovine. Kreiranje i napredovanje porudžbine nisu dozvoljeni nakon početka događaja.

### Dodatne mogućnosti

- izvoz događaja i zbirne dostupnosti karata u CSV format;
- administratorski pregled porudžbina određenog korisnika;
- administratorski pregled svih porudžbina za određeni događaj;
- preuzimanje budućih događaja sa javnog Wikidata Query servisa bez API ključa;
- JSON Resources za dosledan format API odgovora;
- Swagger UI za pregled i ručno testiranje ruta.

## Preduslovi

Za lokalno pokretanje potrebni su:

- Git;
- PHP 8.2 ili noviji, sa uključenim `PDO MySQL` proširenjem;
- Composer;
- MySQL server;
- Node.js i npm samo ako se koriste Vite razvojni resursi.

Verzije instaliranih alata mogu se proveriti komandama:

```bash
php -v
composer --version
mysql --version
node --version
npm --version
```

## Preuzimanje projekta

Za prvo preuzimanje repozitorijuma izvršiti:

```bash
git clone billetterie
cd billetterie
```

Ako projekat već postoji na lokalnoj mašini, najnovije izmene se preuzimaju komandom:

```bash
git pull origin <naziv-grane>
```

Pre povlačenja izmena preporučuje se da lokalne izmene budu sačuvane kroz commit ili stash.

## Instalacija i konfiguracija

### 1. Instaliranje PHP zavisnosti

```bash
composer install
```

### 2. Kreiranje lokalnog konfiguracionog fajla

Linux i macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Zatim generisati aplikacioni ključ:

```bash
php artisan key:generate
```

### 3. Podešavanje MySQL baze

Kreirati praznu bazu podataka:

```sql
CREATE DATABASE billetterie
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

U `.env` fajlu podesiti parametre u skladu sa lokalnim MySQL serverom:

```dotenv
APP_NAME=Billetterie
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billetterie
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Kreiranje tabela i početnih podataka

```bash
php artisan migrate --seed
```

Seeder kreira realistične događaje, tipove karata i porudžbine, kao i dodatne nasumične podatke generisane pomoću factory klasa.

### 5. Generisanje Swagger dokumentacije

```bash
php artisan l5-swagger:generate
```

### 6. Opciona instalacija frontend zavisnosti

Ovaj korak nije potreban za rad JSON API-ja, ali jeste ako se koriste Vite resursi:

```bash
npm install
npm run dev
```

## Pokretanje aplikacije

API server se pokreće komandom:

```bash
php artisan serve
```

Podrazumevane adrese su:

- osnovna adresa aplikacije: `http://127.0.0.1:8000`;
- API rute: `http://127.0.0.1:8000/api`;
- Swagger UI: `http://127.0.0.1:8000/api/documentation`.

Za istovremeno pokretanje Laravel servera, queue listenera i Vite razvojnog servera može se koristiti:

```bash
composer run dev
```

## Testni nalozi

Nakon izvršavanja seedera dostupni su sledeći nalozi:

| Uloga         | Email                        | Lozinka    |
| ------------- | ---------------------------- | ---------- |
| Administrator | `admin@billetterie.test`     | `password` |
| Korisnik      | `marko.petrovic@example.com` | `password` |
| Korisnik      | `jovana.ilic@example.com`    | `password` |

Seeder kreira i dodatne korisničke naloge. Svi eksplicitno definisani testni nalozi koriste lozinku `password` i namenjeni su isključivo lokalnom razvojnom okruženju.

## Korišćenje autentifikovanih ruta

Prijava se vrši slanjem `POST` zahteva na `/api/login`:

```json
{
    "email": "admin@billetterie.test",
    "password": "password"
}
```

Dobijeni `access_token` šalje se na zaštićene rute kroz zaglavlja:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

Token se može uneti i kroz dugme **Authorize** u Swagger UI interfejsu.

## Pregled API ruta

| Oblast           | Javne mogućnosti                                | Zaštićene mogućnosti                                                                                    |
| ---------------- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Autentifikacija  | registracija i prijava                          | profil prijavljenog korisnika i odjava                                                                  |
| Događaji         | lista, detalji i CSV izvoz                      | kreiranje, izmena i brisanje za administratora                                                          |
| Tipovi karata    | tipovi određenog događaja i detalji jednog tipa | kreiranje, izmena i brisanje za administratora                                                          |
| Porudžbine       | nema javnih ruta                                | kreiranje i sopstveni pregled za korisnika; kompletan pregled i upravljanje statusima za administratora |
| Spoljni događaji | budući događaji sa Wikidata servisa             | nije potrebna autentifikacija niti API ključ                                                            |

Kompletni parametri zahteva, validaciona pravila, mogući odgovori i HTTP statusi opisani su u Swagger dokumentaciji.

## Testiranje

Automatizovani testovi pokreću se komandom:

```bash
php artisan test
```

Pre pokretanja testova proveriti da testno okruženje ne koristi produkcionu bazu podataka.

## Korisne razvojne komande

```bash
# Pregled svih ruta
php artisan route:list

# Brisanje konfiguracionog i aplikacionog keša
php artisan optimize:clear

# Ponovno generisanje Swagger dokumentacije
php artisan l5-swagger:generate
```

Za potpuno ponovno kreiranje lokalne baze sa testnim podacima može se koristiti sledeća komanda. Ona briše sve postojeće podatke u trenutno podešenoj bazi:

```bash
php artisan migrate:fresh --seed
```

## Projektna dokumentacija

- [Opis slučajeva korišćenja i dijagrami sekvenci](docs/use-case-sequence-diagrams.md)
- [Opis arhitekture i dijagram komponenti](docs/application-architecture.md)
- [Model podataka i dijagram klasa](docs/data-model-class-diagram.md)

## Struktura najvažnijih direktorijuma

```text
app/Http/Controllers/   API kontroleri i poslovna pravila
app/Http/Resources/     formatiranje JSON odgovora
app/Models/             Eloquent modeli i relacije
database/factories/     generisanje testnih podataka
database/migrations/    struktura i ograničenja baze
database/seeders/       početni i demonstracioni podaci
docs/                   projektna dokumentacija i dijagrami
routes/api.php          definicije API ruta
tests/                  automatizovani testovi
```

## Napomena o produkcionom okruženju

Testne naloge i razvojne seedere ne treba koristiti u produkciji. U produkcionom `.env` fajlu potrebno je isključiti debug režim, postaviti bezbedne pristupne podatke baze, koristiti HTTPS i čuvati tajne vrednosti van repozitorijuma.
