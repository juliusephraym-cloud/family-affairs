<x-layouts.app :title="$version->title">
    <x-page-header :eyebrow="__('Katiba — toleo :v', ['v' => $version->version_label])" :title="$version->title" />
    <div class="grid gap-6 lg:grid-cols-3">
        <article class="card card-pad lg:col-span-2"><div class="prose-family whitespace-pre-line">{{ $version->body }}</div></article>
        <div class="space-y-6">
            <x-card :title="__('Hali')">
                <x-status :value="$version->status" />
                @if ($version->change_summary)<p class="mt-3 text-sm">{{ $version->change_summary }}</p>@endif
                @if ($version->approver)<p class="mt-3 text-sm text-muted">{{ __('Imeidhinishwa na :n, inatumika tangu :d', ['n' => $version->approver->displayName(), 'd' => $version->effective_from?->format('d/m/Y')]) }}</p>@endif
                @if ($previous)<p class="mt-3 text-sm"><a href="{{ route('governance.version', $previous) }}" class="font-bold text-forest-700 hover:underline">{{ __('Ona toleo lililotangulia (:v)', ['v' => $previous->version_label]) }}</a></p>@endif
            </x-card>
            @if ($version->status === 'draft')
                @can('governance.confirm_rules')
                    <x-card :title="__('Idhinisha toleo hili')">
                        <form method="POST" action="{{ route('governance.version.approve', $version) }}" class="space-y-3">@csrf
                            <x-field name="effective_from" type="date" :label="__('Linaanza kutumika')" required />
                            <x-field name="decision_id" type="select" :label="__('Uamuzi wa kikao uliolipitisha')" :options="\App\Models\Decision::latest()->limit(30)->pluck('title', 'id')->all()" required />
                            <button class="btn-primary w-full">{{ __('Idhinisha') }}</button>
                        </form>
                    </x-card>
                @endcan
            @endif
        </div>
    </div>
</x-layouts.app>
