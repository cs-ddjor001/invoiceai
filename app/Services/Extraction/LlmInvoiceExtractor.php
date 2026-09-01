<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Http;

class LlmInvoiceExtractor
{
    public function extract(string $text): string
    {
        $response = Http::timeout(config('extraction.timeout'))->post(config('extraction.base_url').'/chat/completions', [
            'model' => config('extraction.model'),
            'temperature' => config('extraction.temperature'),
            'max_tokens' => config('extraction.max_tokens'),
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $text],
            ],
            'chat_template_kwargs' => ['enable_thinking' => config('extraction.enable_thinking')],
        ]);

        $content = $response->json('choices.0.message.content');

        return $content;
    }

    private const SYSTEM_PROMPT = <<<'PROMPT'

    You are an invoice data extraction assistant. You will be given text extracted
    from a PDF. Extract the invoice fields and return ONLY valid JSON with no additional text,
    no markdown fences, and no explanation.

    ## Output Format
    Return ONLY valid JSON with no additional text or markdown:
    {
    "invoice_number": "string (required — the seller's invoice ID)",
    "date": "YYYY-MM-DD — invoice issue date",
    "po_number": "string or null — the BUYER'S purchase order number referenced on this invoice",
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
    If a document has multiple valid 7-digit PO numbers under different labels, return the
    one with the highest-priority label from the list above.
    - invoice_number: Look for "Invoice #", "Invoice No.", "Invoice Number", "Inv #".
    - date: Use the invoice issue date (not payment due date). Convert to YYYY-MM-DD format.
    - All monetary values must be numbers (not strings). Remove currency symbols and commas.
    - If a field is not found in the document, use null.
    - If there are no line items, return an empty list for "line_items".
    - Do not invent or infer values that are not clearly present in the text.
    - part_number: This is the part number for the line item. It should match the seller's part number exactly.
    Look for the seller's item code, from a column labelled Part, Item No., Product Code, SKU or Model; null if the line only has a description.

    PROMPT;
}
