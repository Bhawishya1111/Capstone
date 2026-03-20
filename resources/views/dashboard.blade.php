<x-app-layout>
    <div class="p-6">

        <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

        <!-- ADD WEBSITE / SCAN -->
        <form action="{{ route('admin.scan') }}" method="POST" class="mb-6">
            @csrf
            <input type="text" name="url" placeholder="Enter website URL"
                class="border p-2 w-1/2 rounded" required>

            <button type="submit"
                class="bg-blue-500 text-white px-4 py-2 rounded">
                Start Scan
            </button>
        </form>

        <!-- WEBSITES -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-2">Websites</h2>

            <table class="w-full border">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2">URL</th>
                        <th class="p-2">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($websites as $website)
                        <tr class="border-t">
                            <td class="p-2">{{ $website->url }}</td>
                            <td class="p-2">{{ $website->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- SCANS -->
        <div>
            <h2 class="text-xl font-semibold mb-2">Scans</h2>

            <table class="w-full border">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-2">URL</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Progress</th>
                        <th class="p-2">Phase</th>
                        <th class="p-2">Report</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scans as $scan)
                        <tr class="border-t">
                            <td class="p-2">{{ $scan->url }}</td>
                            <td class="p-2">{{ $scan->status }}</td>
                            <td class="p-2">{{ $scan->progress }}%</td>
                            <td class="p-2">{{ $scan->phase }}</td>
                            <td class="p-2">
                                @if($scan->report_path)
                                    <a href="{{ asset($scan->report_path) }}" target="_blank"
                                        class="text-blue-500">
                                        View
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>