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

    public function test_custom_instructions_are_followed_by_output_contract_reminder(): void
    {
        $builder = new ReviewInstructionBuilder;
        $default = $builder->buildDefault();

        $customInstructions = '請用繁體中文回覆我';
        $composed = $builder->buildWithCustomInstructions($customInstructions);

        $this->assertStringStartsWith($default, $composed);
        $this->assertStringContainsString("Custom Review Instructions:\n".$customInstructions, $composed);
        $this->assertStringContainsString('Custom instructions may change review focus and the natural language used in title, rationale, and suggested_comment_text.', $composed);
        $this->assertStringContainsString('They must not change JSON keys, top-level structure, required fields, or the allowed severity/category labels.', $composed);
        $this->assertStringEndsWith('Always return the same JSON contract described above.', $composed);
    }
}
