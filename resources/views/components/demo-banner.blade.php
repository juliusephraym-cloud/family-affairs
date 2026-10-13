@php($hasDemo = cache()->remember('has-demo-data', 60, fn () => \App\Models\Member::where('is_demo', true)->exists()))
@if ($hasDemo)
    <div class="border-b border-gold-200 bg-gold-50 px-4 py-2 text-center text-sm text-gold-700 lg:px-10">
        <strong>DEMO</strong> · {{ __('Taarifa unazoziona ni za mfano tu (majina ya kubuni). Si kumbukumbu halisi za familia.') }}
    </div>
@endif
