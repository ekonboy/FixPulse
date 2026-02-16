<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Lanzar Scan</h3>
                <form method="POST" action="{{ route('projects.scans.store', $project) }}" class="mt-4 grid gap-4 md:grid-cols-3">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700" for="target_url">Target URL</label>
                        <input id="target_url" name="target_url" type="url" required placeholder="{{ $project->base_url }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        @error('target_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="device">Device</label>
                        <select id="device" name="device" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="mobile">Mobile</option>
                            <option value="desktop">Desktop</option>
                        </select>
                        @error('device')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="rounded-md border border-black bg-white px-4 py-2 text-sm font-semibold text-black hover:bg-gray-100">
                            Ejecutar Scan
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Historial de Scans</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">ID</th>
                                <th class="px-3 py-2 text-left">URL</th>
                                <th class="px-3 py-2 text-left">Device</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Creado</th>
                                <th class="px-3 py-2 text-left">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($project->scans as $scan)
                                <tr>
                                    <td class="px-3 py-2">#{{ $scan->id }}</td>
                                    <td class="px-3 py-2">{{ $scan->target_url }}</td>
                                    <td class="px-3 py-2">{{ strtoupper($scan->device) }}</td>
                                    <td class="px-3 py-2">{{ $scan->status }}</td>
                                    <td class="px-3 py-2">{{ $scan->created_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-2"><a href="{{ route('scans.show', $scan) }}" class="text-indigo-600 hover:text-indigo-800">Detalle</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">Sin scans.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
