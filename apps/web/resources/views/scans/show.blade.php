<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 leading-tight">Scan #{{ $scan->id }}</h2>
            <a href="{{ route('projects.show', $scan->project) }}" class="text-sm text-gray-600 hover:text-gray-900">Volver al proyecto</a>
        </div>
    </x-slot>

    @php
        $scoreBadge = static function ($value) {
            $v = (int) ($value ?? 0);

            return match (true) {
                $v >= 90 => ['label' => 'Excelente', 'class' => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40'],
                $v >= 75 => ['label' => 'Bueno', 'class' => 'bg-sky-500/20 text-sky-300 border-sky-400/40'],
                $v >= 50 => ['label' => 'Mejorable', 'class' => 'bg-amber-500/20 text-amber-300 border-amber-400/40'],
                default => ['label' => 'Critico', 'class' => 'bg-rose-500/20 text-rose-300 border-rose-400/40'],
            };
        };

        $metricBadge = static function ($name, $value) {
            $v = (float) ($value ?? 0);

            if ($name === 'lcp') {
                return $v <= 2500 ? 'Bueno' : ($v <= 4000 ? 'Mejorable' : 'Critico');
            }

            if ($name === 'cls') {
                return $v <= 0.1 ? 'Bueno' : ($v <= 0.25 ? 'Mejorable' : 'Critico');
            }

            if ($name === 'tbt') {
                return $v <= 200 ? 'Bueno' : ($v <= 600 ? 'Mejorable' : 'Critico');
            }

            return 'N/A';
        };

        $statusClasses = match($scan->status) {
            'done' => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40',
            'running' => 'bg-sky-500/20 text-sky-300 border-sky-400/40',
            'queued' => 'bg-amber-500/20 text-amber-300 border-amber-400/40',
            'failed' => 'bg-rose-500/20 text-rose-300 border-rose-400/40',
            default => 'bg-slate-500/20 text-slate-300 border-slate-400/40',
        };

        $severityClasses = static fn ($severity) => match($severity) {
            'critical' => 'bg-rose-500/20 text-rose-300 border-rose-400/40',
            'high' => 'bg-orange-500/20 text-orange-300 border-orange-400/40',
            'med' => 'bg-amber-500/20 text-amber-300 border-amber-400/40',
            'low' => 'bg-lime-500/20 text-lime-300 border-lime-400/40',
            default => 'bg-slate-500/20 text-slate-300 border-slate-400/40',
        };

        $perf = data_get($scan->summary_jsonb, 'performance_score', 0);
        $a11y = data_get($scan->summary_jsonb, 'a11y_score', 0);
        $best = data_get($scan->summary_jsonb, 'best_practices_score', 0);
        $seo = data_get($scan->summary_jsonb, 'seo_score', 0);

        $lcp = data_get($scan->summary_jsonb, 'lcp_ms');
        $cls = data_get($scan->summary_jsonb, 'cls');
        $tbt = data_get($scan->summary_jsonb, 'tbt_ms');
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="rounded-2xl border border-slate-800 bg-slate-950 text-slate-100 p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Resumen del Scan</h3>
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                        {{ strtoupper($scan->status) }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Rendimiento</p>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-4xl font-bold">{{ $perf }}</p>
                            @php($b = $scoreBadge($perf))
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $b['class'] }}">{{ $b['label'] }}</span>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Accesibilidad</p>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-4xl font-bold">{{ $a11y }}</p>
                            @php($b = $scoreBadge($a11y))
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $b['class'] }}">{{ $b['label'] }}</span>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Practicas recomendadas</p>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-4xl font-bold">{{ $best }}</p>
                            @php($b = $scoreBadge($best))
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $b['class'] }}">{{ $b['label'] }}</span>
                        </div>
                    </article>

                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">SEO</p>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-4xl font-bold">{{ $seo }}</p>
                            @php($b = $scoreBadge($seo))
                            <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $b['class'] }}">{{ $b['label'] }}</span>
                        </div>
                    </article>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-200">LCP</p>
                            <span class="text-xs rounded-full border border-slate-700 px-2 py-1 text-slate-300">{{ $metricBadge('lcp', $lcp) }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-bold">{{ $lcp ?? '-' }} <span class="text-sm font-medium text-slate-400">ms</span></p>
                        <p class="mt-1 text-xs text-slate-400">Rapidez en mostrar el contenido principal.</p>
                    </article>

                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-200">CLS</p>
                            <span class="text-xs rounded-full border border-slate-700 px-2 py-1 text-slate-300">{{ $metricBadge('cls', $cls) }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-bold">{{ $cls ?? '-' }}</p>
                        <p class="mt-1 text-xs text-slate-400">Estabilidad visual de la pagina.</p>
                    </article>

                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-200">TBT</p>
                            <span class="text-xs rounded-full border border-slate-700 px-2 py-1 text-slate-300">{{ $metricBadge('tbt', $tbt) }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-bold">{{ $tbt ?? '-' }} <span class="text-sm font-medium text-slate-400">ms</span></p>
                        <p class="mt-1 text-xs text-slate-400">Bloqueo del hilo principal por JavaScript.</p>
                    </article>
                </div>

                @if($scan->status === 'failed')
                    <div class="mt-4 rounded-lg border border-rose-400/40 bg-rose-500/15 p-4 text-sm text-rose-200">
                        <p class="font-semibold">El scan fallo</p>
                        <p class="mt-1">{{ $scan->error_message }}</p>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-950 text-slate-100 p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Tecnología detectada</h3>
                    <span class="inline-flex items-center rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-200">
                        Fuente: {{ strtoupper(data_get($technology, 'source', 'unknown')) }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4 md:col-span-2">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Tecnología principal</p>
                        <p class="mt-2 text-3xl font-bold text-white">
                            {{ data_get($technology, 'primary.name', 'No detectada') }}
                        </p>
                        <p class="mt-2 text-sm text-slate-300">{{ data_get($technology, 'summary', 'Sin resumen.') }}</p>
                    </article>

                    <article class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Categorías</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse(data_get($technology, 'primary.categories', []) as $category)
                                <span class="inline-flex rounded-full border border-indigo-500/40 bg-indigo-500/20 px-2 py-1 text-xs font-semibold text-indigo-200">
                                    {{ $category }}
                                </span>
                            @empty
                                <span class="text-sm text-slate-400">Sin categoría principal.</span>
                            @endforelse
                        </div>
                    </article>
                </div>

                <div class="mt-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Otras tecnologías detectadas</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse(data_get($technology, 'technologies', []) as $tech)
                            <span class="inline-flex rounded-full border border-slate-600 bg-slate-800 px-2 py-1 text-xs font-semibold text-slate-200">
                                {{ $tech['name'] }}
                            </span>
                        @empty
                            <span class="text-sm text-slate-400">No se detectaron tecnologías adicionales.</span>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Como leer los valores</h3>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4 text-sm">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="font-semibold text-gray-900">Severity</p>
                        <p class="mt-1 text-gray-600">Criticidad del problema: `critical`, `high`, `med`, `low`, `info`.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="font-semibold text-gray-900">Impact (0-100)</p>
                        <p class="mt-1 text-gray-600">Cuanto mejora el sitio si lo arreglas. Mayor valor = mayor beneficio.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="font-semibold text-gray-900">Effort (0-100)</p>
                        <p class="mt-1 text-gray-600">Esfuerzo estimado de implementacion. Mayor valor = mas complejo.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="font-semibold text-gray-900">Priority</p>
                        <p class="mt-1 text-gray-600">Orden recomendado para ejecutar cambios (impacto vs esfuerzo).</p>
                    </div>
                </div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Issues detectados</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Issue</th>
                                <th class="px-3 py-2 text-left">Severity</th>
                                <th class="px-3 py-2 text-left">Impact</th>
                                <th class="px-3 py-2 text-left">Effort</th>
                                <th class="px-3 py-2 text-left">Priority</th>
                                <th class="px-3 py-2 text-left">Que hacer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($scan->issues->sortByDesc('priority_score') as $issue)
                                <tr>
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-gray-900">{{ $issue->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $issue->key }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold {{ $severityClasses($issue->severity) }}">{{ strtoupper($issue->severity) }}</span>
                                    </td>
                                    <td class="px-3 py-3 font-semibold text-gray-800">{{ $issue->impact_score }}</td>
                                    <td class="px-3 py-3 font-semibold text-gray-800">{{ $issue->effort_score }}</td>
                                    <td class="px-3 py-3 font-semibold text-gray-800">{{ $issue->priority_score }}</td>
                                    <td class="px-3 py-3 text-gray-700">{{ data_get($issue->fix_jsonb, 'summary', 'Aplicar recomendacion y volver a escanear.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">No hay issues todavia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Fix Plan accionable</h3>
                @php($plan = $scan->fixPlan?->plan_jsonb)

                @if($plan)
                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <h4 class="text-sm font-semibold text-emerald-900">Quick Wins</h4>
                            <div class="mt-3 space-y-3 text-sm">
                                @forelse(data_get($plan, 'buckets.quick_wins', []) as $item)
                                    <article class="rounded-lg border border-emerald-200 bg-white p-3">
                                        <p class="font-semibold text-gray-900">{{ $item['title'] }}</p>
                                        <p class="mt-1 text-gray-700">{{ $item['fix_summary'] ?? 'Sin resumen de accion.' }}</p>
                                        <p class="mt-2 text-xs text-gray-600"><span class="font-semibold">Donde tocar:</span> {{ $item['where_to_change'] ?? 'Revisar frontend y servidor segun issue.' }}</p>
                                        @if(!empty($item['steps']))
                                            <div class="mt-2 text-xs text-gray-600">
                                                <p class="font-semibold">Pasos:</p>
                                                <ol class="list-decimal pl-4 mt-1 space-y-1">
                                                    @foreach($item['steps'] as $step)
                                                        <li>{{ $step }}</li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <p class="text-sm text-emerald-800">Sin items en este bloque.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <h4 class="text-sm font-semibold text-amber-900">Medium</h4>
                            <div class="mt-3 space-y-3 text-sm">
                                @forelse(data_get($plan, 'buckets.medium', []) as $item)
                                    <article class="rounded-lg border border-amber-200 bg-white p-3">
                                        <p class="font-semibold text-gray-900">{{ $item['title'] }}</p>
                                        <p class="mt-1 text-gray-700">{{ $item['fix_summary'] ?? 'Sin resumen de accion.' }}</p>
                                        <p class="mt-2 text-xs text-gray-600"><span class="font-semibold">Donde tocar:</span> {{ $item['where_to_change'] ?? 'Revisar frontend y servidor segun issue.' }}</p>
                                        @if(!empty($item['steps']))
                                            <div class="mt-2 text-xs text-gray-600">
                                                <p class="font-semibold">Pasos:</p>
                                                <ol class="list-decimal pl-4 mt-1 space-y-1">
                                                    @foreach($item['steps'] as $step)
                                                        <li>{{ $step }}</li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <p class="text-sm text-amber-800">Sin items en este bloque.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-300 bg-slate-100 p-4">
                            <h4 class="text-sm font-semibold text-slate-900">Structural</h4>
                            <div class="mt-3 space-y-3 text-sm">
                                @forelse(data_get($plan, 'buckets.structural', []) as $item)
                                    <article class="rounded-lg border border-slate-300 bg-white p-3">
                                        <p class="font-semibold text-gray-900">{{ $item['title'] }}</p>
                                        <p class="mt-1 text-gray-700">{{ $item['fix_summary'] ?? 'Sin resumen de accion.' }}</p>
                                        <p class="mt-2 text-xs text-gray-600"><span class="font-semibold">Donde tocar:</span> {{ $item['where_to_change'] ?? 'Revisar frontend y servidor segun issue.' }}</p>
                                        @if(!empty($item['steps']))
                                            <div class="mt-2 text-xs text-gray-600">
                                                <p class="font-semibold">Pasos:</p>
                                                <ol class="list-decimal pl-4 mt-1 space-y-1">
                                                    @foreach($item['steps'] as $step)
                                                        <li>{{ $step }}</li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <p class="text-sm text-slate-700">Sin items en este bloque.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-500">El plan se generara cuando termine la normalizacion.</p>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
