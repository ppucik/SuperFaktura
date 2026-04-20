# ARES PHP Client

PHP knižnica na načítanie údajov firiem z českého registra spoločností (ARES).
Navrhnutá podľa SOLID princípov s dôrazom na kvalitu kódu, testovateľnosť a robustnosť.

## Požiadavky

- PHP 8.2+
- Composer
- `psr/log: ^3.0`

## Inštalácia

```bash
composer install
```

---

## Použitie

### Základné použitie (zero config)

```php
use SuperFaktura\AresService;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\InvalidIcoException;

$service = AresService::create();

try {
    $company = $service->getByIco('45274649');
    echo $company->name;      // ČEZ, a. s.
    echo $company->city;      // Praha
    echo $company->isActive;  // true (funguje aj s diakritikou AKTIVNÍ aj bez AKTIVNI)
} catch (InvalidIcoException $e) {
    // Neplatný formát alebo kontrolný súčet IČO
} catch (AresException $e) {
    // Sieťová chyba, firma nenájdená alebo iná chyba API
}
```

### S cache a PSR-3 loggerom

```php
use SuperFaktura\AresService;
use SuperFaktura\Cache\InMemoryCache;

// napr. Monolog alebo akýkoľvek PSR-3 kompatibilný logger
$service = AresService::create(
    timeoutSeconds: 10,
    cache:          new InMemoryCache(),
    logger:         $myPsrLogger,
);

$company = $service->getByIco('45274649');
```

### Hromadné vyhľadávanie

```php
$results = $service->getByIcoMultiple(['45274649', '00177041', '99999999']);

foreach ($results as $ico => $result) {
    if ($result instanceof \Exception) {
        echo "{$ico}: Chyba — " . $result->getMessage();
    } else {
        echo "{$ico}: " . $result->name;
    }
}
```

---

## DTO – CompanyData

Immutable `readonly` objekt — vlastnosti nie je možné po vytvorení zmeniť.

| Property    | Type      | Popis                                          |
| ----------- | --------- | ---------------------------------------------- |
| `ico`       | `string`  | IČO (8 číslic, vždy normalizované)             |
| `name`      | `string`  | Obchodné meno                                  |
| `dic`       | `?string` | DIČ                                            |
| `legalForm` | `?string` | Kód právnej formy                              |
| `street`    | `?string` | Ulica a číslo                                  |
| `city`      | `?string` | Obec                                           |
| `zip`       | `?string` | PSČ                                            |
| `country`   | `?string` | Štát                                           |
| `isActive`  | `bool`    | `true` ak ARES vráti `AKTIVNI` alebo `AKTIVNÍ` |

```php
// Export do poľa (napr. pre JSON)
$array = $company->toArray();
echo json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

---

## Cache

Knižnica obsahuje dve implementácie `CacheInterface`:

| Trieda          | Popis                                                              |
| --------------- | ------------------------------------------------------------------ |
| `NullCache`     | Predvolená — cache vypnutý, žiadne ukladanie (Null Object Pattern) |
| `InMemoryCache` | TTL cache v pamäti procesu, ideálna pre batch lookups a CLI        |

Pre produkčnú perzistenciu (Redis, Memcached) stačí implementovať `CacheInterface`:

```php
use SuperFaktura\Contract\CacheInterface;

class RedisCache implements CacheInterface
{
    public function get(string $key): ?array { /* ... */ }
    public function set(string $key, array $data, int $ttl = 3600): void { /* ... */ }
    public function delete(string $key): void { /* ... */ }
    public function clear(): void { /* ... */ }
}
```

---

## Retry mechanizmus

`RetryableAresClient` automaticky opakuje volanie pri `AresConnectionException`
s exponenciálnym backoffom:

| Pokus | Čakanie        |
| ----- | -------------- |
| 1.    | okamžite       |
| 2.    | 200 ms         |
| 3.    | 400 ms         |
| max   | 5 000 ms (cap) |

- `AresNotFoundException` (HTTP 404) → **žiadny retry** (trvalá chyba)
- `AresException` (HTTP 5xx, JSON chyba) → **žiadny retry**
- `AresConnectionException` (timeout, sieť) → **retry až 3×**

---

## Hierarchia výnimiek

```
\RuntimeException
  └── AresException                 ← zachytí všetky ARES chyby naraz
        ├── InvalidIcoException     ← neplatný formát alebo kontrolný súčet IČO
        ├── AresNotFoundException   ← firma nenájdená (HTTP 404)
        └── AresConnectionException ← sieťový výpadok / timeout
```

---

## Testy a statická analýza

```bash
# Príklady
php example.php

# Unit testy
vendor\bin\phpunit --testdox

# Statická analýza (PHPStan level 8 — 0 errors)
vendor\bin\phpstan analyse src tests --level=8
```

Pokrytie testami: **35 testov**, vrátane:

- validácia IČO (formát, checksum, diakritika, padding)
- cache hit / miss / TTL expirácia
- retry logika (úspech po retry, vyčerpanie pokusov, exception chaining)
- logovanie (info, warning, error pri každej udalosti)
- batch lookup s čiastočnými chybami
- mapovanie DTO (adresa, isActive s/bez diakritiky)

---

## Štruktúra projektu

```
src/
  AresService.php               # Hlavný vstupný bod — fasáda, factory
  AresClient.php                # HTTP klient pre ARES REST API
  RetryableAresClient.php       # Decorator: cache + retry + PSR-3 logging
  IcoValidator.php              # Validácia IČO (formát + MOD-11 checksum)
  Cache/
    NullCache.php               # Null Object — cache vypnutý (predvolené)
    InMemoryCache.php           # TTL cache v pamäti procesu
  Contract/
    AresClientInterface.php     # Kontrakt pre HTTP klienta
    CacheInterface.php          # Kontrakt pre cache backend
  DTO/
    CompanyData.php             # Immutable readonly DTO
  Exception/
    AresException.php           # Základná výnimka
    InvalidIcoException.php     # Neplatné IČO
    AresNotFoundException.php   # Firma nenájdená
    AresConnectionException.php # Sieťová chyba
tests/
  AresServiceTest.php           # Testy fasády (16 testov)
  IcoValidatorTest.php          # Testy validátora (8 testov)
  RetryableAresClientTest.php   # Testy retry + cache + logging (14 testov)
  Cache/
    InMemoryCacheTest.php       # Testy cache (7 testov)
```

---

## SOLID princípy

| Princíp                       | Ako je dodržaný                                                                                                                                                                    |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **S** – Single Responsibility | Každá trieda má jednu zodpovednosť: validácia (`IcoValidator`), HTTP (`AresClient`), retry/cache/log (`RetryableAresClient`), mapovanie (`CompanyData`), orchester (`AresService`) |
| **O** – Open/Closed           | Nový cache backend = nová trieda implementujúca `CacheInterface`, bez zmeny existujúceho kódu                                                                                      |
| **L** – Liskov                | `NullCache` a `InMemoryCache` sú plne zameniteľné cez `CacheInterface`; všetky výnimky dedia od `AresException`                                                                    |
| **I** – Interface Segregation | Dve malé rozhrania (`AresClientInterface`, `CacheInterface`) namiesto jedného veľkého                                                                                              |
| **D** – Dependency Inversion  | `AresService` závisí od `AresClientInterface` a `CacheInterface`, nie od konkrétnych tried                                                                                         |
