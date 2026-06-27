@extends('layouts.app')

@section('title', 'Review Run #'.$reviewRun->id)

@section('content')
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
        <p class="muted">This review run is ready for the next processing step.</p>
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
        </div>
    </section>
@endsection
