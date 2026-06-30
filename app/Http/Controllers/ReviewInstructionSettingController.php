<?php

namespace App\Http\Controllers;

use App\Services\ReviewInstructionSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewInstructionSettingController extends Controller
{
    public function update(
        Request $request,
        ReviewInstructionSettingService $reviewInstructionSettingService,
    ): RedirectResponse {
        $validated = $request->validateWithBag('instructions', [
            'custom_instructions' => ['nullable', 'string', 'max:20000'],
        ]);

        $reviewInstructionSettingService->updateGlobal($validated['custom_instructions'] ?? null);

        return back()->with('status', 'Custom review instructions saved.');
    }
}
