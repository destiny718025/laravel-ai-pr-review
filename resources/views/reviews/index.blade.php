@extends('layouts.app')

@section('title', 'Review Runs')

@section('content')
    <h1>Review Runs</h1>

    <section class="section" aria-labelledby="create-review-run-title">
        <h2 id="create-review-run-title">Create a Review Run</h2>

        <form method="POST" action="{{ route('reviews.store') }}">
            @csrf

            <div class="form-row">
                <div class="field">
                    <label for="pr_url">GitHub Pull Request URL</label>
                    <input
                        id="pr_url"
                        name="pr_url"
                        type="text"
                        value="{{ old('pr_url') }}"
                        placeholder="https://github.com/owner/repo/pull/123"
                        autocomplete="off"
                    >
                    <p class="helper">Use a GitHub pull request URL like https://github.com/owner/repo/pull/123.</p>
                </div>

                <button type="submit">Create Review Run</button>
            </div>

            @error('pr_url')
                <div class="error-block">
                    <strong>Review run was not created</strong>
                    <div>{{ $message }}</div>
                    <div>Check that the URL points to a GitHub pull request and try again.</div>
                </div>
            @enderror

            @if (session('service_error_message'))
                <div class="error-block">
                    <strong>Review run was not created</strong>
                    <div>{{ session('service_error_message') }}</div>
                    <div>Check that the URL points to a GitHub pull request and try again.</div>
                    @if (session('service_error_code'))
                        <div class="helper">Code: {{ session('service_error_code') }}</div>
                    @endif
                </div>
            @endif
        </form>
    </section>

    <section class="section" aria-labelledby="recent-review-runs-title">
        <h2 id="recent-review-runs-title">Recent Review Runs</h2>

        @if ($reviewRuns->isEmpty())
            <div class="empty-state">
                <strong>No review runs yet</strong>
                <span>Paste a GitHub pull request URL above to create your first review run.</span>
            </div>
        @else
            <div class="review-list">
                @foreach ($reviewRuns as $reviewRun)
                    <article class="review-row">
                        <div class="review-row-status">
                            <x-review-status :status="$reviewRun->status" />
                        </div>

                        <div class="review-row-main">
                            <div class="review-row-title">
                                <strong>{{ $reviewRun->pullRequest->repository->full_name }}</strong>
                                <span>PR #{{ $reviewRun->pullRequest->number }}</span>
                            </div>

                            <a href="{{ $reviewRun->pullRequest->source_url }}" target="_blank" rel="noreferrer">
                                {{ $reviewRun->pullRequest->source_url }}
                            </a>

                            @if ($reviewRun->status === \App\Enums\ReviewRunStatus::Failed)
                                <p class="helper">
                                    {{ $reviewRun->safe_error_message ?: 'The run failed, but no safe error summary was recorded.' }}
                                </p>
                            @endif
                        </div>

                        <div class="review-row-meta">
                            <span class="muted">{{ $reviewRun->created_at->format('Y-m-d H:i') }}</span>
                            <a href="{{ route('reviews.show', $reviewRun) }}">View review run</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
