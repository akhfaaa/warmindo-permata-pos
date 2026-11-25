const ESC = 0x1b;
const GS = 0x1d;

// Daftar service UUID standar printer thermal Bluetooth (BLE)
const THERMAL_SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb',
    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    '0000ff00-0000-1000-8000-00805f9b34fb',
];

class ThermalPrinterManager {
    constructor() {
        this.device = null;
        this.characteristic = null;
        this.paperWidth = Number(localStorage.getItem('warmindo_paper_width')) || 58; // 58mm atau 80mm
        this.savedPrinterName = localStorage.getItem('warmindo_printer_name') || '';
    }

    isSupported() {
        return typeof navigator !== 'undefined' && 'bluetooth' in navigator;
    }

    isConnected() {
        return Boolean(this.device && this.device.gatt && this.device.gatt.connected && this.characteristic);
    }

    setPaperWidth(mm) {
        this.paperWidth = mm === 80 ? 80 : 58;
        localStorage.setItem('warmindo_paper_width', String(this.paperWidth));
    }

    getCharsPerLine() {
        return this.paperWidth === 80 ? 48 : 32;
    }

    notifyStatus() {
        window.dispatchEvent(
            new CustomEvent('warmindo:printer-updated', {
                detail: {
                    connected: this.isConnected(),
                    name: this.device?.name || this.savedPrinterName || '',
                },
            })
        );
    }

    async connect() {
        if (!this.isSupported()) {
            throw new Error(
                'Browser ini tidak mendukung Web Bluetooth. Gunakan Google Chrome / Edge di Android atau Windows.'
            );
        }

        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: THERMAL_SERVICES,
        });

        device.addEventListener('gattserverdisconnected', () => {
            this.characteristic = null;
            this.notifyStatus();
        });

        const server = await device.gatt.connect();
        let writeChar = null;

        for (const serviceUuid of THERMAL_SERVICES) {
            try {
                const service = await server.getPrimaryService(serviceUuid);
                const chars = await service.getCharacteristics();
                writeChar = chars.find(
                    (c) => c.properties.write || c.properties.writeWithoutResponse
                );
                if (writeChar) break;
            } catch {
                // Coba UUID service berikutnya
            }
        }

        if (!writeChar) {
            // Fallback: pindai semua primary services yang tersedia di printer
            const allServices = await server.getPrimaryServices();
            for (const srv of allServices) {
                const chars = await srv.getCharacteristics();
                writeChar = chars.find(
                    (c) => c.properties.write || c.properties.writeWithoutResponse
                );
                if (writeChar) break;
            }
        }

        if (!writeChar) {
            throw new Error('Karakteristik cetak ESC/POS tidak ditemukan pada perangkat Bluetooth ini.');
        }

        this.device = device;
        this.characteristic = writeChar;
        this.savedPrinterName = device.name || 'Thermal Printer';
        localStorage.setItem('warmindo_printer_name', this.savedPrinterName);
        this.notifyStatus();

        return this.savedPrinterName;
    }

    disconnect() {
        if (this.device && this.device.gatt && this.device.gatt.connected) {
            this.device.gatt.disconnect();
        }
        this.characteristic = null;
        this.notifyStatus();
    }

    formatRow(left, right, width) {
        const l = String(left || '');
        const r = String(right || '');
        const spaceCount = width - l.length - r.length;
        if (spaceCount >= 1) {
            return l + ' '.repeat(spaceCount) + r;
        }
        const maxLeft = Math.max(4, width - r.length - 1);
        return l.slice(0, maxLeft) + ' ' + r;
    }

    formatRp(val) {
        return 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
    }

    buildEscPosPayload(receipt) {
        const width = this.getCharsPerLine();
        const divider = '-'.repeat(width) + '\n';
        const encoder = new TextEncoder();
        const chunks = [];

        const pushBytes = (...bytes) => chunks.push(new Uint8Array(bytes));
        const pushText = (text) => chunks.push(encoder.encode(text));

        // Init printer
        pushBytes(ESC, 0x40);

        // Header Center + Bold
        pushBytes(ESC, 0x61, 0x01);
        pushBytes(ESC, 0x45, 0x01);
        pushText('WARMINDO PERMATA\n');
        pushBytes(ESC, 0x45, 0x00);
        pushText('Catatan Pesanan & Bukti Bayar\n');
        pushText(divider);

        // Metadata Left Align
        pushBytes(ESC, 0x61, 0x00);
        pushText(`Nota  : ${receipt.invoice_number}\n`);
        pushText(`Waktu : ${receipt.created_at}\n`);
        pushText(`Kasir : ${receipt.cashier_name} (${receipt.order_type})\n`);
        pushText(divider);

        // Items
        for (const item of receipt.items || []) {
            const title = `${item.quantity}x ${item.name}`;
            const priceStr = this.formatRp(item.line_total);
            pushText(this.formatRow(title, priceStr, width) + '\n');

            if (item.modifiers && item.modifiers.length > 0) {
                pushText(`   + ${item.modifiers.join(', ')}\n`);
            }
            if (item.notes) {
                pushText(`   * ${item.notes}\n`);
            }
        }

        pushText(divider);

        // Totals Bold
        pushBytes(ESC, 0x45, 0x01);
        pushText(
            this.formatRow(
                `TOTAL (${receipt.payment_method})`,
                this.formatRp(receipt.total_amount),
                width
            ) + '\n'
        );
        pushBytes(ESC, 0x45, 0x00);

        pushText(this.formatRow('BAYAR', this.formatRp(receipt.cash_received), width) + '\n');
        pushText(this.formatRow('KEMBALI', this.formatRp(receipt.change_amount), width) + '\n');

        pushText(divider);
        pushBytes(ESC, 0x61, 0x01);
        pushText('Terima kasih selamat menikmati!\n\n\n');

        // Feed & Cut
        pushBytes(GS, 0x56, 0x42, 0x00);

        // Gabungkan semua Uint8Array
        const totalLength = chunks.reduce((sum, arr) => sum + arr.length, 0);
        const merged = new Uint8Array(totalLength);
        let offset = 0;
        for (const arr of chunks) {
            merged.set(arr, offset);
            offset += arr.length;
        }
        return merged;
    }

    async sendBytes(data) {
        if (!this.isConnected()) {
            throw new Error('Printer Bluetooth belum terhubung.');
        }

        // Kirim bertahap per 100 byte agar buffer BLE printer thermal tidak penuh
        const CHUNK_SIZE = 100;
        for (let i = 0; i < data.length; i += CHUNK_SIZE) {
            const slice = data.slice(i, i + CHUNK_SIZE);
            if (this.characteristic.properties.writeWithoutResponse) {
                await this.characteristic.writeValueWithoutResponse(slice);
            } else {
                await this.characteristic.writeValue(slice);
            }
            await new Promise((r) => setTimeout(r, 20));
        }
    }

    async printReceipt(receipt) {
        const bytes = this.buildEscPosPayload(receipt);
        await this.sendBytes(bytes);
    }

    async printTest() {
        await this.printReceipt({
            invoice_number: 'TEST-PRINT-001',
            created_at: new Date().toLocaleString('id-ID'),
            cashier_name: 'Tes Printer',
            order_type: 'DINE_IN',
            payment_method: 'TUNAI',
            total_amount: 12000,
            cash_received: 20000,
            change_amount: 8000,
            items: [
                {
                    name: 'Indomie Goreng Original',
                    quantity: 1,
                    line_total: 12000,
                    modifiers: ['Telur Ceplok'],
                    notes: 'Pedas sedang',
                },
            ],
        });
    }
}

window.WarmindoPrinter = new ThermalPrinterManager();