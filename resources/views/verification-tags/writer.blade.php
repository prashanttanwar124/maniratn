<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Write NFC Tag</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f6f7;
            --card: #ffffff;
            --border: #d9d9de;
            --text: #111827;
            --muted: #6b7280;
            --primary: #111827;
            --success: #166534;
            --warn: #92400e;
            --error: #b91c1c;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .card {
            width: 100%;
            max-width: 34rem;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 1.25rem;
        }
        h1 {
            margin: 0 0 .35rem;
            font-size: 1.5rem;
            line-height: 1.2;
        }
        p { margin: 0; }
        .muted { color: var(--muted); }
        .section {
            margin-top: 1rem;
            border: 1px solid var(--border);
            padding: .9rem;
            background: #fafafa;
        }
        .label {
            font-size: .75rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: .35rem;
        }
        .value {
            font-size: .95rem;
            word-break: break-all;
            font-weight: 600;
        }
        .actions {
            margin-top: 1rem;
            display: grid;
            gap: .75rem;
        }
        button {
            width: 100%;
            border: 1px solid var(--primary);
            background: var(--primary);
            color: #fff;
            padding: .95rem 1rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button.secondary {
            background: #fff;
            color: var(--text);
        }
        button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }
        .status {
            margin-top: 1rem;
            border: 1px solid var(--border);
            padding: .9rem;
            font-size: .95rem;
            line-height: 1.5;
        }
        .status.success { color: var(--success); border-color: #bbf7d0; background: #f0fdf4; }
        .status.warn { color: var(--warn); border-color: #fde68a; background: #fffbeb; }
        .status.error { color: var(--error); border-color: #fecaca; background: #fef2f2; }
        .tips {
            margin-top: 1rem;
            font-size: .92rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .tips ul {
            margin: .4rem 0 0 1rem;
            padding: 0;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="card">
            <h1>Write NFC Tag</h1>
            <p class="muted">Open this page on Android Chrome. Tap the blank NFC tag to the back of the phone when prompted.</p>

            <div class="section">
                <div class="label">Sold Item</div>
                <div class="value">{{ $itemName }}</div>
            </div>

            <div class="section">
                <div class="label">Invoice / Customer</div>
                <div class="value">{{ $invoiceNumber ?? '—' }} | {{ $customerName }}</div>
            </div>

            <div class="section">
                <div class="label">Token</div>
                <div class="value">{{ $verificationTag->token }}</div>
            </div>

            <div class="section">
                <div class="label">URL To Write</div>
                <div class="value" id="tag-url">{{ $verificationTag->public_url }}</div>
            </div>

            <div class="actions">
                <button id="write-btn" type="button">Write NFC Tag</button>
                <button id="copy-btn" class="secondary" type="button">Copy Link</button>
            </div>

            <div id="status" class="status warn">
                Ready to write. This first version writes the URL only. Locking stays manual after testing.
            </div>

            <div class="tips">
                <strong>How to use:</strong>
                <ul>
                    <li>Use Android Chrome with NFC turned on.</li>
                    <li>Tap <em>Write NFC Tag</em>, then place the card behind the phone.</li>
                    <li>After success, this page will mark the tag as written in the POS.</li>
                    <li>Then you can lock the tag separately after you confirm the written link is correct.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const publicUrl = @json($verificationTag->public_url);
        const confirmUrl = @json(route('verification-tags.confirm-written', $verificationTag));
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const statusEl = document.getElementById('status');
        const writeBtn = document.getElementById('write-btn');
        const copyBtn = document.getElementById('copy-btn');

        const setStatus = (message, tone = 'warn') => {
            statusEl.className = `status ${tone}`;
            statusEl.textContent = message;
        };

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(publicUrl);
                setStatus('Link copied. You can paste it into any fallback NFC writing app if needed.', 'success');
            } catch (error) {
                setStatus('Could not copy the link on this device.', 'error');
            }
        });

        writeBtn.addEventListener('click', async () => {
            if (!('NDEFReader' in window)) {
                setStatus('This phone/browser does not support Web NFC. Use Android Chrome or copy the link into an NFC app.', 'error');
                return;
            }

            writeBtn.disabled = true;
            setStatus('Hold the NFC tag near the back of the phone now...', 'warn');

            try {
                const ndef = new NDEFReader();
                await ndef.write({
                    records: [{ recordType: 'url', data: publicUrl }],
                });

                await fetch(confirmUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });

                setStatus('NFC tag written successfully. You can return to POS and mark it locked after testing.', 'success');
            } catch (error) {
                setStatus(error?.message || 'Failed to write the NFC tag on this device.', 'error');
            } finally {
                writeBtn.disabled = false;
            }
        });
    </script>
</body>

</html>
