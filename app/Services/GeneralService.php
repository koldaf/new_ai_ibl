<?php
namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AiPerformanceLogger;
use App\Services\OllamaEmbeddingGenerator;

class GeneralService
{
    private OllamaEmbeddingGenerator $embeddingGenerator;
    private string $llmModel;
    private string $ollamaUrl;
     public function __construct()
    {
        $this->embeddingGenerator = new OllamaEmbeddingGenerator(
            model: config('services.ollama.embedding_model', 'nomic-embed-text'),
            baseUrl: config('services.ollama.url', 'http://ollama:11434')
        );

        $this->llmModel = config('services.ollama.llm_model', 'qwen3:0.6b');
        $this->ollamaUrl = config('services.ollama.url', 'http://ollama:11434');
    }

    public static function generateUniqueCode($length = 8)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $uniqueCode = '';
        for ($i = 0; $i < $length; $i++) {
            $uniqueCode .= $characters[rand(0, $charactersLength - 1)];
        }
        return $uniqueCode;
    }

        public static function extractAnswers($responseText){
            $parts = preg_split("/\r?\n\r?\n/", $responseText, 2); 
            $json = $parts[1] ?? ''; 
            // 2. Decode JSON 
            $data = json_decode($json, true); 
            // 3. Extract the answer 
            $answer = isset($data['answer']) ? Str::markdown($data['answer']) : 'No answer available.';
            return $answer;
        }

        protected array $taxonomy = [ 
            'verification' => [ 'Is it true', 'Is it false', 'Did', 'Does', 'Is there', 'Are there' ], 
            'disjunctive' => [ 'Is it X or Y', 'Is X, Y or Z', 'Which of' ], 'concept_completion' => [ 'Who', 'What', 'When', 'Where' ], 
            'example' => [ 'What qualities', 'What characteristics', 'Give an example' ], 'feature_specification' => [ 'What are the properties', 'Describe the features', 'Characteristics of' ], 
            'quantification' => [ 'How much', 'How many', 'What is the value' ], 'definition' => [ 'What does', 'Define', 'Meaning of' ], 
            'comparison' => [ 'How is X similar', 'How is X different', 'Compare' ], 
            'interpretation' => [ 'What can be inferred', 'Interpret', 'What does this pattern mean' ], 
            'causal_antecedent' => [ 'Why did', 'What caused', 'How did this happen', 'Why is' ], 
            'causal_consequence' => [ 'What will happen if', 'What are the consequences', 'What if' ], 
            'goal_orientation' => [ 'Why did the agent', 'What is the motive', 'Goal of' ], 
            'instrumental_procedural' => [ 'How to', 'What steps', 'What procedure', 'How did the agent' ], 
            'enablement' => [ 'What allows', 'What enables', 'What resource' ], 
            'expectation' => [ 'Why did the expected event not occur', 'Why didn’t', 'Why hasn’t' ], 
            'judgmental' => [ 'What value', 'What do you think of', 'Evaluate' ], 
            ];

            /** * Classify a question using rule-based heuristics + optional LLM refinement. */ public function classify(string $question): string { 
                $normalized = Str::lower($question); 
                // 1. RULE-BASED MATCHING (fast, offline, deterministic) 
                foreach ($this->taxonomy as $label => $patterns) { 
                    foreach ($patterns as $pattern) { 
                        if (Str::contains($normalized, Str::lower($pattern))) {
                             return $label; 
                        }
                    }
                } // 2. FALLBACK TO LOCAL LLM (Ollama) FOR COMPLEX QUESTIONS // This ensures deep/complex questions are correctly classified. 
                $response = $this->askOllama($question);

                //return $this->extractLabel($response); 
                return $this->extractLabel($response) ?? 'unknown'; 
            }
        /** * Query your local Ollama model. */ 
        protected function askOllama(string $question): ?string 
        { 
            $callStart = microtime(true);
            try { 
                $prompt = $this->buildPrompt($question, 'question_classifier'); 
                $response = Http::post($this->ollamaUrl . '/api/generate', [ 
                    'model' => $this->llmModel, 
                    'prompt' => $prompt, 
                    'stream' => false, 
                    ]); 
                    $wallClockMs = (microtime(true) - $callStart) * 1000;
                    Log::info('Ollama response: ' . $response);
                    $data = $response->json();
                    AiPerformanceLogger::log(
                        is_array($data) ? $data : [],
                        $wallClockMs,
                        ['caller' => 'general_classify', 'model_name' => $this->llmModel, 'question_snippet' => $question]
                    );
                    return $data['response'] ?? null; 
                } 
            catch (\Exception $e) { 
                AiPerformanceLogger::logError(
                    (microtime(true) - $callStart) * 1000,
                    ['caller' => 'general_classify', 'model_name' => $this->llmModel],
                    $e->getMessage()
                );
                return null; 
                }
        }
        /** * Build the classification prompt for the LLM. */ 
        protected function buildPrompt(string $question, $prompt_type): string { 
            if($prompt_type == 'question_classifier'){
                //taxonomy of question types in inquiry based learning Tawfik, A. A., Graesser, A., Gatewood, J., & Gishbaugher, J. (2020). Role of questions in inquiry-based instruction: towards a design taxonomy for question-asking and implications for design. Educational Technology Research and Development, 68(2), 653–678. https://doi.org/10.1007/s11423-020-09738-9
                return " You are a classifier. Categorize the question into EXACTLY one of the following types: verification, disjunctive, concept_completion, example, feature_specification, quantification, definition, comparison, interpretation, causal_antecedent, causal_consequence, goal_orientation, instrumental_procedural, enablement, expectation, and judgmental Return ONLY the type. 
                Question: \"$question\" "; 
            }else{
                return "";
            }
        }
        /** * Extract the label from the LLM response. */ 
        protected function extractLabel(?string $response): ?string { 
            if (!$response) { 
                return null; 
            } 
            $response = Str::lower(trim($response)); 
            foreach (array_keys($this->taxonomy) as $label) { 
                if (Str::contains($response, $label)) { 
                    return $label; 
                } 
            } 
            return null; 
        }
    }