<?php

namespace App\Services\MyInvois;

final class StateCode
{
    private const MALAYSIA_CODES = [
        '01' => '01', 'JOHOR' => '01',
        '02' => '02', 'KEDAH' => '02',
        '03' => '03', 'KELANTAN' => '03',
        '04' => '04', 'MELAKA' => '04', 'MALACCA' => '04',
        '05' => '05', 'NEGERI SEMBILAN' => '05',
        '06' => '06', 'PAHANG' => '06',
        '07' => '07', 'PULAU PINANG' => '07', 'PENANG' => '07',
        '08' => '08', 'PERAK' => '08',
        '09' => '09', 'PERLIS' => '09',
        '10' => '10', 'SELANGOR' => '10',
        '11' => '11', 'TERENGGANU' => '11',
        '12' => '12', 'SABAH' => '12',
        '13' => '13', 'SARAWAK' => '13',
        '14' => '14', 'KUALA LUMPUR' => '14', 'WILAYAH PERSEKUTUAN KUALA LUMPUR' => '14', 'W.P. KUALA LUMPUR' => '14',
        '15' => '15', 'LABUAN' => '15', 'WILAYAH PERSEKUTUAN LABUAN' => '15', 'W.P. LABUAN' => '15',
        '16' => '16', 'PUTRAJAYA' => '16', 'WILAYAH PERSEKUTUAN PUTRAJAYA' => '16', 'W.P. PUTRAJAYA' => '16',
        '17' => '17', 'NOT APPLICABLE' => '17', 'N/A' => '17', 'NA' => '17',
    ];

    public static function normalize(?string $state, ?string $countryCode): ?string
    {
        $countryCode = CountryCode::alpha3($countryCode);

        if ($countryCode !== 'MYS') {
            return '17';
        }

        $state = self::clean($state);
        if ($state === '') {
            return null;
        }

        return self::MALAYSIA_CODES[$state] ?? null;
    }

    public static function isValid(?string $state, ?string $countryCode): bool
    {
        return self::normalize($state, $countryCode) !== null;
    }

    private static function clean(?string $state): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $state))) ?? '';
    }
}
