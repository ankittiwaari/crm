<?php

namespace App\Actions\BusinessProfile;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Support\Gstin;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UpdateBusinessProfile
{
    /**
     * Uploaded files: input key => [model attribute, storage disk].
     */
    private const array FILES = [
        'logo' => ['logo_path', 'public'],
        'udyam_certificate' => ['udyam_certificate_path', 'local'],
    ];

    /**
     * Update the business profile and record an audit entry for every field that changed.
     *
     * @param  array<string, mixed>  $data  Validated input.
     */
    public function handle(CompanyProfile $profile, array $data, ?User $user = null): CompanyProfile
    {
        $profile->fill(Arr::only($data, $profile->getFillable()));

        // The GSTIN is authoritative for the registered state and PAN.
        if ($gstin = Gstin::tryFrom($profile->gstin)) {
            $profile->state_code = $gstin->state()->value;
            $profile->pan = $gstin->pan();
        }

        $replacedFiles = $this->applyFiles($profile, $data);

        DB::transaction(function () use ($profile, $user): void {
            $changes = $this->describeChanges($profile);

            $profile->save();

            foreach ($changes as $field => [$old, $new]) {
                $profile->changes()->create([
                    'field' => $field,
                    'old_value' => $old,
                    'new_value' => $new,
                    'user_id' => $user?->id,
                ]);
            }
        });

        foreach ($replacedFiles as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $profile;
    }

    /**
     * Store uploads and clear removed files, returning the old files to delete once saved.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{string, string}>
     */
    private function applyFiles(CompanyProfile $profile, array $data): array
    {
        $replaced = [];

        foreach (self::FILES as $input => [$attribute, $disk]) {
            $upload = $data[$input] ?? null;
            $remove = (bool) ($data["remove_{$input}"] ?? false);

            if (! $upload instanceof UploadedFile && ! $remove) {
                continue;
            }

            if ($profile->{$attribute}) {
                $replaced[] = [$disk, $profile->{$attribute}];
            }

            $profile->{$attribute} = $upload instanceof UploadedFile
                ? $this->store($upload, $disk)
                : null;
        }

        return $replaced;
    }

    private function store(UploadedFile $upload, string $disk): string
    {
        $path = $upload->store('company', $disk);

        if ($path === false) {
            throw new RuntimeException("Could not store [{$upload->getClientOriginalName()}] on the [{$disk}] disk.");
        }

        return $path;
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    private function describeChanges(CompanyProfile $profile): array
    {
        $changes = [];

        foreach (array_keys($profile->getDirty()) as $field) {
            if (in_array($field, ['logo_path', 'udyam_certificate_path'], true)) {
                $changes[$field] = [
                    $profile->getOriginal($field) ? 'file' : null,
                    $profile->getAttribute($field) ? 'new file' : null,
                ];

                continue;
            }

            $changes[$field] = [
                $this->stringify($profile->getOriginal($field)),
                $this->stringify($profile->getAttribute($field)),
            ];
        }

        return $changes;
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? 'yes' : 'no',
            default => (string) $value,
        };
    }
}
