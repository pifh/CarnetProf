<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\MarkdownEditor;

class PreviewMarkdownEditor extends MarkdownEditor
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.preview-markdown-editor';

    /**
     * The source toggle is supplied by the custom view, so the code block
     * formatter is deliberately omitted to avoid two ambiguous "Code" tools.
     *
     * @return array<string | array<string>>
     */
    public function getDefaultToolbarButtons(): array
    {
        return [
            ['bold', 'italic', 'strike', 'link'],
            ['heading'],
            ['blockquote', 'bulletList', 'orderedList'],
            [
                'table',
                ...($this->hasFileAttachments(default: true) ? ['attachFiles'] : []),
            ],
            ['undo', 'redo'],
        ];
    }
}
