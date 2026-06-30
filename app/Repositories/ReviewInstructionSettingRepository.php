<?php

namespace App\Repositories;

use App\Models\ReviewInstructionSetting;

class ReviewInstructionSettingRepository
{
    public const GLOBAL_SCOPE = 'global';

    public function findGlobal(): ?ReviewInstructionSetting
    {
        return ReviewInstructionSetting::query()
            ->where('scope', self::GLOBAL_SCOPE)
            ->first();
    }

    public function getOrCreateGlobal(): ReviewInstructionSetting
    {
        return ReviewInstructionSetting::query()->firstOrCreate(
            ['scope' => self::GLOBAL_SCOPE],
            ['custom_instructions' => null],
        );
    }

    public function updateGlobal(?string $customInstructions): ReviewInstructionSetting
    {
        $setting = $this->getOrCreateGlobal();

        $setting->forceFill([
            'custom_instructions' => $customInstructions,
        ])->save();

        return $setting->refresh();
    }
}
