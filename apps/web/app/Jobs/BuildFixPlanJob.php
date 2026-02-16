<?php

namespace App\Jobs;

use App\Models\Scan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildFixPlanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $scanId)
    {
        $this->onQueue('scans');
    }

    public function handle(): void
    {
        $scan = Scan::query()->with('issues')->find($this->scanId);
        if (! $scan || $scan->status !== 'done') {
            return;
        }

        $issues = $scan->issues()->orderByDesc('priority_score')->get();

        $quickWins = [];
        $medium = [];
        $structural = [];

        foreach ($issues as $issue) {
            $fixSummary = data_get($issue->fix_jsonb, 'summary') ?: $this->fallbackSummary($issue->key);
            $whereToChange = data_get($issue->fix_jsonb, 'where_to_change') ?: $this->fallbackWhereToChange($issue->key);
            $steps = data_get($issue->fix_jsonb, 'steps');
            $validation = data_get($issue->fix_jsonb, 'validation') ?: 'Repite el scan y compara métricas antes/después.';

            $entry = [
                'id' => $issue->id,
                'key' => $issue->key,
                'title' => $issue->title,
                'severity' => $issue->severity,
                'category' => $issue->category,
                'impact_score' => $issue->impact_score,
                'effort_score' => $issue->effort_score,
                'priority_score' => $issue->priority_score,
                'estimated_saving_ms' => $issue->estimated_saving_ms,
                'estimated_saving_kb' => $issue->estimated_saving_kb,
                'fix_summary' => $fixSummary,
                'where_to_change' => $whereToChange,
                'steps' => is_array($steps) && $steps !== [] ? $steps : $this->fallbackSteps($issue->key),
                'validation' => $validation,
            ];

            if ($issue->effort_score <= 35 && $issue->impact_score >= 35) {
                $quickWins[] = $entry;
            } elseif ($issue->effort_score <= 65) {
                $medium[] = $entry;
            } else {
                $structural[] = $entry;
            }
        }

        $plan = [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'issues' => $issues->count(),
                'quick_wins' => count($quickWins),
                'medium' => count($medium),
                'structural' => count($structural),
            ],
            'buckets' => [
                'quick_wins' => $quickWins,
                'medium' => $medium,
                'structural' => $structural,
            ],
        ];

        $scan->fixPlan()->updateOrCreate(
            ['scan_id' => $scan->id],
            [
                'plan_jsonb' => $plan,
                'generated_at' => now(),
            ]
        );
    }

    private function fallbackSummary(string $key): string
    {
        return match ($key) {
            'uses-responsive-images' => 'Servir imágenes con tamaño adaptado al viewport.',
            'uses-optimized-images' => 'Recomprimir imágenes para reducir KB sin perder calidad visible.',
            'offscreen-images' => 'Aplicar lazy-load a imágenes fuera del primer viewport.',
            'modern-image-formats' => 'Convertir imágenes a WebP/AVIF cuando sea posible.',
            'largest-contentful-paint-element' => 'Optimizar el elemento LCP (imagen/texto principal) para que renderice antes.',
            'total-byte-weight' => 'Reducir peso total de red en JS/CSS/imágenes/fuentes.',
            'dom-size' => 'Reducir nodos DOM y profundidad de árbol en páginas críticas.',
            'color-contrast' => 'Corregir contraste de color para mejorar accesibilidad.',
            'unminified-javascript' => 'Minificar bundles JavaScript en producción.',
            'render-blocking-resources' => 'Eliminar o diferir recursos que bloquean el primer render.',
            default => 'Aplicar recomendación Lighthouse y verificar mejora en el siguiente scan.',
        };
    }

    private function fallbackWhereToChange(string $key): string
    {
        return match ($key) {
            'uses-responsive-images', 'uses-optimized-images', 'offscreen-images', 'modern-image-formats' => 'Plantillas Blade, pipeline de imágenes y CDN.',
            'render-blocking-resources', 'unminified-javascript', 'total-byte-weight' => 'Assets frontend (Vite), layout principal y configuración de build.',
            'largest-contentful-paint-element' => 'Hero principal, CSS crítico y orden de carga de recursos.',
            'dom-size' => 'Componentes Blade/JS con demasiados nodos repetidos.',
            'color-contrast' => 'Sistema de diseño CSS (colores de texto/fondo).',
            default => 'Código frontend y configuración de servidor según el tipo de issue.',
        };
    }

    private function fallbackSteps(string $key): array
    {
        return match ($key) {
            'uses-responsive-images' => [
                'Generar variantes de imagen por ancho (ej. 480, 768, 1200).',
                'Usar srcset y sizes en etiquetas img.',
                'Servir la variante mínima necesaria para cada viewport.',
            ],
            'offscreen-images' => [
                'Añadir loading=\"lazy\" en imágenes no críticas.',
                'Priorizar solo imagen principal con fetchpriority=\"high\" cuando aplique.',
                'Revisar que el lazy-load no afecte imágenes above-the-fold.',
            ],
            'render-blocking-resources' => [
                'Extraer e inline del CSS crítico.',
                'Diferir JS no crítico con defer o carga tardía.',
                'Preload de recursos críticos y preconnect de orígenes clave.',
            ],
            default => [
                'Aplicar el ajuste principal recomendado.',
                'Desplegar cambios en entorno de prueba.',
                'Ejecutar nuevo scan para comparar resultados.',
            ],
        };
    }
}
