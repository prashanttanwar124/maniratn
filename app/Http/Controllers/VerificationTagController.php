<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\InvoiceItem;
use App\Models\VerificationTag;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class VerificationTagController extends Controller
{
    public function index(): Response
    {
        $publicBaseUrl = $this->publicBaseUrl();

        $eligibleItems = InvoiceItem::query()
            ->with(['invoice.customer', 'product.category', 'silverProduct.category', 'orderItem'])
            ->whereHas('invoice', fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->whereDoesntHave('verificationTags', fn ($query) => $query->where('is_active', true))
            ->latest()
            ->take(200)
            ->get()
            ->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'invoice_number' => $item->invoice?->invoice_number,
                'customer_name' => $item->invoice?->customer?->name ?? 'Walk-in',
                'description' => $item->description ?: $this->itemLabel($item),
                'weight' => (float) $item->weight,
                'purity' => $item->purity,
                'sold_on' => $item->invoice?->date,
                'label' => trim(implode(' | ', array_filter([
                    $item->invoice?->invoice_number,
                    $item->invoice?->customer?->name ?? 'Walk-in',
                    $item->description ?: $this->itemLabel($item),
                ]))),
            ])
            ->values();

        $tags = VerificationTag::query()
            ->with(['invoiceItem.invoice.customer', 'product.category', 'silverProduct.category', 'customer', 'createdBy', 'writtenBy'])
            ->latest()
            ->take(100)
            ->get()
            ->map(fn (VerificationTag $tag) => [
                'id' => $tag->id,
                'token' => $tag->token,
                'public_url' => $tag->public_url,
                'status' => $tag->status,
                'is_active' => (bool) $tag->is_active,
                'written_at' => optional($tag->written_at)?->toDateTimeString(),
                'locked_at' => optional($tag->locked_at)?->toDateTimeString(),
                'verified_count' => (int) $tag->verified_count,
                'notes' => $tag->notes,
                'invoice_number' => $tag->invoiceItem?->invoice?->invoice_number,
                'customer_name' => $tag->customer?->name ?? $tag->invoiceItem?->invoice?->customer?->name ?? 'Walk-in',
                'item_name' => $tag->invoiceItem?->description ?: ($tag->invoiceItem ? $this->itemLabel($tag->invoiceItem) : null),
                'created_by' => $tag->createdBy?->name,
                'written_by' => $tag->writtenBy?->name,
            ])
            ->values();

        return Inertia::render('verification-tags/Index', [
            'publicBaseUrl' => $publicBaseUrl,
            'eligibleItems' => $eligibleItems,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_item_id' => ['required', 'exists:invoice_items,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoiceItem = InvoiceItem::query()
            ->with(['invoice.customer'])
            ->findOrFail($validated['invoice_item_id']);

        if ($invoiceItem->invoice?->status === 'CANCELLED') {
            return back()->withErrors([
                'invoice_item_id' => 'Cancelled invoice items cannot receive verification tags.',
            ]);
        }

        $existingActiveTag = VerificationTag::query()
            ->where('invoice_item_id', $invoiceItem->id)
            ->where('is_active', true)
            ->exists();

        if ($existingActiveTag) {
            return back()->withErrors([
                'invoice_item_id' => 'This sold item already has an active verification tag.',
            ]);
        }

        $token = VerificationTag::generateToken();

        VerificationTag::query()->create([
            'token' => $token,
            'tag_type' => 'NFC',
            'status' => 'PENDING',
            'is_active' => true,
            'invoice_item_id' => $invoiceItem->id,
            'product_id' => $invoiceItem->product_id,
            'silver_product_id' => $invoiceItem->silver_product_id,
            'customer_id' => $invoiceItem->invoice?->customer_id,
            'created_by' => Auth::id(),
            'public_url' => $this->buildPublicUrl($token),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Verification tag created successfully.');
    }

    public function markWritten(VerificationTag $verificationTag): RedirectResponse
    {
        $verificationTag->update([
            'status' => 'WRITTEN',
            'written_by' => Auth::id(),
            'written_at' => $verificationTag->written_at ?: now(),
        ]);

        return back()->with('success', 'Tag marked as written.');
    }

    public function writer(VerificationTag $verificationTag): View
    {
        $verificationTag->load(['invoiceItem.invoice.customer', 'product.category', 'silverProduct.category']);

        return view('verification-tags.writer', [
            'verificationTag' => $verificationTag,
            'itemName' => $verificationTag->invoiceItem?->description ?: ($verificationTag->invoiceItem ? $this->itemLabel($verificationTag->invoiceItem) : 'Sold Item'),
            'customerName' => $verificationTag->customer?->name ?? $verificationTag->invoiceItem?->invoice?->customer?->name ?? 'Walk-in',
            'invoiceNumber' => $verificationTag->invoiceItem?->invoice?->invoice_number,
        ]);
    }

    public function qr(VerificationTag $verificationTag): HttpResponse
    {
        $renderer = new ImageRenderer(
            new RendererStyle(280),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg = $writer->writeString($verificationTag->public_url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function confirmWritten(Request $request, VerificationTag $verificationTag)
    {
        $validated = $request->validate([
            'nfc_uid' => ['nullable', 'string', 'max:255'],
        ]);

        $verificationTag->update([
            'status' => $verificationTag->locked_at ? 'LOCKED' : 'WRITTEN',
            'written_by' => Auth::id(),
            'written_at' => $verificationTag->written_at ?: now(),
            'nfc_uid' => $validated['nfc_uid'] ?? $verificationTag->nfc_uid,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag written successfully.',
        ]);
    }

    public function lock(VerificationTag $verificationTag): RedirectResponse
    {
        $verificationTag->update([
            'status' => 'LOCKED',
            'written_by' => $verificationTag->written_by ?: Auth::id(),
            'written_at' => $verificationTag->written_at ?: now(),
            'locked_at' => $verificationTag->locked_at ?: now(),
        ]);

        return back()->with('success', 'Tag marked as locked.');
    }

    public function deactivate(VerificationTag $verificationTag): RedirectResponse
    {
        $verificationTag->update([
            'is_active' => false,
            'status' => 'DISABLED',
        ]);

        return back()->with('success', 'Tag deactivated.');
    }

    private function publicBaseUrl(): string
    {
        $website = trim((string) BusinessSetting::query()->value('website'));

        return rtrim($website !== '' ? $website : config('app.url'), '/');
    }

    private function buildPublicUrl(string $token): string
    {
        return $this->publicBaseUrl() . '/verify/' . $token;
    }

    private function itemLabel(InvoiceItem $item): string
    {
        return $item->product?->name
            ?? $item->silverProduct?->name
            ?? $item->orderItem?->item_name
            ?? 'Sold Item';
    }
}
