<?php

namespace App\Services\Extraction;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a PDF's text layer and asks a local llama-server to structure it.
 *
 * The model does the hard part; this class only handles the plumbing around it — turning
 * pages into a prompt, making the call, and turning the answer into an InvoiceData.
 */
class LlmInvoiceExtractor implements InvoiceExtractor
{
    /**
     * Two collaborators and four settings. The settings arrive as constructor arguments
     * rather than being read from `config()` inside the methods, so a test can build this
     * class with a two-second timeout without editing a config file.
     */
    public function __construct(
        private readonly PdfTextExtractor $pdfTextExtractor,
        private readonly JsonResponseParser $jsonResponseParser,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
        private readonly float $temperature,
        private readonly int $maxTokens,
        private readonly int $maxPayloadChars,
    ) {}

    public function extract(string $path): InvoiceData
    {
        $document = $this->pdfTextExtractor->extract($path);

        if (! $document['has_text']) {
            // One of the 39 sample PDFs lands here. Until the vision driver exists there is
            // nothing useful to send — an empty prompt would just make the model hallucinate.
            throw new RuntimeException(
                "PDF has no text layer and cannot be read by the LLM driver: {$path}"
            );
        }

        $raw = $this->callModel($this->buildUserPrompt($document));

        return InvoiceData::fromArray($this->jsonResponseParser->decode($raw));
    }

    /**
     * @param  array{file: string, has_text: bool, pages: list<array{page_number: int, text: string}>}  $document
     */
    private function buildUserPrompt(array $document): string
    {
        $payload = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        if (strlen($payload) > $this->maxPayloadChars) {
            $payload = substr($payload, 0, $this->maxPayloadChars)."\n\n[TRUNCATED FOR CONTEXT]";
        }

        return "Extract the invoice fields from this PDF text.\n\n{$payload}\n\n/no_think";
    }

    /**
     * Returns the model's answer as an unparsed string. Everything that can go wrong with the
     * transport is handled here; everything that can go wrong with the *content* is the
     * JsonResponseParser's problem.
     */
    private function callModel(string $userPrompt): string
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->asJson()
                ->post('/chat/completions', [
                    'model' => $this->model,
                    'temperature' => $this->temperature,
                    'max_tokens' => $this->maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "Cannot reach llama-server at {$this->baseUrl}. Is it running?", previous: $e
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                "llama-server returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException(
                'llama-server returned an empty completion. Check that the model id '.
                "'{$this->model}' matches what is actually loaded."
            );
        }

        return $content;
    }

    /**
     * Ported from SYSTEM_PROMPT in the source app's ai_extractor.py, with two additions.
     *
     * `part_number` is new: the source never asked for it, then required it for deterministic
     * matching, which is why that path never fired (REBUILD_PLAN §2). `vendor_name` is new
     * because the rebuild resolves the vendor from the invoice rather than from the upload form.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are an invoice data extraction assistant. You will be given JSON containing the
        text of each page of a PDF invoice. Extract the invoice fields and return ONLY valid
        JSON with no additional text, no markdown fences, and no explanation.

        ## Output Format
        {
          "invoice_number": "string or null — the seller's invoice ID",
          "date": "YYYY-MM-DD or null — invoice issue date",
          "po_number": "string or null — the BUYER'S purchase order number",
          "vendor_name": "string or null — the company that issued this invoice",
          "subtotal": number or null,
          "tax": number or null,
          "total": number or null,
          "line_items": [
            {
              "part_number": "string or null — the seller's item/part/SKU number",
              "description": "string — part name or description",
              "quantity": number,
              "unit_price": number,
              "total": number
            }
          ]
        }

        ## Field Notes
        - po_number: This is the BUYER'S internal purchase order number. All valid PO numbers
          in this system are EXACTLY 7 digits. This is a hard rule — if a number is not 7 digits,
          it is NOT a PO number regardless of its label.
          Scan the ENTIRE document before deciding. If you find multiple 7-digit candidates,
          prefer the one with the highest-priority label below.
          Known labels for the buyer's PO number, from most to least common:
            "P.O. Number", "Customer PO", "Customer PO No.", "Customer PO #", "Your Order",
            "Your Reference", "PO Number", "Purchase Order Number", "Purchase Order No.",
            "Customer P.O.", "Customer Order Number", "Customer PO Nbr", "PO No", "PO No.",
            "Purchase Order", "Customer Purchase Order", "Buyer Reference", "PO", "PO #",
            "P.O. No.", "Your Order No."
          Do NOT extract numbers under these labels — they are the SELLER'S internal references,
          not the buyer's PO: "Order Number", "Order No.", "Order #", "SO", "SO Number",
          "Sales Order", "Sales Order No.", "Invoice No.", "Invoice Number", "Reference No."
        - invoice_number: Look for "Invoice #", "Invoice No.", "Invoice Number", "Inv #".
        - vendor_name: The seller's company name, usually in the letterhead at the top of page 1.
          Not the "bill to" or "ship to" company — that is the buyer.
        - part_number: The seller's own item code for the line, printed in a column labelled
          "Part", "Part No.", "Item", "Item No.", "Product Code", "SKU", "Model" or similar.
          Use null if the line has only a description.
        - date: Use the invoice issue date (not payment due date). Convert to YYYY-MM-DD format.
        - All monetary values must be numbers (not strings). Remove currency symbols and commas.
        - If a field is not found in the document, use null.
        - If there are no line items, return an empty list for "line_items".
        - Do not invent or infer values that are not clearly present in the text.
        PROMPT;
}
