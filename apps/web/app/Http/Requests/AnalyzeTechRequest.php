<?php

namespace App\Http\Requests;

use App\Services\SsrfGuard;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeTechRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(SsrfGuard::class)->assertSafeUrl((string) $this->input('url'));
            } catch (\Throwable $exception) {
                $validator->errors()->add('url', $exception->getMessage());
            }
        });
    }
}
