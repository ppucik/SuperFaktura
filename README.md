# ARES PHP Client

Jednoduchá PHP knižnica na načítanie údajov firiem z českého registra spoločností (ARES).

## Požiadavky

- PHP 8.1+
- Composer

## Inštalácia

```bash
composer install
```

## Použitie

```php
use SuperFaktura\AresService;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\InvalidIcoException;

$service = AresService::create();

try {
    $company = $service->getByIco('45274649');
    echo $company->name;      // ČEZ, a. s.
    echo $company->city;      // Praha
    echo $company->isActive;  // true
} catch (InvalidIcoException $e) {
    // Neplatný formát alebo kontrolný súčet IČO
} catch (AresException $e) {
    // Sieťová chyba alebo firma nenájdená
}
```

### Hromadné vyhľadávanie

```php
$results = $service->getByIcoMultiple(['45274649', '00177041', '99999999']);

foreach ($results as $ico => $result) {
    if ($result instanceof \Exception) {
        echo "{$ico}: Chyba - " . $result->getMessage();
    } else {
        echo "{$ico}: " . $result->name;
    }
}
```

### DTO – CompanyData

| Property    | Type      | Popis                      |
| ----------- | --------- | -------------------------- |
| `ico`       | `string`  | IČO (8 číslic)             |
| `name`      | `string`  | Obchodné meno              |
| `dic`       | `?string` | DIČ                        |
| `legalForm` | `?string` | Kód právnej formy          |
| `street`    | `?string` | Ulica a číslo              |
| `city`      | `?string` | Obec                       |
| `zip`       | `?string` | PSČ                        |
| `country`   | `?string` | Štát                       |
| `isActive`  | `bool`    | True ak je subjekt AKTÍVNY |

## Testy

```bash
composer install
./vendor/bin/phpunit
```

## Štruktúra projektu

```
src/
  AresService.php        # Hlavný vstupný bod (fasáda)
  AresClient.php         # HTTP klient pre ARES API
  IcoValidator.php       # Validácia IČO (formát + kontrolný súčet)
  DTO/
    CompanyData.php      # Immutable DTO s dátami firmy
  Exception/
    AresException.php    # Hierarchia výnimiek
tests/
  IcoValidatorTest.php   # Unit testy validátora
  AresServiceTest.php    # Unit testy service (mockovaný HTTP klient)
```

## SOLID princípy

| Princíp                       | Ako je dodržaný                                                               |
| ----------------------------- | ----------------------------------------------------------------------------- |
| **S** – Single Responsibility | Každá trieda má jednu zodpovednosť (validácia, HTTP, mapovanie, orchester)    |
| **O** – Open/Closed           | `CompanyData::fromAresResponse` možno rozšíriť bez úpravy klienta             |
| **L** – Liskov                | Všetky výnimky dedia od `AresException` → jednotné `catch`                    |
| **I** – Interface Segregation | Malé, sústredené triedy; žiadne tučné rozhrania                               |
| **D** – Dependency Inversion  | `AresService` prijíma `IcoValidator` a `AresClient` cez constructor injection |
