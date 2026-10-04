<?php

namespace App\Services;

/**
 * Default research-based protocol library.
 * Keyed by "species::vaccine". `interval` is [min, recommended, max] in days.
 * `series_completion_age_weeks` = continue primary series until at least this age.
 */
class VaccinationProtocols
{
    private static array $PROTOCOLS = [
        'dog::DHPP / DAPP' => [
            'species' => 'dog', 'vaccine' => 'DHPP / DAPP', 'category' => 'core',
            'minimum_age_weeks' => 6, 'interval' => [21, 28, 28],
            'series_completion_age_weeks' => 16, 'booster_interval_days' => 365,
            'booster_type' => 'annual', 'route' => 'injectable',
            'requires_vet_confirmation' => false,
            'source' => 'WSAVA', 'source_version' => '2024',
            'source_url' => 'https://wsava.org/global-guidelines/vaccination/',
        ],
        'dog::Rabies' => [
            'species' => 'dog', 'vaccine' => 'Rabies', 'category' => 'core',
            'minimum_age_weeks' => 12, 'interval' => [365, 365, 365],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'product/regulation dependent', 'route' => 'injectable',
            'requires_vet_confirmation' => true,
            'source' => 'Local regulation / product label', 'source_version' => 'n/a',
            'source_url' => 'https://www.rabiesalliance.org/',
        ],
        'dog::Leptospirosis' => [
            'species' => 'dog', 'vaccine' => 'Leptospirosis', 'category' => 'core',
            'minimum_age_weeks' => 8, 'interval' => [14, 21, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'annual', 'route' => 'injectable',
            'requires_vet_confirmation' => false,
            'source' => 'AAHA', 'source_version' => '2022',
            'source_url' => 'https://www.aaha.org/professional-resources/2022-aaha-canine-vaccination-guidelines/',
        ],
        'dog::Bordetella' => [
            'species' => 'dog', 'vaccine' => 'Bordetella', 'category' => 'non-core',
            'minimum_age_weeks' => 8, 'interval' => [14, 21, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'risk/route dependent', 'route' => 'injectable/intranasal/oral',
            'requires_vet_confirmation' => true,
            'source' => 'AAHA', 'source_version' => '2022',
            'source_url' => 'https://www.aaha.org/professional-resources/2022-aaha-canine-vaccination-guidelines/',
        ],
        'dog::Canine Influenza' => [
            'species' => 'dog', 'vaccine' => 'Canine Influenza', 'category' => 'non-core',
            'minimum_age_weeks' => 8, 'interval' => [14, 21, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'risk dependent', 'route' => 'injectable',
            'requires_vet_confirmation' => false,
            'source' => 'AAHA', 'source_version' => '2022',
            'source_url' => 'https://www.aaha.org/professional-resources/2022-aaha-canine-vaccination-guidelines/',
        ],
        'dog::Lyme' => [
            'species' => 'dog', 'vaccine' => 'Lyme', 'category' => 'non-core',
            'minimum_age_weeks' => 8, 'interval' => [14, 21, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'annual when indicated', 'route' => 'injectable',
            'requires_vet_confirmation' => false,
            'source' => 'AAHA', 'source_version' => '2022',
            'source_url' => 'https://www.aaha.org/professional-resources/2022-aaha-canine-vaccination-guidelines/',
        ],
        'cat::FVRCP' => [
            'species' => 'cat', 'vaccine' => 'FVRCP', 'category' => 'core',
            'minimum_age_weeks' => 6, 'interval' => [21, 28, 28],
            'series_completion_age_weeks' => 16, 'booster_interval_days' => 365,
            'booster_type' => 'annual', 'route' => 'injectable',
            'requires_vet_confirmation' => false,
            'source' => 'AAHA/AAFP', 'source_version' => '2020',
            'source_url' => 'https://www.aafp.org/professional-resources/practice-guidelines/feline-vaccination-guidelines/',
        ],
        'cat::Rabies' => [
            'species' => 'cat', 'vaccine' => 'Rabies', 'category' => 'core',
            'minimum_age_weeks' => 12, 'interval' => [365, 365, 365],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'product/regulation dependent', 'route' => 'injectable',
            'requires_vet_confirmation' => true,
            'source' => 'Local regulation / product label', 'source_version' => 'n/a',
            'source_url' => 'https://www.rabiesalliance.org/',
        ],
        'cat::FeLV (Feline Leukemia Virus)' => [
            'species' => 'cat', 'vaccine' => 'FeLV (Feline Leukemia Virus)', 'category' => 'risk-based',
            'minimum_age_weeks' => 8, 'interval' => [21, 28, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => '12 months then risk-based', 'route' => 'injectable',
            'requires_vet_confirmation' => true,
            'source' => 'AAHA/AAFP', 'source_version' => '2020',
            'source_url' => 'https://www.aafp.org/professional-resources/practice-guidelines/feline-vaccination-guidelines/',
        ],
        'cat::Chlamydia felis' => [
            'species' => 'cat', 'vaccine' => 'Chlamydia felis', 'category' => 'non-core',
            'minimum_age_weeks' => 8, 'interval' => [21, 28, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'risk dependent', 'route' => 'injectable',
            'requires_vet_confirmation' => true,
            'source' => 'AAHA/AAFP', 'source_version' => '2020',
            'source_url' => 'https://www.aafp.org/professional-resources/practice-guidelines/feline-vaccination-guidelines/',
        ],
        'cat::Bordetella' => [
            'species' => 'cat', 'vaccine' => 'Bordetella', 'category' => 'non-core',
            'minimum_age_weeks' => 8, 'interval' => [14, 21, 28],
            'series_completion_age_weeks' => 12, 'booster_interval_days' => 365,
            'booster_type' => 'product dependent', 'route' => 'intranasal',
            'requires_vet_confirmation' => true,
            'source' => 'AAHA/AAFP', 'source_version' => '2020',
            'source_url' => 'https://www.aafp.org/professional-resources/practice-guidelines/feline-vaccination-guidelines/',
        ],
    ];

    public static function protocolFor(string $species, string $vaccine): ?array
    {
        return self::$PROTOCOLS["{$species}::{$vaccine}"] ?? null;
    }

    /** Protocols relevant to a species (for UI reference + selects). */
    public static function protocolsForSpecies(string $species): array
    {
        $out = [];
        foreach (self::$PROTOCOLS as $p) {
            if ($p['species'] === $species) {
                $out[] = $p;
            }
        }
        return $out;
    }
}
