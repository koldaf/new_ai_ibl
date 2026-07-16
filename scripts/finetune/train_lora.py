#!/usr/bin/env python3
"""
LoRA fine-tune for the classification model, using the JSONL dataset produced by:

    php artisan ai:export-classification-training-data --output=storage/app/finetune/classification-dataset.jsonl

This is a starting point, not a turnkey pipeline — expect to adjust hyperparameters
and library-version-specific API calls for your actual environment. It requires a
CUDA GPU; it is NOT intended to run on the N100 production server. Train on a rented
GPU instance (or any machine with one), then deploy only the resulting GGUF model to
the N100 for inference. See README.md in this directory for the full workflow.

Usage:
    python train_lora.py \
        --base-model Qwen/Qwen2.5-1.5B-Instruct \
        --dataset ../../storage/app/finetune/classification-dataset.jsonl \
        --output-dir ./output/classification-lora
"""

import argparse
import json
import os


def parse_args():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-model", required=True,
                         help="HuggingFace model id or local path, e.g. Qwen/Qwen2.5-1.5B-Instruct. "
                              "Must match the model family you intend to deploy as OLLAMA_CLASSIFICATION_MODEL.")
    parser.add_argument("--dataset", required=True, help="Path to the JSONL file exported by the artisan command.")
    parser.add_argument("--output-dir", required=True, help="Where to write the LoRA adapter and checkpoints.")
    parser.add_argument("--epochs", type=float, default=3.0)
    parser.add_argument("--learning-rate", type=float, default=2e-4)
    parser.add_argument("--batch-size", type=int, default=2,
                         help="Per-device batch size. Keep small — these are short prompts but GPU VRAM varies a lot.")
    parser.add_argument("--gradient-accumulation-steps", type=int, default=8)
    parser.add_argument("--lora-r", type=int, default=16)
    parser.add_argument("--lora-alpha", type=int, default=32)
    parser.add_argument("--max-seq-length", type=int, default=2048)
    parser.add_argument("--merge-and-save", action="store_true",
                         help="After training, merge the LoRA adapter into the base model and save the full "
                              "merged model (needed before GGUF conversion).")
    parser.add_argument("--eval-fraction", type=float, default=0.1,
                         help="Fraction of the dataset held out for eval, to catch overfitting.")
    return parser.parse_args()


def load_dataset(path):
    from datasets import Dataset

    rows = []
    with open(path, "r") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            rows.append(json.loads(line))

    if len(rows) < 20:
        print(f"WARNING: only {len(rows)} examples in dataset — this is very likely too few to "
              "fine-tune reliably without overfitting. Consider reviewing more classifications first.")

    return Dataset.from_list(rows)


def main():
    args = parse_args()

    # Imported lazily so --help works without a GPU environment installed.
    import torch
    from transformers import AutoModelForCausalLM, AutoTokenizer, TrainingArguments
    from peft import LoraConfig, get_peft_model
    from trl import SFTTrainer, SFTConfig

    if not torch.cuda.is_available():
        print("WARNING: no CUDA GPU detected. This will be extremely slow or impractical on CPU. "
              "Run this on a GPU machine instead (see README.md).")

    dataset = load_dataset(args.dataset)
    split = dataset.train_test_split(test_size=args.eval_fraction, seed=42)

    tokenizer = AutoTokenizer.from_pretrained(args.base_model)
    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    model = AutoModelForCausalLM.from_pretrained(
        args.base_model,
        torch_dtype=torch.bfloat16 if torch.cuda.is_available() else torch.float32,
        device_map="auto",
    )

    lora_config = LoraConfig(
        r=args.lora_r,
        lora_alpha=args.lora_alpha,
        lora_dropout=0.05,
        bias="none",
        task_type="CAUSAL_LM",
        # Standard attention/MLP projection names for Qwen2.5-family models.
        target_modules=["q_proj", "k_proj", "v_proj", "o_proj", "gate_proj", "up_proj", "down_proj"],
    )
    model = get_peft_model(model, lora_config)
    model.print_trainable_parameters()

    sft_config = SFTConfig(
        output_dir=args.output_dir,
        num_train_epochs=args.epochs,
        per_device_train_batch_size=args.batch_size,
        per_device_eval_batch_size=args.batch_size,
        gradient_accumulation_steps=args.gradient_accumulation_steps,
        learning_rate=args.learning_rate,
        logging_steps=5,
        eval_strategy="epoch",
        save_strategy="epoch",
        max_seq_length=args.max_seq_length,
        bf16=torch.cuda.is_available(),
        report_to=[],
    )

    trainer = SFTTrainer(
        model=model,
        args=sft_config,
        train_dataset=split["train"],
        eval_dataset=split["test"],
        processing_class=tokenizer,
    )

    trainer.train()
    trainer.save_model(args.output_dir)
    tokenizer.save_pretrained(args.output_dir)
    print(f"LoRA adapter saved to {args.output_dir}")

    if args.merge_and_save:
        merged_dir = os.path.join(args.output_dir, "merged")
        merged_model = trainer.model.merge_and_unload()
        merged_model.save_pretrained(merged_dir)
        tokenizer.save_pretrained(merged_dir)
        print(f"Merged full model saved to {merged_dir} — convert this directory to GGUF next (see README.md).")


if __name__ == "__main__":
    main()
