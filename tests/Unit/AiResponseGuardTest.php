<?php

namespace Tests\Unit;

use App\Support\AiResponseGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiResponseGuardTest extends TestCase
{
    #[Test]
    public function it_flags_the_observed_template_leakage_pattern(): void
    {
        $feedback = 'The student provided a detailed explanation using the law of conservation of energy to ...or null';

        $this->assertTrue(AiResponseGuard::looksLikeTemplateLeakage($feedback));
    }

    #[Test]
    public function it_flags_raw_json_schema_fragments(): void
    {
        $this->assertTrue(AiResponseGuard::looksLikeTemplateLeakage('Here is the "classification" you asked for'));
        $this->assertTrue(AiResponseGuard::looksLikeTemplateLeakage('{"feedback": "good job"}'));
        $this->assertTrue(AiResponseGuard::looksLikeTemplateLeakage('Return only valid JSON, no markdown, no explanation'));
    }

    #[Test]
    public function it_does_not_flag_ordinary_feedback_text(): void
    {
        $this->assertFalse(AiResponseGuard::looksLikeTemplateLeakage('Good link to the key idea, well done.'));
        $this->assertFalse(AiResponseGuard::looksLikeTemplateLeakage('Not quite — energy cannot be created or destroyed.'));
    }

    #[Test]
    public function it_flags_a_correct_verdict_that_shares_no_context_vocabulary(): void
    {
        $this->assertTrue(AiResponseGuard::sharesNoKeywords(
            'I like pizza and video games.',
            ['energy', 'conservation', 'generator']
        ));
    }

    #[Test]
    public function it_does_not_flag_an_answer_that_shares_at_least_one_keyword(): void
    {
        // This is the known limitation: the observed real-world bug ("all forms of
        // energy are created somehow") shares the word "energy" with the lesson
        // context, so this conservative check would NOT catch it on its own — the
        // template-leakage check is what catches that case. This test documents
        // that limitation rather than overstating what the keyword check can do.
        $this->assertFalse(AiResponseGuard::sharesNoKeywords(
            'the law states that all forms of energy are created somehow',
            ['energy', 'conservation', 'generator']
        ));
    }

    #[Test]
    public function it_never_flags_when_there_are_no_context_keywords_to_compare_against(): void
    {
        $this->assertFalse(AiResponseGuard::sharesNoKeywords('anything at all', []));
    }
}
