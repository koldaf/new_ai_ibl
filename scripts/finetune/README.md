# Classification model fine-tuning

Tooling to fine-tune a classification model on admin-verified grading decisions from
the "Review AI Grading" admin page. This is **not runnable yet** for two reasons, and
both need to be true before it's worth using:

1. **No verified data exists yet.** The export command only includes classifications
   an admin has actually reviewed (`ai_chat_message.reviewed_at` set). Right after
   deploying the review flow, that count is zero. Use the admin "Review AI Grading"
   page for a while first — a rough floor of a few hundred verified examples is where
   supervised fine-tuning starts being worth the effort rather than just overfitting
   to a handful of phrasings.
2. **No GPU is available on the N100 production server.** Training (even LoRA) needs
   a CUDA GPU to be practical. None of this runs on the app server — train elsewhere
   (a rented cloud GPU for a few hours is usually a few dollars), then deploy only the
   resulting GGUF model back to Ollama on the N100 for inference.

## Workflow

1. **Collect reviews.** Use the admin "Review AI Grading" page over time to confirm or
   correct real classifications.

2. **Export the dataset** (runs on the app server, no GPU needed — this just reads the
   database):
   ```bash
   php artisan ai:export-classification-training-data \
       --output=storage/app/finetune/classification-dataset.jsonl
   ```
   Copy the resulting JSONL file to wherever you'll train.

3. **Train, on a GPU machine:**
   ```bash
   pip install -r requirements.txt
   python train_lora.py \
       --base-model Qwen/Qwen2.5-1.5B-Instruct \
       --dataset classification-dataset.jsonl \
       --output-dir ./output/classification-lora \
       --merge-and-save
   ```
   Use whichever base model you're actually planning to run as `OLLAMA_CLASSIFICATION_MODEL`
   — it must be a HuggingFace-hosted checkpoint of that same model so the tokenizer and
   architecture match. Adjust `--epochs`, `--learning-rate`, `--batch-size` based on
   dataset size and what fits in your GPU's VRAM; the defaults are a reasonable start,
   not a tuned recommendation.

4. **Convert the merged model to GGUF** (using llama.cpp, on the same GPU machine or
   any machine — this step is CPU-only):
   ```bash
   git clone https://github.com/ggml-org/llama.cpp
   cd llama.cpp && pip install -r requirements.txt
   python convert_hf_to_gguf.py /path/to/output/classification-lora/merged \
       --outfile classification-model.gguf --outtype q8_0
   ```

5. **Import into Ollama** on the N100 server:
   ```bash
   cat > Modelfile <<'EOF'
   FROM ./classification-model.gguf
   EOF
   ollama create custom-classifier -f Modelfile
   ```
   Then set in `.env`:
   ```
   OLLAMA_CLASSIFICATION_MODEL=custom-classifier
   ```

6. **Verify before trusting it** — run the same admin load-test tool against lessons
   with a good spread of reviewed examples, and spot-check classifications against the
   held-out eval split the training script reports on. Don't swap the production
   classification model based on training loss alone.

## Known limitations of the current exporter

- The prompt template in `ExportClassificationTrainingData.php` approximates the real
  prompts in `EngageDecisionService`/`StageCheckpointService` but isn't byte-for-byte
  identical (it doesn't re-run the actual RAG context retrieval, since that context
  isn't persisted anywhere for historical messages). If those services' prompts change
  materially, update the exporter's template to match.
- When an admin marks a classification **incorrect**, we only capture a corrected
  *label* (see `ClassificationReviewController`), not rewritten feedback text. The
  exporter falls back to the reviewer's note (if left) or a generic per-class sentence
  for the `feedback` field in that case. If you want the fine-tune to also learn better
  feedback wording (not just better labels), the review UI would need to collect a
  verified feedback sentence too — worth adding before a serious fine-tuning push.
