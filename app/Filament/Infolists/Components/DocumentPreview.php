<?php

namespace App\Filament\Infolists\Components;

use App\Enums\Accounting\DocumentType;
use Filament\Infolists\Components\Grid;

class DocumentPreview extends Grid
{
    protected string $view = 'filament.infolists.components.document-preview';

    protected DocumentType $documentType = DocumentType::Invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->columnSpan(3);
    }

    public function type(DocumentType | string $type): static
    {

        if (is_string($type)) {
            $type = DocumentType::from($type);
        }

        $this->documentType = $type;

        return $this;
    }

    public function getType(): DocumentType
    {
        return $this->documentType;
    }
}
