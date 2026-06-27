@extends('layouts.app')

@section('title', 'Review Run #'.$reviewRun->id)

@section('content')
    @php
        $lifecycleTimestamps = [
            'Queued' => $reviewRun->queued_at,
            'Started' => $reviewRun->started_at,
            'Completed' => $reviewRun->completed_at,
            'Failed' => $reviewRun->failed_at,
            'Cancelled' => $reviewRun->cancelled_at,
        ];
    @endphp

    <p style="margin: 0 0 24px;">
        <a href="{{ route('reviews.index') }}">Back to review runs</a>
    </p>

    <div class="detail-header">
        <h1>Review Run #{{ $reviewRun->id }}</h1>
        <x-review-status :status="$reviewRun->status" />
    </div>

    @if (session('status'))
        <div class="success-block" style="margin-bottom: 24px;">
            <strong>{{ session('status') }}</strong>
        </div>
    @endif

    <section class="section">
        <h2>Status</h2>
        @if ($reviewRun->status === \App\Enums\ReviewRunStatus::Failed)
            <div class="error-block" style="margin-top: 0;">
                <strong>Review run failed</strong>
                <div>{{ $reviewRun->safe_error_message ?: 'The run failed, but no safe error summary was recorded.' }}</div>
                <div>Review the safe error summary, then create a new run after fixing the source issue.</div>
            </div>
        @else
            <p class="muted">This review run is ready for the next processing step.</p>
        @endif
    </section>

    <section class="section">
        <h2>Pull Request</h2>
        <div class="metadata">
            <div class="metadata-row">
                <span class="meta-label">Repository</span>
                <span>{{ $reviewRun->pullRequest->repository->full_name }}</span>
            </div>
            <div class="metadata-row">
                <span class="meta-label">Pull Request</span>
                <span>PR #{{ $reviewRun->pullRequest->number }}</span>
            </div>
            <div class="metadata-row">
                <span class="meta-label">Source URL</span>
                <a href="{{ $reviewRun->pullRequest->source_url }}" target="_blank" rel="noreferrer">
                    {{ $reviewRun->pullRequest->source_url }}
                </a>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Run Metadata</h2>
        <div class="metadata">
            <div class="metadata-row">
                <span class="meta-label">Created</span>
                <span>{{ $reviewRun->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div class="metadata-row">
                <span class="meta-label">Updated</span>
                <span>{{ $reviewRun->updated_at->format('Y-m-d H:i') }}</span>
            </div>

            @foreach ($lifecycleTimestamps as $label => $timestamp)
                @if ($timestamp)
                    <div class="metadata-row">
                        <span class="meta-label">{{ $label }}</span>
                        <span>{{ $timestamp->format('Y-m-d H:i') }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endsection
