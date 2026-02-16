<?php

namespace App\Http\Requests;

use App\Models\Scan;
use App\Services\SsrfGuard;
use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'target_url' => ['required', 'url', 'max:2048'],
            'device' => ['required', 'in:mobile,desktop'],
            'mode' => ['nullable', 'in:lighthouse'],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $project = $this->route('project');
            if (! $project) {
                return;
            }

            $activeForProject = Scan::query()
                ->where('project_id', $project->id)
                ->whereIn('status', ['queued', 'running'])
                ->exists();

            if ($activeForProject) {
                $validator->errors()->add('project', 'This project already has an active scan.');
                return;
            }

            $activeForUser = Scan::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('status', ['queued', 'running'])
                ->exists();

            if ($activeForUser) {
                $validator->errors()->add('user', 'You already have an active scan.');
                return;
            }

            try {
                app(SsrfGuard::class)->assertSafeUrl((string) $this->input('target_url'));
            } catch (\Throwable $exception) {
                $validator->errors()->add('target_url', $exception->getMessage());
            }
        });
    }
}
