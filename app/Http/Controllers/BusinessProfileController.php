<?php

namespace App\Http\Controllers;

use App\Actions\BusinessProfile\UpdateBusinessProfile;
use App\Enums\IndianState;
use App\Enums\MsmeCategory;
use App\Http\Requests\BusinessProfile\UpdateBusinessProfileRequest;
use App\Models\CompanyProfile;
use App\Models\CompanyProfileChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessProfileController extends Controller
{
    /**
     * Show the business profile form.
     */
    public function edit(): Response
    {
        $profile = CompanyProfile::current();

        return Inertia::render('business-profile/edit', [
            'profile' => [
                ...$profile->only($profile->getFillable()),
                'gst_registered_on' => $profile->gst_registered_on?->toDateString(),
                'msme_category' => $profile->msme_category?->value,
                'msme_category_effective_from' => $profile->msme_category_effective_from?->toDateString(),
                'logo_url' => $profile->logoUrl(),
                'has_udyam_certificate' => $profile->udyam_certificate_path !== null,
            ],
            'states' => IndianState::options(),
            'msmeCategories' => MsmeCategory::options(),
            'msmeMaxPaymentDays' => CompanyProfile::MSME_MAX_PAYMENT_DAYS,
            'changes' => $profile->changes()
                ->with('user:id,name')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (CompanyProfileChange $change): array => [
                    'id' => $change->id,
                    'field' => $change->field,
                    'old_value' => $change->old_value,
                    'new_value' => $change->new_value,
                    'user' => $change->user?->name,
                    'created_at' => $change->created_at->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Update the business profile.
     */
    public function update(UpdateBusinessProfileRequest $request, UpdateBusinessProfile $updateBusinessProfile): RedirectResponse
    {
        $updateBusinessProfile->handle(CompanyProfile::current(), $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business profile saved. New invoices will use these details.')]);

        return to_route('business-profile.edit');
    }

    /**
     * Download the stored Udyam registration certificate.
     */
    public function certificate(): StreamedResponse
    {
        $path = CompanyProfile::current()->udyam_certificate_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'udyam-certificate.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}
