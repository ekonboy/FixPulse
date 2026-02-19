<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">FixPulse Dashboard</h2>
    </x-slot>

    @php
        $scanBadgeClass = static function (int $count): string {
            return match (true) {
                $count >= 10 => 'border-lime-300/60 bg-lime-300/15 text-lime-200',
                $count >= 3 => 'border-sky-300/60 bg-sky-300/15 text-sky-200',
                $count >= 1 => 'border-amber-300/60 bg-amber-300/15 text-amber-200',
                default => 'border-slate-500/50 bg-slate-700/20 text-slate-300',
            };
        };
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Crear Proyecto</h3>
                <form method="POST" action="{{ route('projects.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input id="name" name="name" type="text" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="base_url" class="block text-sm font-medium text-gray-700">Base URL</label>
                        <input id="base_url" name="base_url" type="url" placeholder="https://example.com" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('base_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-3 mt-3 flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center rounded-md border border-black bg-white px-6 py-3 text-base font-semibold text-black shadow-sm hover:bg-gray-100">
                            Crear Proyecto
                        </button>
                        <span class="text-xs text-gray-500">Pulsa Enter o este botón para guardar.</span>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Proyectos</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Nombre</th>
                                <th class="px-3 py-2 text-left">Base URL</th>
                                <th class="px-3 py-2 text-left">Scans</th>
                                <th class="px-3 py-2 text-left">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($projects as $project)
                                <tr>
                                    <td class="px-3 py-2">{{ $project->name }}</td>
                                    <td class="px-3 py-2">{{ $project->base_url }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex min-w-10 items-center justify-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $scanBadgeClass((int) $project->scans_count) }}">
                                            {{ $project->scans_count }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('projects.show', $project) }}" class="inline-flex items-center gap-1 rounded-md border border-indigo-400/40 bg-indigo-500/15 px-3 py-1.5 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/25 hover:text-indigo-100">
                                            Ver proyecto
                                            <span aria-hidden="true">></span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-gray-500">Sin proyectos todav�a.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
