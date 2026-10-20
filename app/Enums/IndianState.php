<?php

namespace App\Enums;

/**
 * States and union territories keyed by their GST state code.
 */
enum IndianState: string
{
    case JammuAndKashmir = '01';
    case HimachalPradesh = '02';
    case Punjab = '03';
    case Chandigarh = '04';
    case Uttarakhand = '05';
    case Haryana = '06';
    case Delhi = '07';
    case Rajasthan = '08';
    case UttarPradesh = '09';
    case Bihar = '10';
    case Sikkim = '11';
    case ArunachalPradesh = '12';
    case Nagaland = '13';
    case Manipur = '14';
    case Mizoram = '15';
    case Tripura = '16';
    case Meghalaya = '17';
    case Assam = '18';
    case WestBengal = '19';
    case Jharkhand = '20';
    case Odisha = '21';
    case Chhattisgarh = '22';
    case MadhyaPradesh = '23';
    case Gujarat = '24';
    case DadraNagarHaveliAndDamanDiu = '26';
    case Maharashtra = '27';
    case Karnataka = '29';
    case Goa = '30';
    case Lakshadweep = '31';
    case Kerala = '32';
    case TamilNadu = '33';
    case Puducherry = '34';
    case AndamanAndNicobarIslands = '35';
    case Telangana = '36';
    case AndhraPradesh = '37';
    case Ladakh = '38';
    case OtherTerritory = '97';

    public function label(): string
    {
        return match ($this) {
            self::JammuAndKashmir => 'Jammu and Kashmir',
            self::HimachalPradesh => 'Himachal Pradesh',
            self::UttarPradesh => 'Uttar Pradesh',
            self::ArunachalPradesh => 'Arunachal Pradesh',
            self::WestBengal => 'West Bengal',
            self::MadhyaPradesh => 'Madhya Pradesh',
            self::DadraNagarHaveliAndDamanDiu => 'Dadra and Nagar Haveli and Daman and Diu',
            self::TamilNadu => 'Tamil Nadu',
            self::AndamanAndNicobarIslands => 'Andaman and Nicobar Islands',
            self::AndhraPradesh => 'Andhra Pradesh',
            self::OtherTerritory => 'Other Territory',
            default => $this->name,
        };
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $state): array => ['code' => $state->value, 'name' => $state->label()],
            self::cases(),
        );
    }
}
