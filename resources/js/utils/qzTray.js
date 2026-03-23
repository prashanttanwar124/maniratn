const QZ_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js';
const DEFAULT_PRINTER_NAME = 'TSC TE244';

let scriptLoadingPromise = null;

const sanitizeText = (value, maxLength = 24) =>
    String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9 .|&()-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, maxLength);

const formatWeight = (value) => `${Number(value || 0).toFixed(3)}g`;

const buildTsplForItem = (item) => {
    const name = sanitizeText(item.name, 24);
    const specs = sanitizeText(`${formatWeight(item.weight)} | ${item.purity || ''}`, 24);
    const code = sanitizeText(item.code, 32);

    return [
        'SIZE 100 mm,15 mm',
        'GAP 2 mm,0 mm',
        'DIRECTION 1',
        'REFERENCE 0,0',
        'OFFSET 0 mm',
        'CLS',
        `TEXT 16,18,"0",0,1,1,"${name}"`,
        `TEXT 16,42,"0",0,1,1,"${specs}"`,
        `BARCODE 300,12,"128",42,0,0,2,2,"${code}"`,
        `TEXT 300,60,"0",0,1,1,"${code}"`,
        'PRINT 1,1',
    ].join('\n');
};

const ensureSecurityPromises = (qz) => {
    if (!qz?.security) return;

    if (typeof qz.security.setCertificatePromise === 'function') {
        qz.security.setCertificatePromise((resolve) => resolve());
    }

    if (typeof qz.security.setSignaturePromise === 'function') {
        qz.security.setSignaturePromise(() => (resolve) => resolve());
    }
};

export const loadQzTray = async () => {
    if (window.qz) {
        ensureSecurityPromises(window.qz);
        return window.qz;
    }

    if (!scriptLoadingPromise) {
        scriptLoadingPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector(`script[src="${QZ_SCRIPT_URL}"]`);
            if (existingScript) {
                existingScript.addEventListener('load', () => resolve(window.qz));
                existingScript.addEventListener('error', () => reject(new Error('Unable to load QZ Tray script.')));
                return;
            }

            const script = document.createElement('script');
            script.src = QZ_SCRIPT_URL;
            script.async = true;
            script.onload = () => resolve(window.qz);
            script.onerror = () => reject(new Error('Unable to load QZ Tray script.'));
            document.head.appendChild(script);
        });
    }

    const qz = await scriptLoadingPromise;

    if (!qz) {
        throw new Error('QZ Tray script loaded, but QZ Tray is not available.');
    }

    ensureSecurityPromises(qz);

    return qz;
};

export const printLabelsViaQz = async (items, printerName = DEFAULT_PRINTER_NAME) => {
    if (!Array.isArray(items) || items.length === 0) {
        throw new Error('No labels selected for QZ print.');
    }

    const qz = await loadQzTray();

    if (!qz.websocket.isActive()) {
        await qz.websocket.connect();
    }

    let printer = null;

    try {
        printer = await qz.printers.find(printerName);
    } catch {
        const printers = await qz.printers.find();
        printer = printers.find((name) => String(name).toLowerCase().includes('tsc')) || printers[0];
    }

    if (!printer) {
        throw new Error('No QZ printer was found. Check that TSC TE244 is installed.');
    }

    const config = qz.configs.create(printer, {
        encoding: 'UTF-8',
    });

    const payload = items.map((item) => `${buildTsplForItem(item)}\n`).join('\n');

    await qz.print(config, [
        {
            type: 'raw',
            format: 'command',
            flavor: 'plain',
            data: payload,
        },
    ]);

    return printer;
};
