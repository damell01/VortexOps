import { BrowserMultiFormatReader } from '@zxing/browser';

let codeReader = null;
let currentStream = null;
let isScanning = false;
let lastScannedCode = null;
let scanTimeout = null;
let torchController = null;

export async function initScanner() {
    const scanner = document.getElementById('zxing-scanner');
    if (!scanner) return;

    try {
        codeReader = new BrowserMultiFormatReader();
        updateStatus('Starting camera...', 'info');

        const videoElement = document.createElement('video');
        videoElement.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        scanner.appendChild(videoElement);

        currentStream = await codeReader.decodeFromVideoDevice(
            undefined,
            videoElement,
            (result, err) => {
                if (result) {
                    const barcode = result.getText();

                    if (barcode !== lastScannedCode) {
                        lastScannedCode = barcode;
                        flashScreen('success');
                        playBeep('success');
                        vibrate([50, 30, 50]);
                        updateStatus(`Scanned: ${barcode}`, 'success');

                        if (window.livewire) {
                            window.livewire.dispatch('barcodeScanned', { barcode: barcode });
                        }

                        if (scanTimeout) clearTimeout(scanTimeout);
                        scanTimeout = setTimeout(() => {
                            lastScannedCode = null;
                            updateStatus('Ready to scan', 'info');
                        }, 1000);
                    }
                } else if (err) {
                    // Silently ignore scan errors
                }
            }
        );

        isScanning = true;
        updateStatus('Camera active - scanning...', 'success');

        const track = currentStream.getVideoTracks()[0];
        if (track && 'getCapabilities' in track) {
            const capabilities = track.getCapabilities();
            if (capabilities.torch) {
                torchController = track;
            }
        }

    } catch (error) {
        console.error('Scanner error:', error);

        let errorMsg = 'Camera failed to start';
        if (error.name === 'NotAllowedError') {
            errorMsg = 'Camera permission denied - use manual input';
        } else if (error.name === 'NotFoundError') {
            errorMsg = 'Camera not found on device';
        } else if (error.message?.includes('ZXing')) {
            errorMsg = 'Barcode library error - try refreshing';
        }

        updateStatus(errorMsg, 'error');
    }
}

export function stopScanner() {
    isScanning = false;
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }

    const scanner = document.getElementById('zxing-scanner');
    if (scanner) {
        scanner.innerHTML = '';
    }

    if (torchController) {
        torchController.applyConstraints({ advanced: [{ torch: false }] });
        torchController = null;
    }

    updateStatus('Camera closed', 'info');
}

export function toggleFlashlight(state) {
    if (!torchController) {
        console.warn('Flashlight not available on this device');
        return;
    }

    torchController.applyConstraints({ advanced: [{ torch: state }] }).catch(err => {
        console.warn('Flashlight toggle failed:', err);
    });
}

function updateStatus(message, type = 'info') {
    const status = document.getElementById('scanner-status');
    if (status) {
        status.textContent = message;
        status.className = `text-sm font-medium ${
            type === 'error' ? 'text-red-400' :
            type === 'success' ? 'text-green-400' :
            type === 'warning' ? 'text-yellow-400' :
            'text-gray-300'
        }`;
    }
}

function flashScreen(type) {
    const scanner = document.getElementById('zxing-scanner');
    if (!scanner) return;

    const color = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    scanner.classList.add(color);
    setTimeout(() => scanner.classList.remove(color), 200);
}

function playBeep(type) {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.connect(gain);
        gain.connect(audioContext.destination);

        if (type === 'success') {
            oscillator.frequency.value = 800;
            gain.gain.setValueAtTime(0.1, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } else {
            oscillator.frequency.value = 400;
            gain.gain.setValueAtTime(0.05, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        }
    } catch (e) {
        console.warn('Beep failed:', e);
    }
}

function vibrate(pattern) {
    if (navigator.vibrate) {
        navigator.vibrate(pattern);
    }
}

// Initialize scanner when component is loaded
document.addEventListener('DOMContentLoaded', () => {
    const scanner = document.getElementById('zxing-scanner');
    if (scanner) {
        initScanner();
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    stopScanner();
});
