<?php

namespace App\Services;

use App\Repositories\ReviewInstructionSettingRepository;

class ReviewInstructionSettingService
{
    public function __construct(
        private readonly ReviewInstructionSettingRepository $settings,
    ) {}

    public function currentGlobalInstructions(): ?string
    {
        return $this->settings->getOrCreateGlobal()->custom_instructions;
    }

    public function updateGlobal(?string $customInstructions): void
    {
        $this->settings->updateGlobal($this->normalize($customInstructions));
    }

    private function normalize(?string $customInstructions): ?string
    {
        if ($customInstructions === null) {
            return null;
        }

        $customInstructions = trim($customInstructions);

        return $customInstructions === '' ? null : $customInstructions;
    }
}
