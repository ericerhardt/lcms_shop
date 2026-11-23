<?php

namespace App\Enums;

enum PackagingType: string
{
    case Each = 'each';
    case Box = 'box';
    case Case = 'case';
    case Carton = 'carton';
    case Pack = 'pack';
    case Roll = 'roll';
    case Pallet = 'pallet';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
