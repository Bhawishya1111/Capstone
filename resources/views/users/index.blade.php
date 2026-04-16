<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Users') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Role management') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Set each user role as Admin, Analyst, or User.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Name') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Email') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Role') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-600">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-6 py-3 font-medium text-gray-900">
                                        {{ $user->name }}
                                        @if (auth()->id() === $user->id)
                                            <span class="ml-2 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-800">{{ __('You') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-gray-700">{{ $user->email }}</td>
                                    <td class="px-6 py-3">
                                        <form method="POST" action="{{ route('users.update-role', $user) }}" class="flex items-center gap-2 justify-end sm:justify-start">
                                            @csrf
                                            @method('PATCH')
                                            <select
                                                name="role"
                                                class="rounded-md border-gray-300 text-sm"
                                                @disabled(auth()->id() === $user->id)
                                            >
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                                @endforeach
                                            </select>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                            <button
                                                type="submit"
                                                class="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-medium text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                                                @disabled(auth()->id() === $user->id)
                                            >
                                                {{ __('Save') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
