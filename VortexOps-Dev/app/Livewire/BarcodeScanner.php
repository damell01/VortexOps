<?php

namespace App\Livewire;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Reusable barcode scanner component using ZXing.
 * Works on iOS (Safari) and Android (Chrome/Edge).
 * Supports continuous scanning, flashlight, and manual input fallback.
 *
 * Usage:
 *   <livewire:barcode-scanner on-scan="handleBarcodeScan" />
 *
 * Then in your page component:
 *   public function handleBarcodeScan($barcode) { ... }
 */
class BarcodeScanner extends Component
{
    #[Validate('string|required')]
    public string $manualInput = '';

    public bool $showCamera = false;
    public bool $flashlightOn = false;
    public string $cameraStatus = 'Ready to scan';
    public string $statusType = 'info'; // info, success, error, warning

    public function toggleCamera(): void
    {
        $this->showCamera = !$this->showCamera;
        if (!$this->showCamera) {
            $this->flashlightOn = false;
            $this->cameraStatus = 'Camera closed';
            $this->statusType = 'info';
        } else {
            $this->cameraStatus = 'Opening camera...';
        }
    }

    public function toggleFlashlight(): void
    {
        $this->flashlightOn = !$this->flashlightOn;
        $this->dispatch('toggleFlashlight', state: $this->flashlightOn);
    }

    public function submitManual(): void
    {
        $barcode = trim($this->manualInput);
        if (empty($barcode)) {
            $this->addError('manualInput', 'Please enter a barcode');
            return;
        }

        $this->dispatch('barcodeScanned', barcode: $barcode);
        $this->manualInput = '';
    }

    public function render()
    {
        return view('livewire.barcode-scanner');
    }
}
