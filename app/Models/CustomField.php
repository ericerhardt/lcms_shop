<?php

namespace App\Models;

use Database\Factories\CustomFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string $type
 * @property array<int, string>|null $options
 * @property int $position
 */
#[Fillable(['name', 'key', 'type', 'options', 'position'])]
class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use HasFactory;

    public const TYPES = [
        'text' => 'Text',
        'number' => 'Number',
        'select' => 'Dropdown',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('value');
    }
}
