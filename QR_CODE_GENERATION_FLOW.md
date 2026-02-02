# QR Code Generation Flow for Email Confirmations

## Generation Location

**File:** [app/Services/EventService.php](app/Services/EventService.php)  
**Method:** `generateQRCodeForUrl()` (Lines 335-346)

```php
protected function generateQRCodeForUrl(string $url): string
{
    $fileName = 'qr-codes/' . Str::uuid() . '.svg';

    $qrSvg = QrCode::format('svg')
        ->size(400)
        ->margin(2)
        ->encoding('UTF-8')
        ->generate($url);

    Storage::disk('public')->put($fileName, $qrSvg);

    return Storage::url($fileName);
}
```

---

## Complete QR Code Generation Chain

### 1. Table Reservation Email QR Code

```
Payment Confirmed
    ↓
EventService::confirmPayment() [Line 155]
    ↓
sendTableReservationConfirmationEmail() [Line 402]
    ↓
generateReservationQRCode($reservation) [Line 424]
    ↓
$url = route('events.show', ['event' => $reservation->event_id]) . '?reservation=' . $reservation->qr_code
    ↓
generateQRCodeForUrl($url) [Line 335]
    ↓
QrCode::format('svg')->generate($url)
    ↓
Stored in: public/storage/qr-codes/{uuid}.svg
    ↓
Returns: Storage::url($fileName) → /storage/qr-codes/{uuid}.svg
    ↓
Passed to EventTableReservationConfirmation mail → Attached as PNG
    ↓
Guest receives email with QR code attachment
```

### 2. Ticket Purchase Email QR Code

```
Payment Confirmed
    ↓
EventService::confirmPayment() [Line 155]
    ↓
sendTicketConfirmationEmail() [Line 386]
    ↓
generateTicketQRCode($ticket) [Line 419]
    ↓
$url = route('events.show', ['event' => $ticket->event_id]) . '?ticket=' . $ticket->qr_code
    ↓
generateQRCodeForUrl($url) [Line 335]
    ↓
QrCode::format('svg')->generate($url)
    ↓
Stored in: public/storage/qr-codes/{uuid}.svg
    ↓
Returns: Storage::url($fileName) → /storage/qr-codes/{uuid}.svg
    ↓
Passed to EventTicketPurchaseConfirmation mail → Attached as PNG
    ↓
Guest receives email with QR code attachment
```

---

## Code References

### QR Code Generation Method [Line 335-346]
**Location:** [app/Services/EventService.php#L335](app/Services/EventService.php#L335)

```php
protected function generateQRCodeForUrl(string $url): string
{
    // Generate unique filename with UUID
    $fileName = 'qr-codes/' . Str::uuid() . '.svg';

    // Generate QR Code as SVG
    $qrSvg = QrCode::format('svg')     // SVG format
        ->size(400)                    // 400x400px
        ->margin(2)                    // 2px margin
        ->encoding('UTF-8')            // UTF-8 encoding
        ->generate($url);              // Generate from URL

    // Store in public disk
    Storage::disk('public')->put($fileName, $qrSvg);

    // Return public URL
    return Storage::url($fileName);
}
```

### Ticket QR Code Generation [Line 419-421]
**Location:** [app/Services/EventService.php#L419](app/Services/EventService.php#L419)

```php
protected function generateTicketQRCode(EventTicket $ticket): string
{
    $url = route('events.show', ['event' => $ticket->event_id]) . '?ticket=' . $ticket->qr_code;
    return $this->generateQRCodeForUrl($url);
}
```

**QR Code Contains:**
```
https://hotelms.local/events/{event_id}?ticket={ticket->qr_code}
```

### Table Reservation QR Code Generation [Line 424-427]
**Location:** [app/Services/EventService.php#L424](app/Services/EventService.php#L424)

```php
protected function generateReservationQRCode(EventTableReservation $reservation): string
{
    $url = route('events.show', ['event' => $reservation->event_id]) . '?reservation=' . $reservation->qr_code;
    return $this->generateQRCodeForUrl($url);
}
```

**QR Code Contains:**
```
https://hotelms.local/events/{event_id}?reservation={reservation->qr_code}
```

### Email Sending - Ticket [Line 386-397]
**Location:** [app/Services/EventService.php#L386](app/Services/EventService.php#L386)

```php
protected function sendTicketConfirmationEmail(EventTicket $ticket): void
{
    try {
        $event = $ticket->event;
        $qrCodeUrl = $this->generateTicketQRCode($ticket);  // ← Generated here
        
        Mail::to($ticket->guest_email)
            ->cc(config('mail.from.address'))
            ->send(new EventTicketPurchaseConfirmation($ticket, $event, $qrCodeUrl));
            
        logger()->info("Ticket confirmation email sent to: {$ticket->guest_email}");
    } catch (\Exception $e) {
        logger()->error("Failed to send ticket confirmation email: " . $e->getMessage());
    }
}
```

### Email Sending - Table Reservation [Line 402-413]
**Location:** [app/Services/EventService.php#L402](app/Services/EventService.php#L402)

```php
protected function sendTableReservationConfirmationEmail(EventTableReservation $reservation): void
{
    try {
        $event = $reservation->event;
        $qrCodeUrl = $this->generateReservationQRCode($reservation);  // ← Generated here
        
        Mail::to($reservation->guest_email)
            ->cc(config('mail.from.address'))
            ->send(new EventTableReservationConfirmation($reservation, $event, $qrCodeUrl));
            
        logger()->info("Table reservation confirmation email sent to: {$reservation->guest_email}");
    } catch (\Exception $e) {
        logger()->error("Failed to send table reservation confirmation email: " . $e->getMessage());
    }
}
```

---

## QR Code Technical Specifications

| Property | Value | Details |
|----------|-------|---------|
| **Format** | SVG | Scalable Vector Graphics |
| **Size** | 400×400px | Standard for email |
| **Margin** | 2px | Quiet zone around code |
| **Encoding** | UTF-8 | Unicode support |
| **Storage Location** | `public/storage/qr-codes/` | Public disk in Laravel |
| **Filename** | `{uuid}.svg` | Unique UUID for each QR code |
| **Library** | simplesoftwareio/simple-qrcode | Bacon QR Code renderer |
| **Email Attachment** | PNG format | Converted to PNG when attached |

---

## Storage & File Structure

### Directory Structure
```
public/
└── storage/
    └── qr-codes/
        ├── {uuid-1}.svg      ← Ticket QR code
        ├── {uuid-2}.svg      ← Reservation QR code
        ├── {uuid-3}.svg      ← Event QR code
        └── ...
```

### Generated Filename Example
```
qr-codes/550e8400-e29b-41d4-a716-446655440000.svg
```

### Public URL Example
```
https://hotelms.local/storage/qr-codes/550e8400-e29b-41d4-a716-446655440000.svg
```

---

## Mail Attachment Configuration

### EventTicketPurchaseConfirmation [Line 46-51]
**Location:** [app/Mail/EventTicketPurchaseConfirmation.php#L46](app/Mail/EventTicketPurchaseConfirmation.php#L46)

```php
public function attachments(): array
{
    return [
        Attachment::fromStorageDisk('public', $this->qrCodeUrl)
            ->as('ticket-qr-code.png')      // Attachment filename
            ->withMime('image/png'),        // MIME type
    ];
}
```

### EventTableReservationConfirmation [Line 48-53]
**Location:** [app/Mail/EventTableReservationConfirmation.php#L48](app/Mail/EventTableReservationConfirmation.php#L48)

```php
public function attachments(): array
{
    return [
        Attachment::fromStorageDisk('public', $this->qrCodeUrl)
            ->as('reservation-qr-code.png')  // Attachment filename
            ->withMime('image/png'),        // MIME type
    ];
}
```

---

## QR Code Scanning Flow

When a guest scans the QR code at the event venue:

```
QR Code (PNG/SVG)
    ↓
Contains URL: https://hotelms.local/events/{id}?ticket={qr_code}
             or https://hotelms.local/events/{id}?reservation={qr_code}
    ↓
Guest scans with phone camera
    ↓
Redirects to event show page
    ↓
App receives ticket/reservation QR code as query parameter
    ↓
EventService::checkInTicket() or checkInTableReservation()
    ↓
Updates: checked_in_at = now()
    ↓
Guest checked in ✅
```

---

## Dependencies

### QR Code Library
- **Package:** `simplesoftwareio/simple-qrcode`
- **Renderer:** Bacon QR Code (BaconQrCode)
- **Facade:** `SimpleSoftwareIO\QrCode\Facades\QrCode`
- **Used in:** `generateQRCodeForUrl()` method

### Storage
- **Disk:** `public` (Laravel storage)
- **Driver:** File system
- **URL Helper:** `Storage::url()` generates public URLs

### Mail
- **Attachments:** Converted from SVG to PNG by mail system
- **Encoding:** Base64 (standard email attachment encoding)
- **Size:** Typically < 5KB per QR code

---

## Summary

**QR codes for guest emails are generated in:**
- **File:** `app/Services/EventService.php`
- **Method:** `generateQRCodeForUrl()` (Lines 335-346)
- **Timing:** Immediately when payment is confirmed
- **Storage:** `public/storage/qr-codes/{uuid}.svg`
- **Delivery:** Attached to confirmation email as PNG
- **Contents:** URL link to event check-in page with unique QR code identifier
