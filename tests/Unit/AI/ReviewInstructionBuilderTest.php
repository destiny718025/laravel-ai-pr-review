<?php

namespace Tests\Unit\AI;

use App\Services\AI\ReviewInstructionBuilder;
use Tests\TestCase;

class ReviewInstructionBuilderTest extends TestCase
{
    public function test_blank_custom_instructions_return_default_instructions_only(): void
    {
        $builder = new ReviewInstructionBuilder;

        $this->assertSame($builder->buildDefault(), $builder->buildWithCustomInstructions(null));
        $this->assertSame($builder->buildDefault(), $builder->buildWithCustomInstructions(" \n "));
    }

    public function test_custom_instructions_are_appended_after_default_guidance(): void
    {
        $builder = new ReviewInstructionBuilder;
        $default = $builder->buildDefault();

        $composed = $builder->buildWithCustomInstructions('Only report issues that can affect production behavior.');

        $this->assertStringStartsWith($default, $composed);
        $this->assertSame(
            $default."\n\nCustom Review Instructions:\nOnly report issues that can affect production behavior.",
            $composed,
        );
    }
}
