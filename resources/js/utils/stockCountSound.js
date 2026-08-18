/**
 * Audio feedback utility for Gold Stock Count using Web Audio API.
 * Provides synthesized sounds for inventory scanning without external audio files.
 */

let audioCtx = null;
const STORAGE_KEY = 'gold_stock_count_sound_enabled';

/**
 * Initialize or resume AudioContext safely on user interaction
 */
export function getAudioContext() {
    if (typeof window === 'undefined') return null;

    if (!audioCtx) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }

    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume().catch(() => {});
    }

    return audioCtx;
}

export function isSoundEnabled() {
    if (typeof window === 'undefined') return true;
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === null ? true : stored === 'true';
}

export function setSoundEnabled(enabled) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(STORAGE_KEY, enabled ? 'true' : 'false');
}

/**
 * Play a tone with specified frequency, type, start time, duration, and volume.
 */
function playTone({ ctx, freq, type = 'sine', startTime = 0, duration = 0.1, gainValue = 0.15 }) {
    if (!ctx) return;

    try {
        if (ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }

        const now = ctx.currentTime;
        const start = now + (startTime || 0);
        const end = start + duration;

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, start);

        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(gainValue, start + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, end);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(start);
        osc.stop(end + 0.05);
    } catch {
        // Ignore audio playback errors if context is blocked
    }
}

/**
 * 1. SUCCESS / ITEM COUNTED SOUND:
 * Crisp, pleasant high double-chime (880Hz -> 1320Hz).
 */
export function playSuccessSound() {
    if (!isSoundEnabled()) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    playTone({ ctx, freq: 880, type: 'sine', startTime: 0, duration: 0.07, gainValue: 0.18 });
    playTone({ ctx, freq: 1320, type: 'sine', startTime: 0.08, duration: 0.12, gainValue: 0.15 });
}

/**
 * 2. ALREADY ADDED / DUPLICATE ITEM SOUND:
 * Distinct medium-pitched warning double-tone (587Hz -> 440Hz).
 */
export function playAlreadyAddedSound() {
    if (!isSoundEnabled()) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    playTone({ ctx, freq: 587.33, type: 'triangle', startTime: 0, duration: 0.1, gainValue: 0.22 });
    playTone({ ctx, freq: 440, type: 'triangle', startTime: 0.12, duration: 0.16, gainValue: 0.2 });
}

/**
 * 3. MISSING / NOT FOUND / INVALID SOUND:
 * Low error buzz / descending dissonant alert (220Hz -> 146Hz).
 */
export function playNotFoundSound() {
    if (!isSoundEnabled()) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    playTone({ ctx, freq: 220, type: 'sawtooth', startTime: 0, duration: 0.12, gainValue: 0.18 });
    playTone({ ctx, freq: 146.83, type: 'sawtooth', startTime: 0.13, duration: 0.2, gainValue: 0.2 });
}

/**
 * 4. ALL STOCK COUNTED / COMPLETED CELEBRATION FANFARE:
 * Ascending arpeggio (C5 -> E5 -> G5 -> C6).
 */
export function playCompleteSound() {
    if (!isSoundEnabled()) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    playTone({ ctx, freq: 523.25, type: 'sine', startTime: 0, duration: 0.1, gainValue: 0.16 });
    playTone({ ctx, freq: 659.25, type: 'sine', startTime: 0.1, duration: 0.1, gainValue: 0.16 });
    playTone({ ctx, freq: 783.99, type: 'sine', startTime: 0.2, duration: 0.12, gainValue: 0.16 });
    playTone({ ctx, freq: 1046.50, type: 'sine', startTime: 0.32, duration: 0.25, gainValue: 0.2 });
}

// Auto-warm AudioContext on first user interaction in browser
if (typeof window !== 'undefined') {
    const unlockAudio = () => {
        getAudioContext();
        window.removeEventListener('click', unlockAudio);
        window.removeEventListener('keydown', unlockAudio);
        window.removeEventListener('touchstart', unlockAudio);
    };
    window.addEventListener('click', unlockAudio, { once: true, passive: true });
    window.addEventListener('keydown', unlockAudio, { once: true, passive: true });
    window.addEventListener('touchstart', unlockAudio, { once: true, passive: true });
}
