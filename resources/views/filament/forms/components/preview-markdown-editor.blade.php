@php
    $id = $getId();
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $key = $getKey();
    $label = $getLabel();
    $statePath = $getStatePath();
    $fileAttachmentsMaxSize = $getFileAttachmentsMaxSize();
    $fileAttachmentsAcceptedFileTypes = $getFileAttachmentsAcceptedFileTypes();
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    @if ($isDisabled())
        <div id="{{ $id }}" class="fi-fo-markdown-editor fi-disabled fi-prose">
            {!! str($getState())->markdown($getCommonMarkOptions(), $getCommonMarkExtensions())->sanitizeHtml() !!}
        </div>
    @else
        <x-filament::input.wrapper
            :valid="! $errors->has($statePath)"
            :attributes="
                \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
                    ->class(['fi-fo-markdown-editor'])
            "
        >
            <div
                aria-labelledby="{{ $id }}-label"
                id="{{ $id }}"
                role="group"
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('markdown-editor', 'filament/forms') }}"
                x-data="markdownEditorFormComponent({
                    canAttachFiles: @js($hasFileAttachments()),
                    isLiveDebounced: @js($isLiveDebounced()),
                    isLiveOnBlur: @js($isLiveOnBlur()),
                    label: @js($label),
                    liveDebounce: @js($getNormalizedLiveDebounce()),
                    maxHeight: @js($getMaxHeight()),
                    minHeight: @js($getMinHeight()),
                    placeholder: @js($getPlaceholder()),
                    state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')", isOptimisticallyLive: false) }},
                    toolbarButtons: @js($getToolbarButtons()),
                    translations: @js(__('filament-forms::components.markdown_editor')),
                    setUpUsing: (component) => {
                        const toolbar = component.$root.querySelector('.editor-toolbar')
                        const toggle = document.createElement('button')

                        toggle.type = 'button'
                        toggle.className = 'code'
                        toggle.title = 'Afficher le code Markdown'
                        toggle.setAttribute('aria-label', 'Afficher le code Markdown')
                        toggle.setAttribute('aria-pressed', 'false')

                        toggle.addEventListener('click', () => {
                            const sourceIsVisible = toggle.getAttribute('aria-pressed') === 'true'

                            component.editor.togglePreview()
                            toggle.setAttribute('aria-pressed', sourceIsVisible ? 'false' : 'true')
                            toggle.title = sourceIsVisible ? 'Afficher le code Markdown' : 'Afficher le compte rendu mis en forme'
                            toggle.setAttribute('aria-label', toggle.title)
                            toggle.classList.toggle('active', ! sourceIsVisible)
                        })

                        toolbar.prepend(toggle)
                        component.editor.togglePreview()

                        const preview = component.editor.codemirror
                            .getWrapperElement()
                            .querySelector('.editor-preview-full')

                        preview?.classList.add('fi-prose')

                        if (preview) {
                            preview.style.backgroundColor = document.documentElement.classList.contains('dark')
                                ? 'rgb(24 24 27)'
                                : 'rgb(255 255 255)'

                            const renderPreview = () => {
                                const rendered = component.editor.options.previewRender(
                                    component.editor.value(),
                                    preview,
                                )

                                if (rendered !== null && rendered !== undefined) {
                                    preview.innerHTML = rendered
                                }
                            }

                            // Livewire can hydrate the entangled value during the
                            // preview opening transition. Render once the preview
                            // is active, then keep it synchronized with the state.
                            requestAnimationFrame(() => {
                                component.editor.value(component.state ?? '')
                                renderPreview()
                            })

                            component.$watch('state', () => requestAnimationFrame(renderPreview))
                        }
                    },
                    uploadFileAttachmentUsing: async (file, onSuccess, onError) => {
                        const acceptedTypes = @js($fileAttachmentsAcceptedFileTypes)

                        if (acceptedTypes && ! acceptedTypes.includes(file.type)) {
                            return onError(@js($fileAttachmentsAcceptedFileTypes ? __('filament-forms::components.markdown_editor.file_attachments_accepted_file_types_message', ['values' => implode(', ', $fileAttachmentsAcceptedFileTypes)]) : null))
                        }

                        const maxSize = @js($fileAttachmentsMaxSize)

                        if (maxSize && file.size > +maxSize * 1024) {
                            return onError(@js($fileAttachmentsMaxSize ? trans_choice('filament-forms::components.markdown_editor.file_attachments_max_size_message', $fileAttachmentsMaxSize, ['max' => $fileAttachmentsMaxSize]) : null))
                        }

                        $wire.upload(`componentFileAttachments.{{ $statePath }}`, file, () => {
                            $wire
                                .callSchemaComponentMethod('{{ $key }}', 'saveUploadedFileAttachmentAndGetUrl')
                                .then((url) => url ? onSuccess(url) : onError())
                        })
                    },
                })"
                wire:ignore
                {{ $getExtraAlpineAttributeBag() }}
            >
                <textarea x-ref="editor" x-cloak></textarea>
            </div>
        </x-filament::input.wrapper>
    @endif
</x-dynamic-component>
