<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use SuperFaktura\AresService;
use SuperFaktura\Exception\AresException;
use SuperFaktura\Exception\InvalidIcoException;

// ── Single lookup ──────────────────────────────────────────────────────────────

$service = AresService::create();

try {
    $company = $service->getByIco('45274649'); // ČEZ, a. s.

    echo "=== Company Info ===\n";
    echo "IČO:        {$company->ico}\n";
    echo "Name:       {$company->name}\n";
    echo "DIČ:        " . ($company->dic ?? '—') . "\n";
    echo "Street:     " . ($company->street ?? '—') . "\n";
    echo "City:       " . ($company->city ?? '—') . "\n";
    echo "ZIP:        " . ($company->zip ?? '—') . "\n";
    echo "Country:    " . ($company->country ?? '—') . "\n";
    echo "Active:     " . ($company->isActive ? 'Yes' : 'No') . "\n";

    echo "\n--- As JSON ---\n";
    echo json_encode($company->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (InvalidIcoException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
} catch (AresException $e) {
    echo "ARES error: " . $e->getMessage() . "\n";
}

// ── Batch lookup ───────────────────────────────────────────────────────────────

echo "\n=== Batch Lookup ===\n";
$results = $service->getByIcoMultiple([
    '45274649', // ČEZ
    '00177041', // Škoda Auto
    '99999999', // Non-existent
]);

foreach ($results as $ico => $result) {
    if ($result instanceof \Exception) {
        echo "{$ico}: ERROR – " . $result->getMessage() . "\n";
    } else {
        echo "{$ico}: {$result->name}\n";
    }
}
