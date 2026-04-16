{{--
    WAVe-Lite dashboard — authorized scanning only; see project README / UI warnings.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('WAVe-Lite') }}
                </h2>
                <p class="text-sm text-gray-500 font-normal">{{ __('Security scanning operations dashboard') }}</p>
            </div>
            <div class="text-xs text-gray-500">
                {{ __('Last scan:') }}
                <span class="font-medium text-gray-700">
                    {{ $stats['last_scan_at'] ? \Illuminate\Support\Carbon::parse($stats['last_scan_at'])->format('Y-m-d H:i') : '—' }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Legal / ethics — scans only on authorized targets --}}
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <strong class="font-semibold">{{ __('Authorization required.') }}</strong>
                {{ __('Run scanners only on sites, APIs, or servers you own or have explicit written permission to test. Unauthorized scanning may violate law.') }}
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-500">{{ __('Total scans') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $stats['total_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-indigo-200 bg-indigo-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-indigo-800">{{ __('Queued') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-indigo-950">{{ $stats['queued_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-blue-200 bg-blue-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-blue-800">{{ __('Running') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-blue-950">{{ $stats['running_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-green-200 bg-green-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-green-800">{{ __('Completed') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-green-950">{{ $stats['completed_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-red-200 bg-red-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-red-800">{{ __('Failed') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-red-950">{{ $stats['failed_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-emerald-800">{{ __('Success rate') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-emerald-950">
                            {{ $stats['success_rate'] !== null ? $stats['success_rate'].'%' : '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm xl:col-span-2">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Start scan') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Enter any authorized http(s) URL to scan.') }}</p>
                    <form method="POST" action="{{ route('scans.start') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="url" :value="__('Target URL')" />
                            <x-text-input id="url" class="block mt-1 w-full" type="url" name="url" :value="old('url')" required placeholder="https://example.com" />
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>
                        <x-primary-button>{{ __('Start scan') }}</x-primary-button>
                    </form>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Scan health') }}</h3>
                        <form method="POST" action="{{ route('scans.destroy-all') }}" onsubmit="return confirm('Delete all scans visible to you? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                {{ __('Clear all scans') }}
                            </button>
                        </form>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Current distribution and runtime behavior.') }}</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Queued') }}</dt><dd class="font-medium text-indigo-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_QUEUED] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Running') }}</dt><dd class="font-medium text-blue-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_RUNNING] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Completed') }}</dt><dd class="font-medium text-green-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_COMPLETED] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Failed') }}</dt><dd class="font-medium text-red-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_FAILED] }}</dd></div>
                        <div class="pt-2 mt-2 border-t border-gray-100 flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('Avg duration') }}</dt>
                            <dd class="font-medium text-gray-800">
                                @if ($stats['avg_duration_seconds'] > 0)
                                    {{ gmdate('H:i:s', (int) $stats['avg_duration_seconds']) }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-xs text-gray-500">{{ __('Tip: Keep a queue worker running to avoid stalled scans.') }}</p>
                </div>
            </div>

            {{-- Scans table --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Recent scans') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Target') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Scanner steps') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Findings') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600 w-48">{{ __('Progress') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Phase') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Started') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Duration') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-600">{{ __('') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($scans as $scan)
                                @php
                                    $statusStyles = match ($scan->status) {
                                        \App\Models\Scan::STATUS_RUNNING => 'bg-blue-100 text-blue-800',
                                        \App\Models\Scan::STATUS_COMPLETED => 'bg-green-100 text-green-800',
                                        \App\Models\Scan::STATUS_FAILED => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                    $poll = $scan->status === \App\Models\Scan::STATUS_RUNNING || $scan->status === \App\Models\Scan::STATUS_QUEUED;
                                    $phase = strtolower((string) $scan->phase);
                                    $isTerminal = in_array($scan->status, [\App\Models\Scan::STATUS_COMPLETED, \App\Models\Scan::STATUS_FAILED], true);

                                    $stepState = function (string $step) use ($phase, $isTerminal) {
                                        if ($isTerminal) {
                                            return 'done';
                                        }

                                        return match ($step) {
                                            'zap' => str_contains($phase, 'zap') ? 'running' : (str_contains($phase, 'nikto') || str_contains($phase, 'ai') || str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            'nikto' => str_contains($phase, 'nikto') ? 'running' : (str_contains($phase, 'ai') || str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            'ai' => str_contains($phase, 'ai') ? 'running' : (str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            default => 'pending',
                                        };
                                    };

                                    $stepClass = function (string $state) {
                                        return match ($state) {
                                            'running' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'done' => 'bg-green-100 text-green-800 border-green-200',
                                            default => 'bg-gray-100 text-gray-600 border-gray-200',
                                        };
                                    };
                                @endphp
                                <tr
                                    data-scan-row
                                    @if ($poll) data-poll-url="{{ route('scans.progress', $scan) }}" @endif
                                >
                                    <td class="px-6 py-3 font-medium text-gray-900 break-all max-w-xs">
                                        <span class="block">{{ \Illuminate\Support\Str::limit($scan->url, 56) }}</span>
                                        <span class="text-xs text-gray-500">#{{ $scan->id }}</span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span data-scan-status-badge class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusStyles }}">
                                            {{ $scan->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex flex-wrap gap-1.5 text-[11px]">
                                            @php $zapState = $stepState('zap'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($zapState) }}">ZAP</span>
                                            @php $niktoState = $stepState('nikto'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($niktoState) }}">Nikto</span>
                                            @php $aiState = $stepState('ai'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($aiState) }}">AI</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-xs text-gray-700 space-y-0.5">
                                            <p class="font-medium text-gray-900">{{ (int) $scan->findings_count }} {{ __('total') }}</p>
                                            <p>ZAP: {{ (int) $scan->zap_findings_count }} · Nikto: {{ (int) $scan->nikto_findings_count }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                            <div
                                                data-scan-progress-bar
                                                class="h-full rounded-full bg-indigo-600 transition-all duration-300"
                                                style="width: {{ (int) $scan->progress }}%"
                                            ></div>
                                        </div>
                                        <span class="mt-1 inline-block text-xs text-gray-500">{{ (int) $scan->progress }}%</span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-700" data-scan-phase>{{ $scan->phase }}</td>
                                    <td class="px-6 py-3 text-gray-600 whitespace-nowrap" title="{{ $scan->created_at->format('Y-m-d H:i:s') }}">{{ $scan->created_at->diffForHumans() }}</td>
                                    <td class="px-6 py-3 text-gray-600 whitespace-nowrap">
                                        @if ($scan->completed_at)
                                            {{ $scan->created_at->diffAsCarbonInterval($scan->completed_at)->cascade()->forHumans(['short' => true]) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('scans.report', $scan) }}" class="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700 hover:bg-indigo-100 font-medium">
                                            {{ __('Report') }}
                                        </a>
                                        <form method="POST" action="{{ route('scans.destroy', $scan) }}" onsubmit="return confirm('Delete this scan? This cannot be undone.');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-red-700 hover:bg-red-100 font-medium">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('No scans yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @vite(['resources/js/dashboard.js'])
    @endpush
</x-app-layout>
