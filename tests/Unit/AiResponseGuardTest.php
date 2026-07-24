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

    #[Test]
    public function it_trims_a_truncated_answer_back_to_the_last_complete_sentence(): void
    {
        $truncated = 'Energy is stored in the battery as chemical energy. When the torch switches on, it changes into light and he';

        $this->assertSame(
            'Energy is stored in the battery as chemical energy.',
            AiResponseGuard::trimToLastCompleteSentence($truncated)
        );
    }

    #[Test]
    public function it_keeps_the_full_text_when_it_already_ends_cleanly(): void
    {
        $clean = 'Good, that is correct!';

        $this->assertSame($clean, AiResponseGuard::trimToLastCompleteSentence($clean));
    }

    #[Test]
    public function it_returns_the_original_text_when_no_sentence_boundary_exists(): void
    {
        $fragment = 'energy is stored in the battery as chemical';

        $this->assertSame($fragment, AiResponseGuard::trimToLastCompleteSentence($fragment));
    }

    #[Test]
    public function it_detects_the_out_of_context_phrase_case_insensitively(): void
    {
        $this->assertTrue(AiResponseGuard::containsPhrase(
            'Your question is out of context for this lesson.',
            'your question is out of context for this lesson'
        ));
        $this->assertFalse(AiResponseGuard::containsPhrase(
            'Energy cannot be created or destroyed.',
            'your question is out of context for this lesson'
        ));
    }

    #[Test]
    public function it_truncates_right_after_the_refusal_phrase_and_drops_the_answer_that_followed(): void
    {
        // Reproduces the real bug: the model correctly said the refusal, then
        // kept going anyway and answered from outside the RAG corpus.
        $leaked = 'Your question is out of context for this lesson. Answer: Energy cannot be created; ' .
            'it can only change from one form into another. This is known as the law of conservation of energy.';

        $result = AiResponseGuard::truncateAfterPhrase($leaked, 'your question is out of context for this lesson');

        $this->assertSame('Your question is out of context for this lesson.', $result);
    }

    #[Test]
    public function it_leaves_text_unchanged_when_the_phrase_is_not_present(): void
    {
        $answer = 'Chemical energy stored in the battery powers the torch.';

        $this->assertSame($answer, AiResponseGuard::truncateAfterPhrase($answer, 'your question is out of context for this lesson'));
    }
}
