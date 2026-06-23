<?php

namespace Tests\Unit;

use App\Services\BloomTaxonomyClassifier;
use PHPUnit\Framework\TestCase;

class BloomTaxonomyClassifierTest extends TestCase
{
    public function test_it_classifies_create_level_keywords(): void
    {
        $classifier = new BloomTaxonomyClassifier();

        $result = $classifier->classify('Design an experiment to test this concept.', 'elaborate');

        $this->assertSame('create', $result['bloom_level']);
        $this->assertGreaterThanOrEqual(0.8, $result['bloom_confidence']);
    }

    public function test_it_uses_stage_baseline_without_strong_keywords(): void
    {
        $classifier = new BloomTaxonomyClassifier();

        $result = $classifier->classify('Can we talk more about this idea?', 'explore');

        $this->assertSame('analyze', $result['bloom_level']);
        $this->assertGreaterThan(0.5, $result['bloom_confidence']);
    }

    public function test_it_skips_system_tokens(): void
    {
        $classifier = new BloomTaxonomyClassifier();

        $result = $classifier->classify('__engage_start__', 'engage');

        $this->assertNull($result['bloom_level']);
        $this->assertNull($result['bloom_confidence']);
    }
}
