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

        <div class="empty-state">
            <strong>No review runs yet</strong>
            <span>Paste a GitHub pull request URL above to create your first review run.</span>
        </div>
    </section>
@endsection
