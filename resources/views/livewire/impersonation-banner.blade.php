<div>
    @if ($this->impersonator)
        <div class="flex items-center justify-center gap-3 bg-warning-500 px-4 py-2 text-sm font-medium text-white">
            <span>
                Connecté en tant que {{ Auth::user()->name }} — connecté par {{ $this->impersonator->name }}
            </span>
            <button
                type="button"
                wire:click="stop"
                class="rounded-md bg-white/20 px-2 py-1 text-xs font-semibold hover:bg-white/30"
            >
                Revenir à mon compte
            </button>
        </div>
    @endif
</div>
