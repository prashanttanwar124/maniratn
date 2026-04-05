<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Desktop NFC Writer</title>
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
            max-width: 42rem;
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
        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 1rem;
        }
        .section {
            border: 1px solid var(--border);
            padding: .9rem;
            background: #fafafa;
        }
        .full { grid-column: 1 / -1; }
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        button {
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
        button.success {
            background: #fff;
            color: var(--success);
            border-color: var(--success);
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
        @media (max-width: 768px) {
            .grid, .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="card">
            <h1>Desktop NFC Writer</h1>
            <p class="muted">Use this page on the PC where the USB NFC reader/writer is connected. The page will send the URL to your local NFC helper, then mark the tag as written or locked in the POS.</p>

            <div class="grid">
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
                    <div class="label">Local NFC Helper</div>
                    <div class="value" id="helper-url">http://127.0.0.1:8090</div>
                </div>

                <div class="section full">
                    <div class="label">URL To Write</div>
                    <div class="value">{{ $verificationTag->public_url }}</div>
                </div>
            </div>

            <div class="actions">
                <button id="write-btn" type="button">Write Tag</button>
                <button id="lock-btn" class="success" type="button">Lock Tag</button>
                <button id="copy-btn" class="secondary" type="button">Copy Link</button>
            </div>

            <div id="status" class="status warn">
                Waiting for the local NFC helper. Keep the reader connected to this PC and place a blank tag on the reader only when prompted.
            </div>

            <div class="tips">
                <strong>Expected helper API:</strong>
                <ul>
                    <li><code>GET http://127.0.0.1:8090/health</code></li>
                    <li><code>POST http://127.0.0.1:8090/nfc/write-url</code> with <code>{ url, token }</code></li>
                    <li><code>POST http://127.0.0.1:8090/nfc/lock</code> with <code>{ token }</code></li>
                </ul>
                <strong>Recommended flow:</strong>
                <ul>
                    <li>Click <em>Write Tag</em> first and verify the written link by reading the tag.</li>
                    <li>Only after the link is confirmed should you click <em>Lock Tag</em>.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const helperBaseUrl = 'http://127.0.0.1:8090';
        const publicUrl = @json($verificationTag->public_url);
        const token = @json($verificationTag->token);
        const confirmWrittenUrl = @json(route('verification-tags.confirm-written', $verificationTag));
        const confirmLockedUrl = @json(route('verification-tags.confirm-locked', $verificationTag));
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const statusEl = document.getElementById('status');
        const writeBtn = document.getElementById('write-btn');
        const lockBtn = document.getElementById('lock-btn');
        const copyBtn = document.getElementById('copy-btn');

        const setStatus = (message, tone = 'warn') => {
            statusEl.className = `status ${tone}`;
            statusEl.textContent = message;
        };

        const postApp = async (url, payload = {}) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('POS update failed.');
            }

            return response.json();
        };

        const parseHelperError = async (response) => {
            const fallback = `Local NFC helper request failed (${response.status} ${response.statusText || 'Error'}).`;

            try {
                const contentType = response.headers.get('content-type') || '';

                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    const message = data?.message || data?.error || data?.detail;

                    return message ? `${fallback} ${message}` : fallback;
                }

                const text = (await response.text()).trim();

                return text ? `${fallback} ${text}` : fallback;
            } catch (error) {
                return fallback;
            }
        };

        const postHelper = async (path, payload = {}) => {
            const response = await fetch(`${helperBaseUrl}${path}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(await parseHelperError(response));
            }

            return response.json();
        };

        const checkHelper = async () => {
            try {
                const response = await fetch(`${helperBaseUrl}/health`);
                if (!response.ok) {
                    throw new Error('Health check failed');
                }

                setStatus('Local NFC helper is connected. You can place a blank NTAG215 tag on the reader and start writing.', 'success');
            } catch (error) {
                setStatus('Local NFC helper not detected on this PC. Start the NFC helper first, then refresh this page.', 'warn');
            }
        };

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(publicUrl);
                setStatus('Link copied. You can still use vendor software as a fallback if needed.', 'success');
            } catch (error) {
                setStatus('Could not copy the link on this PC.', 'error');
            }
        });

        writeBtn.addEventListener('click', async () => {
            writeBtn.disabled = true;
            setStatus('Writing URL to the NFC tag. Keep the tag steady on the reader...', 'warn');

            try {
                const helperResult = await postHelper('/nfc/write-url', {
                    url: publicUrl,
                    token,
                });

                await postApp(confirmWrittenUrl, {
                    nfc_uid: helperResult.nfc_uid || null,
                });

                setStatus('Tag written successfully. Read it once to verify, then lock it when ready.', 'success');
            } catch (error) {
                setStatus(error?.message || 'Failed to write the tag using the local helper.', 'error');
            } finally {
                writeBtn.disabled = false;
            }
        });

        lockBtn.addEventListener('click', async () => {
            lockBtn.disabled = true;
            setStatus('Locking the NFC tag as read-only. Keep the same tag on the reader...', 'warn');

            try {
                const helperResult = await postHelper('/nfc/lock', {
                    token,
                });

                await postApp(confirmLockedUrl, {
                    nfc_uid: helperResult.nfc_uid || null,
                });

                setStatus('Tag locked successfully. This tag should now be read-only.', 'success');
            } catch (error) {
                setStatus(error?.message || 'Failed to lock the tag using the local helper.', 'error');
            } finally {
                lockBtn.disabled = false;
            }
        });

        checkHelper();
    </script>
</body>

</html>
