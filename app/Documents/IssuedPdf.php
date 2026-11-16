<?php

namespace App\Documents;

use App\Documents\Models\IssuedDocument;

/** ملف PDF صادر مع سجله. */
final class IssuedPdf
{
    public function __construct(public readonly IssuedDocument $document, public readonly string $bytes) {}
}
