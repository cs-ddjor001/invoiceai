# InvoiceAI — Laravel Rebuild Plan

Working document. Written 2026-07-28 at the end of the planning session.

---

## 1. What this project is

A from-scratch Laravel/PHP rebuild of **InvoiceAI**, a Flask capstone project (ODU CS 411W,
Team Copper, Spring 2026) that extracts data from invoice PDFs using a locally hosted LLM
and matches those invoices against purchase orders in a database.

**The rebuild is a learning exercise**, not a product. The goal is to genuinely understand
Laravel ahead of a junior full-stack role whose stack is:

> HTML, CSS, Tailwind, JavaScript · PHP, Laravel, Livewire, MySQL ·
> Laravel Forge, DigitalOcean, Azure · CI/CD via GitHub Actions and PHPUnit with coverage

Every technology choice here is made to match that stack.

### Two standing rules

1. **Idiomatic Laravel always beats faithfulness to the original.** Faithfulness applies to
   *behavior* — pipeline stages, matching weights, price tolerance tiers, the 7-digit PO
   rule. It never applies to *structure*. Do not port a Python shape into PHP just because
   that is how the Flask app did it.
2. **Working mode:** Claude scaffolds boilerplate (migrations, config, CI). For the concepts
   that carry the learning — Eloquent relationships, Livewire, queues, policies — Claude
   explains the concept first, the user writes the code, then Claude reviews. Code the user
   didn't write teaches nothing.

---

## 2. The source project

**Location:** `c:\Users\dusan\CS_411W\S26-TeamCopper-InvoiceAI` (separate git repo, `main`)

Flask + Jinja2 + Tailwind (CDN) + SQLite + SQLAlchemy, with Celery/Redis partially wired
and Docker Compose running MailHog for simulated email intake.

### The pipeline (the heart of the app)

```
PDF ──► pdfplumber (text + tables → JSON) ──► has text? ──yes─► AIExtractor (LLM chat → JSON)
                                              └──────────no───► VisionExtractor (PyMuPDF → PNG → vision model)
                                                                        │
                                        normalize_raw_invoice()  ◄──────┘   (~40 alias keys for po_number)
                                        InvoiceValidator (pydantic)
                                          · PO number must be EXACTLY 7 digits
                                          · many date formats → Y-m-d, year sanity 2000–2030
                                          · currency repair ("22.810.20" → 22810.20)
                                                    │
                                        save_parsed_invoice() → invoices + invoice_line_items
                                                    │
                        ┌───────────────────────────┴────────────────────────┐
                run_matching()                                      run_ai_matching()
                exact → fuzzy (rapidfuzz)                           fuzzy top-10 prefilter → LLM judge
                                                                    returns confidence + reasoning
                        └───────────────► matches table + invoice.confidence_score
```

### Matching rules (preserve these exactly)

Weighted score: **PO number 50% · line items 45% · date 5%**

Unit-price tolerance tiers:

| PO unit price | Tolerance |
|---|---|
| > $5,000 | 1% |
| < $100 | 5% |
| otherwise | 2% |

Deterministic path: exact PO-number match **plus** a line item whose part number matches and
whose unit price is within tolerance scores 100. Otherwise fall back to weighted fuzzy, with
a 0.55 acceptance threshold. A match is only recorded when the final score is > 25.

Quality score (`app/utils/invoice_quality_score.py`): starts at 100 and subtracts for missing
raw text, missing `po_number` (−30), missing total (−10), missing date (−8), missing line
items (−30), and per-line-item gaps. Final match confidence = match score × (quality / 100).

### Key source files

| Concern | File |
|---|---|
| App factory, blueprints | `app/__init__.py` |
| PDF → JSON | `app/extraction/pdfplumber_extractor.py` |
| Text LLM extraction + prompt | `app/extraction/ai_extractor.py` |
| Vision extraction + prompt | `app/extraction/vision_extractor.py` |
| Normalization (PO aliases) | `app/parsing/db_writer.py` |
| Validation rules | `app/parsing/validator.py` |
| Quality scoring | `app/utils/invoice_quality_score.py` |
| Fuzzy scoring, tolerances | `app/matching/fuzzy_matcher.py` |
| Exact matching | `app/matching/exact_matcher.py` |
| AI matching + prompt | `app/matching/ai_matcher.py` |
| Match runners | `app/matching/run_matching.py`, `run_ai_matching.py` |
| CSV seeding | `app/data_loaders/*.py` |
| Email intake | `email_pipeline/mailhog_client.py` |
| Main UI (647 lines) | `templates/ap.html` |

### Known defects in the original — fix these, don't port them

- **Two colliding score scales in the deterministic path.** `run_matching.py:31` does
  `round(match_score * invoice_quality_score)` with no `/ 100`, but the damage is uneven
  because `match_score` arrives on two different scales:

  | Path | `match_score` | × quality (100) | Stored |
  |---|---|---|---|
  | Exact (`exact_matcher` step 1) | `100` | ×100 | **10000** |
  | Fuzzy (`match_by_fields_fuzzy`) | `0.55`–`1.0` | ×100 | 55–100 |

  So the fuzzy path lands in the right range *by accident* and only the exact path is
  inflated. Fix: pick one internal scale (0–1 is cleaner), multiply by `quality / 100`, and
  convert to 0–100 only at the presentation boundary.

- **`matcher.py`'s fuzzy fallback is dead code.** `exact_matcher.match_invoice` already falls
  back to `match_by_fields_fuzzy` internally and returns its raw 0–1 score; it returns
  `(None, 0)` only when fuzzy *also* failed. So `matcher.py` step 2 — the branch containing
  the `round(score * 100)` rescale — is unreachable. That unreachable rescale is what hides
  the scale collision above. The rebuild should have exactly one matching entry point.

- **The exact-match 100 score can never fire from the web upload path.** `validator.py`'s
  `LineItem` model defines only `description`, `quantity`, `unit_price`, `total` — there is
  **no `part_number` field** — and `db_writer.save_parsed_invoice` hardcodes
  `part_number=None` when building each `Invoice_Line_Item`. But
  `exact_matcher.invoice_has_matching_line_item` requires a part-number match before it will
  return 100. Every uploaded invoice therefore has null part numbers and silently degrades to
  fuzzy. **The Phase 3 `InvoiceData` DTO must carry `part_number` end to end**, or Phase 4's
  deterministic path is decorative.

- **Part-number comparison is inconsistent between the two matchers.**
  `fuzzy_matcher.has_valid_line_item_match` compares via `normalize()` (uppercase, strips
  `\s-_:;,.`) while `exact_matcher.invoice_has_matching_line_item` uses a bare `.strip()` —
  case- and punctuation-sensitive. Same stated ADS rule, two implementations. Normalize in
  both. (`has_valid_line_item_match` is also never called by anything; `exact_matcher` keeps
  its own copy.)
- `Purchase_Order.query.all()` runs inside a per-invoice loop — a full table scan per invoice
  on every match run. Push candidate prefiltering into SQL.
- Triple bookkeeping: `invoice.matched_po_id` + `invoice.ai_matched_po_id` + a `match` table.
- Auth is fake: no password, `session['username']` is set *before* the user is validated,
  and route protection is a bare `session.get()` check.
- Quality score is computed in `text_parser.py`, which the web upload path never calls, so
  stored `quality_score` is always the default 100.
- Uploaded PDFs are copied to `data/sample_{id}.pdf` and served by a route that guesses the
  filename from the invoice id.
- Extraction runs synchronously inside the HTTP request (hence the 5–15s loading overlay).
- `app/tasks.py` imports `parsing.parser`, a path that does not exist.

---

## 3. Decisions already made

| Decision | Choice | Why |
|---|---|---|
| PDF text extraction | **`smalot/pdfparser`** (pure PHP) | No external binaries, works on Herd/Windows immediately, keeps the stack deployable. Accepts the loss of pdfplumber's table extraction — layout text is fed to the model instead. |
| Vision path (scanned PDFs) | **Deferred**, behind an interface | Avoids blocking on Windows Imagick + Ghostscript. `InvoiceExtractor` interface ships from day one so the driver slots in with no rework. |
| Fidelity | **Improve while porting** | Real auth, normalized matches table, queued extraction, Storage disks, fixed scoring bug. |
| Auth features enabled | registration, email-verification, password-confirmation | 2FA and passkeys add surface area that teaches nothing relevant here. Registration is enabled *for now* — Phase 2 replaces self-registration with admin provisioning, which is what the domain actually calls for. |
| `po_number` column type | **`string(20)`** — unique on `purchase_orders`, nullable + indexed on `invoices` | The original used `db.Integer`. But the invoice-side PO number arrives as free text from an LLM and normalization strips non-digits, so string keeps extraction and storage in the same shape and survives leading zeros or a future non-numeric format. The 7-digit rule is a *validation* concern and lives in the Phase 3 DTO, not the schema. |
| Extraction HTTP client | **Laravel's built-in `Http::`**, not `openai-php/laravel` | llama-server exposes the OpenAI chat-completions API — one POST with a JSON body. The facade covers it in ~10 lines, and `Http::fake()` lets the whole pipeline be tested with llama-server switched off. One fewer dependency for no lost capability. |
| Multi-invoice PDFs | **Deferred** — `InvoiceExtractor::extract()` returns one `InvoiceData` | Measured: all sample PDFs hold a single invoice except ~2. `rockybrands_535357-001…pdf` is 5 pages, each a distinct invoice with its own PO and invoice number — but **only the first page's PO appears in the ADS CSVs**, so the others cannot be cross-referenced anyway. If the interface later returns a collection, taking the first element is the correct behaviour for this data. |
| Model reasoning (`enable_thinking`) | **Off by default** (`EXTRACTION_ENABLE_THINKING=false`). Config-driven, so it can be flipped per environment. | Measured over all 39 sample invoices, twice. **Off:** 37/39 extracted, **29 POs verified against seeded records**, ~8s each, ~5 min total. **On (max_tokens 16384):** 26/39 extracted, 24 verified, ~260s each, **2h49m total** — 11 invoices burned the entire token budget on `reasoning_content` and returned an empty `content`. Raising the budget did not help; the model simply reasons longer to fill it. Note the tradeoff is precision vs recall, not quality: of the 24 POs reasoning returned, **all 24 were correct** (vs 29/33 with it off), and it uniquely rescued `SI266A00090` and `rockybrands`. A **hybrid** is therefore the known upgrade path — run cheap, retry with reasoning only when the first pass yields no valid 7-digit PO (~6 invoices here). Deferred: Phase 5's queue makes slow extraction a worker-time cost rather than a user-facing one, which is where this belongs. |
| Extraction accuracy target | **None.** 29/39 is accepted as sufficient. | The project's goal is to learn Laravel, not to build a state-of-the-art extractor. The data only has to be good enough to drive Phase 4 matching and the Phase 7 review screens — and a human reviews every invoice anyway. Do not spend time tuning prompts or models beyond what the pipeline needs to function. |
| PO number normalization | **Not needed — do not build.** | The plan originally called for porting ~40 PO-number alias mappings from `db_writer.py`. Measured: across all 39 invoices, **zero** extracted PO numbers would be rescued by stripping non-digits or alias-mapping. The model returns clean 7-digit values or nothing. A negative result, recorded so it is not rebuilt on speculation. |
| Reasoning-tag stripping | **Not needed — do not port.** | The source app stripped `<think>...</think>` blocks out of the response text. That was correct for Qwen3, which emits reasoning inline in `content`. The rebuild's model returns reasoning in a **separate `reasoning_content` field**, so `content` is either clean JSON or empty. The `/no_think` marker the source appended to every user prompt is likewise inert here. Ported code would have been dead code. |
| Vendor identity | **Never extracted from the invoice.** `InvoiceData` carries no vendor field and `SYSTEM_PROMPT` does not ask for one. `invoices.vendor_id` / `invoices.vendor_name` stay in the schema, filled elsewhere. | Measured on the real dataset: only 3–4 of the 39 sample invoices carry any vendor label at all, and where one exists it is a vendor *ID number* for an AP clerk to cross-reference in the CSVs — not a name. Asking the model for a field the documents do not contain produces confident garbage (one run returned `Ward PRO# 0280828432`, a freight tracking number). The source project reached the same conclusion and moved to back-filling. Vendor is resolved two other ways instead: **back-filled from the matched PO** (Phase 4), and **resolved from the sender** on email intake (Phase 6), which was the original team's stretch goal — an AP clerk can identify a vendor from the email that delivered the invoice. |
| Sample data location | **Copy the two CSVs into `database/data/`, gitignored**; commit a ~20-row subset to `tests/fixtures/` | Gives the seeder a stable relative path instead of depending on a sibling repo's absolute path, and lets Phase 8 CI seed from the committed fixture without putting real ADS vendor and pricing data into git history. The 40 sample PDFs stay in the source repo until Phase 5 needs them. |

---

## 4. Target stack (verified, not assumed)

PHP **8.4** · Laravel **13** · Livewire **4** · Laravel Fortify **1** (Breeze no longer
exists in the official starter kits) · Flux UI **v2 free** · Tailwind **v4** (CSS-first
config, no `tailwind.config.js`) · PHPUnit **12** · Larastan/PHPStan · Pint · Laravel Boost 2

Planned additions (not yet installed — needs approval per Boost guidelines):
`smalot/pdfparser`, `zbateson/mail-mime-parser`, and either `openai-php/laravel` pointed at
llama-server or plain `Http::`.

---

## 5. Concept mapping (Flask → Laravel)

| Flask / Python | Laravel / PHP |
|---|---|
| `create_app()` factory, blueprints | `bootstrap/app.php`, route files, controllers |
| SQLAlchemy models | Eloquent models + **migrations** |
| `db.create_all()` + `load_po_csv.py` | Migrations + Seeders + Factories |
| Jinja `{% extends %}` / macros | Blade `@extends` / Flux components |
| Hand-written tab & upload JS | **Livewire components** (`wire:model`, `wire:poll`) |
| `session['username']` | Fortify guard, `auth()->user()`, middleware |
| Role checks (effectively none) | Gates + Policies + role middleware |
| Celery + Redis | **Laravel Queues** (`database` driver), Jobs, Batches |
| `openai` Python client | `openai-php/laravel` at llama-server, or `Http::` |
| pydantic `InvoiceValidator` | Form Request + DTO / value object |
| pdfplumber | `smalot/pdfparser` |
| PyMuPDF → PNG | Imagick / `spatie/pdf-to-image` (deferred) |
| rapidfuzz | `FuzzyScorer` service (normalized Levenshtein) |
| MailHog + Docker | **Mailpit** (bundled with Herd) |
| `data/uploads/` | `Storage::disk('invoices')` |
| — | PHPUnit + GitHub Actions coverage |

---

## 6. Target schema

```
users            (+ role, is_active, accepts_queue on the stock Fortify table)
vendors          id, external_id, name, user_id (assigned AP rep), timestamps
vendor_emails    id, vendor_id, email
purchase_orders  id, po_number (unique, indexed), vendor_id, vendor_name, po_date,
                 status, buyer_name
po_lines         id, purchase_order_id, line_num, part_number, description, uom,
                 qty_ordered, qty_delivered, qty_cancelled, unit_price, amt_invoiced,
                 status, clin
invoices         id, invoice_number, po_number, vendor_id, vendor_name, amount, subtotal,
                 tax, issued_at, status (enum), quality_score, source (enum: upload|email),
                 file_path, uploaded_by, timestamps
invoice_lines    id, invoice_id, line_num, part_number, description, uom, qty,
                 unit_price, amount, clin
invoice_matches  id, invoice_id, purchase_order_id, strategy (enum: exact|fuzzy|ai),
                 confidence, reasoning (json), is_accepted, reviewed_by, timestamps
extraction_runs  id, invoice_id, extractor (enum), raw_response (json), duration_ms,
                 status, error
activity_log     id, user_id, invoice_id, action, timestamps
```

Two deliberate departures from the original:

- **`invoice_matches`** replaces the three-way duplicated bookkeeping. One row per strategy
  keeps the deterministic-vs-AI comparison the original was built to make.
- **`extraction_runs`** gives real observability into model behavior — which is what the
  original's `model-trainer.html` was faking with static markup.

**PHP gotcha:** `Match` is a reserved keyword in PHP 8. The model must be `InvoiceMatch`.

**`po_number` is `string(20)`, not an integer** — see §3. Everything that compares PO numbers
must normalize first (strip non-digits) rather than cast.

**Invoice numbers have no format; PO numbers strictly do.** Measured against the 17 extracted
JSONs in the source repo's `data/json_output/`:

| Field | Finding |
|---|---|
| `po_number` | **17 of 17 are exactly 7 digits** (1339887, 1374495, 1513233 …). The source app's 7-digit rule is real and holds. |
| `invoice_number` | No pattern at all: `13983`, `5951865995`, `DEF037692`, `II24010307`, `0002473-IN`, `25-IN02001`, `INV050-008681`, `INV-1040`. 5–13 chars. Only one begins with `INV`. PDF filenames add `SI-US012291`, `C3151-0001`, `F260009`, `ARI2425679`. |

The asymmetry has a cause: ADS issues PO numbers, 39 different vendors issue invoice numbers.

Consequences: **never validate `invoice_number` against a format** — `validator.py` treats it as
an unconstrained optional string and that is correct. It must **not** be unique (two vendors can
both issue `13983`). It is not an input to matching (PO 50% / lines 45% / date 5%), so Phase 4 is
unaffected. Factories must generate the observed variety, or tests pass against assumptions
production does not share.

**`CLIN` is not a single code.** One CSV row (PO 1481497, line 1) carries
`"0029, 0029, 0029, 0029, 0029, 0029, 0029, 0029, 0029"` — 52 characters — because a single
ADS line can cover several contract line item numbers. `clin` is therefore `string(255)` on
both `po_lines` and `invoice_lines`, not the 50 originally planned. Anything that later parses
CLIN must expect a comma-separated list.

**Dataset size, measured:** the two ADS CSVs hold 114 + 124 = **238 line rows across only 39
distinct PO numbers**. `LazyCollection` + `chunk` + `upsert` is still the technique worth
learning in Phase 1, but expect the milestone to complete instantly — this is not a volume
exercise. Note the CSV shape: every row is a *line item*, and PO header fields repeat across
all rows sharing a `PO_NUMBER`, so the seeder needs the equivalent of the Python loader's
`po_cache` (or a two-pass: distinct headers first, then lines).

---

## 7. Phases

- [x] **Phase 0 — Setup.** Laravel installed, MySQL wired, migrations run, tests green, Herd
      serving, llama-server reachable.
- [x] **Phase 1 — Schema & data.** 13 migrations, 5 enums, 10 models with relationships and
      casts, 10 factories, `PurchaseOrderSeeder` streaming both ADS CSVs via
      `LazyCollection` + `chunk` + `upsert`, and `SchemaRelationshipsTest` (8 tests) proving
      the graph hangs together. *Milestone met: `migrate:fresh --seed` yields 39 vendors /
      39 purchase orders / 238 PO lines, and re-running converges instead of duplicating.*
- [x] **Phase 2 — Auth, roles, layout.** Role enum (`ap`, `admin`, `trainer`), middleware,
      policies. Disable self-registration via `config/fortify.php`. Shared Blade layout.
      Three protected role landing pages.
- [x] **Phase 3 — Extraction pipeline.** `PdfTextExtractor` → `LlmInvoiceExtractor` →
      `JsonResponseParser` → `InvoiceData` / `InvoiceLineData`, chained by
      `InvoiceExtractionPipeline`. Config-driven via `config/extraction.php`. 15 tests,
      `Http::fake()` throughout. *Milestone met: benchmarked over all 39 sample invoices —
      37 extracted, **29 PO numbers verified against seeded purchase orders**, 36/37 with
      line-item part numbers, ~8s per invoice.*
      **Deliberately not built** (see the decisions table for the measurements behind each):
      `InvoiceExtractor` interface (one implementation is not a choice — add it with the
      vision driver), `InvoiceNormalizer` (measured unnecessary), `<think>` stripping (wrong
      model family), vendor extraction (absent from the source documents).
      **Still open:** `QualityScorer`, and the reasoning-retry hybrid for the ~6 invoices the
      fast path misses.
- [ ] **Phase 4 — Matching engine.** `FuzzyScorer`, `ExactMatcher`, `FuzzyMatcher`,
      `AiMatcher`, `MatchingService`. Fix the ÷100 bug. SQL-side candidate prefiltering.
      Heavy PHPUnit coverage — this phase is nearly pure PHP.
- [ ] **Phase 5 — Upload & queues.** Livewire upload (`WithFileUploads` gives drag-drop
      nearly free), `Storage` disk, `ProcessInvoice` job on the queue, `wire:poll` for live
      status, job batching for multi-file, retries and `failed_jobs`.
- [ ] **Phase 6 — Email intake.** Mailpit (Herd's — no Docker). `Mail::to()` Mailable to
      replace `test_email.py`. `FetchInvoiceEmails` job hitting Mailpit's API with
      `zbateson/mail-mime-parser`. Vendor resolution by sender domain. Laravel scheduler.
- [ ] **Phase 7 — The three screens.** AP workspace as Livewire components (tabs, review
      queue, PO panel, PDF preview) — replaces ~160 lines of hand-rolled JS. Admin dashboard
      with aggregate queries. Trainer page backed by real `extraction_runs` data.
- [ ] **Phase 8 — Tests & CI.** Feature tests per route, unit tests for extraction/matching/
      scoring, `Http::fake` + `Storage::fake` + `Queue::fake`. Extend the existing workflow
      with coverage and a MySQL service container.
- [ ] **Phase 9 — Polish.** Form requests, API resources, N+1 audit, caching, README.
      Optional Forge/DO deploy with a fake extractor driver (llama-server can't follow).

**Rough sizing:** Phases 1–2 are a weekend and teach ~60% of daily Laravel. 3–4 are the meaty
ones. 5–8 are where it stops looking like a tutorial project.

---

## 8. Environment runbook

| Thing | Detail |
|---|---|
| App URL | `http://invoiceai.test` (Herd) |
| MySQL | DBngin, `127.0.0.1:3306`, user `root`, **empty password**, database `invoiceai` |
| mysql client | `C:\Users\dusan\AppData\Local\com.tinyapp.DBngin\Binaries\mysql\9.7.1\bin\mysql.exe` |
| MySQL Workbench | Just a GUI client onto that same DBngin server — not a second server |
| llama-server | `http://localhost:8080/v1`, model id `Negentropy-claude-opus-4.7-4B-Q4_K_M.gguf`, capabilities `completion` + `multimodal` |
| Mailpit | Bundled with Herd, **not currently running** — start it in Phase 6 |
| Dev loop | `composer dev` (serve + queue listener + vite concurrently) |
| Tests | `php artisan test --compact`; full gate is `composer ci:check` (Pint + PHPStan + PHPUnit) |
| Test DB | `phpunit.xml` uses **in-memory SQLite**, while dev uses MySQL |

**Phase 0 verification results:** 9 tables migrated into MySQL; 29 tests pass, 1 skipped;
`invoiceai.test` returns HTTP 200; llama-server responds on `/v1/models`.

---

## 9. Open questions

- **Which model is actually canonical?** `ai_models/` holds
  `Negentropy-claude-opus-4.7-4B-Q4_K_M.gguf` and llama-server is serving it, but the source
  README documents Qwen3.5-4B and the Python code hardcodes `model="qwen"` with fuzzy name
  resolution. The rebuild should read the model id from config explicitly rather than
  guessing. Model-accuracy testing across the full PDF set was still pending when the Flask
  project wrapped.
- **CI PHP version** is pinned to 8.3 in `.github/workflows/tests.yml` while local is 8.4.
  Worth aligning.
- **CI runs on SQLite** via `composer setup`. Phase 8 should add MySQL to match the employer's
  setup and catch MySQL-specific behavior.
- ~~**Sample data** location~~ — **resolved 2026-07-28**, see the decisions table in §3.
