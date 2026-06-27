<?php

namespace App\Http\Controllers;

use App\Models\ReviewRun;
use App\Services\ReviewRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(): View
    {
        return view('reviews.index');
    }

    public function store(Request $request, ReviewRunService $reviewRunService): RedirectResponse
    {
        $validated = $request->validate([
            'pr_url' => ['required', 'string'],
        ]);

        $result = $reviewRunService->createFromPullRequestUrl($validated['pr_url']);

        if (! $result->successful()) {
            return redirect()
                ->route('reviews.index')
                ->withInput()
                ->with('service_error_code', $result->errorCode())
                ->with('service_error_message', $result->message());
        }

        return redirect()
            ->route('reviews.show', $result->reviewRun())
            ->with('status', $result->message());
    }

    public function show(ReviewRun $reviewRun): View
    {
        return view('reviews.show', [
            'reviewRun' => $reviewRun->load('pullRequest.repository'),
        ]);
    }
}
