<?php

$confirmation = file_get_contents(__DIR__ . '/../payment/confirmation.php');

function assertPdfRendererContains(string $source, string $needle, string $message): void
{
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }
}

assertPdfRendererContains($confirmation, 'width:           captureWidth', 'receipt capture uses the full element width');
assertPdfRendererContains($confirmation, 'height:          captureHeight', 'receipt capture uses the full element height');
assertPdfRendererContains($confirmation, 'windowWidth:     captureWidth', 'receipt capture viewport matches the full element width');
assertPdfRendererContains($confirmation, 'windowHeight:    captureHeight', 'receipt capture viewport matches the full element height');
assertPdfRendererContains($confirmation, "format: 'a4'", 'receipt PDF uses a standard A4 page');
assertPdfRendererContains($confirmation, 'Math.min(maxW / canvas.width, maxH / canvas.height)', 'receipt is scaled to fit both A4 dimensions');
assertPdfRendererContains($confirmation, '(pdfW - imageW) / 2', 'receipt is horizontally centered on the page');
assertPdfRendererContains($confirmation, '(pdfH - imageH) / 2', 'receipt is vertically centered on the page');
assertPdfRendererContains($confirmation, "pdf.addImage(imgData, 'JPEG', imageX, imageY, imageW, imageH)", 'receipt uses the centered image placement');
assertPdfRendererContains($confirmation, "captureAndSave('receiptSection', 'RTTC2026_Payment_Receipt_", 'receipt download uses the shared capture renderer');
assertPdfRendererContains($confirmation, ".pdf', 8, true)", 'receipt download enables A4 rendering');

echo "payment_receipt_pdf_test passed\n";
