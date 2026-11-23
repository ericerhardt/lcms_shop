<?php

namespace App\Models;

use Database\Factories\InquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A volume pricing request ("Need more than 10 cases?").
 *
 * @property int $id
 * @property string $company
 * @property string $contact_name
 * @property string $email
 * @property string $label_type
 * @property string $monthly_volume
 * @property string $delivery_zip
 * @property string|null $packaging_needs
 * @property Carbon|null $notified_at
 */
#[Fillable(['company', 'contact_name', 'email', 'label_type', 'monthly_volume', 'delivery_zip', 'packaging_needs', 'notified_at'])]
class Inquiry extends Model
{
    /** @use HasFactory<InquiryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function reference(): string
    {
        return 'INQ-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Plain-text body of the notification sent to the sales team.
     */
    public function emailBody(): string
    {
        return implode("\n", [
            "New volume pricing request {$this->reference()}",
            '',
            "Company: {$this->company}",
            "Contact: {$this->contact_name}",
            "Email: {$this->email}",
            '',
            "Label type: {$this->label_type}",
            "Monthly volume: {$this->monthly_volume}",
            "Delivery ZIP: {$this->delivery_zip}",
            'Other packaging needs: '.($this->packaging_needs ?: '-'),
        ]);
    }
}
