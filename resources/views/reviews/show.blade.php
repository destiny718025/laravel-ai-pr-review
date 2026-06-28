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

    @if (session('service_error_message'))
        <div class="error-block" style="margin-bottom: 24px;">
            <strong>{{ session('service_error_message') }}</strong>
        </div>
    @endif

    <section class="section">
        <h2>Status</h2>
        @if ($reviewRun->status === \App\Enums\ReviewRunStatus::Failed)
            <div class="error-block" style="margin-top: 0;">
                <strong>Review run failed</strong>
                <div>{{ $reviewRun->safe_error_message ?: 'The run failed, but no safe error summary was recorded.' }}</div>
                <div>Review the safe error summary, then run AI review again after fixing the source issue.</div>
            </div>
        @else
            <p class="muted">This review run is ready for the next processing step.</p>
        @endif
    </section>

    <section class="section">
        <h2>Pull Request</h2>
        <form method="POST" action="{{ route('reviews.fetch', $reviewRun) }}" style="margin-bottom: 20px;">
            @csrf
            <button type="submit">Fetch</button>
        </form>

        @if ($reviewRun->github_fetched_at)
            <form method="POST" action="{{ route('reviews.run', $reviewRun) }}" style="margin-bottom: 20px;">
                @csrf
                <button type="submit">Run AI Review</button>
            </form>
        @else
            <p class="muted">Fetch GitHub pull request data before running AI review.</p>
        @endif

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

    @if ($reviewRun->github_fetched_at)
        <section class="section">
            <h2>GitHub Snapshot</h2>
            <div class="metadata">
                <div class="metadata-row">
                    <span class="meta-label">Title</span>
                    <span>{{ $reviewRun->github_title }}</span>
                </div>
                <div class="metadata-row">
                    <span class="meta-label">State</span>
                    <span>{{ $reviewRun->github_state }}</span>
                </div>
                <div class="metadata-row">
                    <span class="meta-label">Head SHA</span>
                    <span>{{ $reviewRun->github_head_sha }}</span>
                </div>
                <div class="metadata-row">
                    <span class="meta-label">Fetched</span>
                    <span>{{ $reviewRun->github_fetched_at->format('Y-m-d H:i') }}</span>
                </div>
            </div>
        </section>

        <section class="section">
            <h2>Fetched Files</h2>
            @if ($reviewRun->files->isEmpty())
                <p class="muted">No changed files were returned by GitHub.</p>
            @else
                <div class="metadata">
                    @foreach ($reviewRun->files as $file)
                        <div class="metadata-row">
                            <span class="meta-label">{{ $file->filename }}</span>
                            <span>{{ $file->sha }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="section">
            <h2>Structured Findings</h2>
            @if ($reviewRun->findings->isEmpty())
                <p class="muted">No AI review findings have been persisted for this run.</p>
            @else
                <div class="metadata">
                    @foreach ($reviewRun->findings as $finding)
                        <div class="metadata-row">
                            <span class="meta-label">{{ str($finding->severity)->title() }} {{ str($finding->category)->title() }}</span>
                            <span>
                                <strong>{{ $finding->title }}</strong><br>
                                {{ $finding->file_path }}@if ($finding->line_reference):{{ $finding->line_reference }}@endif<br>
                                {{ $finding->rationale }}<br>
                                Suggested comment: {{ $finding->suggested_comment_text }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

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
